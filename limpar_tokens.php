<?php
// limpar_tokens.php
require_once __DIR__ . '/config/Database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Limpar Tokens</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Remover tokens expirados
    $delete = $conn->prepare("DELETE FROM remember_tokens WHERE expires < NOW()");
    $delete->execute();
    $removidos = $delete->rowCount();
    
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ Tokens expirados removidos!</h4>";
    echo "<p>Total removido: {$removidos} tokens</p>";
    echo "</div>";
    
    // Mostrar tokens restantes
    $tokens = $conn->query("
        SELECT rt.*, u.username 
        FROM remember_tokens rt 
        JOIN users u ON rt.user_id = u.id 
        ORDER BY rt.expires
    ");
    
    if ($tokens->rowCount() > 0) {
        echo "<h4>📌 Tokens ativos:</h4>";
        echo "<table class='table table-bordered'>";
        echo "<tr><th>ID</th><th>Usuário</th><th>Token</th><th>Expira</th></tr>";
        while ($token = $tokens->fetch()) {
            $expired = strtotime($token['expires']) < time();
            $class = $expired ? 'table-danger' : '';
            echo "<tr class='{$class}'>";
            echo "<td>{$token['id']}</td>";
            echo "<td>{$token['username']}</td>";
            echo "<td><code>" . substr($token['token'], 0, 30) . "...</code></td>";
            echo "<td>{$token['expires']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='text-muted'>Nenhum token ativo.</p>";
    }
    
    echo "<a href='verificar_tabela_remember.php' class='btn btn-primary'>Voltar</a>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>ERRO: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>