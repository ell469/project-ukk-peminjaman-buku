<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/connection.php';

$userId = (int) $_SESSION['user_id'];
$bukuId = (int) ($_POST['buku_id'] ?? $_GET['buku_id'] ?? 0);
$return = $_POST['return'] ?? $_GET['return'] ?? 'dashboard/dashboard.php';

$allowed = ['dashboard/dashboard.php', 'buku/detail.php', 'bookmark/index.php'];
if (!in_array($return, $allowed, true)) {
    $return = 'dashboard/dashboard.php';
}

if ($bukuId > 0) {
    $check = $conn->prepare("SELECT id FROM buku WHERE id = ? AND status_aktif = 1 LIMIT 1");
    $check->bind_param('i', $bukuId);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        $check = $conn->prepare("SELECT id FROM bookmark WHERE user_id = ? AND buku_id = ? LIMIT 1");
        $check->bind_param('ii', $userId, $bukuId);
        $check->execute();
        $saved = $check->get_result()->fetch_assoc();

        if ($saved) {
            $stmt = $conn->prepare("DELETE FROM bookmark WHERE id = ?");
            $stmt->bind_param('i', $saved['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO bookmark (user_id, buku_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $userId, $bukuId);
        }
        $stmt->execute();
    }
}

$location = url($return);
if ($return === 'buku/detail.php') {
    $location .= '?id=' . $bukuId;
}
header('Location: ' . $location);
exit;
