<?php
// admin/novo_agendamento.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/AgendamentoManager.php';
require_once __DIR__ . '/../includes/UserManager.php';

$middleware = new AuthMiddleware();
$middleware->requireAuth();

$agendamentoManager = new AgendamentoManager();
$error = '';
$success = '';
$csrf_token = Validator::generateCSRFToken();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
    if (!Validator::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Erro de validação do formulário';
    } else {
        // Buscar dados do usuário
        $userManager = new UserManager();
        $user = $userManager->getUserById($_SESSION['user_id']);
        
        // Preparar dados para salvar
        $dados = [
            'cliente_nome' => $user['username'],
            'cliente_email' => $user['email'],
            'cliente_telefone' => Validator::sanitizeString($_POST['telefone'] ?? ''),
            'endereco' => Validator::sanitizeString($_POST['endereco'] ?? ''),
            'servico' => Validator::sanitizeString($_POST['servico'] ?? ''),
            'data_agendamento' => $_POST['data'] ?? '',
            'horario' => $_POST['horario'] ?? '',
            'observacoes' => Validator::sanitizeString($_POST['observacoes'] ?? '')
        ];
        
        // Validar telefone
        if (empty($dados['cliente_telefone'])) {
            $error = 'Telefone é obrigatório';
        } else {
            // Salvar no banco
            $result = $agendamentoManager->createAgendamento($dados, $_SESSION['user_id']);
            
            if ($result['success']) {
                $success = $result['message'];
                
                // Log da ação
                $audit = new AuditLog();
                $audit->log($_SESSION['user_id'], 'CREATE_AGENDAMENTO', 'agendamentos', 
                           $result['agendamento_id'], null, $dados, 
                           'Novo agendamento criado - Protocolo: ' . $result['protocolo']);
                
                // Redirecionar para dashboard após 3 segundos
                header("refresh:3;url=dashboard.php");
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Buscar dados do usuário
$userManager = new UserManager();
$user = $userManager->getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Agendamento - MussaneAqua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .success-animation {
            text-align: center;
            padding: 40px;
        }
        
        .success-animation i {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .protocolo-box {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                        <a class="nav-link active" href="novo_agendamento.php">
                            <i class="fas fa-calendar-plus"></i>Novo Agendamento
                        </a>
                        <a class="nav-link" href="meus_dados.php">
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
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-calendar-plus me-2 text-primary"></i>Novo Agendamento</h2>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar para Dashboard
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-animation">
                        <i class="fas fa-check-circle"></i>
                        <h3 class="text-success">Agendamento realizado com sucesso!</h3>
                        <p>Você será redirecionado para o dashboard em instantes...</p>
                        <div class="spinner-border text-primary mt-3" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-card">
                        <form method="POST" id="agendamentoForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="novo_agendamento" value="1">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly>
                                    <small class="text-muted">Seu nome cadastrado</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo $user['email']; ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Telefone para Contato *</label>
                                <input type="tel" name="telefone" class="form-control" 
                                       placeholder="+258 87 334 9977" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo de Serviço *</label>
                                <select class="form-select" name="servico" required>
                                    <option value="">Selecione...</option>
                                    <option value="limpeza">Limpeza Completa - 3.500 MZN</option>
                                    <option value="analise">Análise de Água - 1.500 MZN</option>
                                    <option value="manutencao">Manutenção de Equipamentos - Sob orçamento</option>
                                    <option value="invernagem">Invernagem - 1.500 MZN</option>
                                    <option value="reabertura">Reabertura de Temporada - 2.000 MZN</option>
                                    <option value="regularidade">Serviço de Regularidade - Sob consulta</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data Preferencial *</label>
                                    <input type="date" name="data" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Horário Preferencial *</label>
                                    <select class="form-select" name="horario" required>
                                        <option value="">Selecione...</option>
                                        <option value="08:00-10:00">08:00 - 10:00</option>
                                        <option value="10:00-12:00">10:00 - 12:00</option>
                                        <option value="13:00-15:00">13:00 - 15:00</option>
                                        <option value="15:00-17:00">15:00 - 17:00</option>
                                        <option value="17:00-19:00">17:00 - 19:00</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Endereço Completo *</label>
                                <textarea name="endereco" class="form-control" rows="2" 
                                          placeholder="Rua, número, bairro, cidade" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observações / Informações Adicionais</label>
                                <textarea name="observacoes" class="form-control" rows="3" 
                                          placeholder="Descreva detalhes específicos..."></textarea>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-4">
                                <h5>Resumo do Pedido</h5>
                                <div id="resumo" class="bg-light p-3 rounded">
                                    <p class="text-muted mb-0">Selecione um serviço para ver o resumo</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-check me-2"></i>Confirmar Agendamento
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('[name="servico"]').addEventListener('change', function() {
            const servico = this.value;
            const data = document.querySelector('[name="data"]').value;
            const horario = document.querySelector('[name="horario"]').value;
            const resumo = document.getElementById('resumo');
            
            let preco = '';
            switch(servico) {
                case 'limpeza': preco = '3.500 MZN'; break;
                case 'analise': preco = '1.500 MZN'; break;
                case 'invernagem': preco = '1.500 MZN'; break;
                case 'reabertura': preco = '2.000 MZN'; break;
                default: preco = 'A consultar';
            }
            
            resumo.innerHTML = `
                <p><strong>Serviço:</strong> ${this.options[this.selectedIndex].text}</p>
                <p><strong>Preço:</strong> ${preco}</p>
                <p><strong>Data:</strong> ${data ? new Date(data).toLocaleDateString('pt-BR') : 'Não selecionada'}</p>
                <p><strong>Horário:</strong> ${horario || 'Não selecionado'}</p>
            `;
        });
    </script>
</body>
</html>