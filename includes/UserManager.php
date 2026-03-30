<?php
// includes/UserManager.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/Validator.php';

class UserManager {
    private $conn;
    private $audit;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->audit = new AuditLog();
    }

    public function createUser($data, $created_by) {
        try {
            $errors = $this->validateUserData($data);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors, 'message' => implode(', ', $errors)];
            }

            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Email já cadastrado'];
            }

            $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

            $query = "INSERT INTO users 
                      (username, email, password_hash, role, is_active, created_by, created_at) 
                      VALUES 
                      (:username, :email, :password, :role, :is_active, :created_by, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $data['role']);
            $stmt->bindParam(':is_active', $data['is_active']);
            $stmt->bindParam(':created_by', $created_by);

            if ($stmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                $this->audit->logCreate(
                    $created_by,
                    'users',
                    $user_id,
                    $data,
                    'Usuário criado'
                );

                return ['success' => true, 'message' => 'Usuário criado com sucesso', 'user_id' => $user_id];
            }

            return ['success' => false, 'message' => 'Erro ao criar usuário'];

        } catch (Exception $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor: ' . $e->getMessage()];
        }
    }

    public function updateUser($user_id, $data, $updated_by) {
        try {
            $current = $this->getUserById($user_id);
            if (!$current) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }

            $updates = [];
            $params = [':id' => $user_id];

            $allowed_fields = ['username', 'email', 'role', 'is_active'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field]) && $data[$field] !== $current[$field]) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            if (!empty($data['password'])) {
                if (!Validator::validateStrongPassword($data['password'])) {
                    return ['success' => false, 'errors' => ['password' => 'Senha não atende aos requisitos de segurança']];
                }
                $updates[] = "password_hash = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (empty($updates)) {
                return ['success' => true, 'message' => 'Nenhuma alteração necessária'];
            }

            $updates[] = "updated_at = NOW()";
            $updates[] = "updated_by = :updated_by";
            $params[':updated_by'] = $updated_by;

            $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }

            if ($stmt->execute()) {
                $this->audit->logUpdate(
                    $updated_by,
                    'users',
                    $user_id,
                    $current,
                    $data,
                    'Usuário atualizado'
                );

                return ['success' => true, 'message' => 'Usuário atualizado com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao atualizar usuário'];

        } catch (Exception $e) {
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor: ' . $e->getMessage()];
        }
    }

    public function deleteUser($user_id, $deleted_by) {
        try {
            $user = $this->getUserById($user_id);
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }

            if ($user_id == $deleted_by) {
                return ['success' => false, 'message' => 'Não é possível excluir seu próprio usuário'];
            }

            $query = "UPDATE users SET 
                      is_active = 0, 
                      deleted_at = NOW(), 
                      deleted_by = :deleted_by 
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':deleted_by', $deleted_by);
            $stmt->bindParam(':id', $user_id);

            if ($stmt->execute()) {
                $this->audit->logDelete(
                    $deleted_by,
                    'users',
                    $user_id,
                    $user,
                    'Usuário desativado'
                );

                return ['success' => true, 'message' => 'Usuário desativado com sucesso'];
            }

            return ['success' => false, 'message' => 'Erro ao desativar usuário'];

        } catch (Exception $e) {
            error_log("Erro ao deletar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno no servidor: ' . $e->getMessage()];
        }
    }

    public function getUsers($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            $where_conditions = ['deleted_at IS NULL'];
            $params = [];

            if (!empty($filters['search'])) {
                $where_conditions[] = "(username LIKE :search OR email LIKE :search)";
                $params[':search'] = "%{$filters['search']}%";
            }

            if (!empty($filters['role'])) {
                $where_conditions[] = "role = :role";
                $params[':role'] = $filters['role'];
            }

            if (isset($filters['is_active'])) {
                $where_conditions[] = "is_active = :is_active";
                $params[':is_active'] = $filters['is_active'];
            }

            $where_clause = implode(' AND ', $where_conditions);

            $count_query = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
            $count_stmt = $this->conn->prepare($count_query);
            
            foreach ($params as $key => &$value) {
                $count_stmt->bindParam($key, $value);
            }
            
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];

            $query = "SELECT id, username, email, role, is_active, 
                             created_at, last_login, login_attempts
                      FROM users 
                      WHERE $where_clause
                      ORDER BY created_at DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            return [
                'users' => $stmt->fetchAll(),
                'total' => $total,
                'pages' => ceil($total / $limit),
                'current_page' => $page
            ];

        } catch (Exception $e) {
            error_log("Erro ao listar usuários: " . $e->getMessage());
            return ['users' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1, 'error' => $e->getMessage()];
        }
    }

    public function getUserById($user_id) {
        try {
            $query = "SELECT id, username, email, role, is_active, 
                             created_at, last_login, login_attempts
                      FROM users 
                      WHERE id = :id AND deleted_at IS NULL";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();

            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            return null;
        }
    }

    public function getUserByEmail($email) {
        try {
            $query = "SELECT id, username, email, role, is_active, 
                             created_at, last_login, login_attempts
                      FROM users 
                      WHERE email = :email AND deleted_at IS NULL";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar usuário por email: " . $e->getMessage());
            return null;
        }
    }

    private function validateUserData($data) {
        $errors = [];

        if (empty($data['username'])) {
            $errors['username'] = 'Nome de usuário é obrigatório';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email é obrigatório';
        } elseif (!Validator::validateEmail($data['email'])) {
            $errors['email'] = 'Email inválido';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Senha é obrigatória';
        } elseif (!Validator::validateStrongPassword($data['password'])) {
            $errors['password'] = 'Senha deve ter no mínimo 8 caracteres, uma maiúscula, uma minúscula e um número';
        }

        $valid_roles = ['admin', 'funcionario', 'cliente'];
        if (empty($data['role']) || !in_array($data['role'], $valid_roles)) {
            $errors['role'] = 'Papel inválido';
        }

        return $errors;
    }

    private function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM users WHERE email = :email";
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
        
        return $stmt->fetch() !== false;
    }

    public function toggleUserStatus($user_id, $status, $updated_by) {
        try {
            $query = "UPDATE users SET is_active = :status, updated_at = NOW(), updated_by = :updated_by WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':updated_by', $updated_by);
            $stmt->bindParam(':id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao alterar status do usuário: " . $e->getMessage());
            return false;
        }
    }

    public function resetLoginAttempts($user_id) {
        try {
            $query = "UPDATE users SET login_attempts = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao resetar tentativas de login: " . $e->getMessage());
            return false;
        }
    }
}