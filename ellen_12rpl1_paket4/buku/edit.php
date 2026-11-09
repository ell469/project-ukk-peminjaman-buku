<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';

[$flash, $old] = takeFlash();

$id = (int) ($_GET['id'] ?? 0);

if ($id < 1) {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

$stmt = $conn->prepare(
    'SELECT *
     FROM buku
     WHERE id = ? AND status_aktif = 1'
);

$stmt->bind_param('i', $id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

// Jika update gagal, isian terakhir pengguna dipertahankan.
foreach (
    ['judul', 'kategori_id', 'penerbit_id', 'pengarang',
     'jumlah_halaman', 'jumlah_stok', 'tahun_terbit', 'sinopsis'] as $kolom
) {
    if (array_key_exists($kolom, $old)) {
        $data[$kolom] = $old[$kolom];
    }
}

$kategori = $conn->query(
    'SELECT id, nama_kategori
     FROM kategori
     ORDER BY nama_kategori'
);

$penerbit = $conn->query(
    'SELECT id, nama_penerbit
     FROM penerbit
     ORDER BY nama_penerbit'
);

$pageTitle = 'Edit Buku';
$pageDescription = 'Perbarui informasi buku yang dipilih.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="panel form-panel">
    <div class="form-title">
        <span class="page-label">DATA BUKU</span>
        <h1>Edit Buku</h1>
        <p>Perbarui informasi buku.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e(url('buku/update.php')) ?>?id=<?= $id ?>"
        method="post"
        enctype="multipart/form-data"
        class="form-grid"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >

        <div>
            <label>Judul Buku</label>
            <input
                type="text"
                name="judul"
                value="<?= e($data['judul']) ?>"
                required
            >
        </div>

        <div>
            <label>Kategori</label>
            <select name="kategori_id" required>
                <?php while ($row = $kategori->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $row['id'] ?>"
                        <?= $row['id'] == $data['kategori_id'] ? 'selected' : '' ?>
                    >
                        <?= e($row['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Penerbit</label>
            <select name="penerbit_id" required>
                <?php while ($row = $penerbit->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $row['id'] ?>"
                        <?= $row['id'] == $data['penerbit_id'] ? 'selected' : '' ?>
                    >
                        <?= e($row['nama_penerbit']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Pengarang</label>
            <input
                type="text"
                name="pengarang"
                value="<?= e($data['pengarang'] ?? '') ?>"
            >
        </div>

        <div>
            <label>Jumlah Halaman</label>
            <input
                type="number"
                name="jumlah_halaman"
                min="1"
                value="<?= e($data['jumlah_halaman'] ?? '') ?>"
            >
        </div>

        <div>
            <label>Jumlah Stok</label>
            <input
                type="number"
                name="jumlah_stok"
                min="0"
                value="<?= e($data['jumlah_stok']) ?>"
                required
            >
        </div>

        <div>
            <label>Tahun Terbit</label>
            <input
                type="number"
                name="tahun_terbit"
                min="1900"
                max="2100"
                value="<?= e($data['tahun_terbit'] ?? '') ?>"
            >
        </div>

        <div>
            <label>Gambar Cover</label>

            <?php if (!empty($data['gambar'])): ?>
                <div class="current-image">
                    <img
                        src="<?= e(url('assets/images/' . $data['gambar'])) ?>"
                        alt="Gambar buku"
                        onerror="this.src='<?= e(url('assets/images/default.svg')) ?>'"
                    >
                </div>
            <?php endif; ?>

            <input
                type="file"
                name="gambar"
                accept="image/jpeg,image/png,image/webp"
                data-cover-input
            >

            <small class="file-help">
                Kosongkan jika ingin mempertahankan gambar lama.
                Maksimal 5 MB.
            </small>
            <small class="file-error" data-cover-msg></small>
            <img class="cover-preview" data-cover-preview alt="Preview cover baru" hidden>
        </div>

        <div class="full">
            <label>Sinopsis</label>
            <textarea
                name="sinopsis"
                rows="6"
            ><?= e($data['sinopsis'] ?? '') ?></textarea>
        </div>

        <div class="full form-actions">
            <button type="submit" class="btn primary">
                Simpan Perubahan
            </button>

            <a
                href="<?= e(url('buku/detail.php')) ?>?id=<?= $id ?>"
                class="btn"
            >
                Batal
            </a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
