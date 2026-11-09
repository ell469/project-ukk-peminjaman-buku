<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/upload_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$kembali = url('buku/edit.php') . '?id=' . $id;

if (requestMelebihiBatasPost()) {
    // $_POST kosong pada kondisi ini, jadi id tidak tersedia.
    $idRef = (int) ($_GET['id'] ?? 0);
    redirectWithFlash(
        $idRef > 0 ? url('buku/edit.php') . '?id=' . $idRef : url('buku/tampil.php'),
        'danger',
        pesanBatasPost()
    );
}

$judul = trim($_POST['judul'] ?? '');
$kategoriId = (int) ($_POST['kategori_id'] ?? 0);
$penerbitId = (int) ($_POST['penerbit_id'] ?? 0);
$pengarang = trim($_POST['pengarang'] ?? '');

$jumlahHalaman = ($_POST['jumlah_halaman'] ?? '') !== ''
    ? (int) $_POST['jumlah_halaman']
    : null;

$jumlahStok = (int) ($_POST['jumlah_stok'] ?? 0);

$tahunTerbit = ($_POST['tahun_terbit'] ?? '') !== ''
    ? (int) $_POST['tahun_terbit']
    : null;

$sinopsis = trim($_POST['sinopsis'] ?? '');

if ($id < 1 || $judul === '' || $kategoriId < 1 || $penerbitId < 1 || $jumlahStok < 0) {
    redirectWithFlash($kembali, 'danger', 'Data buku belum lengkap.', $_POST);
}

$oldStmt = $conn->prepare(
    'SELECT gambar
     FROM buku
     WHERE id = ? AND status_aktif = 1'
);

$oldStmt->bind_param('i', $id);
$oldStmt->execute();

$old = $oldStmt->get_result()->fetch_assoc();

if (!$old) {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

$oldGambar = $old['gambar'] ?? '';

$upload = uploadGambarBuku('gambar', false);

if (!$upload['success']) {
    redirectWithFlash($kembali, 'danger', $upload['error'], $_POST);
}

$gambar = $upload['filename'] ?: $oldGambar;

$sql = 'UPDATE buku SET
            judul = ?,
            kategori_id = ?,
            penerbit_id = ?,
            pengarang = ?,
            jumlah_halaman = ?,
            jumlah_stok = ?,
            tahun_terbit = ?,
            sinopsis = ?,
            gambar = ?
        WHERE id = ? AND status_aktif = 1';

try {
    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'siisiiissi',
        $judul,
        $kategoriId,
        $penerbitId,
        $pengarang,
        $jumlahHalaman,
        $jumlahStok,
        $tahunTerbit,
        $sinopsis,
        $gambar,
        $id
    );

    $stmt->execute();
} catch (mysqli_sql_exception $ex) {
    hapusFileGambar($upload['filename']);

    redirectWithFlash(
        $kembali,
        'danger',
        'Data gagal diubah: ' . $ex->getMessage(),
        $_POST
    );
}

// Cover lama dihapus hanya setelah data baru berhasil tersimpan.
if ($upload['filename'] && $oldGambar && $oldGambar !== $gambar) {
    hapusFileGambar($oldGambar);
}

redirectWithFlash(
    url('buku/tampil.php'),
    'success',
    'Buku "' . $judul . '" berhasil diperbarui.'
);
