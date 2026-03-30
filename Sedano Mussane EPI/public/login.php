<?php
session_start();
require_once __DIR__ . "/../includes/Auth.php";
require_once __DIR__ . "/../includes/Validator.php";

$auth = new Auth();

if ($auth->isAuthenticated()) {
    header("Location: ../admin/dashboard.php");
    exit;
}

$error = "";

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]) {
        $error = "Erro de validação CSRF";
    } else {
        $email = $_POST["email"] ?? "";
        $password = $_POST["password"] ?? "";
        $remember = isset($_POST["remember"]);
        
        $result = $auth->login($email, $password, $remember);
        
        if ($result["success"]) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            header("Location: ../admin/dashboard.php");
            exit;
        } else {
            $error = $result["message"];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - MussaneAqua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">MussaneAqua - Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Senha</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Lembrar-me</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>