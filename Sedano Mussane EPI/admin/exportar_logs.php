<?php
// admin/exportar_logs.php
session_start();
require_once __DIR__ . '/../includes/AuthMiddleware.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$middleware = new AuthMiddleware();
$middleware->requireRole('admin');

$audit = new AuditLog();

// Coletar filtros
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

// Buscar todos os logs (sem limite)
$logs = $audit->getLogs($filters, 10000, 0);

// Configurar cabeçalhos para download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.csv"');

// Criar arquivo CSV
$output = fopen('php://output', 'w');

// UTF-8 BOM para Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos
fputcsv($output, [
    'Data/Hora',
    'Usuário ID',
    'Usuário',
    'Email',
    'Ação',
    'Tabela',
    'Registro ID',
    'IP',
    'User Agent',
    'Descrição',
    'Valores Antigos',
    'Valores Novos'
]);

// Dados
foreach ($logs as $log) {
    fputcsv($output, [
        $log['created_at'],
        $log['user_id'] ?? '',
        $log['username'] ?? 'Sistema',
        $log['email'] ?? '',
        $log['action'],
        $log['table_name'] ?? '',
        $log['record_id'] ?? '',
        $log['ip_address'] ?? '',
        $log['user_agent'] ?? '',
        $log['description'] ?? '',
        $log['old_values'],
        $log['new_values']
    ]);
}

fclose($output);
exit;
?>