<?php
// criar_tudo.php
echo "<pre>\n";
echo "=== CRIANDO TODA A ESTRUTURA DO PROJETO ===\n\n";

// Função para criar arquivo com conteúdo
function criarArquivo($caminho, $conteudo) {
    $dir = dirname($caminho);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo " Diretório criado: {$dir}\n";
    }
    
    if (file_put_contents($caminho, $conteudo)) {
        echo " Arquivo criado: {$caminho}\n";
    } else {
        echo " Erro ao criar: {$caminho}\n";
    }
}

// 1. Criar diretórios
$diretorios = [
    'config',
    'includes',
    'api',
    'public',
    'admin',
    'sql',
    'assets/css',
    'assets/js',
    'assets/images',
    'logs'
];

foreach ($diretorios as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo " Diretório criado: {$dir}\n";
    } else {
        echo "Diretório já existe: {$dir}\n";
    }
}

// 2. Criar arquivo de configuração do banco de dados
$conteudo = <<<'PHP'
<?php
// config/Database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'mussaneaqua';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch(PDOException $e) {
            error_log("Erro de conexão: " . $e->getMessage());
            throw new Exception("Erro na conexão com o banco de dados");
        }
        return $this->conn;
    }
}
PHP;
criarArquivo('config/Database.php', $conteudo);

// 3. Criar Validator.php
$conteudo = <<<'PHP'
<?php
// includes/Validator.php
class Validator {
    
    public static function sanitizeString($input) {
        if ($input === null) return '';
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }

    public static function sanitizeEmail($email) {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return $email;
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateStrongPassword($password) {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }

    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
PHP;
criarArquivo('includes/Validator.php', $conteudo);

// 4. Criar AuditLog.php
$conteudo = <<<'PHP'
<?php
// includes/AuditLog.php
require_once __DIR__ . '/../config/Database.php';

class AuditLog {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function log($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null, $description = null) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $old_values_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
            $new_values_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;

            $query = "INSERT INTO audit_logs 
                      (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, description, created_at) 
                      VALUES 
                      (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, :description, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->bindParam(':old_values', $old_values_json);
            $stmt->bindParam(':new_values', $new_values_json);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':description', $description);

            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Erro ao registrar log de auditoria: " . $e->getMessage());
            return false;
        }
    }
}
PHP;
criarArquivo('includes/AuditLog.php', $conteudo);

// 5. Criar arquivo de login simplificado para teste
$conteudo = <<<'HTML'
<?php
// public/login.php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Teste</title>
</head>
<body>
    <h1>Página de Login</h1>
    <p>Se você está vendo esta página, o arquivo foi encontrado!</p>
    <p>Caminho: <?php echo __FILE__; ?></p>
    <p><a href="../index.php">Voltar para o site</a></p>
</body>
</html>
HTML;
criarArquivo('public/login.php', $conteudo);

// 6. Criar index.php simplificado
$conteudo = <<<'HTML'
<?php
// index.php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>MussaneAqua</title>
</head>
<body>
    <h1>MussaneAqua - Sistema de Gestão</h1>
    <p>Bem-vindo ao sistema!</p>
    <ul>
        <li><a href="public/login.php">Login</a></li>
        <li><a href="public/register.php">Registro</a></li>
        <li><a href="public/hash.php">Gerar Hash</a></li>
        <li><a href="admin/dashboard.php">Dashboard</a></li>
        <li><a href="teste.php">Teste de Estrutura</a></li>
    </ul>
    <p>Caminho atual: <?php echo __DIR__; ?></p>
</body>
</html>
HTML;
criarArquivo('index.php', $conteudo);

// 7. Criar arquivo .htaccess para resolver problemas de URL
$conteudo = <<<'HTACCESS'
Options -Indexes
DirectoryIndex index.php

# Ativar rewrite engine
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Redirecionar /public para public/
    RewriteRule ^public/?$ public/ [L]
    
    # Redirecionar /admin para admin/
    RewriteRule ^admin/?$ admin/ [L]
</IfModule>

# PHP settings
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
    php_value max_input_time 300
</IfModule>
HTACCESS;
criarArquivo('.htaccess', $conteudo);

echo "\n ESTRUTURA CRIADA COM SUCESSO!\n";
echo "Agora acesse: http://localhost/Sedano%20Mussane%20EPI/index.php\n";
?>