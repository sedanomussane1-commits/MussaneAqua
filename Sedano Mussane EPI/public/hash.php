<?php
// public/hash.php - Gerador de hash de senha
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Hash de Senha - MussaneAqua</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hash-container {
            max-width: 700px;
            margin: 50px auto;
        }
        
        .hash-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .hash-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .hash-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .hash-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .hash-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
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
        
        .btn-generate {
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
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-copy {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-copy:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .result-box {
            background: #1e1e1e;
            color: #0f0;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 20px 0;
            border: 2px solid #333;
            position: relative;
        }
        
        .result-label {
            position: absolute;
            top: -12px;
            left: 20px;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .sql-box {
            background: #1e1e1e;
            color: #ff0;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 20px 0;
            border: 2px solid #333;
            position: relative;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .requirements ul {
            list-style: none;
            padding-left: 0;
            margin-top: 10px;
        }
        
        .requirements li {
            margin-bottom: 8px;
            color: #666;
        }
        
        .requirements li.valid {
            color: #28a745;
        }
        
        .requirements li i {
            margin-right: 8px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container hash-container">
        <div class="hash-card">
            <div class="hash-header">
                <h1><i class="fas fa-key me-2"></i>Gerador de Hash de Senha</h1>
                <p>Gere hashes seguros usando bcrypt para o sistema MussaneAqua</p>
            </div>
            
            <div class="hash-body">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['senha'])) {
                    $senha = $_POST['senha'];
                    $confirmar = $_POST['confirmar_senha'] ?? '';
                    
                    $errors = [];
                    
                    if (strlen($senha) < 8) {
                        $errors[] = "A senha deve ter no mínimo 8 caracteres";
                    }
                    
                    if (!preg_match('/[A-Z]/', $senha)) {
                        $errors[] = "A senha deve conter pelo menos uma letra maiúscula";
                    }
                    
                    if (!preg_match('/[a-z]/', $senha)) {
                        $errors[] = "A senha deve conter pelo menos uma letra minúscula";
                    }
                    
                    if (!preg_match('/[0-9]/', $senha)) {
                        $errors[] = "A senha deve conter pelo menos um número";
                    }
                    
                    if ($senha !== $confirmar) {
                        $errors[] = "As senhas não coincidem";
                    }
                    
                    if (empty($errors)) {
                        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                        $verificado = password_verify($senha, $hash) ? 'Sim ✓' : 'Não ✗';
                        
                        echo '<div class="info-box">';
                        echo '<strong><i class="fas fa-check-circle text-success me-2"></i>Hash gerado com sucesso!</strong>';
                        echo '</div>';
                        
                        echo '<div class="result-box">';
                        echo '<span class="result-label">HASH GERADO (bcrypt)</span>';
                        echo '<code style="color: #0f0;">' . $hash . '</code>';
                        echo '</div>';
                        
                        echo '<button class="btn-copy" onclick="copyToClipboard(\'' . $hash . '\')">';
                        echo '<i class="fas fa-copy me-2"></i>Copiar Hash';
                        echo '</button>';
                        
                        echo '<div class="sql-box">';
                        echo '<span class="result-label">QUERY SQL PARA ATUALIZAR</span>';
                        echo '<code style="color: #ff0;">';
                        echo '-- Atualizar por email<br>';
                        echo 'UPDATE users SET password_hash = \'' . $hash . '\' WHERE email = \'admin@mussaneaqua.com\';<br><br>';
                        echo '-- Atualizar por ID<br>';
                        echo 'UPDATE users SET password_hash = \'' . $hash . '\' WHERE id = 1;';
                        echo '</code>';
                        echo '</div>';
                        
                        echo '<button class="btn-copy" onclick="copyToClipboard(\'UPDATE users SET password_hash = \\\'' . $hash . '\\\' WHERE email = \\\'admin@mussaneaqua.com\\\';\')">';
                        echo '<i class="fas fa-copy me-2"></i>Copiar Query SQL';
                        echo '</button>';
                        
                        echo '<hr class="my-4">';
                        
                        echo '<h5><i class="fas fa-info-circle me-2"></i>Informações do Hash:</h5>';
                        echo '<ul class="list-group">';
                        echo '<li class="list-group-item"><strong>Algoritmo:</strong> bcrypt</li>';
                        echo '<li class="list-group-item"><strong>Custo:</strong> 12 (recomendado)</li>';
                        echo '<li class="list-group-item"><strong>Tamanho:</strong> 60 caracteres</li>';
                        echo '<li class="list-group-item"><strong>Verificação:</strong> ' . $verificado . '</li>';
                        echo '</ul>';
                        
                    } else {
                        echo '<div class="alert alert-danger">';
                        echo '<strong><i class="fas fa-exclamation-triangle me-2"></i>Erros encontrados:</strong><br>';
                        echo '<ul>';
                        foreach ($errors as $erro) {
                            echo '<li>' . $erro . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                }
                ?>
                
                <form method="POST" id="hashForm" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label><i class="fas fa-lock me-2"></i>Digite a senha:</label>
                        <input type="password" name="senha" id="senha" class="form-control" 
                               placeholder="Digite a senha" required onkeyup="checkPassword()">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-2"></i>Confirmar senha:</label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" 
                               placeholder="Digite a senha novamente" required onkeyup="checkPasswordMatch()">
                    </div>
                    
                    <div class="requirements" id="requirements">
                        <strong>Requisitos de segurança:</strong>
                        <ul>
                            <li id="req-length"><i class="fas fa-circle"></i> Mínimo 8 caracteres</li>
                            <li id="req-upper"><i class="fas fa-circle"></i> Pelo menos 1 letra maiúscula</li>
                            <li id="req-lower"><i class="fas fa-circle"></i> Pelo menos 1 letra minúscula</li>
                            <li id="req-number"><i class="fas fa-circle"></i> Pelo menos 1 número</li>
                            <li id="req-match"><i class="fas fa-circle"></i> Senhas coincidem</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn-generate" id="submitBtn">
                        <i class="fas fa-key me-2"></i>Gerar Hash
                    </button>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <h6 class="mb-3">Outras opções:</h6>
                    <a href="login.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                    <a href="register.php" class="btn btn-outline-success btn-sm me-2">
                        <i class="fas fa-user-plus me-1"></i>Registrar
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </div>
            </div>
        </div>
        
        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Voltar para o site
        </a>
    </div>
    
    <script>
        function checkPassword() {
            const password = document.getElementById('senha').value;
            
            const reqLength = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqLower = document.getElementById('req-lower');
            const reqNumber = document.getElementById('req-number');
            
            if (password.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> Mínimo 8 caracteres ✓';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> Mínimo 8 caracteres';
            }
            
            if (/[A-Z]/.test(password)) {
                reqUpper.classList.add('valid');
                reqUpper.innerHTML = '<i class="fas fa-check-circle"></i> Pelo menos 1 letra maiúscula ✓';
            } else {
                reqUpper.classList.remove('valid');
                reqUpper.innerHTML = '<i class="fas fa-circle"></i> Pelo menos 1 letra maiúscula';
            }
            
            if (/[a-z]/.test(password)) {
                reqLower.classList.add('valid');
                reqLower.innerHTML = '<i class="fas fa-check-circle"></i> Pelo menos 1 letra minúscula ✓';
            } else {
                reqLower.classList.remove('valid');
                reqLower.innerHTML = '<i class="fas fa-circle"></i> Pelo menos 1 letra minúscula';
            }
            
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
            const password = document.getElementById('senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
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
            const password = document.getElementById('senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
            
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
            
            return true;
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copiado para a área de transferência!');
            }, function(err) {
                alert('Erro ao copiar: ' + err);
            });
        }
    </script>
</body>
</html>