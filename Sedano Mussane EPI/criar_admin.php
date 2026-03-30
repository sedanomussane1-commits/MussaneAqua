<?php
// criar_admin.php
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/Validator.php';

echo "<pre>\n";
echo "=== CRIAR ADMINISTRADOR ===\n\n";
echo "Data: " . date('d/m/Y H:i:s') . "\n";
echo "========================================\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Dados do novo admin
    $username = 'sedano_admin';
    $email = 'sedano@mussaneaqua.com';
    $password = 'Admin@123';
    
    echo " Dados do administrador a ser criado:\n";
    echo "   Usuário: {$username}\n";
    echo "   Email: {$email}\n";
    echo "   Senha: {$password}\n\n";
    
    // Validar email
    if (!Validator::validateEmail($email)) {
        die(" Email inválido!\n");
    }
    echo " Email válido\n";
    
    // Validar senha forte
    if (!Validator::validateStrongPassword($password)) {
        die(" Senha fraca! A senha deve ter 8+ caracteres, maiúscula, minúscula e número.\n");
    }
    echo " Senha forte\n";
    
    // Verificar se já existe
    $check = $conn->prepare("SELECT id, username, email, role FROM users WHERE email = ? OR username = ?");
    $check->execute([$email, $username]);
    $existing = $check->fetch();
    
    if ($existing) {
        echo "\n  Já existe um usuário com estes dados:\n";
        echo "   ID: {$existing['id']}\n";
        echo "   Usuário: {$existing['username']}\n";
        echo "   Email: {$existing['email']}\n";
        echo "   Papel: {$existing['role']}\n\n";
        
        if ($existing['email'] === $email) {
            die("❌ Email já cadastrado!\n");
        } else {
            die("❌ Nome de usuário já cadastrado!\n");
        }
    }
    
    // Verificar se a tabela users existe e tem a estrutura correta
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'funcionario', 'cliente') DEFAULT 'cliente',
            is_active BOOLEAN DEFAULT TRUE,
            login_attempts INT DEFAULT 0,
            last_login_attempt DATETIME NULL,
            last_login DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            created_by INT NULL,
            updated_by INT NULL,
            deleted_by INT NULL,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        echo " Tabela 'users' verificada/criada\n";
    } catch (Exception $e) {
        echo "  Nota: Tabela 'users' já existe\n";
    }
    
    // Gerar hash da senha
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    echo " Hash gerado: {$hash}\n\n";
    
    // Inserir novo admin
    $query = "INSERT INTO users (username, email, password_hash, role, is_active, created_at) 
              VALUES (?, ?, ?, 'admin', 1, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$username, $email, $hash]);
    
    $novo_id = $conn->lastInsertId();
    
    echo "════════════════════════════════════════════\n";
    echo "ADMIN CRIADO COM SUCESSO!\n";
    echo "════════════════════════════════════════════\n\n";
    echo " DADOS DO ADMIN:\n";
    echo "   ID: " . $novo_id . "\n";
    echo "   Usuário: " . $username . "\n";
    echo "   Email: " . $email . "\n";
    echo "   Senha: " . $password . "\n";
    echo "   Papel: admin\n";
    echo "   Status: Ativo\n\n";
    
    echo "Links importantes:\n";
    echo "   Login: http://localhost/Sedano%20Mussane%20EPI/public/login.php\n";
    echo "   Dashboard: http://localhost/Sedano%20Mussane%20EPI/admin/dashboard.php\n\n";
    
    // Mostrar todos os admins
    echo "════════════════════════════════════════════\n";
    echo " ADMINS EXISTENTES NO SISTEMA:\n";
    echo "════════════════════════════════════════════\n";
    $admins = $conn->query("SELECT id, username, email, created_at, is_active FROM users WHERE role = 'admin' ORDER BY id");
    $count = 0;
    while ($admin = $admins->fetch()) {
        $count++;
        $status = $admin['is_active'] ? 'Ativo' : 'Inativo';
        echo "   {$count}. ID {$admin['id']}: {$admin['username']} ({$admin['email']})\n";
        echo "      Criado em: {$admin['created_at']} - Status: {$status}\n\n";
    }
    
    if ($count === 0) {
        echo "   Nenhum admin encontrado.\n\n";
    }
    
    echo "════════════════════════════════════════════\n";
    echo "OPERAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo " ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . " (Linha " . $e->getLine() . ")\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}
?>