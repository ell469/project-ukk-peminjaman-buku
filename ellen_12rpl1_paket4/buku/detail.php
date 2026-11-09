<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/connection.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id < 1) {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];

$sql = "SELECT
            buku.*,
            kategori.nama_kategori,
            penerbit.nama_penerbit,
            COALESCE((SELECT ROUND(AVG(r.rating),1) FROM rating_buku r WHERE r.buku_id = buku.id),0) AS rating_rata,
            COALESCE((SELECT COUNT(*) FROM rating_buku r WHERE r.buku_id = buku.id),0) AS jumlah_rating,
            COALESCE((SELECT rating FROM rating_buku r WHERE r.buku_id = buku.id AND r.user_id = $userId LIMIT 1),0) AS rating_saya,
            EXISTS(SELECT 1 FROM bookmark bm WHERE bm.buku_id = buku.id AND bm.user_id = $userId) AS is_bookmarked,
            COALESCE((SELECT SUM(pd.jumlah) FROM peminjaman_detail pd JOIN peminjaman pm ON pm.id = pd.peminjaman_id WHERE pd.buku_id = buku.id AND pm.status = 'dipinjam'),0) AS jumlah_dipinjam
        FROM buku
        LEFT JOIN kategori ON buku.kategori_id = kategori.id
        LEFT JOIN penerbit ON buku.penerbit_id = penerbit.id
        WHERE buku.id = ?
          AND buku.status_aktif = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();

$buku = $stmt->get_result()->fetch_assoc();

if (!$buku) {
    header('Location: ' . url('buku/tampil.php'));
    exit;
}

$stok = (int) $buku['jumlah_stok'];
$dipinjam = (int) $buku['jumlah_dipinjam'];
$tersedia = max(0, $stok - $dipinjam);

$gambar = !empty($buku['gambar'])
    ? url('assets/images/' . $buku['gambar'])
    : url('assets/images/default.svg');

$pageTitle = 'Detail Buku';
$pageDescription = 'Informasi lengkap koleksi buku.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="detail-page">
    <div class="detail-cover">
        <img
            src="<?= e($gambar) ?>"
            alt="<?= e($buku['judul']) ?>"
            onerror="this.src='<?= e(url('assets/images/default.svg')) ?>'"
        >
    </div>

    <div class="detail-body">
        <span class="book-category">
            <?= e($buku['nama_kategori'] ?? 'Umum') ?>
        </span>

        <h1><?= e($buku['judul']) ?></h1>

        <p class="detail-author">
            ✍ <?= e($buku['pengarang'] ?? '-') ?>
        </p>

        <div class="detail-synopsis">
            <h3>Sinopsis</h3>
            <p>
                <?= nl2br(e($buku['sinopsis'] ?? 'Belum ada sinopsis.')) ?>
            </p>
        </div>

        <div class="detail-info">

            <div>
                <small>Kategori</small>
                <b><?= e($buku['nama_kategori'] ?? '-') ?></b>
            </div>

            <div>
                <small>Penerbit</small>
                <b><?= e($buku['nama_penerbit'] ?? '-') ?></b>
            </div>

            <div>
                <small>Tahun Terbit</small>
                <b><?= e($buku['tahun_terbit'] ?? '-') ?></b>
            </div>

            <div>
                <small>Halaman</small>
                <b><?= e($buku['jumlah_halaman'] ?? '-') ?></b>
            </div>

            <div>
                <small>Total Stok</small>
                <b><?= $stok ?></b>
            </div>

            <div>
                <small>Tersedia</small>
                <b class="text-green"><?= $tersedia ?></b>
            </div>

            <div>
                <small>Sedang Dipinjam</small>
                <b class="text-red"><?= $dipinjam ?></b>
            </div>
        </div>

        <div class="book-social-panel">
            <div class="rating-box">
                <div class="rating-score">⭐ <?= e($buku['rating_rata']) ?><small>/5</small></div>
                <div>
                    <strong>Rating Buku</strong>
                    <p><?= (int) $buku['jumlah_rating'] ?> pengguna sudah memberi rating.</p>
                </div>
            </div>

            <form class="rating-form" method="post" action="<?= e(url('aksi/rating.php')) ?>">
                <input type="hidden" name="buku_id" value="<?= $id ?>">
                <span>Rating kamu:</span>
                <div class="star-picker" role="radiogroup" aria-label="Pilih rating">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <input type="radio" id="star<?= $star ?>" name="rating" value="<?= $star ?>" <?= (int)$buku['rating_saya'] === $star ? 'checked' : '' ?> required>
                        <label for="star<?= $star ?>" title="<?= $star ?> bintang">★</label>
                    <?php endfor; ?>
                </div>
                <button class="btn primary small" type="submit">Simpan Rating</button>
            </form>

            <?php if (($_SESSION['level'] ?? '') !== 'admin'): ?>
                <form method="post" action="<?= e(url('aksi/bookmark.php')) ?>">
                    <input type="hidden" name="buku_id" value="<?= $id ?>">
                    <input type="hidden" name="return" value="buku/detail.php">
                    <button type="submit" class="btn <?= !empty($buku['is_bookmarked']) ? 'bookmark-active' : '' ?>">
                        <?= !empty($buku['is_bookmarked']) ? '✓ Tersimpan di Bookmark' : '🔖 Tambahkan ke Bookmark' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <a
                href="<?= e(url('buku/tampil.php')) ?>"
                class="btn"
            >
                ← Kembali
            </a>

            <?php if ($_SESSION['level'] === 'admin'): ?>
                <a
                    href="<?= e(url('buku/edit.php')) ?>?id=<?= $id ?>"
                    class="btn primary"
                >
                    Edit Buku
                </a>

                <a
                    href="<?= e(url('buku/hapus.php')) ?>?id=<?= $id ?>"
                    class="btn danger"
                    onclick="return confirm('Yakin ingin menghapus buku ini?')"
                >
                    Hapus
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
