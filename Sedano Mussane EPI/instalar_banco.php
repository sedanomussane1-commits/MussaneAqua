<?php
// instalar_banco.php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔧 Instalando Banco de Dados</h1>";
    echo "<pre>";
    
    // Criar banco
    $pdo->exec("DROP DATABASE IF EXISTS mussaneaqua");
    echo "✅ Banco antigo removido\n";
    
    $pdo->exec("CREATE DATABASE mussaneaqua CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Banco 'mussaneaqua' criado\n";
    
    $pdo->exec("USE mussaneaqua");
    
    // Criar tabela users
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'funcionario', 'cliente') DEFAULT 'cliente',
        is_active BOOLEAN DEFAULT TRUE,
        login_attempts INT DEFAULT 0,
        last_login_attempt DATETIME NULL,
        last_login DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        deleted_at DATETIME NULL,
        created_by INT NULL,
        updated_by INT NULL,
        deleted_by INT NULL,
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "✅ Tabela 'users' criada\n";
    
    // Criar remember_tokens
    $sql = "CREATE TABLE remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expires (expires)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "✅ Tabela 'remember_tokens' criada\n";
    
    // Criar audit_logs
    $sql = "CREATE TABLE audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(100) NULL,
        record_id INT NULL,
        old_values JSON NULL,
        new_values JSON NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        description TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_table (table_name),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "✅ Tabela 'audit_logs' criada\n";
    
    // Criar agendamentos
    $sql = "CREATE TABLE agendamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        protocolo VARCHAR(20) NULL UNIQUE,
        cliente_nome VARCHAR(255) NOT NULL,
        cliente_email VARCHAR(255) NOT NULL,
        cliente_telefone VARCHAR(20) NOT NULL,
        endereco TEXT NOT NULL,
        servico VARCHAR(100) NOT NULL,
        data_agendamento DATE NOT NULL,
        horario VARCHAR(20) NOT NULL,
        status ENUM('pendente', 'confirmado', 'concluido', 'cancelado') DEFAULT 'pendente',
        observacoes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        created_by INT NULL,
        updated_by INT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_data (data_agendamento),
        INDEX idx_status (status),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "✅ Tabela 'agendamentos' criada\n";
    
    // Criar password_resets
    $sql = "CREATE TABLE password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "✅ Tabela 'password_resets' criada\n";
    
    // Inserir admin
    $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $insert->execute(['admin', 'admin@mussaneaqua.com', $hash]);
    echo "✅ Admin criado: admin@mussaneaqua.com / Admin@123\n";
    
    // Inserir Saidade
    $insert->execute(['Saidade Willem', 'saidade@mussaneaqua.com', $hash, 'funcionario']);
    echo "✅ Funcionário criado: saidade@mussaneaqua.com\n";
    
    echo "\n🎉 BANCO DE DADOS INSTALADO COM SUCESSO!\n";
    echo "Acesse: <a href='public/login.php'>Login</a>\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage();
}
?>