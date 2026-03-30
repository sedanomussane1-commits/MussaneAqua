<?php
// includes/AgendamentoManager.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/Validator.php';

class AgendamentoManager {
    private $conn;
    private $audit;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->audit = new AuditLog();
    }

    public function createAgendamento($data, $user_id) {
        try {
            $errors = $this->validateAgendamentoData($data);
            if (!empty($errors)) {
                return ['success' => false, 'message' => implode(', ', $errors)];
            }

            $query = "INSERT INTO agendamentos 
                      (user_id, cliente_nome, cliente_email, cliente_telefone, endereco, servico, 
                       data_agendamento, horario, observacoes, status, created_by, created_at) 
                      VALUES 
                      (:user_id, :cliente_nome, :cliente_email, :cliente_telefone, :endereco, :servico,
                       :data_agendamento, :horario, :observacoes, 'pendente', :created_by, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':cliente_nome', $data['cliente_nome']);
            $stmt->bindParam(':cliente_email', $data['cliente_email']);
            $stmt->bindParam(':cliente_telefone', $data['cliente_telefone']);
            $stmt->bindParam(':endereco', $data['endereco']);
            $stmt->bindParam(':servico', $data['servico']);
            $stmt->bindParam(':data_agendamento', $data['data_agendamento']);
            $stmt->bindParam(':horario', $data['horario']);
            $stmt->bindParam(':observacoes', $data['observacoes']);
            $stmt->bindParam(':created_by', $user_id);

            if ($stmt->execute()) {
                $agendamento_id = $this->conn->lastInsertId();
                
                $protocolo = 'AG' . date('Ymd') . str_pad($agendamento_id, 4, '0', STR_PAD_LEFT);
                
                $update = $this->conn->prepare("UPDATE agendamentos SET protocolo = :protocolo WHERE id = :id");
                $update->bindParam(':protocolo', $protocolo);
                $update->bindParam(':id', $agendamento_id);
                $update->execute();

                $this->audit->logCreate(
                    $user_id,
                    'agendamentos',
                    $agendamento_id,
                    $data,
                    'Novo agendamento criado - Protocolo: ' . $protocolo
                );

                return [
                    'success' => true, 
                    'message' => 'Agendamento realizado com sucesso!',
                    'agendamento_id' => $agendamento_id,
                    'protocolo' => $protocolo
                ];
            }

            return ['success' => false, 'message' => 'Erro ao criar agendamento'];

        } catch (Exception $e) {
            error_log("Erro ao criar agendamento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor: ' . $e->getMessage()];
        }
    }

    public function getAgendamentosByUser($user_id, $limit = 10, $offset = 0) {
        try {
            $query = "SELECT a.*, 
                             CASE 
                                 WHEN a.data_agendamento < CURDATE() AND a.status = 'pendente' THEN 'atrasado'
                                 ELSE a.status
                             END as status_real
                      FROM agendamentos a
                      WHERE a.user_id = :user_id
                      ORDER BY 
                          CASE a.status
                              WHEN 'pendente' THEN 1
                              WHEN 'confirmado' THEN 2
                              WHEN 'concluido' THEN 3
                              WHEN 'cancelado' THEN 4
                          END,
                          a.data_agendamento DESC,
                          a.horario DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar agendamentos: " . $e->getMessage());
            return [];
        }
    }

    public function getAllAgendamentos($filters = [], $limit = 20, $offset = 0) {
        try {
            $where_conditions = [];
            $params = [];

            if (!empty($filters['status'])) {
                $where_conditions[] = "a.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['user_id'])) {
                $where_conditions[] = "a.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            if (!empty($filters['data_inicio'])) {
                $where_conditions[] = "a.data_agendamento >= :data_inicio";
                $params[':data_inicio'] = $filters['data_inicio'];
            }

            if (!empty($filters['data_fim'])) {
                $where_conditions[] = "a.data_agendamento <= :data_fim";
                $params[':data_fim'] = $filters['data_fim'];
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

            $query = "SELECT a.*, u.username, u.email as user_email 
                      FROM agendamentos a
                      LEFT JOIN users u ON a.user_id = u.id
                      $where_clause
                      ORDER BY a.data_agendamento DESC, a.horario DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar todos agendamentos: " . $e->getMessage());
            return [];
        }
    }

    public function getAgendamentoById($agendamento_id) {
        try {
            $query = "SELECT a.*, u.username, u.email 
                      FROM agendamentos a
                      LEFT JOIN users u ON a.user_id = u.id
                      WHERE a.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $agendamento_id);
            $stmt->execute();

            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar agendamento: " . $e->getMessage());
            return null;
        }
    }

    public function updateStatus($agendamento_id, $status, $updated_by) {
        try {
            $agendamento = $this->getAgendamentoById($agendamento_id);
            if (!$agendamento) {
                return ['success' => false, 'message' => 'Agendamento não encontrado'];
            }

            $query = "UPDATE agendamentos 
                      SET status = :status, updated_at = NOW(), updated_by = :updated_by 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':updated_by', $updated_by);
            $stmt->bindParam(':id', $agendamento_id);

            if ($stmt->execute()) {
                $this->audit->logUpdate(
                    $updated_by,
                    'agendamentos',
                    $agendamento_id,
                    ['status' => $agendamento['status']],
                    ['status' => $status],
                    'Status do agendamento atualizado'
                );

                return ['success' => true, 'message' => 'Status atualizado com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao atualizar status'];

        } catch (Exception $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor'];
        }
    }

    public function cancelarAgendamento($agendamento_id, $user_id) {
        try {
            $agendamento = $this->getAgendamentoById($agendamento_id);
            if (!$agendamento) {
                return ['success' => false, 'message' => 'Agendamento não encontrado'];
            }

            if ($agendamento['user_id'] != $user_id && $_SESSION['role'] !== 'admin') {
                return ['success' => false, 'message' => 'Você não tem permissão para cancelar este agendamento'];
            }

            if (!in_array($agendamento['status'], ['pendente', 'confirmado'])) {
                return ['success' => false, 'message' => 'Este agendamento não pode ser cancelado'];
            }

            $query = "UPDATE agendamentos 
                      SET status = 'cancelado', updated_at = NOW(), updated_by = :updated_by 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':updated_by', $user_id);
            $stmt->bindParam(':id', $agendamento_id);

            if ($stmt->execute()) {
                $this->audit->logUpdate(
                    $user_id,
                    'agendamentos',
                    $agendamento_id,
                    ['status' => $agendamento['status']],
                    ['status' => 'cancelado'],
                    'Agendamento cancelado pelo usuário'
                );

                return ['success' => true, 'message' => 'Agendamento cancelado com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao cancelar agendamento'];

        } catch (Exception $e) {
            error_log("Erro ao cancelar agendamento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor'];
        }
    }

    public function countUserAgendamentos($user_id, $status = null) {
        try {
            $query = "SELECT COUNT(*) as total FROM agendamentos WHERE user_id = :user_id";
            $params = [':user_id' => $user_id];

            if ($status) {
                $query .= " AND status = :status";
                $params[':status'] = $status;
            }

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }
            
            $stmt->execute();
            
            return $stmt->fetch()['total'];

        } catch (Exception $e) {
            error_log("Erro ao contar agendamentos: " . $e->getMessage());
            return 0;
        }
    }

    private function validateAgendamentoData($data) {
        $errors = [];

        if (empty($data['cliente_nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['cliente_email'])) {
            $errors[] = 'Email é obrigatório';
        } elseif (!Validator::validateEmail($data['cliente_email'])) {
            $errors[] = 'Email inválido';
        }

        if (empty($data['cliente_telefone'])) {
            $errors[] = 'Telefone é obrigatório';
        }

        if (empty($data['endereco'])) {
            $errors[] = 'Endereço é obrigatório';
        }

        if (empty($data['servico'])) {
            $errors[] = 'Serviço é obrigatório';
        }

        if (empty($data['data_agendamento'])) {
            $errors[] = 'Data é obrigatória';
        } elseif (strtotime($data['data_agendamento']) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Data não pode ser no passado';
        }

        if (empty($data['horario'])) {
            $errors[] = 'Horário é obrigatório';
        }

        return $errors;
    }

    public function getProximosAgendamentos($user_id, $limit = 5) {
        try {
            $query = "SELECT * FROM agendamentos 
                      WHERE user_id = :user_id 
                      AND data_agendamento >= CURDATE() 
                      AND status IN ('pendente', 'confirmado')
                      ORDER BY data_agendamento ASC, horario ASC
                      LIMIT :limit";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar próximos agendamentos: " . $e->getMessage());
            return [];
        }
    }
}