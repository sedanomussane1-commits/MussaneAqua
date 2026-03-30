<?php
// includes/AuthMiddleware.php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/TokenManager.php';

class AuthMiddleware {
    private $auth;
    private $audit;
    private $tokenManager;

    public function __construct() {
        $this->auth = new Auth();
        $this->audit = new AuditLog();
        $this->tokenManager = TokenManager::getInstance();
    }

    public function requireAuth() {
        if (!$this->auth->isAuthenticated()) {
            $this->audit->logInvalidAccess(null, 'Tentativa de acesso a página protegida sem autenticação');
            $this->redirectToLogin('Por favor, faça login para acessar esta página.');
        }
    }

    public function requireRole($required_role) {
        $this->requireAuth();
        
        if (!$this->auth->hasPermission($required_role)) {
            $user_id = $_SESSION['user_id'] ?? null;
            $this->audit->logInvalidAccess($user_id, 'Tentativa de acesso sem permissão: ' . $required_role);
            $this->redirectToHome('Acesso negado. Você não tem permissão para acessar esta página.');
        }
    }

    public function requireCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$this->tokenManager->validateCSRFToken($token)) {
                $user_id = $_SESSION['user_id'] ?? null;
                $this->audit->logInvalidAccess($user_id, 'Tentativa de acesso com CSRF token inválido');
                
                die(json_encode([
                    'success' => false,
                    'message' => 'Erro de validação CSRF. Por favor, recarregue a página e tente novamente.'
                ]));
            }
        }
    }

    public function requireHttps() {
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $redirect);
            exit;
        }
    }

    public function setSecurityHeaders() {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Ativar proteção XSS no navegador
        header('X-XSS-Protection: 1; mode=block');
        
        // Política de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy básica
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com;");
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    private function redirectToLogin($message = null) {
        if ($message) {
            $_SESSION['error_message'] = $message;
        }
        header('Location: ../public/login.php');
        exit;
    }

    private function redirectToHome($message = null) {
        if ($message) {
            $_SESSION['error_message'] = $message;
        }
        header('Location: ../index.php');
        exit;
    }
}