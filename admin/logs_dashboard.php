<?php
// admin/logs_dashboard.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$middleware = new AuthMiddleware();
$middleware->requireRole('admin');

$audit = new AuditLog();
$filters = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d')
];

$logs = $audit->getLogs($filters, 100, 0);

// Estatísticas
$actions_count = [];
$users_count = [];
$dates_count = [];

foreach ($logs as $log) {
    $actions_count[$log['action']] = ($actions_count[$log['action']] ?? 0) + 1;
    $users_count[$log['username'] ?? 'Sistema'] = ($users_count[$log['username'] ?? 'Sistema'] ?? 0) + 1;
    $date = date('Y-m-d', strtotime($log['created_at']));
    $dates_count[$date] = ($dates_count[$date] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard de Logs - MussaneAqua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2 class="my-4">Dashboard de Auditoria</h2>
                
                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-4">
                                <label>Data Início</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label>Data Fim</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                Ações por Tipo
                            </div>
                            <div class="card-body">
                                <canvas id="actionsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                Logs por Usuário
                            </div>
                            <div class="card-body">
                                <canvas id="usersChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                Logs por Data
                            </div>
                            <div class="card-body">
                                <canvas id="datesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de Logs -->
                <div class="card">
                    <div class="card-header">
                        Últimos Logs
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Usuário</th>
                                        <th>Ação</th>
                                        <th>Tabela</th>
                                        <th>ID</th>
                                        <th>IP</th>
                                        <th>Descrição</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo $log['username'] ?? 'Sistema'; ?></td>
                                        <td><span class="badge bg-<?php 
                                            echo strpos($log['action'], 'LOGIN_SUCCESS') !== false ? 'success' : 
                                                (strpos($log['action'], 'LOGIN_FAILED') !== false ? 'danger' : 
                                                (strpos($log['action'], 'CREATE') !== false ? 'info' : 
                                                (strpos($log['action'], 'UPDATE') !== false ? 'warning' : 'secondary'))); 
                                        ?>"><?php echo $log['action']; ?></span></td>
                                        <td><?php echo $log['table_name'] ?? '-'; ?></td>
                                        <td><?php echo $log['record_id'] ?? '-'; ?></td>
                                        <td><?php echo $log['ip_address'] ?? '-'; ?></td>
                                        <td><?php echo $log['description'] ?? '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Gráfico de Ações
        new Chart(document.getElementById('actionsChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($actions_count)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($actions_count)); ?>,
                    backgroundColor: [
                        '#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40'
                    ]
                }]
            }
        });
        
        // Gráfico de Usuários
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($users_count)); ?>,
                datasets: [{
                    label: 'Quantidade de Logs',
                    data: <?php echo json_encode(array_values($users_count)); ?>,
                    backgroundColor: '#36a2eb'
                }]
            }
        });
        
        // Gráfico de Datas
        new Chart(document.getElementById('datesChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($dates_count)); ?>,
                datasets: [{
                    label: 'Logs por Data',
                    data: <?php echo json_encode(array_values($dates_count)); ?>,
                    borderColor: '#ff6384',
                    fill: false
                }]
            }
        });
    </script>
</body>
</html>