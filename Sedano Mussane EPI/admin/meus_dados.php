<?php
// admin/meus_dados.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$middleware = new AuthMiddleware();
$middleware->requireAuth();

$userManager = new UserManager();
$audit = new AuditLog();
$user = $userManager->getUserById($_SESSION['user_id']);

$error = '';
$success = '';
$csrf_token = Validator::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_dados'])) {
    if (!Validator::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Erro de validação do formulário';
    } else {
        $dados = [];
        
        if (!empty($_POST['username']) && $_POST['username'] !== $user['username']) {
            $dados['username'] = Validator::sanitizeString($_POST['username']);
        }
        
        if (!empty($_POST['email']) && $_POST['email'] !== $user['email']) {
            if (!Validator::validateEmail($_POST['email'])) {
                $error = 'Email inválido';
            } else {
                $dados['email'] = Validator::sanitizeEmail($_POST['email']);
            }
        }
        
        if (!empty($_POST['password'])) {
            if (!Validator::validateStrongPassword($_POST['password'])) {
                $error = 'A nova senha deve ter no mínimo 8 caracteres, uma maiúscula, uma minúscula e um número';
            } elseif ($_POST['password'] !== $_POST['confirm_password']) {
                $error = 'As senhas não coincidem';
            } else {
                $dados['password'] = $_POST['password'];
            }
        }
        
        if (empty($error) && !empty($dados)) {
            $result = $userManager->updateUser($user['id'], $dados, $user['id']);
            if ($result['success']) {
                $success = $result['message'];
                if (isset($dados['username'])) $_SESSION['username'] = $dados['username'];
                if (isset($dados['email'])) $_SESSION['email'] = $dados['email'];
                $user = $userManager->getUserById($_SESSION['user_id']);
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Dados - MussaneAqua</title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            padding: 30px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .is-valid {
            border-color: #28a745 !important;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .invalid-feedback {
            display: block;
        }
        
        .password-requirements {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .requirement {
            margin-bottom: 5px;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement.invalid {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-swimming-pool fa-3x mb-2"></i>
                        <h4>MussaneAqua</h4>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                        <a class="nav-link" href="novo_agendamento.php">
                            <i class="fas fa-calendar-plus"></i>Novo Agendamento
                        </a>
                        <a class="nav-link active" href="meus_dados.php">
                            <i class="fas fa-user"></i>Meus Dados
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a class="nav-link" href="gestao_usuarios.php">
                            <i class="fas fa-users-cog"></i>Gestão de Usuários
                        </a>
                        <a class="nav-link" href="visualizar_logs.php">
                            <i class="fas fa-history"></i>Logs de Auditoria
                        </a>
                        <?php endif; ?>
                        <hr style="border-color: rgba(255,255,255,0.2);">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home"></i>Site
                        </a>
                        <a class="nav-link text-danger" href="../public/logout.php">
                            <i class="fas fa-sign-out-alt"></i>Sair
                        </a>
                    </nav>
                </div>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="profile-header d-flex align-items-center">
                    <div class="profile-avatar me-4">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h2 class="mb-2"><?php echo $user['username']; ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-2"></i><?php echo $user['email']; ?>
                        </p>
                        <p class="mb-0 mt-2">
                            <span class="badge bg-light text-dark p-2">
                                <i class="fas fa-user-tag me-1"></i><?php echo $user['role']; ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="mb-4"><i class="fas fa-info-circle me-2 text-primary"></i>Informações da Conta</h5>
                            
                            <div class="row mb-3">
                                <div class="col-5 info-label">ID do Usuário</div>
                                <div class="col-7 info-value">#<?php echo $user['id']; ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-5 info-label">Papel</div>
                                <div class="col-7 info-value">
                                    <span class="badge bg-primary"><?php echo $user['role']; ?></span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-5 info-label">Status</div>
                                <div class="col-7 info-value">
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-5 info-label">Membro desde</div>
                                <div class="col-7 info-value">
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-5 info-label">Último login</div>
                                <div class="col-7 info-value">
                                    <?php 
                                    echo $user['last_login'] 
                                        ? date('d/m/Y H:i', strtotime($user['last_login'])) 
                                        : 'Primeiro acesso';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="mb-4"><i class="fas fa-edit me-2 text-primary"></i>Editar Dados</h5>
                            
                            <form method="POST" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="atualizar_dados" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Nome de Usuário</label>
                                    <input type="text" class="form-control" name="username" 
                                           id="username" value="<?php echo $user['username']; ?>"
                                           data-validate="minlength:3">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           id="email" value="<?php echo $user['email']; ?>"
                                           data-validate="email">
                                    <div class="invalid-feedback"></div>
                                    <small class="text-muted" id="email-status"></small>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nova Senha (deixe em branco para manter)</label>
                                    <input type="password" class="form-control" name="password" id="password">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Nova Senha</label>
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="password-requirements" id="password-requirements" style="display: none;">
                                    <strong>Requisitos da senha:</strong>
                                    <div class="requirement" id="req-length">
                                        <i class="fas fa-circle me-2"></i>Mínimo 8 caracteres
                                    </div>
                                    <div class="requirement" id="req-upper">
                                        <i class="fas fa-circle me-2"></i>Pelo menos 1 letra maiúscula
                                    </div>
                                    <div class="requirement" id="req-lower">
                                        <i class="fas fa-circle me-2"></i>Pelo menos 1 letra minúscula
                                    </div>
                                    <div class="requirement" id="req-number">
                                        <i class="fas fa-circle me-2"></i>Pelo menos 1 número
                                    </div>
                                    <div class="requirement" id="req-match">
                                        <i class="fas fa-circle me-2"></i>Senhas coincidem
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary mt-3" id="btn-submit">
                                    <i class="fas fa-save me-2"></i>Salvar Alterações
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '<?php echo $csrf_token; ?>'
                }
            });

            $('#username, #email').on('blur', function() {
                validarCampo($(this));
            });

            $('#email').on('blur', function() {
                verificarEmail($(this).val(), <?php echo $user['id']; ?>);
            });

            $('#password').on('keyup', function() {
                const senha = $(this).val();
                if (senha.length > 0) {
                    $('#password-requirements').show();
                    validarSenha(senha);
                } else {
                    $('#password-requirements').hide();
                }
            });

            $('#confirm_password').on('keyup', function() {
                verificarSenhasCoincidem();
            });
        });

        function validarCampo($campo) {
            const nome = $campo.attr('name');
            const valor = $campo.val();
            const dados = {};
            dados[nome] = valor;

            $.ajax({
                url: '../api/api.php',
                method: 'POST',
                data: {
                    action: 'validar_campos',
                    campos: dados
                },
                success: function(response) {
                    if (response.erros && response.erros[nome]) {
                        $campo.addClass('is-invalid').removeClass('is-valid');
                        if ($campo.next('.invalid-feedback').length === 0) {
                            $campo.after('<div class="invalid-feedback">' + response.erros[nome] + '</div>');
                        } else {
                            $campo.next('.invalid-feedback').text(response.erros[nome]);
                        }
                    } else {
                        $campo.addClass('is-valid').removeClass('is-invalid');
                        $campo.next('.invalid-feedback').remove();
                    }
                }
            });
        }

        function verificarEmail(email, exclude_id) {
            if (!email) return;

            $.ajax({
                url: '../api/api.php',
                method: 'POST',
                data: {
                    action: 'verificar_email',
                    email: email,
                    exclude_id: exclude_id
                },
                success: function(response) {
                    const $emailField = $('#email');
                    const $status = $('#email-status');
                    
                    if (response.disponivel) {
                        $emailField.addClass('is-valid').removeClass('is-invalid');
                        $status.text('✓ Email disponível').css('color', '#28a745');
                    } else {
                        $emailField.addClass('is-invalid').removeClass('is-valid');
                        $status.text('✗ Email já cadastrado').css('color', '#dc3545');
                    }
                }
            });
        }

        function validarSenha(senha) {
            $.ajax({
                url: '../api/api.php',
                method: 'POST',
                data: {
                    action: 'validar_senha',
                    senha: senha
                },
                success: function(response) {
                    const requisitos = {
                        length: $('#req-length'),
                        upper: $('#req-upper'),
                        lower: $('#req-lower'),
                        number: $('#req-number')
                    };

                    for (let req in requisitos) {
                        if (response.requisitos[req]) {
                            requisitos[req].addClass('valid').removeClass('invalid');
                            requisitos[req].html('<i class="fas fa-check-circle me-2"></i> ' + 
                                requisitos[req].text().replace('✗', '✓'));
                        } else {
                            requisitos[req].addClass('invalid').removeClass('valid');
                            requisitos[req].html('<i class="fas fa-times-circle me-2"></i> ' + 
                                requisitos[req].text().replace('✓', '✗'));
                        }
                    }

                    if (response.valida) {
                        $('#password').addClass('is-valid').removeClass('is-invalid');
                    } else {
                        $('#password').addClass('is-invalid').removeClass('is-valid');
                    }
                    
                    verificarSenhasCoincidem();
                }
            });
        }

        function verificarSenhasCoincidem() {
            const senha = $('#password').val();
            const confirm = $('#confirm_password').val();
            const $reqMatch = $('#req-match');

            if (senha && confirm) {
                if (senha === confirm) {
                    $reqMatch.addClass('valid').removeClass('invalid');
                    $reqMatch.html('<i class="fas fa-check-circle me-2"></i> Senhas coincidem ✓');
                    $('#confirm_password').addClass('is-valid').removeClass('is-invalid');
                    $('#confirm_password').next('.invalid-feedback').remove();
                } else {
                    $reqMatch.addClass('invalid').removeClass('valid');
                    $reqMatch.html('<i class="fas fa-times-circle me-2"></i> Senhas coincidem ✗');
                    $('#confirm_password').addClass('is-invalid').removeClass('is-valid');
                    if ($('#confirm_password').next('.invalid-feedback').length === 0) {
                        $('#confirm_password').after('<div class="invalid-feedback">As senhas não coincidem</div>');
                    }
                }
            }
        }
    </script>
</body>
</html>