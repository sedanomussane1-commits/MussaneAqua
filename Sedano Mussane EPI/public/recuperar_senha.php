<?php
// recuperar_senha.php
session_start();
require_once 'Database.php';
require_once 'Validator.php';
require_once 'Auth.php';

$auth = new Auth();
$error = '';
$success = '';
$csrf_token = Validator::generateCSRFToken();

// Processar solicitação de recuperação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recuperar'])) {
    if (!Validator::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Erro de validação CSRF';
    } else {
        $email = Validator::sanitizeEmail($_POST['email'] ?? '');
        
        // Verificar se email existe
        $userManager = new UserManager();
        $user = $userManager->getUserByEmail($email);
        
        if ($user) {
            // Gerar token único
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Salvar token no banco
            $conn = (new Database())->getConnection();
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expira]);
            
            // Enviar email (simulado)
            $link = "http://" . $_SERVER['HTTP_HOST'] . "/resetar_senha.php?token=" . $token;
            
            // Aqui você deve implementar o envio real de email
            // mail($email, "Recuperação de Senha", "Clique no link: " . $link);
            
            $success = "Instruções enviadas para seu email!";
            
            // Log de auditoria
            $audit = new AuditLog();
            $audit->log($user['id'], 'PASSWORD_RESET_REQUEST', 'users', $user['id'], null, null, 'Solicitação de recuperação de senha');
        } else {
            // Não revelar se email existe ou não (segurança)
            $success = "Se o email existir, você receberá instruções para recuperar sua senha.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recuperar Senha - MussaneAqua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0">Recuperar Senha</h4>
                    </div>
                    <div class="card-body">
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
                                <label class="form-label">Email cadastrado:</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100">Enviar Instruções</button>
                        </form>
                        
                        <hr>
                        <p class="text-center mb-0">
                            <a href="login.php">Voltar para o login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>