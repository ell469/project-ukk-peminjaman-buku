<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';

[$flash, $old] = takeFlash();

$kategori = $conn->query(
    'SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori'
);

$penerbit = $conn->query(
    'SELECT id, nama_penerbit FROM penerbit ORDER BY nama_penerbit'
);

$pageTitle = 'Tambah Buku';
$pageDescription = 'Tambahkan koleksi baru ke ruang baca.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="panel form-panel">
    <div class="form-title">
        <span class="page-label">DATA BUKU</span>
        <h1>Tambah Buku</h1>
        <p>Lengkapi informasi buku di bawah ini.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e(url('buku/tambah.php')) ?>"
        method="post"
        enctype="multipart/form-data"
        class="form-grid"
    >
        <div>
            <label>Judul Buku</label>
            <input
                type="text"
                name="judul"
                value="<?= e($old['judul'] ?? '') ?>"
                placeholder="Masukkan judul buku"
                required
            >
        </div>

        <div>
            <label>Kategori</label>
            <select name="kategori_id" required>
                <option value="">Pilih kategori</option>

                <?php while ($row = $kategori->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $row['id'] ?>"
                        <?= (int) ($old['kategori_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>
                    >
                        <?= e($row['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Penerbit</label>
            <select name="penerbit_id" required>
                <option value="">Pilih penerbit</option>

                <?php while ($row = $penerbit->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $row['id'] ?>"
                        <?= (int) ($old['penerbit_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>
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
                value="<?= e($old['pengarang'] ?? '') ?>"
                placeholder="Nama pengarang"
            >
        </div>

        <div>
            <label>Jumlah Halaman</label>
            <input
                type="number"
                name="jumlah_halaman"
                min="1"
                value="<?= e($old['jumlah_halaman'] ?? '') ?>"
                placeholder="Contoh: 300"
            >
        </div>

        <div>
            <label>Jumlah Stok</label>
            <input
                type="number"
                name="jumlah_stok"
                min="0"
                value="<?= e($old['jumlah_stok'] ?? '1') ?>"
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
                value="<?= e($old['tahun_terbit'] ?? '') ?>"
                placeholder="2026"
            >
        </div>

        <div>
            <label>Gambar Cover</label>
            <input
                type="file"
                name="gambar"
                accept="image/jpeg,image/png,image/webp"
                data-cover-input
            >
            <small class="file-help">
                JPG, PNG, atau WEBP. Maksimal 5 MB. Boleh dikosongkan,
                nanti dipakai gambar default.
            </small>
            <small class="file-error" data-cover-msg></small>
            <img class="cover-preview" data-cover-preview alt="Preview cover" hidden>
        </div>

        <div class="full">
            <label>Sinopsis</label>
            <textarea
                name="sinopsis"
                rows="6"
                placeholder="Masukkan sinopsis buku"
            ><?= e($old['sinopsis'] ?? '') ?></textarea>
        </div>

        <div class="full form-actions">
            <button type="submit" class="btn primary">
                Simpan Buku
            </button>

            <a
                href="<?= e(url('buku/tampil.php')) ?>"
                class="btn"
            >
                Batal
            </a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
