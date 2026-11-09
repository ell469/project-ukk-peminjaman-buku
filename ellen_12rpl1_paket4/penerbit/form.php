<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/connection.php';

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$data = [
    'kode_penerbit' => '',
    'nama_penerbit' => '',
];
$error = '';

if ($id) {
    $query = $conn->prepare('SELECT * FROM penerbit WHERE id = ?');
    $query->bind_param('i', $id);
    $query->execute();
    $data = $query->get_result()->fetch_assoc() ?: $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['kode_penerbit'] = trim($_POST['kode_penerbit'] ?? '');
    $data['nama_penerbit'] = trim($_POST['nama_penerbit'] ?? '');

    if ($data['kode_penerbit'] === '' || $data['nama_penerbit'] === '') {
        $error = 'Kode dan nama penerbit wajib diisi.';
    } else {
        try {
            if ($id) {
                $query = $conn->prepare('UPDATE penerbit SET kode_penerbit = ?, nama_penerbit = ? WHERE id = ?');
                $query->bind_param('ssi', $data['kode_penerbit'], $data['nama_penerbit'], $id);
            } else {
                $query = $conn->prepare('INSERT INTO penerbit (kode_penerbit, nama_penerbit) VALUES (?, ?)');
                $query->bind_param('ss', $data['kode_penerbit'], $data['nama_penerbit']);
            }

            $query->execute();
            header('Location: ' . url('penerbit/index.php'));
            exit;
        } catch (mysqli_sql_exception $ex) {
            // 1062 = duplicate entry (kode penerbit sudah dipakai)
            $error = (int) $ex->getCode() === 1062
                ? 'Kode penerbit "' . $data['kode_penerbit'] . '" sudah digunakan. Gunakan kode lain.'
                : 'Data penerbit gagal disimpan: ' . $ex->getMessage();
        }
    }
}

$pageTitle = $id ? 'Edit Penerbit' : 'Tambah Penerbit';
$pageDescription = $id ? 'Perbarui data penerbit.' : 'Tambahkan penerbit baru.';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="form-title">
        <span class="page-label">DATA MASTER</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Isi informasi penerbit dengan benar.</p>
    </div>

    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div>
            <label>Kode Penerbit</label>
            <input type="text" name="kode_penerbit" value="<?= e($data['kode_penerbit']) ?>" placeholder="Contoh: PB011" required>
        </div>
        <div>
            <label>Nama Penerbit</label>
            <input type="text" name="nama_penerbit" value="<?= e($data['nama_penerbit']) ?>" placeholder="Contoh: Penerbit Baru" required>
        </div>
        <div class="full form-actions">
            <button type="submit" class="btn primary">Simpan</button>
            <a href="<?= e(url('penerbit/index.php')) ?>" class="btn">Kembali</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
