<?php
class Validator {
    
    public static function sanitizeString($input) {
        if ($input === null) return "";
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, "UTF-8");
        return $input;
    }

    public static function sanitizeEmail($email) {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return $email;
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateStrongPassword($password) {
        return strlen($password) >= 8 &&
               preg_match("/[A-Z]/", $password) &&
               preg_match("/[a-z]/", $password) &&
               preg_match("/[0-9]/", $password);
    }

    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION["csrf_token"]) || $token !== $_SESSION["csrf_token"]) {
            return false;
        }
        return true;
    }

    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf_token"];
    }
}