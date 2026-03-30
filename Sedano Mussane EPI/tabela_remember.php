<?php
// corrigir_tabela_remember.php
require_once __DIR__ . '/config/Database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Corrigir Tabela Remember Tokens</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 10px; }
        .danger { color: #721c24; background: #f8d7da; padding: 15px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔧 CORRIGIR TABELA REMEMBER_TOKENS</h1>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<div class='card'>";
    echo "<h3>📌 Estrutura ATUAL da tabela:</h3>";
    
    // Mostrar estrutura atual
    $columns = $conn->query("DESCRIBE remember_tokens");
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
    while ($col = $columns->fetch()) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h3>🔧 Aplicando correções...</h3>";
    
    // 1. Verificar se a coluna 'token' existe
    $check_token = $conn->query("SHOW COLUMNS FROM remember_tokens LIKE 'token'");
    if ($check_token->rowCount() == 0) {
        // Adicionar coluna token
        $conn->exec("ALTER TABLE remember_tokens ADD COLUMN token VARCHAR(255) NOT NULL UNIQUE AFTER user_id");
        echo "<p class='success'>✅ Coluna 'token' adicionada com sucesso!</p>";
    } else {
        echo "<p class='success'>✅ Coluna 'token' já existe</p>";
    }
    
    // 2. Verificar se a coluna 'selector' existe (versão antiga) e remover
    $check_selector = $conn->query("SHOW COLUMNS FROM remember_tokens LIKE 'selector'");
    if ($check_selector->rowCount() > 0) {
        $conn->exec("ALTER TABLE remember_tokens DROP COLUMN selector");
        echo "<p class='success'>✅ Coluna obsoleta 'selector' removida</p>";
    }
    
    // 3. Verificar se a coluna 'hashed_validator' existe (versão antiga) e remover
    $check_validator = $conn->query("SHOW COLUMNS FROM remember_tokens LIKE 'hashed_validator'");
    if ($check_validator->rowCount() > 0) {
        $conn->exec("ALTER TABLE remember_tokens DROP COLUMN hashed_validator");
        echo "<p class='success'>✅ Coluna obsoleta 'hashed_validator' removida</p>";
    }
    
    // 4. Verificar se a coluna 'used' existe (versão antiga) e remover
    $check_used = $conn->query("SHOW COLUMNS FROM remember_tokens LIKE 'used'");
    if ($check_used->rowCount() > 0) {
        $conn->exec("ALTER TABLE remember_tokens DROP COLUMN used");
        echo "<p class='success'>✅ Coluna obsoleta 'used' removida</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h3>📌 NOVA estrutura da tabela:</h3>";
    
    $new_columns = $conn->query("DESCRIBE remember_tokens");
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
    while ($col = $new_columns->fetch()) {
        $color = ($col['Field'] == 'token') ? 'table-success' : '';
        echo "<tr class='{$color}'>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h3>🧪 Testando inserção:</h3>";
    
    // Testar inserção
    try {
        $test_user = $conn->query("SELECT id FROM users LIMIT 1")->fetch();
        if ($test_user) {
            $test_token = bin2hex(random_bytes(32));
            $test_expires = date('Y-m-d H:i:s', time() + 3600);
            
            $test = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
            if ($test->execute([$test_user['id'], $test_token, $test_expires])) {
                echo "<p class='success'>✅ Teste de inserção funcionou!</p>";
                
                // Remover token de teste
                $conn->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$test_token]);
            }
        } else {
            echo "<p class='danger'>⚠️ Nenhum usuário encontrado para teste</p>";
        }
    } catch (Exception $e) {
        echo "<p class='danger'>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h3>✅ CORREÇÃO CONCLUÍDA!</h3>";
    echo "<p>Agora a tabela 'remember_tokens' tem a estrutura correta:</p>";
    echo "<ul>";
    echo "<li><strong>id</strong> - INT (Primary Key)</li>";
    echo "<li><strong>user_id</strong> - INT (Foreign Key)</li>";
    echo "<li><strong>token</strong> - VARCHAR(255) (Unique) - ✅ ADICIONADO</li>";
    echo "<li><strong>expires</strong> - DATETIME</li>";
    echo "<li><strong>created_at</strong> - DATETIME</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='text-center mt-4'>";
    echo "<a href='public/login.php' class='btn btn-success btn-lg'>Ir para Login</a> ";
    echo "<a href='verificar_tabela_remember.php' class='btn btn-primary btn-lg'>Verificar Tabela</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='danger'>❌ ERRO: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>