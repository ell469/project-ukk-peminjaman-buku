<?php
// Pesan sementara (flash) + data form lama, dipakai setelah redirect.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function setFlash(string $type, string $message, array $old = []): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    $_SESSION['flash_old'] = $old;
}

// Mengambil pesan lalu menghapusnya agar tidak muncul dua kali.
function takeFlash(): array
{
    $flash = $_SESSION['flash'] ?? null;
    $old = $_SESSION['flash_old'] ?? [];
    unset($_SESSION['flash'], $_SESSION['flash_old']);

    return [$flash, $old];
}

function redirectWithFlash(
    string $to,
    string $type,
    string $message,
    array $old = []
): void {
    setFlash($type, $message, $old);
    header('Location: ' . $to);
    exit;
}
