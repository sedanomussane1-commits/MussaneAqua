<?php
// admin/visualizar_logs.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/AuditLog.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../config/Database.php';

$middleware = new AuthMiddleware();
$middleware->requireRole('admin'); // Apenas admin pode ver logs

$audit = new AuditLog();
$csrf_token = Validator::generateCSRFToken();

// Configurar filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

$filters = [];

if (!empty($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}

if (!empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}

if (!empty($_GET['table_name'])) {
    $filters['table_name'] = $_GET['table_name'];
}

if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Buscar logs
$logs = $audit->getLogs($filters, $limit, $offset);

// Buscar total de logs para paginação
$all_logs = $audit->getLogs($filters, 10000, 0);
$total_logs = count($all_logs);
$total_pages = ceil($total_logs / $limit);

// Estatísticas
$stats = [
    'total' => $total_logs,
    'logins_success' => 0,
    'logins_failed' => 0,
    'creates' => 0,
    'updates' => 0,
    'deletes' => 0,
    'invalid_access' => 0,
    'logout' => 0
];

foreach ($all_logs as $log) {
    if (strpos($log['action'], 'LOGIN_SUCCESS') !== false) $stats['logins_success']++;
    elseif (strpos($log['action'], 'LOGIN_FAILED') !== false) $stats['logins_failed']++;
    elseif (strpos($log['action'], 'LOGOUT') !== false) $stats['logout']++;
    elseif (strpos($log['action'], 'CREATE') !== false) $stats['creates']++;
    elseif (strpos($log['action'], 'UPDATE') !== false) $stats['updates']++;
    elseif (strpos($log['action'], 'DELETE') !== false) $stats['deletes']++;
    elseif (strpos($log['action'], 'INVALID_ACCESS') !== false) $stats['invalid_access']++;
}

// Buscar lista de usuários para o filtro
$conn = (new Database())->getConnection();
$users = $conn->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Logs - MussaneAqua</title>
    
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
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stats-card.primary { border-left-color: #667eea; }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.danger { border-left-color: #dc3545; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.info { border-left-color: #17a2b8; }
        
        .stats-icon {
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .badge-login-success {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-login-failed {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-create {
            background: #cce5ff;
            color: #004085;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-update {
            background: #d1ecf1;
            color: #0c5460;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-delete {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-invalid {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-logout {
            background: #e2e3e5;
            color: #383d41;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        
        .log-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            border-left: 3px solid #667eea;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            display: none;
        }
        
        .log-details.show {
            display: block;
        }
        
        .btn-view-details {
            padding: 3px 8px;
            font-size: 0.8rem;
        }
        
        .user-avatar-mini {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .pagination {
            margin-top: 20px;
        }
        
        .old-values {
            background: #fff3cd;
            padding: 5px;
            border-radius: 5px;
        }
        
        .new-values {
            background: #d4edda;
            padding: 5px;
            border-radius: 5px;
            margin-top: 5px;
        }
        
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
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
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                        <a class="nav-link" href="novo_agendamento.php">
                            <i class="fas fa-calendar-plus"></i>Novo Agendamento
                        </a>
                        <a class="nav-link" href="meus_dados.php">
                            <i class="fas fa-user"></i>Meus Dados
                        </a>
                        <a class="nav-link" href="gestao_usuarios.php">
                            <i class="fas fa-users-cog"></i>Gestão de Usuários
                        </a>
                        <a class="nav-link active" href="visualizar_logs.php">
                            <i class="fas fa-history"></i>Logs de Auditoria
                        </a>
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
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-history me-2 text-primary"></i>Logs de Auditoria</h2>
                        <p class="text-muted mb-0">Visualize todas as atividades do sistema: logins, criações, edições, exclusões e acessos inválidos</p>
                    </div>
                    <div>
                        <button class="btn btn-success me-2" onclick="exportarLogs()">
                            <i class="fas fa-download me-2"></i>Exportar CSV
                        </button>
                        <button class="btn btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Atualizar
                        </button>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card primary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                                    <div class="stats-label">Total de Logs</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card success">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['logins_success']; ?></div>
                                    <div class="stats-label">Logins com Sucesso</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card danger">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['logins_failed']; ?></div>
                                    <div class="stats-label">Logins Falhos</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['invalid_access']; ?></div>
                                    <div class="stats-label">Acessos Inválidos</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Second row of stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['creates']; ?></div>
                                    <div class="stats-label">Criações</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['updates']; ?></div>
                                    <div class="stats-label">Edições</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card danger">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['deletes']; ?></div>
                                    <div class="stats-label">Exclusões</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-trash"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-card secondary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number"><?php echo $stats['logout']; ?></div>
                                    <div class="stats-label">Logouts</div>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filter-card">
                    <h5 class="mb-3"><i class="fas fa-filter me-2 text-primary"></i>Filtros</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Usuário</label>
                            <select class="form-select" name="user_id">
                                <option value="">Todos</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($_GET['user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo $user['username']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Ação</label>
                            <select class="form-select" name="action">
                                <option value="">Todas</option>
                                <option value="LOGIN_SUCCESS" <?php echo ($_GET['action'] ?? '') == 'LOGIN_SUCCESS' ? 'selected' : ''; ?>>✅ Login com Sucesso</option>
                                <option value="LOGIN_FAILED" <?php echo ($_GET['action'] ?? '') == 'LOGIN_FAILED' ? 'selected' : ''; ?>>❌ Login Falho</option>
                                <option value="LOGOUT" <?php echo ($_GET['action'] ?? '') == 'LOGOUT' ? 'selected' : ''; ?>>🚪 Logout</option>
                                <option value="CREATE" <?php echo ($_GET['action'] ?? '') == 'CREATE' ? 'selected' : ''; ?>>➕ Criação</option>
                                <option value="UPDATE" <?php echo ($_GET['action'] ?? '') == 'UPDATE' ? 'selected' : ''; ?>>✏️ Edição</option>
                                <option value="DELETE" <?php echo ($_GET['action'] ?? '') == 'DELETE' ? 'selected' : ''; ?>>🗑️ Exclusão</option>
                                <option value="INVALID_ACCESS" <?php echo ($_GET['action'] ?? '') == 'INVALID_ACCESS' ? 'selected' : ''; ?>>⚠️ Acesso Inválido</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Tabela</label>
                            <select class="form-select" name="table_name">
                                <option value="">Todas</option>
                                <option value="users" <?php echo ($_GET['table_name'] ?? '') == 'users' ? 'selected' : ''; ?>>Usuários</option>
                                <option value="agendamentos" <?php echo ($_GET['table_name'] ?? '') == 'agendamentos' ? 'selected' : ''; ?>>Agendamentos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">Por página</label>
                            <select class="form-select" name="limit">
                                <option value="20" <?php echo ($_GET['limit'] ?? 50) == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($_GET['limit'] ?? 50) == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($_GET['limit'] ?? 50) == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo ($_GET['limit'] ?? 50) == 200 ? 'selected' : ''; ?>>200</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Aplicar Filtros
                            </button>
                            <a href="visualizar_logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Tabela de Logs -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>Tabela</th>
                                    <th>Registro ID</th>
                                    <th>IP</th>
                                    <th>Descrição</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-history fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">Nenhum log encontrado</h5>
                                        <p class="text-muted">Tente ajustar os filtros ou realizar algumas ações no sistema</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar-mini me-2">
                                                        <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold"><?php echo $log['username']; ?></span>
                                                        <br>
                                                        <small class="text-muted"><?php echo $log['email']; ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Sistema</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            $badge_text = $log['action'];
                                            
                                            if (strpos($log['action'], 'LOGIN_SUCCESS') !== false) {
                                                $badge_class = 'login-success';
                                                $badge_text = '✅ Login com Sucesso';
                                            } elseif (strpos($log['action'], 'LOGIN_FAILED') !== false) {
                                                $badge_class = 'login-failed';
                                                $badge_text = '❌ Login Falho';
                                            } elseif (strpos($log['action'], 'LOGOUT') !== false) {
                                                $badge_class = 'logout';
                                                $badge_text = '🚪 Logout';
                                            } elseif (strpos($log['action'], 'CREATE') !== false) {
                                                $badge_class = 'create';
                                                $badge_text = '➕ Criação';
                                            } elseif (strpos($log['action'], 'UPDATE') !== false) {
                                                $badge_class = 'update';
                                                $badge_text = '✏️ Edição';
                                            } elseif (strpos($log['action'], 'DELETE') !== false) {
                                                $badge_class = 'delete';
                                                $badge_text = '🗑️ Exclusão';
                                            } elseif (strpos($log['action'], 'INVALID_ACCESS') !== false) {
                                                $badge_class = 'invalid';
                                                $badge_text = '⚠️ Acesso Inválido';
                                            }
                                            ?>
                                            <span class="badge-<?php echo $badge_class; ?>">
                                                <?php echo $badge_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['table_name']): ?>
                                                <span class="badge bg-secondary"><?php echo $log['table_name']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['record_id']): ?>
                                                <span class="badge bg-dark">#<?php echo $log['record_id']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo $log['ip_address'] ?? '-'; ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo $log['description'] ?? '-'; ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-view-details" 
                                                    onclick="verDetalhes(this, <?php echo htmlspecialchars(json_encode([
                                                        'old_values' => $log['old_values'],
                                                        'new_values' => $log['new_values'],
                                                        'user_agent' => $log['user_agent']
                                                    ])); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Anterior</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para exibir detalhes completos -->
    <div class="modal fade" id="detalhesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalhes do Log</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalhesModalBody">
                    Carregando...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function verDetalhes(button, dados) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            const modalBody = document.getElementById('detalhesModalBody');
            
            let html = '';
            
            if (dados.old_values && dados.old_values !== 'null') {
                try {
                    const oldValues = typeof dados.old_values === 'string' ? JSON.parse(dados.old_values) : dados.old_values;
                    html += '<h6 class="text-danger mb-2">Valores Antigos:</h6>';
                    html += '<pre class="old-values">' + JSON.stringify(oldValues, null, 2) + '</pre>';
                } catch (e) {
                    html += '<h6 class="text-danger mb-2">Valores Antigos:</h6>';
                    html += '<pre class="old-values">' + dados.old_values + '</pre>';
                }
            }
            
            if (dados.new_values && dados.new_values !== 'null') {
                try {
                    const newValues = typeof dados.new_values === 'string' ? JSON.parse(dados.new_values) : dados.new_values;
                    html += '<h6 class="text-success mb-2 mt-3">Valores Novos:</h6>';
                    html += '<pre class="new-values">' + JSON.stringify(newValues, null, 2) + '</pre>';
                } catch (e) {
                    html += '<h6 class="text-success mb-2 mt-3">Valores Novos:</h6>';
                    html += '<pre class="new-values">' + dados.new_values + '</pre>';
                }
            }
            
            if (dados.user_agent) {
                html += '<h6 class="mb-2 mt-3">User Agent:</h6>';
                html += '<p class="text-muted small">' + dados.user_agent + '</p>';
            }
            
            if (!dados.old_values && !dados.new_values && !dados.user_agent) {
                html = '<p class="text-muted text-center">Nenhum detalhe adicional disponível</p>';
            }
            
            modalBody.innerHTML = html;
            modal.show();
        }
        
        function exportarLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'exportar_logs.php?' + params.toString();
        }
    </script>
</body>
</html>