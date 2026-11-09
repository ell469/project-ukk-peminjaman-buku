<?php
require_once __DIR__ . '/../includes/auth.php';

$_SESSION = [];// Menghapus semua data sesi (session) yang tersimpan

// Jika sesi menggunakan cookie, hapus cookie sesi dari browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();// Menghancurkan sesi (session) secara permanen

header('Location: ' . url('auth/login.php'));
exit;
