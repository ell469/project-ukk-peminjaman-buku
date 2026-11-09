<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/upload_helper.php';

$kembali = url('buku/form_tambah.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $kembali);
    exit;
}

if (requestMelebihiBatasPost()) {
    redirectWithFlash($kembali, 'danger', pesanBatasPost());
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

if ($judul === '' || $kategoriId < 1 || $penerbitId < 1 || $jumlahStok < 0) {
    redirectWithFlash($kembali, 'danger', 'Data buku belum lengkap.', $_POST);
}

// Cover opsional: bila kosong, tampilan memakai gambar default.
$upload = uploadGambarBuku('gambar', false);

if (!$upload['success']) {
    redirectWithFlash($kembali, 'danger', $upload['error'], $_POST);
}

$gambar = $upload['filename'];

$sql = 'INSERT INTO buku (
            judul,
            kategori_id,
            penerbit_id,
            pengarang,
            jumlah_halaman,
            jumlah_stok,
            tahun_terbit,
            sinopsis,
            gambar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

try {
    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'siisiiiss',
        $judul,
        $kategoriId,
        $penerbitId,
        $pengarang,
        $jumlahHalaman,
        $jumlahStok,
        $tahunTerbit,
        $sinopsis,
        $gambar
    );

    $stmt->execute();
} catch (mysqli_sql_exception $ex) {
    // Data gagal disimpan, jadi file cover yang sudah terunggah dibuang.
    hapusFileGambar($gambar);

    redirectWithFlash(
        $kembali,
        'danger',
        'Data gagal ditambahkan: ' . $ex->getMessage(),
        $_POST
    );
}

redirectWithFlash(
    url('buku/tampil.php'),
    'success',
    'Buku "' . $judul . '" berhasil ditambahkan.'
);
