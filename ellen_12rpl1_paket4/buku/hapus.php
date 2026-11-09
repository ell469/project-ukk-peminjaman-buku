<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . url('buku/tampil.php'));
    exit();
}

$id = (int)$_GET['id'];

$conn->begin_transaction();

$stmt = $conn->prepare("
    SELECT id
    FROM buku
    WHERE id = ?
      AND status_aktif = 1
    FOR UPDATE
");
$stmt->bind_param('i', $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    $conn->rollback();
    redirectWithFlash(
        url('buku/tampil.php'),
        'danger',
        'Buku tidak ditemukan atau sudah diarsipkan.'
    );
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS jumlah
    FROM peminjaman_detail pd
    JOIN peminjaman p
        ON p.id = pd.peminjaman_id
    WHERE pd.buku_id = ?
      AND p.status = 'dipinjam'
");
$stmt->bind_param('i', $id);
$stmt->execute();
$activeLoans = (int) $stmt->get_result()->fetch_assoc()['jumlah'];

if ($activeLoans > 0) {
    $conn->rollback();
    redirectWithFlash(
        url('buku/tampil.php'),
        'danger',
        'Buku tidak dapat dihapus karena masih dipinjam oleh siswa.'
    );
}

// Buku diarsipkan, bukan dihapus dari database, agar riwayat tetap tersimpan.
backupSebelumHapus($conn, 'buku', $id);
$stmt = $conn->prepare("
    UPDATE buku
    SET status_aktif = 0
    WHERE id = ?
      AND status_aktif = 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$conn->commit();

redirectWithFlash(
    url('buku/tampil.php'),
    'success',
    'Buku berhasil dihapus.'
);
?>