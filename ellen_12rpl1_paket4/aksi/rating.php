<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/connection.php';

$userId = (int) $_SESSION['user_id'];
$bukuId = (int) ($_POST['buku_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);

if ($bukuId < 1 || $rating < 1 || $rating > 5) {
    header('Location: ' . url('buku/detail.php?id=' . $bukuId));
    exit;
}

$check = $conn->prepare("SELECT id FROM buku WHERE id = ? AND status_aktif = 1 LIMIT 1");
$check->bind_param('i', $bukuId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    header('Location: ' . url('dashboard/dashboard.php'));
    exit;
}

$stmt = $conn->prepare("SELECT id FROM rating_buku WHERE user_id = ? AND buku_id = ? LIMIT 1");
$stmt->bind_param('ii', $userId, $bukuId);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();

if ($old) {
    $stmt = $conn->prepare("UPDATE rating_buku SET rating = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('ii', $rating, $old['id']);
} else {
    $stmt = $conn->prepare("INSERT INTO rating_buku (user_id, buku_id, rating) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $userId, $bukuId, $rating);
}
$stmt->execute();

header('Location: ' . url('buku/detail.php?id=' . $bukuId));
exit;
