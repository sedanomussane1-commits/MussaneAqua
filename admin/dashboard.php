<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/Validator.php';

$auth = new Auth();

if (!$auth->isAuthenticated()) {
    header('Location: ../public/login.php');
    exit;
}

$userManager = new UserManager();
$audit = new AuditLog();
$current_user = $userManager->getUserById($_SESSION['user_id']);

$csrf_token = Validator::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MussaneAqua</title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 4px solid;
            margin-bottom: 20px;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.warning { border-left-color: #ffc107; }
        
        .stat-icon {
            font-size: 2.5rem;
            color: #667eea;
            opacity: 0.3;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .agendamento-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .agendamento-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-pendente { border-left-color: #ffc107; }
        .status-confirmado { border-left-color: #28a745; }
        .status-cancelado { border-left-color: #dc3545; }
        .status-concluido { border-left-color: #17a2b8; }
        .status-atrasado { border-left-color: #dc3545; }
        
        .loading-spinner {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(0,0,0,.1);
            border-left-color: #667eea;
            border-radius: 50%;
            animation: spinner 0.6s linear infinite;
        }
        
        @keyframes spinner {
            to {transform: rotate(360deg);}
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
                        <p class="small">Sistema de Gestão</p>
                    </div>
                    
                    <div class="user-info text-center mb-4 p-3" style="background: rgba(255,255,255,0.1); border-radius: 10px;">
                        <div class="user-avatar mx-auto mb-2">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                        <h6 class="mb-0"><?php echo $current_user['username']; ?></h6>
                        <small><?php echo $current_user['role']; ?></small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                        <a class="nav-link" href="novo_agendamento.php">
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
                <div class="welcome-banner d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Bem-vindo, <?php echo $current_user['username']; ?>! 👋</h2>
                        <p class="mb-0">Hoje é <?php echo date('d/m/Y'); ?> - Gerencie seus serviços de piscina</p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark p-3">
                            <i class="fas fa-clock me-2"></i><?php echo date('H:i'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row" id="statistics-container">
                    <div class="col-12 text-center py-5">
                        <div class="loading-spinner"></div>
                        <p class="mt-3">Carregando estatísticas...</p>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Próximos Agendamentos</h5>
                                <a href="novo_agendamento.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Novo
                                </a>
                            </div>
                            <div class="card-body" id="proximos-agendamentos">
                                <div class="text-center py-4">
                                    <div class="loading-spinner"></div>
                                    <p class="mt-3">Carregando agendamentos...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>Meus Dados</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Usuário:</strong> <?php echo $current_user['username']; ?></p>
                                <p><strong>Email:</strong> <?php echo $current_user['email']; ?></p>
                                <p><strong>Papel:</strong> <span class="badge bg-primary"><?php echo $current_user['role']; ?></span></p>
                                <p><strong>Membro desde:</strong> <?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?></p>
                                <hr>
                                <a href="meus_dados.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-edit me-2"></i>Editar Perfil
                                </a>
                            </div>
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

            carregarEstatisticas();
            carregarProximosAgendamentos();
        });

        function carregarEstatisticas() {
            $.ajax({
                url: '../api/api.php',
                method: 'GET',
                data: {
                    action: 'buscar_estatisticas'
                },
                success: function(response) {
                    let html = '';
                    
                    html += '<div class="col-md-3">';
                    html += '<div class="stat-card primary">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div><div class="stat-number">' + (response.total_agendamentos || 0) + '</div>';
                    html += '<div class="stat-label">Total Agendamentos</div></div>';
                    html += '<div class="stat-icon"><i class="fas fa-calendar-check"></i></div>';
                    html += '</div></div></div>';
                    
                    html += '<div class="col-md-3">';
                    html += '<div class="stat-card success">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div><div class="stat-number">' + (response.agendamentos_hoje || 0) + '</div>';
                    html += '<div class="stat-label">Agendamentos Hoje</div></div>';
                    html += '<div class="stat-icon"><i class="fas fa-calendar-day"></i></div>';
                    html += '</div></div></div>';
                    
                    let pendentes = 0;
                    if (response.agendamentos_status && response.agendamentos_status.length > 0) {
                        response.agendamentos_status.forEach(function(item) {
                            if (item.status === 'pendente') pendentes = item.total;
                        });
                    }
                    
                    html += '<div class="col-md-3">';
                    html += '<div class="stat-card info">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div><div class="stat-number">' + pendentes + '</div>';
                    html += '<div class="stat-label">Pendentes</div></div>';
                    html += '<div class="stat-icon"><i class="fas fa-clock"></i></div>';
                    html += '</div></div></div>';
                    
                    let concluidos = 0;
                    if (response.agendamentos_status && response.agendamentos_status.length > 0) {
                        response.agendamentos_status.forEach(function(item) {
                            if (item.status === 'concluido') concluidos = item.total;
                        });
                    }
                    
                    html += '<div class="col-md-3">';
                    html += '<div class="stat-card warning">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div><div class="stat-number">' + concluidos + '</div>';
                    html += '<div class="stat-label">Concluídos</div></div>';
                    html += '<div class="stat-icon"><i class="fas fa-check-circle"></i></div>';
                    html += '</div></div></div>';
                    
                    $('#statistics-container').html(html);
                },
                error: function() {
                    $('#statistics-container').html('<div class="col-12"><div class="alert alert-danger">Erro ao carregar estatísticas</div></div>');
                }
            });
        }

        function carregarProximosAgendamentos() {
            $.ajax({
                url: '../api/api.php',
                method: 'GET',
                data: {
                    action: 'buscar_agendamentos'
                },
                success: function(response) {
                    if (response.success && response.agendamentos && response.agendamentos.length > 0) {
                        let html = '';
                        const hoje = new Date().toISOString().split('T')[0];
                        
                        const proximos = response.agendamentos.filter(a => 
                            a.status === 'pendente' || a.status === 'confirmado'
                        ).slice(0, 5);
                        
                        if (proximos.length > 0) {
                            proximos.forEach(function(a) {
                                const dataAgendamento = new Date(a.data_agendamento).toLocaleDateString('pt-BR');
                                const statusClass = a.data_agendamento < hoje && a.status === 'pendente' ? 'atrasado' : a.status;
                                
                                let badgeClass = 'secondary';
                                if (statusClass === 'pendente') badgeClass = 'warning';
                                else if (statusClass === 'confirmado') badgeClass = 'success';
                                else if (statusClass === 'atrasado') badgeClass = 'danger';
                                
                                html += '<div class="agendamento-card status-' + statusClass + '">';
                                html += '<div class="d-flex justify-content-between align-items-center">';
                                html += '<div><strong>' + dataAgendamento + ' - ' + a.horario + '</strong><br>';
                                html += '<small>' + (a.servico || 'Serviço não especificado') + '</small></div>';
                                html += '<span class="badge bg-' + badgeClass + '">';
                                html += statusClass.charAt(0).toUpperCase() + statusClass.slice(1);
                                html += '</span></div></div>';
                            });
                        } else {
                            html = '<p class="text-center text-muted py-4">Nenhum agendamento próximo</p>';
                        }
                        
                        $('#proximos-agendamentos').html(html);
                    } else {
                        $('#proximos-agendamentos').html('<p class="text-center text-muted py-4">Nenhum agendamento encontrado</p>');
                    }
                },
                error: function() {
                    $('#proximos-agendamentos').html('<div class="alert alert-danger m-3">Erro ao carregar agendamentos</div>');
                }
            });
        }
    </script>
</body>
</html>