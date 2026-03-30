<?php
// admin/gestao_usuarios.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/AuditLog.php';

// Verificar se as classes existem
if (!class_exists('AuthMiddleware')) {
    die('Erro: Classe AuthMiddleware não encontrada');
}

$middleware = new AuthMiddleware();
$middleware->requireRole('admin'); // Apenas admin pode acessar

$userManager = new UserManager();
$audit = new AuditLog();

$error = '';
$success = '';
$csrf_token = Validator::generateCSRFToken();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Validator::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Erro de validação do formulário';
    } else {
        // Criar usuário
        if (isset($_POST['criar_usuario'])) {
            $dados = [
                'username' => Validator::sanitizeString($_POST['username']),
                'email' => Validator::sanitizeEmail($_POST['email']),
                'password' => $_POST['password'],
                'role' => $_POST['role'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $result = $userManager->createUser($dados, $_SESSION['user_id']);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
        
        // Editar usuário
        if (isset($_POST['editar_usuario'])) {
            $user_id = (int)$_POST['user_id'];
            $dados = [];
            
            if (!empty($_POST['username'])) {
                $dados['username'] = Validator::sanitizeString($_POST['username']);
            }
            if (!empty($_POST['email'])) {
                $dados['email'] = Validator::sanitizeEmail($_POST['email']);
            }
            if (!empty($_POST['password'])) {
                $dados['password'] = $_POST['password'];
            }
            if (!empty($_POST['role'])) {
                $dados['role'] = $_POST['role'];
            }
            $dados['is_active'] = isset($_POST['is_active']) ? 1 : 0;
            
            $result = $userManager->updateUser($user_id, $dados, $_SESSION['user_id']);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
        
        // Excluir/Desativar usuário
        if (isset($_POST['excluir_usuario'])) {
            $user_id = (int)$_POST['user_id'];
            $result = $userManager->deleteUser($user_id, $_SESSION['user_id']);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$filters = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = Validator::sanitizeString($_GET['search']);
}
if (isset($_GET['role']) && !empty($_GET['role'])) {
    $filters['role'] = $_GET['role'];
}

$result = $userManager->getUsers($filters, $page, 10);
$users = $result['users'];
$total_pages = $result['pages'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - MussaneAqua</title>
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .user-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .user-table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .user-avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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
                        <a class="nav-link" href="novo_agendamento.php">
                            <i class="fas fa-calendar-plus"></i>Novo Agendamento
                        </a>
                        <a class="nav-link" href="meus_dados.php">
                            <i class="fas fa-user"></i>Meus Dados
                        </a>
                        <a class="nav-link active" href="gestao_usuarios.php">
                            <i class="fas fa-users-cog"></i>Gestão de Usuários
                        </a>
                        <a class="nav-link" href="visualizar_logs.php">
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
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Gestão de Usuários</h2>
                        <p class="text-muted mb-0">Total de usuários: <?php echo $result['total']; ?></p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                        <i class="fas fa-plus me-2"></i>Novo Usuário
                    </button>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Buscar por nome ou email" 
                                   value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="role">
                                <option value="">Todos os papéis</option>
                                <option value="admin" <?php echo ($_GET['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="funcionario" <?php echo ($_GET['role'] ?? '') === 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
                                <option value="cliente" <?php echo ($_GET['role'] ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filtrar
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="gestao_usuarios.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-2"></i>Limpar
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="user-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Email</th>
                                <th>Papel</th>
                                <th>Status</th>
                                <th>Último Login</th>
                                <th>Tentativas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar-mini me-2">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <?php echo $user['username']; ?>
                                    </div>
                                </td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <?php
                                    $badge_color = match($user['role']) {
                                        'admin' => 'bg-danger',
                                        'funcionario' => 'bg-warning',
                                        'cliente' => 'bg-info',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_color; ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $user['last_login'] 
                                            ? date('d/m/Y H:i', strtotime($user['last_login'])) 
                                            : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user['login_attempts'] > 0): ?>
                                        <span class="badge bg-warning"><?php echo $user['login_attempts']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            data-bs-toggle="modal" data-bs-target="#modalEditarUsuario">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmarExclusao(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')"
                                            data-bs-toggle="modal" data-bs-target="#modalExcluirUsuario">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Nenhum usuário encontrado</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&role=<?php echo $_GET['role'] ?? ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Novo Usuário -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Novo Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="criar_usuario" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome de Usuário *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Senha *</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Mínimo 8 caracteres, com maiúscula, minúscula e número</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Papel *</label>
                            <select class="form-select" name="role" required>
                                <option value="cliente">Cliente</option>
                                <option value="funcionario">Funcionário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" checked>
                                <label class="form-check-label">Usuário ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="editar_usuario" value="1">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome de Usuário</label>
                            <input type="text" class="form-control" name="username" id="edit_username">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nova Senha (deixe em branco para manter)</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Papel</label>
                            <select class="form-select" name="role" id="edit_role">
                                <option value="cliente">Cliente</option>
                                <option value="funcionario">Funcionário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                <label class="form-check-label">Usuário ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Excluir Usuário -->
    <div class="modal fade" id="modalExcluirUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="excluir_usuario" value="1">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        
                        <p>Tem certeza que deseja desativar o usuário <strong id="delete_username"></strong>?</p>
                        <p class="text-danger"><small>Esta ação pode ser revertida posteriormente.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarUsuario(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
        }
        
        function confirmarExclusao(id, username) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_username').textContent = username;
        }
    </script>
</body>
</html>