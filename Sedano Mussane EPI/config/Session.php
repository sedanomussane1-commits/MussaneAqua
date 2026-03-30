<?php
// config/Session.php
class Session {
    private static $instance = null;
    private $session_name = 'MUSSANEAQUA_SESSION';
    private $secure = false; // true em produção com HTTPS
    private $httponly = true;
    private $samesite = 'Strict';
    private $lifetime = 7200; // 2 horas

    private function __construct() {
        // Configurações devem ser feitas ANTES de iniciar a sessão
        $this->configureSession();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function configureSession() {
        // Só configurar se a sessão ainda não foi iniciada
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de segurança da sessão
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', $this->httponly);
            ini_set('session.cookie_secure', $this->secure);
            ini_set('session.cookie_samesite', $this->samesite);
            ini_set('session.gc_maxlifetime', $this->lifetime);
            ini_set('session.cookie_lifetime', 0); // Sessão expira ao fechar o navegador
            ini_set('session.sid_length', 48);
            ini_set('session.sid_bits_per_character', 6);
            ini_set('session.hash_function', 'sha256');

            session_name($this->session_name);
        }
    }

    public function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->regenerateId();
        }
    }

    private function regenerateId() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Regenerar ID a cada requisição para prevenir fixation
            if (!isset($_SESSION['_generated'])) {
                session_regenerate_id(true);
                $_SESSION['_generated'] = time();
            } elseif (time() - $_SESSION['_generated'] > 300) { // A cada 5 minutos
                session_regenerate_id(true);
                $_SESSION['_generated'] = time();
            }
        }
    }

    public function set($key, $value) {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get($key, $default = null) {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function has($key) {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove($key) {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    $this->session_name,
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            session_destroy();
        }
    }

    public function regenerate() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function isValid() {
        $this->start();
        
        // Verificar se a sessão é válida (não expirada, IP consistente, etc.)
        if (!isset($_SESSION['login_time'])) {
            return false;
        }

        if (time() - $_SESSION['login_time'] > $this->lifetime) {
            $this->destroy();
            return false;
        }

        // Verificar User Agent
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->destroy();
            return false;
        }

        return true;
    }

    public function refresh() {
        $this->start();
        $_SESSION['login_time'] = time();
    }
}