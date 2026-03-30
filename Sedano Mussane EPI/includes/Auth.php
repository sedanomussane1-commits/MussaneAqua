<?php
// includes/Auth.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/AuditLog.php';

class Auth {
    private $conn;
    private $audit;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->audit = new AuditLog();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($email, $password, $remember = false) {
        try {
            $email = Validator::sanitizeEmail($email);
            
            // Buscar usuário
            $query = "SELECT id, username, email, password_hash, role, is_active 
                      FROM users 
                      WHERE email = :email AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user) {
                $this->audit->log(null, 'LOGIN_FAILED', 'users', null, null, null, "Tentativa de login com email não encontrado: $email");
                return ["success" => false, "message" => "Credenciais inválidas"];
            }

            if (!$user["is_active"]) {
                $this->audit->log($user["id"], 'LOGIN_FAILED', 'users', $user["id"], null, null, "Tentativa de login com usuário inativo");
                return ["success" => false, "message" => "Conta desativada"];
            }

            if (!password_verify($password, $user["password_hash"])) {
                $this->audit->log($user["id"], 'LOGIN_FAILED', 'users', $user["id"], null, null, "Senha incorreta");
                return ["success" => false, "message" => "Credenciais inválidas"];
            }

            // Login bem-sucedido
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["login_time"] = time();
            $_SESSION["user_agent"] = $_SERVER['HTTP_USER_AGENT'];

            // Atualizar último login
            $update = $this->conn->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
            $update->execute([$user["id"]]);

            $this->audit->log($user["id"], 'LOGIN_SUCCESS', 'users', $user["id"], null, null, "Login realizado com sucesso");

            // Criar remember token se solicitado
            if ($remember) {
                $this->createRememberToken($user["id"]);
            }

            return [
                "success" => true,
                "message" => "Login realizado com sucesso",
                "user" => [
                    "id" => $user["id"],
                    "username" => $user["username"],
                    "email" => $user["email"],
                    "role" => $user["role"]
                ]
            ];

        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return ["success" => false, "message" => "Erro interno no servidor"];
        }
    }

    private function createRememberToken($user_id) {
        try {
            // Gerar token
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", time() + 2592000); // 30 dias
            
            // Remover tokens antigos
            $delete = $this->conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $delete->execute([$user_id]);
            
            // Inserir novo token
            $insert = $this->conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
            $insert->execute([$user_id, $token, $expires]);
            
            // Configurar cookie
            setcookie(
                'remember_token',
                $token,
                [
                    'expires' => time() + 2592000,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao criar remember token: " . $e->getMessage());
            return false;
        }
    }

    public function checkRememberToken() {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            
            try {
                $query = "SELECT rt.user_id, u.username, u.email, u.role, u.is_active 
                          FROM remember_tokens rt
                          JOIN users u ON rt.user_id = u.id
                          WHERE rt.token = :token AND rt.expires > NOW()";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                $user = $stmt->fetch();
                
                if ($user && $user['is_active']) {
                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["login_time"] = time();
                    
                    return true;
                }
            } catch (Exception $e) {
                error_log("Erro ao verificar remember token: " . $e->getMessage());
            }
        }
        
        return false;
    }

    public function isAuthenticated() {
        return isset($_SESSION["user_id"]);
    }

    public function logout() {
        if (isset($_COOKIE['remember_token'])) {
            try {
                $delete = $this->conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
                $delete->execute([$_COOKIE['remember_token']]);
            } catch (Exception $e) {}
            
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->audit->log($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], null, null, 'Logout realizado');
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Verifica se o usuário tem permissão para acessar um recurso
     * @param string $required_role Papel necessário (admin, funcionario, cliente)
     * @return bool
     */
    public function hasPermission($required_role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $user_role = $_SESSION['role'];
        
        // Admin tem acesso a tudo
        if ($user_role === 'admin') {
            return true;
        }
        
        // Funcionário pode acessar recursos de funcionário e cliente
        if ($required_role === 'funcionario') {
            return in_array($user_role, ['admin', 'funcionario']);
        }
        
        // Cliente só pode acessar recursos de cliente
        if ($required_role === 'cliente') {
            return in_array($user_role, ['admin', 'funcionario', 'cliente']);
        }
        
        return false;
    }
}
?>