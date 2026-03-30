<?php
// includes/AuditLog.php
require_once __DIR__ . '/../config/Database.php';

class AuditLog {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Registrar evento de auditoria
     */
    public function log($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null, $description = null) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Converter arrays para JSON
            $old_values_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
            $new_values_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;

            $query = "INSERT INTO audit_logs 
                      (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, description, created_at) 
                      VALUES 
                      (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, :description, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->bindParam(':old_values', $old_values_json);
            $stmt->bindParam(':new_values', $new_values_json);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':description', $description);

            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Erro ao registrar log de auditoria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar log estruturado em JSON
     */
    public function logStructured($user_id, $action, $data = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'username' => $_SESSION['username'] ?? null,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'data' => $data
        ];
        
        // Criar diretório de logs se não existir
        $log_file = __DIR__ . '/../logs/audit_' . date('Y-m-d') . '.json';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Salvar em arquivo JSON
        file_put_contents($log_file, json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Também salvar no banco
        return $this->log(
            $user_id, 
            $action, 
            $data['table'] ?? null, 
            $data['record_id'] ?? null, 
            $data['old'] ?? null, 
            $data['new'] ?? null, 
            $data['description'] ?? null
        );
    }

    /**
     * Buscar logs de auditoria com filtros
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        try {
            $where_conditions = [];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where_conditions[] = "al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            if (!empty($filters['action'])) {
                $where_conditions[] = "al.action = :action";
                $params[':action'] = $filters['action'];
            }

            if (!empty($filters['table_name'])) {
                $where_conditions[] = "al.table_name = :table_name";
                $params[':table_name'] = $filters['table_name'];
            }

            if (!empty($filters['date_from'])) {
                $where_conditions[] = "DATE(al.created_at) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where_conditions[] = "DATE(al.created_at) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

            $query = "SELECT al.*, u.username, u.email 
                      FROM audit_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      $where_clause
                      ORDER BY al.created_at DESC
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
            error_log("Erro ao buscar logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registrar criação de registro
     */
    public function logCreate($user_id, $table_name, $record_id, $new_values, $description = null) {
        return $this->log($user_id, 'CREATE', $table_name, $record_id, null, $new_values, $description);
    }

    /**
     * Registrar atualização de registro
     */
    public function logUpdate($user_id, $table_name, $record_id, $old_values, $new_values, $description = null) {
        return $this->log($user_id, 'UPDATE', $table_name, $record_id, $old_values, $new_values, $description);
    }

    /**
     * Registrar exclusão de registro
     */
    public function logDelete($user_id, $table_name, $record_id, $old_values, $description = null) {
        return $this->log($user_id, 'DELETE', $table_name, $record_id, $old_values, null, $description);
    }

    /**
     * Registrar login
     */
    public function logLogin($user_id, $status, $description = null) {
        $action = $status === 'success' ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED';
        return $this->log($user_id, $action, 'users', $user_id, null, null, $description);
    }

    /**
     * Registrar logout
     */
    public function logLogout($user_id) {
        return $this->log($user_id, 'LOGOUT', 'users', $user_id, null, null, 'Logout realizado');
    }

    /**
     * Registrar acesso inválido
     */
    public function logInvalidAccess($user_id = null, $description = null) {
        return $this->log($user_id, 'INVALID_ACCESS', null, null, null, null, $description);
    }
}