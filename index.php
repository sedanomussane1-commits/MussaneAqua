<?php
// index.php
// NÃO usar session_start() aqui, a classe Auth vai gerenciar

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/Validator.php';
require_once __DIR__ . '/includes/AuditLog.php';
require_once __DIR__ . '/includes/UserManager.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/AuthMiddleware.php';
require_once __DIR__ . '/includes/TokenManager.php';

$site_title = "MussaneAqua - Limpeza de Piscinas";
$contact_email = "Sedanomussane1@gmail.com";
$contact_phone = "+258 873349977";
$contact_address = "Santa Isabel, 123, 1000-001 Maputo";
$whatsapp_number = "258845149977";

$auth = new Auth();
$tokenManager = TokenManager::getInstance();
$current_user = null;

if ($auth->isAuthenticated()) {
    $userManager = new UserManager();
    $current_user = $userManager->getUserById($_SESSION['user_id']);
}



if (!isset($_SESSION['agendamentos'])) {
    $_SESSION['agendamentos'] = [];
}

$form_message = "";
$form_error = "";
$ultimo_agendamento = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agendar_servico'])) {
    if (!Validator::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $form_error = "Erro de validação do formulário.";
    } else {
        $name = Validator::sanitizeString($_POST['name'] ?? '');
        $email = Validator::sanitizeEmail($_POST['email'] ?? '');
        $phone = Validator::sanitizeString($_POST['phone'] ?? '');
        $address = Validator::sanitizeString($_POST['address'] ?? '');
        $service = $_POST['service'] ?? '';
        $service_date = $_POST['service_date'] ?? '';
        $service_time = $_POST['service_time'] ?? '';
        $message = Validator::sanitizeString($_POST['message'] ?? '');
        
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($service) || empty($service_date) || empty($service_time)) {
            $form_error = "Por favor, preencha todos os campos obrigatórios.";
        } elseif (!Validator::validateEmail($email)) {
            $form_error = "Por favor, insira um email válido.";
        } elseif (strtotime($service_date) < strtotime(date('Y-m-d'))) {
            $form_error = "A data do agendamento não pode ser no passado.";
        } else {
            $servicos = [
                'limpeza_completa' => 'Limpeza Completa - 3500 MZN',
                'analise_agua' => 'Análise de Água - 1500 MZN',
                'manutencao_equipamentos' => 'Manutenção de Equipamentos',
                'invernagem' => 'Invernagem - 1500 MZN',
                'reabertura_temporada' => 'Reabertura de Temporada - 2000 MZN',
                'servico_regularidade' => 'Serviço de Regularidade'
            ];
            
            $nome_servico = $servicos[$service] ?? $service;
            $protocolo = 'AG' . date('Ymd') . rand(1000, 9999);
            
            $novo_agendamento = [
                'id' => count($_SESSION['agendamentos']) + 1,
                'protocolo' => $protocolo,
                'nome' => $name,
                'email' => $email,
                'telefone' => $phone,
                'endereco' => $address,
                'servico' => $service,
                'nome_servico' => $nome_servico,
                'data' => $service_date,
                'hora' => $service_time,
                'observacoes' => $message,
                'data_solicitacao' => date('d/m/Y H:i:s'),
                'status' => 'pendente'
            ];
            
            $_SESSION['agendamentos'][] = $novo_agendamento;
            
            $ultimo_agendamento = $novo_agendamento;
            $_SESSION['ultimo_agendamento'] = $novo_agendamento;
            
            $mensagem_whatsapp = "*NOVO AGENDAMENTO - MussaneAqua* \n\n";
            $mensagem_whatsapp .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $mensagem_whatsapp .= "*PROTOCOLO:* `" . $protocolo . "`\n";
            $mensagem_whatsapp .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $mensagem_whatsapp .= " *DADOS DO CLIENTE*\n";
            $mensagem_whatsapp .= "▸ *Nome:* " . $name . "\n";
            $mensagem_whatsapp .= "▸ *Email:* " . $email . "\n";
            $mensagem_whatsapp .= "▸ *Telefone:* " . $phone . "\n\n";
            $mensagem_whatsapp .= "*ENDEREÇO COMPLETO*\n";
            $mensagem_whatsapp .= "▸ " . $address . "\n\n";
            $mensagem_whatsapp .= " *SERVIÇO SOLICITADO*\n";
            $mensagem_whatsapp .= "▸ " . $nome_servico . "\n\n";
            $mensagem_whatsapp .= " *DATA E HORA*\n";
            $mensagem_whatsapp .= "▸ *Data:* " . date('d/m/Y', strtotime($service_date)) . "\n";
            $mensagem_whatsapp .= "▸ *Hora:* " . $service_time . "\n\n";
            
            if (!empty($message)) {
                $mensagem_whatsapp .= " *OBSERVAÇÕES*\n";
                $mensagem_whatsapp .= "▸ " . $message . "\n\n";
            }
            
            $mensagem_whatsapp .= " *SOLICITADO EM:* " . date('d/m/Y H:i:s') . "\n\n";
            $mensagem_whatsapp .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $mensagem_whatsapp .= "https://maps.google.com/?q=" . urlencode($address) . "\n\n";
            $mensagem_whatsapp .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            $mensagem_codificada = urlencode($mensagem_whatsapp);
            $whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$mensagem_codificada}";
            
            header("Location: $whatsapp_link");
            exit;
        }
    }
}

if (isset($_GET['cancelar']) && is_numeric($_GET['cancelar'])) {
    $agendamento_id = (int)$_GET['cancelar'];
    
    foreach ($_SESSION['agendamentos'] as $key => $agendamento) {
        if ($agendamento['id'] == $agendamento_id && $agendamento['status'] == 'pendente') {
            $_SESSION['agendamentos'][$key]['status'] = 'cancelado';
            $form_message = "Agendamento #{$agendamento_id} cancelado com sucesso!";
            break;
        }
    }
}

if (isset($_SESSION['ultimo_agendamento'])) {
    $ultimo_agendamento = $_SESSION['ultimo_agendamento'];
}

$csrf_token = Validator::generateCSRFToken();
$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- jQuery Mask Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #198754;
            --accent-color: #20c997;
            --whatsapp-color: #25D366;
            --light-blue: #e7f1ff;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .hero-section {
            background: linear-gradient(rgba(13, 110, 253, 0.85), rgba(25, 135, 84, 0.8)), url('https://images.unsplash.com/photo-1575429198097-0414ec08e8cd?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 3rem;
            padding-bottom: 1rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .section-title.text-center::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .btn-primary, .btn-success {
            position: relative;
            overflow: hidden;
            transition: all 0.4s;
            z-index: 1;
        }
        
        .btn-primary:hover, .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .btn-whatsapp {
            background-color: var(--whatsapp-color);
            border-color: var(--whatsapp-color);
            color: white;
            transition: all 0.3s;
        }
        
        .btn-whatsapp:hover {
            background-color: #128C7E;
            border-color: #128C7E;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(37, 211, 102, 0.4);
        }
        
        .btn-pulse {
            animation: pulse-animation 2s infinite;
        }
        
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(25, 135, 84, 0); }
            100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
        }
        
        .agendamento-confirmado {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            animation: slideInDown 0.5s ease;
        }
        
        .tabela-agendamentos {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .tabela-agendamentos table {
            margin-bottom: 0;
        }
        
        .tabela-agendamentos th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border: none;
            padding: 15px;
        }
        
        .tabela-agendamentos td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tabela-agendamentos tr:last-child td {
            border-bottom: none;
        }
        
        .tabela-agendamentos tr:hover td {
            background-color: #f8f9fa;
        }
        
        .protocolo-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
            font-size: 1.1rem;
            letter-spacing: 2px;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmado {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-agendamento {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        
        .info-agendamento i {
            color: #0d6efd;
            margin-right: 10px;
        }
        
        .service-card {
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s;
            height: 100%;
            border: none;
            background: linear-gradient(145deg, #ffffff, #f5f5f5);
            box-shadow: 5px 5px 15px rgba(0,0,0,0.1);
        }
        
        .service-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .service-icon {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            transition: transform 0.4s;
        }
        
        .service-card:hover .service-icon {
            transform: scale(1.2) rotate(10deg);
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .contact-card-modern {
            background: white;
            border-radius: 20px;
            padding: 35px 25px;
            text-align: center;
            position: relative;
            transition: all 0.5s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            cursor: pointer;
        }
        
        .contact-card-modern:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        
        .contact-card-modern:nth-child(1) {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
        }
        .contact-card-modern:nth-child(2) {
            background: linear-gradient(145deg, #43e97b, #38f9d7);
            color: white;
        }
        .contact-card-modern:nth-child(3) {
            background: linear-gradient(145deg, #fa709a, #fee140);
            color: white;
        }
        
        .contact-icon-modern {
            font-size: 3rem;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.2);
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            display: inline-block;
            transition: all 0.4s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .contact-card-modern:hover .contact-icon-modern {
            transform: scale(1.2) rotate(360deg);
            background: rgba(255,255,255,0.3);
        }
        
        .contact-title-modern {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .floating-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }
        
        .schedule-card {
            background: linear-gradient(145deg, #1a2634, #0f1824);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-top: 30px;
            transition: all 0.4s;
        }
        
        .schedule-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        
        .schedule-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .schedule-item:last-child {
            border-bottom: none;
        }
        
        .schedule-item i {
            font-size: 1.5rem;
            margin-right: 15px;
            color: var(--accent-color);
        }
        
        .user-welcome {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            margin-left: 15px;
        }
        
        .footer {
            background-color: #1c2a39;
            color: #ddd;
        }
        
        .footer-title {
            color: white;
            margin-bottom: 1.5rem;
        }
        
        .copyright {
            background-color: #0f1824;
            color: #aaa;
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #25D366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            animation: pulse-whatsapp 2s infinite;
        }
        
        .whatsapp-float:hover {
            background-color: #128C7E;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 15px rgba(0,0,0,0.4);
        }
        
        @keyframes pulse-whatsapp {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }
        
        .whatsapp-tooltip {
            position: absolute;
            right: 70px;
            background: #333;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            pointer-events: none;
        }
        
        .whatsapp-float:hover .whatsapp-tooltip {
            opacity: 1;
            visibility: visible;
            right: 80px;
        }
        
        .nav-tabs-custom {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 25px;
            margin-right: 5px;
            border-radius: 10px 10px 0 0;
        }
        
        .nav-tabs-custom .nav-link:hover {
            border: none;
            color: #0d6efd;
            background: #e7f1ff;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: #0d6efd;
            background: white;
            border-bottom: 3px solid #0d6efd;
        }
        
        .counter-badge {
            background: #0d6efd;
            color: white;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        
        .is-valid {
            border-color: #28a745 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
            padding-right: calc(1.5em + .75rem);
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
            padding-right: calc(1.5em + .75rem);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: .25rem;
            font-size: .875em;
            color: #dc3545;
        }
        
        .valid-feedback {
            display: block;
            width: 100%; 
            margin-top: .25rem;
            font-size: .875em;
            color: #28a745;
        }
        
        .requisito-senha {
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .requisito-senha.valid {
            color: #28a745;
        }
        
        .requisito-senha.invalid {
            color: #dc3545;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(0,0,0,.1);
            border-left-color: #667eea;
            border-radius: 50%;
            animation: spinner 0.6s linear infinite;
        }
        
        @keyframes spinner {
            to {transform: rotate(360deg);}
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
            padding: 15px 20px;
            border-radius: 10px;
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .toast-error { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .toast-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="?page=home">
                <i class="fas fa-swimming-pool text-primary me-2"></i>
                <span class="fw-bold">MussaneAqua</span>
                <?php if (count($_SESSION['agendamentos']) > 0): ?>
                <span class="badge bg-primary ms-2"><?php echo count($_SESSION['agendamentos']); ?> agendamentos</span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'home' ? 'active' : ''; ?>" href="?page=home">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'servicos' ? 'active' : ''; ?>" href="?page=servicos">Serviços</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'agendar' ? 'active' : ''; ?>" href="?page=agendar">Agendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'contacto' ? 'active' : ''; ?>" href="?page=contacto">Contacto</a>
                    </li>
                    
                    <?php if ($current_user): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo $current_user['username']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin/meus_dados.php"><i class="fas fa-user me-2"></i>Meus Dados</a></li>
                                <li><a class="dropdown-item" href="admin/novo_agendamento.php"><i class="fas fa-calendar-plus me-2"></i>Novo Agendamento</a></li>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/gestao_usuarios.php"><i class="fas fa-users-cog me-2"></i>Gestão de Usuários</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm ms-2" href="public/login.php">
                                <i class="fas fa-sign-in-alt me-2"></i>Entrar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-success btn-sm ms-2" href="public/register.php">
                                <i class="fas fa-user-plus me-2"></i>Registrar
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($current_page == 'home'): ?>
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <h1 class="display-4 fw-bold mb-4 animate__animated animate__fadeInLeft">
                            Sua piscina cristalina o ano todo
                        </h1>
                        <p class="lead mb-4 animate__animated animate__fadeInLeft animate__delay-1s">
                            Oferecemos serviços profissionais de limpeza e manutenção de piscinas para sua casa ou empresa. 
                            Trabalhamos com os melhores produtos e equipamentos para garantir água saudável e segura.
                        </p>
                        <div class="animate__animated animate__fadeInUp animate__delay-2s">
                            <a href="?page=agendar" class="btn btn-success btn-lg me-3 btn-pulse">
                                <i class="fas fa-calendar-check me-2"></i>Agendar Serviço
                            </a>
                            <a href="?page=servicos" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-info-circle me-2"></i>Ver Serviços
                            </a>
                        </div>
                        
                        <?php if (!$current_user): ?>
                            <div class="mt-4 animate__animated animate__fadeInUp animate__delay-3s">
                                <p class="mb-2">Já tem conta?</p>
                                <a href="public/login.php" class="btn btn-light">
                                    <i class="fas fa-sign-in-alt me-2"></i>Fazer Login
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

    <?php elseif ($current_page == 'servicos'): ?>
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold">Nossos Serviços</h1>
                    <p class="lead">Conheça todos os serviços que oferecemos para sua piscina</p>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-water service-icon"></i>
                                <h3>Limpeza Completa</h3>
                                <p>Aspiração, escovação das paredes e fundo, limpeza de bordas, skimmers e filtros.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Aspiração profissional</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Escovação de paredes</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Limpeza de filtros</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Tratamento químico</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">3500 MZN</span>
                                    <a href="?page=agendar&servico=limpeza_completa" class="btn btn-primary float-end">Agendar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-vial service-icon"></i>
                                <h3>Análise de Água</h3>
                                <p>Análise completa dos parâmetros da água: pH, cloro, alcalinidade, dureza cálcica.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Teste de pH</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Nível de cloro</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Alcalinidade total</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Relatório detalhado</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">1500 MZN</span>
                                    <a href="?page=agendar&servico=analise_agua" class="btn btn-primary float-end">Agendar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-tools service-icon"></i>
                                <h3>Manutenção de Equipamentos</h3>
                                <p>Manutenção preventiva e corretiva de bombas, filtros, aquecedores e sistemas.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Reparo de bombas</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Troca de filtros</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Manutenção de aquecedores</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Sistemas automáticos</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">Sob orçamento</span>
                                    <a href="?page=agendar&servico=manutencao_equipamentos" class="btn btn-primary float-end">Orçamento</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-snowflake service-icon"></i>
                                <h3>Invernagem</h3>
                                <p>Preparação completa da piscina para o período de inverno.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Produtos específicos</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Proteção contra geadas</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Cobertura adequada</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">1500 MZN</span>
                                    <a href="?page=agendar&servico=invernagem" class="btn btn-primary float-end">Agendar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-sun service-icon"></i>
                                <h3>Reabertura de Temporada</h3>
                                <p>Preparação da piscina para a temporada de verão.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Remoção de cobertura</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Limpeza profunda</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Equilíbrio químico</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">2000 MZN</span>
                                    <a href="?page=agendar&servico=reabertura_temporada" class="btn btn-primary float-end">Agendar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="service-card card p-4">
                            <div class="text-center">
                                <i class="fas fa-hand-sparkles service-icon"></i>
                                <h3>Serviço de Regularidade</h3>
                                <p>Planos personalizados de manutenção regular.</p>
                                <ul class="list-unstyled text-start mt-3">
                                    <li><i class="fas fa-check text-success me-2"></i>Planos semanais</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Planos quinzenais</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Planos mensais</li>
                                </ul>
                                <div class="mt-4">
                                    <span class="h4 text-primary">Sob consulta</span>
                                    <a href="?page=agendar&servico=servico_regularidade" class="btn btn-primary float-end">Agendar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <?php elseif ($current_page == 'agendar'): ?>
        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="text-center mb-5">
                            <h1 class="display-4 fw-bold">Agendar Serviço</h1>
                            <p class="lead">Preencha o formulário abaixo para solicitar um serviço</p>
                        </div>
                        
                        <?php if ($form_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $form_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($form_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $form_error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <ul class="nav nav-tabs-custom" id="agendamentoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo !isset($_GET['ver_agendamentos']) ? 'active' : ''; ?>" 
                                        id="novo-tab" data-bs-toggle="tab" data-bs-target="#novo" 
                                        type="button" role="tab" aria-controls="novo" aria-selected="true">
                                    <i class="fas fa-plus-circle me-2"></i>Novo Agendamento
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo isset($_GET['ver_agendamentos']) ? 'active' : ''; ?>" 
                                        id="lista-tab" data-bs-toggle="tab" data-bs-target="#lista" 
                                        type="button" role="tab" aria-controls="lista" aria-selected="false">
                                    <i class="fas fa-list me-2"></i>Meus Agendamentos
                                    <span class="counter-badge"><?php echo count($_SESSION['agendamentos']); ?></span>
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <div class="tab-pane fade <?php echo !isset($_GET['ver_agendamentos']) ? 'show active' : ''; ?>" 
                                 id="novo" role="tabpanel" aria-labelledby="novo-tab">
                                
                                <div class="card shadow-lg border-0">
                                    <div class="card-body p-5">
                                        <form method="POST" id="agendamentoForm" data-ajax="true">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="agendar_servico" value="1">
                                            
                                            <?php
                                            $servico_selecionado = $_GET['servico'] ?? '';
                                            ?>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Nome Completo *</label>
                                                    <input type="text" class="form-control form-control-lg" name="name" 
                                                           value="<?php echo $current_user['username'] ?? ''; ?>" 
                                                           data-validate="required minlength:3" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Email *</label>
                                                    <input type="email" class="form-control form-control-lg" name="email" 
                                                           value="<?php echo $current_user['email'] ?? ''; ?>" 
                                                           data-validate="required email" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Telefone *</label>
                                                    <input type="tel" class="form-control form-control-lg" name="phone" 
                                                           id="telefone" placeholder="+258 87 334 9977" 
                                                           data-validate="required phone" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Serviço *</label>
                                                    <select class="form-select form-select-lg" name="service" 
                                                            data-validate="required" required>
                                                        <option value="">Selecione um serviço</option>
                                                        <option value="limpeza_completa" <?php echo $servico_selecionado == 'limpeza_completa' ? 'selected' : ''; ?>>Limpeza Completa - 3500MZN</option>
                                                        <option value="analise_agua" <?php echo $servico_selecionado == 'analise_agua' ? 'selected' : ''; ?>>Análise de Água - 1500MZN</option>
                                                        <option value="manutencao_equipamentos" <?php echo $servico_selecionado == 'manutencao_equipamentos' ? 'selected' : ''; ?>>Manutenção de Equipamentos</option>
                                                        <option value="invernagem" <?php echo $servico_selecionado == 'invernagem' ? 'selected' : ''; ?>>Invernagem - 1500MZN</option>
                                                        <option value="reabertura_temporada" <?php echo $servico_selecionado == 'reabertura_temporada' ? 'selected' : ''; ?>>Reabertura de Temporada - 2000MZN</option>
                                                        <option value="servico_regularidade" <?php echo $servico_selecionado == 'servico_regularidade' ? 'selected' : ''; ?>>Serviço de Regularidade</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">CEP</label>
                                                    <input type="text" class="form-control" name="cep" id="cep" 
                                                           placeholder="00000-000">
                                                    <small class="text-muted">Digite o CEP para preencher o endereço automaticamente</small>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Endereço Completo *</label>
                                                    <textarea class="form-control" name="address" id="endereco" rows="2" 
                                                              placeholder="Rua, número, bairro, cidade..." 
                                                              data-validate="required" required></textarea>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Data do Serviço *</label>
                                                    <input type="date" class="form-control form-control-lg" name="service_date" 
                                                           id="data" min="<?php echo date('Y-m-d'); ?>" 
                                                           data-validate="required date" required>
                                                    <div class="invalid-feedback"></div>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-bold">Hora do Serviço *</label>
                                                    <select class="form-select form-select-lg" name="service_time" 
                                                            id="horario" data-validate="required" required>
                                                        <option value="">Selecione...</option>
                                                        <option value="08:00">08:00</option>
                                                        <option value="09:00">09:00</option>
                                                        <option value="10:00">10:00</option>
                                                        <option value="11:00">11:00</option>
                                                        <option value="13:00">13:00</option>
                                                        <option value="14:00">14:00</option>
                                                        <option value="15:00">15:00</option>
                                                        <option value="16:00">16:00</option>
                                                        <option value="17:00">17:00</option>
                                                    </select>
                                                    <div class="invalid-feedback"></div>
                                                    <small class="text-muted" id="disponibilidade-msg"></small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Mensagem / Observações</label>
                                                <textarea class="form-control" name="message" rows="4" 
                                                          placeholder="Descreva detalhes específicos sobre o serviço..."></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success btn-lg w-100 py-3" id="btn-submit">
                                                <i class="fab fa-whatsapp me-2"></i>
                                                Confirmar e Enviar para WhatsApp
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade <?php echo isset($_GET['ver_agendamentos']) ? 'show active' : ''; ?>" 
                                 id="lista" role="tabpanel" aria-labelledby="lista-tab">
                                
                                <div class="mb-3">
                                    <button class="btn btn-primary" id="btn-carregar-agendamentos">
                                        <i class="fas fa-sync-alt me-2"></i>Atualizar Lista
                                    </button>
                                </div>
                                
                                <div id="lista-agendamentos">
                                    <div class="text-center py-5">
                                        <span class="loading-spinner me-2"></span>
                                        Carregando agendamentos...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <?php elseif ($current_page == 'contacto'): ?>
        <section class="py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold">Contacte-nos</h1>
                    <p class="lead">Estamos sempre disponíveis para atender você</p>
                </div>
                
                <div class="contact-grid">
                    <div class="contact-card-modern animate__animated animate__zoomIn">
                        <span class="floating-badge">📞 Disponível 24/7</span>
                        <div class="contact-icon-modern">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <h3 class="contact-title-modern">Telefone</h3>
                        <div class="contact-info-modern"><?php echo $contact_phone; ?></div>
                        <div class="mt-3">
                            <i class="fas fa-clock me-2"></i>Atendimento: 8h às 20h
                        </div>
                    </div>
                    
                    <div class="contact-card-modern animate__animated animate__zoomIn animate__delay-1s">
                        <span class="floating-badge">📧 Resposta rápida</span>
                        <div class="contact-icon-modern">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="contact-title-modern">Email</h3>
                        <div class="contact-info-modern"><?php echo $contact_email; ?></div>
                        <div class="mt-3">
                            <i class="fas fa-check-circle me-2"></i>Respondemos em até 24h
                        </div>
                    </div>
                    
                    <div class="contact-card-modern animate__animated animate__zoomIn animate__delay-2s">
                        <span class="floating-badge">📍 Atendemos toda Maputo</span>
                        <div class="contact-icon-modern">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3 class="contact-title-modern">Localização</h3>
                        <div class="contact-info-modern"><?php echo $contact_address; ?></div>
                        <div class="mt-3">
                            <i class="fas fa-truck me-2"></i>Visita técnica gratuita
                        </div>
                    </div>
                </div>
                
                <div class="row justify-content-center mt-5">
                    <div class="col-lg-8">
                        <div class="schedule-card">
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-clock fa-4x mb-3" style="color: var(--accent-color);"></i>
                                    <h4 class="text-white">Horário de<br>Funcionamento</h4>
                                </div>
                                <div class="col-md-8">
                                    <div class="schedule-item">
                                        <i class="fas fa-calendar-day"></i>
                                        <div><strong>Segunda a Sexta:</strong> 08:00 - 20:00</div>
                                    </div>
                                    <div class="schedule-item">
                                        <i class="fas fa-calendar-day"></i>
                                        <div><strong>Sábado:</strong> 09:00 - 18:00</div>
                                    </div>
                                    <div class="schedule-item">
                                        <i class="fas fa-calendar-day"></i>
                                        <div><strong>Domingo:</strong> 10:00 - 16:00</div>
                                    </div>
                                    <div class="schedule-item">
                                        <i class="fas fa-star"></i>
                                        <div><strong>Emergência 24h:</strong> <?php echo $contact_phone; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <a href="?page=agendar" class="btn btn-primary btn-lg px-5 py-3 fw-bold">
                        <i class="fas fa-calendar-check me-2"></i>
                        AGENDAR SERVIÇO AGORA
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="footer-title"><i class="fas fa-swimming-pool me-2"></i>MussaneAqua</h5>
                    <p>Especialistas em limpeza e manutenção de piscinas.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="footer-title">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="?page=home" class="text-light">Início</a></li>
                        <li><a href="?page=servicos" class="text-light">Serviços</a></li>
                        <li><a href="?page=agendar" class="text-light">Agendar</a></li>
                        <li><a href="?page=contacto" class="text-light">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="footer-title">Contacto</h5>
                    <p><i class="fas fa-phone me-2"></i><?php echo $contact_phone; ?></p>
                    <p><i class="fas fa-envelope me-2"></i><?php echo $contact_email; ?></p>
                </div>
            </div>
        </div>
    </footer>

    <a href="https://wa.me/<?php echo $whatsapp_number; ?>" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-tooltip">Fale conosco</span>
    </a>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '<?php echo $csrf_token; ?>'
                }
            });

            $('#telefone').mask('+258 00 000 0000');
            $('#cep').mask('00000-000');
            
            $('form').on('input change', 'input, select, textarea', function() {
                validarCampo($(this));
            });

            $('#cep').on('blur', function() {
                const cep = $(this).val().replace(/\D/g, '');
                if (cep.length === 8) {
                    buscarCep(cep);
                }
            });

            $('#data, #horario').on('change', function() {
                verificarDisponibilidade();
            });

            $('#btn-carregar-agendamentos').on('click', function() {
                carregarAgendamentos();
            });

            $(document).on('click', '.btn-cancelar-agendamento', function() {
                const id = $(this).data('id');
                cancelarAgendamento(id);
            });

            if ($('#lista').hasClass('show active')) {
                carregarAgendamentos();
            }
        });

        function validarCampo($campo) {
            const nome = $campo.attr('name');
            const valor = $campo.val();
            const dados = {};
            dados[nome] = valor;

            $.ajax({
                url: 'api/api.php',
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

        function buscarCep(cep) {
            const $loading = $('<span class="loading-spinner ms-2"></span>');
            $('#cep').after($loading);

            $.ajax({
                url: 'api/api.php',
                method: 'POST',
                data: {
                    action: 'buscar_cep',
                    cep: cep
                },
                success: function(response) {
                    $loading.remove();
                    
                    if (response.success) {
                        $('#endereco').val(
                            response.logradouro + ', ' + response.bairro + ', ' + 
                            response.cidade + ' - ' + response.uf
                        );
                        mostrarNotificacao('Endereço preenchido automaticamente', 'success');
                    } else {
                        mostrarNotificacao(response.error || 'CEP não encontrado', 'error');
                    }
                },
                error: function() {
                    $loading.remove();
                    mostrarNotificacao('Erro ao buscar CEP', 'error');
                }
            });
        }

        function verificarDisponibilidade() {
            const data = $('#data').val();
            const horario = $('#horario').val();

            if (!data || !horario) return;

            $.ajax({
                url: 'api/api.php',
                method: 'POST',
                data: {
                    action: 'verificar_disponibilidade',
                    data: data,
                    horario: horario
                },
                success: function(response) {
                    const $horarioField = $('#horario');
                    const $msg = $('#disponibilidade-msg');
                    
                    if (response.disponivel) {
                        $horarioField.addClass('is-valid').removeClass('is-invalid');
                        $msg.text('✓ Horário disponível').css('color', '#28a745');
                    } else {
                        $horarioField.addClass('is-invalid').removeClass('is-valid');
                        $msg.text('✗ Horário indisponível').css('color', '#dc3545');
                    }
                }
            });
        }

        function carregarAgendamentos() {
            const $container = $('#lista-agendamentos');
            $container.html('<div class="text-center py-5"><span class="loading-spinner me-2"></span>Carregando...</div>');

            $.ajax({
                url: 'api/api.php',
                method: 'GET',
                data: {
                    action: 'buscar_agendamentos'
                },
                success: function(response) {
                    if (response.success && response.agendamentos.length > 0) {
                        let html = '<div class="tabela-agendamentos"><table class="table">' +
                                  '<thead><tr><th>#</th><th>Protocolo</th><th>Serviço</th>' +
                                  '<th>Data</th><th>Hora</th><th>Status</th><th>Ações</th></tr></thead><tbody>';

                        response.agendamentos.forEach(function(a) {
                            let statusClass = a.status_real || a.status;
                            html += '<tr>' +
                                   '<td>#' + a.id + '</td>' +
                                   '<td><small>' + (a.protocolo || 'AG' + a.id) + '</small></td>' +
                                   '<td>' + a.servico + '</td>' +
                                   '<td>' + new Date(a.data_agendamento).toLocaleDateString('pt-BR') + '</td>' +
                                   '<td>' + a.horario + '</td>' +
                                   '<td><span class="status-badge status-' + statusClass + '">' + 
                                   (statusClass.charAt(0).toUpperCase() + statusClass.slice(1)) + '</span></td>' +
                                   '<td>';

                            if (a.status === 'pendente') {
                                html += '<button class="btn btn-sm btn-outline-danger btn-cancelar-agendamento" ' +
                                       'data-id="' + a.id + '"><i class="fas fa-times"></i></button>';
                            }

                            html += '</td></tr>';
                        });

                        html += '</tbody></table></div>';
                        $container.html(html);
                    } else {
                        $container.html('<div class="text-center py-5"><i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>' +
                                      '<p class="text-muted">Nenhum agendamento encontrado</p></div>');
                    }
                },
                error: function() {
                    $container.html('<div class="alert alert-danger m-3">Erro ao carregar agendamentos</div>');
                }
            });
        }

        function cancelarAgendamento(id) {
            if (!confirm('Tem certeza que deseja cancelar este agendamento?')) {
                return;
            }

            $.ajax({
                url: 'api/api.php',
                method: 'POST',
                data: {
                    action: 'cancelar_agendamento',
                    agendamento_id: id
                },
                success: function(response) {
                    if (response.success) {
                        mostrarNotificacao(response.message, 'success');
                        carregarAgendamentos();
                    } else {
                        mostrarNotificacao(response.message, 'error');
                    }
                },
                error: function() {
                    mostrarNotificacao('Erro ao cancelar agendamento', 'error');
                }
            });
        }

        function mostrarNotificacao(mensagem, tipo) {
            const $toast = $('<div class="toast-notification toast-' + tipo + '">' + mensagem + '</div>');
            $('body').append($toast);
            
            setTimeout(function() {
                $toast.addClass('show');
            }, 100);

            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>