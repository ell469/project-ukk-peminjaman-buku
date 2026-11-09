<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLogin(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function isAdmin(): bool
{
    return isLogin() && ($_SESSION['level'] ?? '') === 'admin';
}

function isUser(): bool
{
    return isLogin() && ($_SESSION['level'] ?? '') === 'user';
}

function requireLogin(): void
{
    if (!isLogin()) {
        header('Location: ' . url('auth/login.php'));
        exit;
    }
}

// Halaman khusus admin, user biasa dilempar ke dashboard
function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        header('Location: ' . url('dashboard/dashboard.php'));
        exit;
    }
}

// Halaman khusus user (anggota). Admin nggak boleh masuk, contohnya buat pinjam buku
function requireUser(): void
{
    requireLogin();

    if (!isUser()) {
        header('Location: ' . url('dashboard/dashboard.php'));
        exit;
    }
}
