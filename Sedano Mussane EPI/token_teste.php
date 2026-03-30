<?php
// criar_token_teste.php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/Auth.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Criar Token de Teste</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (isset($_POST['criar_token'])) {
        $user_id = $_POST['user_id'];
        $dias = (int)$_POST['dias'];
        
        // Verificar se usuário existe
        $check = $conn->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $check->execute([$user_id]);
        $user = $check->fetch();
        
        if ($user) {
            // Remover tokens antigos
            $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user_id]);
            
            // Criar novo token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + ($dias * 86400));
            
            $insert = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
            
            if ($insert->execute([$user_id, $token, $expires])) {
                echo "<div class='alert alert-success'>";
                echo "<h4>✅ TOKEN CRIADO COM SUCESSO!</h4>";
                echo "<p><strong>Usuário:</strong> {$user['username']} ({$user['email']})</p>";
                echo "<p><strong>Token:</strong> <code>{$token}</code></p>";
                echo "<p><strong>Expira em:</strong> {$dias} dias ({$expires})</p>";
                
                // Configurar cookie
                setcookie('remember_token', $token, time() + ($dias * 86400), '/');
                echo "<p><strong>Cookie:</strong> Configurado com sucesso!</p>";
                echo "</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Usuário não encontrado!</div>";
        }
    }
    
    // Listar usuários
    $users = $conn->query("SELECT id, username, email, role FROM users ORDER BY id");
    
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3>🔧 Criar Token de Teste</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Selecionar Usuário:</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php while ($user = $users->fetch()): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['username']; ?> (<?php echo $user['email']; ?>) - <?php echo $user['role']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Expira em (dias):</label>
                    <input type="number" name="dias" class="form-control" value="30" min="1" max="365" required>
                </div>
                <button type="submit" name="criar_token" class="btn btn-primary">Criar Token</button>
                <a href="verificar_tabela_remember.php" class="btn btn-secondary">Voltar</a>
            </form>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>ERRO: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>