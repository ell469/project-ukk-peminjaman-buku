<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/connection.php';

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$data = [
    'kode_kategori' => '',
    'nama_kategori' => '',
];
$error = '';

if ($id) {
    $query = $conn->prepare('SELECT * FROM kategori WHERE id = ?');
    $query->bind_param('i', $id);
    $query->execute();
    $data = $query->get_result()->fetch_assoc() ?: $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['kode_kategori'] = trim($_POST['kode_kategori'] ?? '');
    $data['nama_kategori'] = trim($_POST['nama_kategori'] ?? '');

    if ($data['kode_kategori'] === '' || $data['nama_kategori'] === '') {
        $error = 'Kode dan nama kategori wajib diisi.';
    } else {
        try {
            if ($id) {
                $query = $conn->prepare('UPDATE kategori SET kode_kategori = ?, nama_kategori = ? WHERE id = ?');
                $query->bind_param('ssi', $data['kode_kategori'], $data['nama_kategori'], $id);
            } else {
                $query = $conn->prepare('INSERT INTO kategori (kode_kategori, nama_kategori) VALUES (?, ?)');
                $query->bind_param('ss', $data['kode_kategori'], $data['nama_kategori']);
            }

            $query->execute();
            header('Location: ' . url('kategori/index.php'));
            exit;
        } catch (mysqli_sql_exception $ex) {
            // 1062 = duplicate entry (kode kategori sudah dipakai)
            $error = (int) $ex->getCode() === 1062
                ? 'Kode kategori "' . $data['kode_kategori'] . '" sudah digunakan. Gunakan kode lain.'
                : 'Data kategori gagal disimpan: ' . $ex->getMessage();
        }
    }
}

$pageTitle = $id ? 'Edit Kategori' : 'Tambah Kategori';
$pageDescription = $id ? 'Perbarui data kategori.' : 'Tambahkan kategori baru.';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($error): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="form-title">
        <span class="page-label">DATA MASTER</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Isi informasi kategori dengan benar.</p>
    </div>

    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div>
            <label>Kode Kategori</label>
            <input type="text" name="kode_kategori" value="<?= e($data['kode_kategori']) ?>" placeholder="Contoh: KT011" required>
        </div>
        <div>
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" value="<?= e($data['nama_kategori']) ?>" placeholder="Contoh: Pemrograman" required>
        </div>
        <div class="full form-actions">
            <button type="submit" class="btn primary">Simpan</button>
            <a href="<?= e(url('kategori/index.php')) ?>" class="btn">Kembali</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
