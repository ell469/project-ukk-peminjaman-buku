<?php
require_once __DIR__ . '/app.php';

$host = "sql312.infinityfree.com";
$user = "if0_43387106";
$pass = "hIN7q1Pkly17";
$db   = "if0_43387106_perpus";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/**
 * Pengganti TRIGGER BEFORE DELETE (hosting tidak mengizinkan TRIGGER).
 * Simpan data lama ke tabel `delete` sebelum dihapus/diarsipkan.
 */
function backupSebelumHapus(mysqli $conn, string $table, int $id): void
{
    $allowed = ['buku', 'kategori', 'penerbit', 'anggota', 'user'];
    if (!in_array($table, $allowed, true)) {
        return;
    }

    $q = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
    $q->bind_param('i', $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if (!$row) {
        return;
    }

    unset($row['password']); // jangan simpan password di tabel backup
    $json = json_encode($row, JSON_UNESCAPED_UNICODE);
    $ins = $conn->prepare(
        "INSERT INTO `delete` (`table_name`, `original_id`, `data`, `deleted_at`)
         VALUES (?, ?, ?, NOW())"
    );
    $ins->bind_param('sis', $table, $id, $json);
    $ins->execute();
}
