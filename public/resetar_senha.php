<?php
// public/recuperar_senha.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/TokenManager.php';

$error = '';
$success = '';
$tokenManager = TokenManager::getInstance();
$csrf_token = $tokenManager->generateCSRFToken();

// Processar solicitação de recuperação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recuperar'])) {
    if (!$tokenManager->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Erro de validação CSRF';
    } else {
        $email = Validator::sanitizeEmail($_POST['email'] ?? '');
        
        // Verificar se email existe
        $userManager = new UserManager();
        $user = $userManager->getUserByEmail($email);
        
        if ($user) {
            // Criar tabela password_resets se não existir
            $conn = (new Database())->getConnection();
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_token (token),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Exception $e) {
                // Tabela já existe
            }
            
            // Gerar token único
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Marcar tokens antigos como usados
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            // Salvar token no banco
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expira]);
            
            // Enviar email (simulado)
            $link = "http://" . $_SERVER['HTTP_HOST'] . "/Sedano%20Mussane%20EPI/public/resetar_senha.php?token=" . $token;
            
            // Em produção, enviar email de verdade
            // mail($email, "Recuperação de Senha - MussaneAqua", "Clique no link para redefinir sua senha: " . $link);
            
            // Log de auditoria
            $audit = new AuditLog();
            $audit->log($user['id'], 'PASSWORD_RESET_REQUEST', 'users', $user['id'], null, null, 'Solicitação de recuperação de senha');
            
            // Para teste, mostrar o link (remover em produção)
            $success = "Link de recuperação gerado: <br><a href='{$link}' target='_blank'>{$link}</a>";
        } else {
            // Não revelar se email existe ou não (segurança)
            $success = "Se o email existir, você receberá instruções para recuperar sua senha.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - MussaneAqua</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .recuperar-container {
            max-width: 450px;
            margin: 100px auto;
        }
        
        .recuperar-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .recuperar-header {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .recuperar-body {
            padding: 40px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #ff9a9e;
            box-shadow: 0 0 0 3px rgba(255, 154, 158, 0.1);
        }
        
        .btn-recuperar {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-recuperar:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 154, 158, 0.4);
        }
    </style>
</head>
<body>
    <div class="container recuperar-container">
        <div class="recuperar-card">
            <div class="recuperar-header">
                <h2><i class="fas fa-lock me-2"></i>Recuperar Senha</h2>
                <p class="mb-0">Enviaremos instruções para seu email</p>
            </div>
            
            <div class="recuperar-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="recuperar" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email cadastrado:</label>
                        <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                    </div>
                    
                    <button type="submit" class="btn-recuperar">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Instruções
                    </button>
                </form>
                
                <hr>
                <p class="text-center mb-0">
                    <a href="login.php" class="text-muted"><i class="fas fa-arrow-left me-1"></i>Voltar para o login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>