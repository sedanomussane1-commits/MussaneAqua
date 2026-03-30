<?php
// api.php
session_start();
require_once 'Database.php';
require_once 'Validator.php';
require_once 'Auth.php';
require_once 'UserManager.php';
require_once 'AgendamentoManager.php';

header('Content-Type: application/json');

class API {
    private $conn;
    private $auth;
    private $userManager;
    private $agendamentoManager;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->auth = new Auth();
        $this->userManager = new UserManager();
        $this->agendamentoManager = new AgendamentoManager();
    }

    public function handleRequest() {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'validar_campos':
                $this->validarCampos();
                break;
            case 'buscar_cep':
                $this->buscarCep();
                break;
            case 'verificar_disponibilidade':
                $this->verificarDisponibilidade();
                break;
            case 'buscar_agendamentos':
                $this->buscarAgendamentos();
                break;
            case 'cancelar_agendamento':
                $this->cancelarAgendamento();
                break;
            case 'verificar_email':
                $this->verificarEmail();
                break;
            case 'validar_senha':
                $this->validarSenha();
                break;
            case 'buscar_estatisticas':
                $this->buscarEstatisticas();
                break;
            case 'atualizar_status':
                $this->atualizarStatus();
                break;
            default:
                echo json_encode(['error' => 'Ação não encontrada']);
        }
    }

    private function validarCampos() {
        $campos = $_POST['campos'] ?? [];
        $erros = [];

        foreach ($campos as $campo => $valor) {
            switch ($campo) {
                case 'cliente_nome':
                case 'username':
                case 'name':
                    if (empty($valor)) {
                        $erros[$campo] = 'Este campo é obrigatório';
                    } elseif (strlen($valor) < 3) {
                        $erros[$campo] = 'Mínimo 3 caracteres';
                    }
                    break;

                case 'cliente_email':
                case 'email':
                    if (empty($valor)) {
                        $erros[$campo] = 'Email é obrigatório';
                    } elseif (!Validator::validateEmail($valor)) {
                        $erros[$campo] = 'Email inválido';
                    }
                    break;

                case 'cliente_telefone':
                case 'telefone':
                case 'phone':
                    if (empty($valor)) {
                        $erros[$campo] = 'Telefone é obrigatório';
                    } elseif (!preg_match('/^[0-9\+\-\s\(\)]{9,}$/', $valor)) {
                        $erros[$campo] = 'Telefone inválido';
                    }
                    break;

                case 'password':
                    if (empty($valor)) {
                        $erros[$campo] = 'Senha é obrigatória';
                    } elseif (!Validator::validateStrongPassword($valor)) {
                        $erros[$campo] = 'Senha deve ter 8+ caracteres, maiúscula, minúscula e número';
                    }
                    break;

                case 'confirm_password':
                    if ($valor !== $_POST['campos']['password']) {
                        $erros[$campo] = 'As senhas não coincidem';
                    }
                    break;

                case 'data_agendamento':
                case 'data':
                case 'service_date':
                    if (empty($valor)) {
                        $erros[$campo] = 'Data é obrigatória';
                    } elseif (strtotime($valor) < strtotime(date('Y-m-d'))) {
                        $erros[$campo] = 'Data não pode ser no passado';
                    }
                    break;

                case 'horario':
                case 'service_time':
                    if (empty($valor)) {
                        $erros[$campo] = 'Horário é obrigatório';
                    }
                    break;

                case 'endereco':
                case 'address':
                    if (empty($valor)) {
                        $erros[$campo] = 'Endereço é obrigatório';
                    }
                    break;

                case 'servico':
                case 'service':
                    if (empty($valor)) {
                        $erros[$campo] = 'Serviço é obrigatório';
                    }
                    break;
            }
        }

        echo json_encode(['success' => empty($erros), 'erros' => $erros]);
    }

    private function buscarCep() {
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
        
        if (strlen($cep) !== 8) {
            echo json_encode(['error' => 'CEP inválido']);
            return;
        }

        $url = "https://viacep.com.br/ws/{$cep}/json/";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!isset($data['erro'])) {
                echo json_encode([
                    'success' => true,
                    'logradouro' => $data['logradouro'] ?? '',
                    'bairro' => $data['bairro'] ?? '',
                    'cidade' => $data['localidade'] ?? '',
                    'uf' => $data['uf'] ?? '',
                    'cep' => $data['cep'] ?? ''
                ]);
                return;
            }
        }

        echo json_encode(['error' => 'CEP não encontrado']);
    }

    private function verificarDisponibilidade() {
        $data = $_POST['data'] ?? '';
        $horario = $_POST['horario'] ?? '';

        if (empty($data) || empty($horario)) {
            echo json_encode(['error' => 'Data e horário são obrigatórios']);
            return;
        }

        try {
            // Verificar se a tabela agendamentos existe
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'agendamentos'");
            if ($checkTable->rowCount() == 0) {
                echo json_encode([
                    'disponivel' => true,
                    'mensagem' => 'Horário disponível',
                    'agendamentos' => 0
                ]);
                return;
            }

            $query = "SELECT COUNT(*) as total FROM agendamentos 
                      WHERE data_agendamento = :data 
                      AND horario = :horario 
                      AND status IN ('pendente', 'confirmado')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':data', $data);
            $stmt->bindParam(':horario', $horario);
            $stmt->execute();
            
            $result = $stmt->fetch();
            $disponivel = $result['total'] < 3;

            echo json_encode([
                'disponivel' => $disponivel,
                'mensagem' => $disponivel ? 'Horário disponível' : 'Horário não disponível',
                'agendamentos' => $result['total']
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'disponivel' => true,
                'mensagem' => 'Horário disponível',
                'agendamentos' => 0
            ]);
        }
    }

    private function buscarAgendamentos() {
        if (!$this->auth->isAuthenticated()) {
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        $user_id = $_SESSION['user_id'];
        
        try {
            // Verificar se a tabela agendamentos existe
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'agendamentos'");
            if ($checkTable->rowCount() == 0) {
                echo json_encode(['success' => true, 'agendamentos' => []]);
                return;
            }

            $query = "SELECT a.* 
                      FROM agendamentos a 
                      WHERE a.user_id = :user_id 
                      ORDER BY a.data_agendamento DESC, a.horario DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $agendamentos = $stmt->fetchAll();

            // Adicionar status_real
            foreach ($agendamentos as &$a) {
                $hoje = date('Y-m-d');
                if ($a['data_agendamento'] < $hoje && $a['status'] == 'pendente') {
                    $a['status_real'] = 'atrasado';
                } else {
                    $a['status_real'] = $a['status'];
                }
                
                // Gerar protocolo se não existir
                if (!isset($a['protocolo']) || empty($a['protocolo'])) {
                    $a['protocolo'] = 'AG' . date('Ymd', strtotime($a['created_at'])) . str_pad($a['id'], 4, '0', STR_PAD_LEFT);
                }
            }

            echo json_encode(['success' => true, 'agendamentos' => $agendamentos]);

        } catch (Exception $e) {
            error_log("Erro ao buscar agendamentos: " . $e->getMessage());
            echo json_encode(['success' => true, 'agendamentos' => []]);
        }
    }

    private function cancelarAgendamento() {
        if (!$this->auth->isAuthenticated()) {
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        $agendamento_id = $_POST['agendamento_id'] ?? 0;
        $user_id = $_SESSION['user_id'];

        $result = $this->agendamentoManager->cancelarAgendamento($agendamento_id, $user_id);
        echo json_encode($result);
    }

    private function verificarEmail() {
        $email = Validator::sanitizeEmail($_POST['email'] ?? '');
        $exclude_id = $_POST['exclude_id'] ?? null;

        try {
            $query = "SELECT id FROM users WHERE email = :email AND deleted_at IS NULL";
            $params = [':email' => $email];

            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
                $params[':exclude_id'] = $exclude_id;
            }

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }
            
            $stmt->execute();
            
            $existe = $stmt->fetch() !== false;

            echo json_encode([
                'disponivel' => !$existe,
                'mensagem' => $existe ? 'Email já cadastrado' : 'Email disponível'
            ]);

        } catch (Exception $e) {
            echo json_encode(['error' => 'Erro ao verificar email']);
        }
    }

    private function validarSenha() {
        $senha = $_POST['senha'] ?? '';
        
        $requisitos = [
            'length' => strlen($senha) >= 8,
            'upper' => preg_match('/[A-Z]/', $senha),
            'lower' => preg_match('/[a-z]/', $senha),
            'number' => preg_match('/[0-9]/', $senha)
        ];

        $valida = $requisitos['length'] && $requisitos['upper'] && 
                  $requisitos['lower'] && $requisitos['number'];

        echo json_encode([
            'valida' => $valida,
            'requisitos' => $requisitos
        ]);
    }

    private function buscarEstatisticas() {
        if (!$this->auth->isAuthenticated()) {
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        $user_id = $_SESSION['user_id'];
        $is_admin = ($_SESSION['role'] ?? '') === 'admin';

        try {
            $response = [
                'success' => true,
                'total_agendamentos' => 0,
                'agendamentos_hoje' => 0,
                'agendamentos_status' => [],
                'total_usuarios' => 0
            ];

            // Verificar se a tabela agendamentos existe
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'agendamentos'");
            if ($checkTable->rowCount() > 0) {
                if ($is_admin) {
                    // Admin vê todos os agendamentos
                    $stmt = $this->conn->query("SELECT COUNT(*) as total FROM agendamentos");
                    $response['total_agendamentos'] = $stmt->fetch()['total'];

                    $stmt = $this->conn->query("SELECT status, COUNT(*) as total FROM agendamentos GROUP BY status");
                    $response['agendamentos_status'] = $stmt->fetchAll();

                    $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE data_agendamento = CURDATE()");
                    $stmt->execute();
                    $response['agendamentos_hoje'] = $stmt->fetch()['total'];
                } else {
                    // Usuário comum vê apenas seus agendamentos
                    $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $response['total_agendamentos'] = $stmt->fetch()['total'];

                    $stmt = $this->conn->prepare("SELECT status, COUNT(*) as total FROM agendamentos WHERE user_id = :user_id GROUP BY status");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $response['agendamentos_status'] = $stmt->fetchAll();

                    $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE user_id = :user_id AND data_agendamento = CURDATE()");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $response['agendamentos_hoje'] = $stmt->fetch()['total'];
                }
            }

            // Total de usuários (apenas admin)
            if ($is_admin) {
                $stmt = $this->conn->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
                $response['total_usuarios'] = $stmt->fetch()['total'];
            }

            echo json_encode($response);

        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            echo json_encode([
                'success' => true,
                'total_agendamentos' => 0,
                'agendamentos_hoje' => 0,
                'agendamentos_status' => [],
                'total_usuarios' => 0
            ]);
        }
    }

    private function atualizarStatus() {
        if (!$this->auth->isAuthenticated() || $_SESSION['role'] !== 'admin') {
            echo json_encode(['error' => 'Acesso negado']);
            return;
        }

        $agendamento_id = $_POST['agendamento_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $user_id = $_SESSION['user_id'];

        $result = $this->agendamentoManager->updateStatus($agendamento_id, $status, $user_id);
        echo json_encode($result);
    }
}

$api = new API();
$api->handleRequest();
?>