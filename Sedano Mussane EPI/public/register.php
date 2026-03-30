<?php
// public/register.php
session_start();
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Validator.php';

$auth = new Auth();

if ($auth->isAuthenticated()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$error = '';
$success = '';
$csrf_token = Validator::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Validator::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Erro de validação CSRF';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validações básicas
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'O nome de usuário é obrigatório';
        } elseif (strlen($username) < 3) {
            $errors[] = 'O nome de usuário deve ter pelo menos 3 caracteres';
        }
        
        if (empty($email)) {
            $errors[] = 'O email é obrigatório';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($password)) {
            $errors[] = 'A senha é obrigatória';
        } elseif (strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'As senhas não coincidem';
        }
        
        if (empty($errors)) {
            $result = $auth->register($username, $email, $password);
            
            if ($result['success']) {
                $success = $result['message'];
                // Gerar novo token CSRF após registro bem-sucedido
                $csrf_token = Validator::generateCSRFToken();
            } else {
                $error = $result['message'];
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - MussaneAqua</title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
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
        
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #43e97b;
            box-shadow: 0 0 0 3px rgba(67, 233, 123, 0.1);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 233, 123, 0.4);
        }
        
        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .is-valid {
            border-color: #28a745 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .invalid-feedback {
            display: block;
            font-size: 0.875rem;
            color: #dc3545;
            margin-top: 0.25rem;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 0.9rem;
        }
        
        .requirement {
            margin-bottom: 8px;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement.invalid {
            color: #dc3545;
        }
        
        .requirement i {
            width: 20px;
            text-align: center;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        #email-status {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .alert a {
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="register-card">
            <div class="register-header">
                <h2><i class="fas fa-user-plus me-2"></i>Criar Conta</h2>
                <p class="mb-0">Junte-se à MussaneAqua</p>
            </div>
            
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <br>
                        <a href="login.php" class="alert-link">Faça login aqui</a>
                    </div>
                <?php else: ?>
                    <form method="POST" id="registerForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">
                                <i class="fas fa-user me-1"></i>Nome de Usuário *
                            </label>
                            <input type="text" 
                                   name="username" 
                                   class="form-control" 
                                   id="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   required 
                                   minlength="3"
                                   data-validate="true"
                                   autocomplete="username">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">
                                <i class="fas fa-envelope me-1"></i>Email *
                            </label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   id="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required 
                                   data-validate="true"
                                   autocomplete="email">
                            <div class="invalid-feedback"></div>
                            <small class="text-muted" id="email-status"></small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">
                                <i class="fas fa-lock me-1"></i>Senha *
                            </label>
                            <input type="password" 
                                   name="password" 
                                   class="form-control" 
                                   id="password" 
                                   required 
                                   minlength="8"
                                   data-validate="true"
                                   autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-bold">
                                <i class="fas fa-lock me-1"></i>Confirmar Senha *
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   required 
                                   data-validate="true"
                                   autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="password-requirements" id="password-requirements">
                            <strong><i class="fas fa-shield-alt me-2"></i>Requisitos da senha:</strong>
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
                        
                        <button type="submit" class="btn btn-register mt-3" id="btn-register">
                            <i class="fas fa-user-plus me-2"></i>Registrar
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    <p class="text-center mb-0">
                        Já tem conta? <a href="login.php" class="text-decoration-none">Faça login</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Configurar CSRF para todas as requisições AJAX
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('input[name="csrf_token"]').val()
                }
            });

            let timeoutId;

            // Validação em tempo real
            $('#username, #email, #password, #confirm_password').on('input', function() {
                clearTimeout(timeoutId);
                const $campo = $(this);
                
                timeoutId = setTimeout(function() {
                    validarCampo($campo);
                }, 500);
            });

            // Validação específica para senha
            $('#password').on('keyup', function() {
                validarSenha($(this).val());
            });

            // Validação específica para confirmação de senha
            $('#confirm_password').on('keyup', function() {
                verificarSenhasCoincidem();
                validarCampo($(this));
            });

            // Verificar email quando sair do campo
            $('#email').on('blur', function() {
                verificarEmail($(this).val());
            });

            // Submissão do formulário
            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validar todos os campos antes de enviar
                let formValido = true;
                
                // Remover validações anteriores
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').empty();
                
                // Validar cada campo
                $('#username, #email, #password, #confirm_password').each(function() {
                    if (!validarCampoAjax($(this))) {
                        formValido = false;
                    }
                });
                
                if (formValido && $('#password').val() === $('#confirm_password').val()) {
                    submitForm($(this));
                } else {
                    if ($('#password').val() !== $('#confirm_password').val()) {
                        mostrarErro($('#confirm_password'), 'As senhas não coincidem');
                    }
                }
            });

            // Função para validar campo via AJAX
            function validarCampoAjax($campo) {
                const valor = $campo.val().trim();
                const nome = $campo.attr('name');
                
                if (!valor && $campo.prop('required')) {
                    mostrarErro($campo, 'Este campo é obrigatório');
                    return false;
                }
                
                if (nome === 'username' && valor.length < 3) {
                    mostrarErro($campo, 'O nome de usuário deve ter pelo menos 3 caracteres');
                    return false;
                }
                
                if (nome === 'email' && valor && !isValidEmail(valor)) {
                    mostrarErro($campo, 'Email inválido');
                    return false;
                }
                
                if (nome === 'password' && valor.length < 8) {
                    mostrarErro($campo, 'A senha deve ter pelo menos 8 caracteres');
                    return false;
                }
                
                return true;
            }
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function mostrarErro($campo, mensagem) {
                $campo.addClass('is-invalid').removeClass('is-valid');
                let $feedback = $campo.siblings('.invalid-feedback');
                if ($feedback.length === 0) {
                    $feedback = $('<div class="invalid-feedback"></div>');
                    $campo.after($feedback);
                }
                $feedback.text(mensagem);
            }
        });

        function validarCampo($campo) {
            const nome = $campo.attr('name');
            const valor = $campo.val().trim();
            
            // Validações básicas no cliente
            if (nome === 'username' && valor.length > 0 && valor.length < 3) {
                $campo.addClass('is-invalid').removeClass('is-valid');
                atualizarFeedback($campo, 'O nome de usuário deve ter pelo menos 3 caracteres');
                return;
            }
            
            if (nome === 'email' && valor.length > 0 && !isValidEmail(valor)) {
                $campo.addClass('is-invalid').removeClass('is-valid');
                atualizarFeedback($campo, 'Email inválido');
                return;
            }
            
            if (nome === 'password' && valor.length > 0 && valor.length < 8) {
                $campo.addClass('is-invalid').removeClass('is-valid');
                atualizarFeedback($campo, 'A senha deve ter pelo menos 8 caracteres');
                return;
            }
            
            if (valor.length > 0) {
                $campo.addClass('is-valid').removeClass('is-invalid');
                removerFeedback($campo);
            } else {
                $campo.removeClass('is-valid is-invalid');
                removerFeedback($campo);
            }
        }
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function atualizarFeedback($campo, mensagem) {
            let $feedback = $campo.siblings('.invalid-feedback');
            if ($feedback.length === 0) {
                $feedback = $('<div class="invalid-feedback"></div>');
                $campo.after($feedback);
            }
            $feedback.text(mensagem);
        }
        
        function removerFeedback($campo) {
            $campo.siblings('.invalid-feedback').remove();
        }

        function validarSenha(senha) {
            const requisitos = {
                length: senha.length >= 8,
                upper: /[A-Z]/.test(senha),
                lower: /[a-z]/.test(senha),
                number: /[0-9]/.test(senha)
            };
            
            const reqElements = {
                length: $('#req-length'),
                upper: $('#req-upper'),
                lower: $('#req-lower'),
                number: $('#req-number')
            };
            
            let todosValidos = true;
            
            for (let req in requisitos) {
                const elemento = reqElements[req];
                if (requisitos[req]) {
                    elemento.addClass('valid').removeClass('invalid');
                    elemento.html('<i class="fas fa-check-circle me-2"></i> ' + 
                        elemento.text().replace(/[✓✗]/g, '').trim() + ' ✓');
                } else {
                    elemento.addClass('invalid').removeClass('valid');
                    elemento.html('<i class="fas fa-times-circle me-2"></i> ' + 
                        elemento.text().replace(/[✓✗]/g, '').trim() + ' ✗');
                    todosValidos = false;
                }
            }
            
            if (senha.length > 0) {
                if (todosValidos) {
                    $('#password').addClass('is-valid').removeClass('is-invalid');
                } else {
                    $('#password').addClass('is-invalid').removeClass('is-valid');
                }
            }
            
            verificarSenhasCoincidem();
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
                    removerFeedback($('#confirm_password'));
                } else {
                    $reqMatch.addClass('invalid').removeClass('valid');
                    $reqMatch.html('<i class="fas fa-times-circle me-2"></i> Senhas coincidem ✗');
                    $('#confirm_password').addClass('is-invalid').removeClass('is-valid');
                    atualizarFeedback($('#confirm_password'), 'As senhas não coincidem');
                }
            } else {
                $reqMatch.removeClass('valid invalid');
                $reqMatch.html('<i class="fas fa-circle me-2"></i> Senhas coincidem');
            }
        }

        function verificarEmail(email) {
            if (!email || email.length < 5) return;
            
            const $emailField = $('#email');
            const $status = $('#email-status');
            
            $.ajax({
                url: '../api/api.php',
                method: 'POST',
                data: {
                    action: 'verificar_email',
                    email: email
                },
                success: function(response) {
                    if (response && response.disponivel) {
                        $emailField.addClass('is-valid').removeClass('is-invalid');
                        $status.html('<i class="fas fa-check-circle text-success me-1"></i> Email disponível')
                               .removeClass('text-danger').addClass('text-success');
                        removerFeedback($emailField);
                    } else if (response && !response.disponivel) {
                        $emailField.addClass('is-invalid').removeClass('is-valid');
                        $status.html('<i class="fas fa-times-circle text-danger me-1"></i> Email já cadastrado')
                               .removeClass('text-success').addClass('text-danger');
                        atualizarFeedback($emailField, 'Este email já está cadastrado');
                    }
                },
                error: function() {
                    console.error('Erro ao verificar email');
                }
            });
        }

        function submitForm($form) {
            const $btn = $('#btn-register');
            const originalText = $btn.html();
            
            // Desabilitar botão e mostrar loading
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Processando...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    // Recarregar a página para mostrar mensagem de sucesso/erro
                    location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', error);
                    alert('Erro ao processar registro. Tente novamente.');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    </script>
</body>
</html>