<?php
// includes/TokenManager.php
require_once __DIR__ . '/../config/Database.php';

class TokenManager {
    private $conn;
    private static $instance = null;

    private function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Gerar token CSRF
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }

    /**
     * Validar token CSRF
     */
    public function validateCSRFToken($token, $max_age = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Verificar se o token expirou
        if (time() - $_SESSION['csrf_token_time'] > $max_age) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }

        // Comparação segura contra timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Gerar token de "remember me"
     */
    public function createRememberToken($user_id) {
        try {
            // Remover tokens antigos do usuário
            $this->deleteOldTokens($user_id);

            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $token = $selector . ':' . $validator;
            $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 dias

            $query = "INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires) 
                      VALUES (:user_id, :selector, :hashed_validator, :expires)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':selector', $selector);
            $stmt->bindParam(':hashed_validator', $hashed_validator);
            $stmt->bindParam(':expires', $expires);
            
            if ($stmt->execute()) {
                // Configurar cookie seguro
                $this->setSecureCookie('remember_token', $token, 2592000);
                return true;
            }
            
            return false;

        } catch (Exception $e) {
            error_log("Erro ao criar remember token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar token de "remember me"
     */
    public function validateRememberToken($token) {
        try {
            if (empty($token) || !strpos($token, ':')) {
                return false;
            }

            list($selector, $validator) = explode(':', $token, 2);

            $query = "SELECT * FROM remember_tokens 
                      WHERE selector = :selector 
                      AND expires > NOW() 
                      AND used = 0";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':selector', $selector);
            $stmt->execute();
            
            $token_data = $stmt->fetch();

            if (!$token_data) {
                return false;
            }

            if (password_verify($validator, $token_data['hashed_validator'])) {
                // Marcar token como usado (one-time use)
                $update = $this->conn->prepare("UPDATE remember_tokens SET used = 1 WHERE id = :id");
                $update->bindParam(':id', $token_data['id']);
                $update->execute();

                return $token_data['user_id'];
            }

            return false;

        } catch (Exception $e) {
            error_log("Erro ao validar remember token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover token de "remember me"
     */
    public function removeRememberToken($token) {
        try {
            if (empty($token) || !strpos($token, ':')) {
                return false;
            }

            list($selector, $validator) = explode(':', $token, 2);

            $query = "DELETE FROM remember_tokens WHERE selector = :selector";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':selector', $selector);
            
            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Erro ao remover remember token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar tokens antigos de um usuário
     */
    private function deleteOldTokens($user_id) {
        try {
            $query = "DELETE FROM remember_tokens WHERE user_id = :user_id OR expires < NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao deletar tokens antigos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Configurar cookie seguro
     */
    private function setSecureCookie($name, $value, $expiry) {
        $secure = false; // true em produção com HTTPS
        $httponly = true;
        $samesite = 'Strict';
        
        setcookie(
            $name,
            $value,
            time() + $expiry,
            '/',
            '',
            $secure,
            $httponly
        );
    }

    /**
     * Limpar cookie
     */
    public function clearCookie($name) {
        setcookie($name, '', time() - 3600, '/');
    }
}