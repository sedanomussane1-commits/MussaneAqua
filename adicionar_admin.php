<?php
// adicionar_admin.php
session_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Administrador - MussaneAqua</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 50px auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .card-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .requirement {
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement i {
            margin-right: 8px;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .user-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .badge-admin {
            background: #dc3545;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-funcionario {
            background: #ffc107;
            color: #212529;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-cliente {
            background: #17a2b8;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-user-shield me-2"></i>Adicionar Administrador</h1>
                <p class="mb-0">Crie uma nova conta de administrador no sistema</p>
            </div>
            
            <div class="card-body">
                <?php
                // Processar formulário
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_admin'])) {
                    
                    // Incluir arquivos necessários
                    require_once __DIR__ . '/config/Database.php';
                    require_once __DIR__ . '/includes/Validator.php';
                    
                    $username = Validator::sanitizeString($_POST['username'] ?? '');
                    $email = Validator::sanitizeEmail($_POST['email'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';
                    
                    $errors = [];
                    
                    // Validações
                    if (empty($username)) {
                        $errors[] = "Nome de usuário é obrigatório";
                    } elseif (strlen($username) < 3) {
                        $errors[] = "Nome de usuário deve ter pelo menos 3 caracteres";
                    }
                    
                    if (empty($email)) {
                        $errors[] = "Email é obrigatório";
                    } elseif (!Validator::validateEmail($email)) {
                        $errors[] = "Email inválido";
                    }
                    
                    if (empty($password)) {
                        $errors[] = "Senha é obrigatória";
                    } elseif (!Validator::validateStrongPassword($password)) {
                        $errors[] = "Senha deve ter 8+ caracteres, maiúscula, minúscula e número";
                    }
                    
                    if ($password !== $confirm_password) {
                        $errors[] = "As senhas não coincidem";
                    }
                    
                    if (empty($errors)) {
                        try {
                            $db = new Database();
                            $conn = $db->getConnection();
                            
                            // Verificar se email já existe
                            $check = $conn->prepare("SELECT id, username, role FROM users WHERE email = ?");
                            $check->execute([$email]);
                            $existing = $check->fetch();
                            
                            if ($existing) {
                                echo '<div class="alert alert-danger">';
                                echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                echo "Email já cadastrado para o usuário: {$existing['username']} (Papel: {$existing['role']})";
                                echo '</div>';
                            } else {
                                // Verificar se username já existe
                                $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                                $check->execute([$username]);
                                if ($check->fetch()) {
                                    echo '<div class="alert alert-danger">';
                                    echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                    echo "Nome de usuário já está em uso";
                                    echo '</div>';
                                } else {
                                    // Gerar hash da senha
                                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                                    
                                    // Inserir novo admin
                                    $insert = $conn->prepare("
                                        INSERT INTO users (username, email, password_hash, role, is_active, created_at) 
                                        VALUES (?, ?, ?, 'admin', 1, NOW())
                                    ");
                                    
                                    if ($insert->execute([$username, $email, $hash])) {
                                        $novo_id = $conn->lastInsertId();
                                        
                                        echo '<div class="alert alert-success">';
                                        echo '<i class="fas fa-check-circle me-2"></i>';
                                        echo '<strong> ADMINISTRADOR CRIADO COM SUCESSO!</strong><br>';
                                        echo "ID: {$novo_id}<br>";
                                        echo "Usuário: {$username}<br>";
                                        echo "Email: {$email}<br>";
                                        echo "Papel: admin<br>";
                                        echo "Status: Ativo<br>";
                                        echo '</div>';
                                        
                                        echo '<div class="info-box">';
                                        echo '<i class="fas fa-info-circle me-2"></i>';
                                        echo '<strong>Dica:</strong> Guarde estas informações em local seguro.';
                                        echo '</div>';
                                    } else {
                                        echo '<div class="alert alert-danger">';
                                        echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                        echo "Erro ao criar administrador";
                                        echo '</div>';
                                    }
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">';
                            echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                            echo "Erro: " . $e->getMessage();
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger">';
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                        echo '<strong>Erros encontrados:</strong><br>';
                        echo '<ul>';
                        foreach ($errors as $error) {
                            echo '<li>' . $error . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                }
                
                // Mostrar lista de admins existentes
                try {
                    require_once __DIR__ . '/config/Database.php';
                    $db = new Database();
                    $conn = $db->getConnection();
                    
                    $admins = $conn->query("
                        SELECT id, username, email, created_at, is_active 
                        FROM users 
                        WHERE role = 'admin' 
                        ORDER BY id
                    ");
                    
                    if ($admins->rowCount() > 0) {
                        echo '<div class="user-list">';
                        echo '<h5><i class="fas fa-users-cog me-2"></i>Administradores Existentes:</h5>';
                        
                        while ($admin = $admins->fetch()) {
                            $status = $admin['is_active'] ? 'Ativo' : 'Inativo';
                            $status_color = $admin['is_active'] ? 'success' : 'secondary';
                            
                            echo '<div class="user-item">';
                            echo '<div>';
                            echo '<strong>' . htmlspecialchars($admin['username']) . '</strong><br>';
                            echo '<small>' . htmlspecialchars($admin['email']) . '</small>';
                            echo '</div>';
                            echo '<div>';
                            echo '<span class="badge-admin me-2">Admin</span>';
                            echo '<span class="badge bg-' . $status_color . '">' . $status . '</span>';
                            echo '<small class="text-muted ms-2">ID: ' . $admin['id'] . '</small>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    // Ignorar erro se a tabela não existir
                }
                ?>
                
                <form method="POST" id="adminForm" onsubmit="return validateForm()">
                    <input type="hidden" name="adicionar_admin" value="1">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user me-2"></i>Nome de Usuário *</label>
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Ex: admin2" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope me-2"></i>Email *</label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="admin@exemplo.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-2"></i>Senha *</label>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Digite a senha" required onkeyup="checkPassword()">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-2"></i>Confirmar Senha *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                               placeholder="Digite a senha novamente" required onkeyup="checkPasswordMatch()">
                    </div>
                    
                    <div class="password-requirements" id="requirements">
                        <strong>Requisitos de segurança:</strong>
                        <div class="requirement" id="req-length">
                            <i class="fas fa-circle"></i> Mínimo 8 caracteres
                        </div>
                        <div class="requirement" id="req-upper">
                            <i class="fas fa-circle"></i> Pelo menos 1 letra maiúscula
                        </div>
                        <div class="requirement" id="req-lower">
                            <i class="fas fa-circle"></i> Pelo menos 1 letra minúscula
                        </div>
                        <div class="requirement" id="req-number">
                            <i class="fas fa-circle"></i> Pelo menos 1 número
                        </div>
                        <div class="requirement" id="req-match">
                            <i class="fas fa-circle"></i> Senhas coincidem
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit mt-3" id="btn-submit">
                        <i class="fas fa-user-shield me-2"></i>Adicionar Administrador
                    </button>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <a href="public/login.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function checkPassword() {
            const password = document.getElementById('password').value;
            
            const reqLength = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqLower = document.getElementById('req-lower');
            const reqNumber = document.getElementById('req-number');
            
            // Mínimo 8 caracteres
            if (password.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> Mínimo 8 caracteres ✓';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> Mínimo 8 caracteres';
            }
            
            // Pelo menos uma maiúscula
            if (/[A-Z]/.test(password)) {
                reqUpper.classList.add('valid');
                reqUpper.innerHTML = '<i class="fas fa-check-circle"></i> Pelo menos 1 letra maiúscula ✓';
            } else {
                reqUpper.classList.remove('valid');
                reqUpper.innerHTML = '<i class="fas fa-circle"></i> Pelo menos 1 letra maiúscula';
            }
            
            // Pelo menos uma minúscula
            if (/[a-z]/.test(password)) {
                reqLower.classList.add('valid');
                reqLower.innerHTML = '<i class="fas fa-check-circle"></i> Pelo menos 1 letra minúscula ✓';
            } else {
                reqLower.classList.remove('valid');
                reqLower.innerHTML = '<i class="fas fa-circle"></i> Pelo menos 1 letra minúscula';
            }
            
            // Pelo menos um número
            if (/[0-9]/.test(password)) {
                reqNumber.classList.add('valid');
                reqNumber.innerHTML = '<i class="fas fa-check-circle"></i> Pelo menos 1 número ✓';
            } else {
                reqNumber.classList.remove('valid');
                reqNumber.innerHTML = '<i class="fas fa-circle"></i> Pelo menos 1 número';
            }
            
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const reqMatch = document.getElementById('req-match');
            
            if (password && confirm && password === confirm) {
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Senhas coincidem ✓';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Senhas coincidem';
            }
        }
        
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                alert('A senha deve ter no mínimo 8 caracteres');
                return false;
            }
            
            if (!/[A-Z]/.test(password)) {
                alert('A senha deve conter pelo menos uma letra maiúscula');
                return false;
            }
            
            if (!/[a-z]/.test(password)) {
                alert('A senha deve conter pelo menos uma letra minúscula');
                return false;
            }
            
            if (!/[0-9]/.test(password)) {
                alert('A senha deve conter pelo menos um número');
                return false;
            }
            
            if (password !== confirm) {
                alert('As senhas não coincidem');
                return false;
            }
            
            const btn = document.getElementById('btn-submit');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processando...';
            btn.disabled = true;
            
            return true;
        }
    </script>
</body>
</html>