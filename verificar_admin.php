<?php
// verificar_admin.php
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/Validator.php';

echo "<h1>🔍 VERIFICAR ADMIN</h1>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar se a tabela users existe
    $tables = $conn->query("SHOW TABLES");
    echo "<h3>📌 Tabelas no banco:</h3>";
    echo "<ul>";
    $tabelas_encontradas = false;
    while ($table = $tables->fetch()) {
        echo "<li>" . $table[0] . "</li>";
        if ($table[0] == 'users') $tabelas_encontradas = true;
    }
    echo "</ul>";
    
    if (!$tabelas_encontradas) {
        echo "<p style='color:red'>❌ Tabela 'users' não encontrada!</p>";
        echo "<p>Execute o script SQL para criar as tabelas.</p>";
        exit;
    }
    
    // Verificar estrutura da tabela
    echo "<h3>📌 Estrutura da tabela users:</h3>";
    $columns = $conn->query("DESCRIBE users");
    echo "<pre>";
    print_r($columns->fetchAll());
    echo "</pre>";
    
    // Verificar se existe algum admin
    $admins = $conn->query("SELECT id, username, email, role, is_active FROM users WHERE role = 'admin'");
    $admins = $admins->fetchAll();
    
    echo "<h3> Administradores encontrados:</h3>";
    if (count($admins) > 0) {
        foreach ($admins as $admin) {
            echo "<p> ID: {$admin['id']} - Usuário: {$admin['username']} - Email: {$admin['email']} - Papel: {$admin['role']} - Ativo: " . ($admin['is_active'] ? 'Sim' : 'Não') . "</p>";
        }
    } else {
        echo "<p style='color:orange'> Nenhum administrador encontrado!</p>";
    }
    
    // Verificar se o email específico existe
    $email = 'admin@mussaneaqua.com';
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    echo "<h3> Verificando email: {$email}</h3>";
    if ($user) {
        echo "<p style='color:green'> Usuário encontrado!</p>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
        // Testar a senha Admin@123
        $senha_teste = 'Admin@123';
        if (password_verify($senha_teste, $user['password_hash'])) {
            echo "<p style='color:green; font-size:18px;'> A SENHA 'Admin@123' ESTÁ CORRETA!</p>";
        } else {
            echo "<p style='color:red; font-size:18px;'> A SENHA 'Admin@123' NÃO CORRESPODE AO HASH!</p>";
            
            // Gerar novo hash para comparação
            $novo_hash = password_hash($senha_teste, PASSWORD_BCRYPT);
            echo "<p>Hash atual: " . $user['password_hash'] . "</p>";
            echo "<p>Novo hash para referência: " . $novo_hash . "</p>";
        }
    } else {
        echo "<p style='color:red'> Usuário com email {$email} não encontrado!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>ERRO: " . $e->getMessage() . "</p>";
}
?>