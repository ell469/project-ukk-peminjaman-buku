<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/connection.php';

$userId = (int) $_SESSION['user_id'];
$pageTitle = 'Bookmark Saya';
$pageDescription = 'Daftar buku yang kamu simpan.';

$stmt = $conn->prepare("SELECT b.id, b.judul, b.pengarang, b.gambar, b.tahun_terbit,
    k.nama_kategori,
    COALESCE((SELECT ROUND(AVG(r.rating),1) FROM rating_buku r WHERE r.buku_id=b.id),0) AS rating_rata,
    COALESCE((SELECT COUNT(*) FROM rating_buku r WHERE r.buku_id=b.id),0) AS jumlah_rating
    FROM bookmark bm
    JOIN buku b ON b.id=bm.buku_id
    LEFT JOIN kategori k ON k.id=b.kategori_id
    WHERE bm.user_id=? AND b.status_aktif=1
    ORDER BY bm.created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$books = $stmt->get_result();

require_once __DIR__ . '/../includes/layout_header.php';
?>
<section class="panel">
    <div class="page-intro">
        <div>
            <span class="page-label">KOLEKSI PRIBADI</span>
            <h1>🔖 Bookmark Saya</h1>
            <p>Buku yang kamu simpan untuk dibaca atau dipinjam nanti.</p>
        </div>
        <a class="btn" href="<?= e(url('dashboard/dashboard.php')) ?>">← Kembali ke Dashboard</a>
    </div>

    <?php if ($books->num_rows === 0): ?>
        <div class="empty-card">
            <div>🔖</div>
            <h3>Belum ada bookmark</h3>
            <p>Klik tombol Bookmark pada buku yang ingin kamu simpan.</p>
            <a class="btn primary" href="<?= e(url('dashboard/dashboard.php')) ?>">Cari Buku</a>
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php while ($book = $books->fetch_assoc()): ?>
                <?php $img = !empty($book['gambar']) ? url('assets/images/' . $book['gambar']) : url('assets/images/default.svg'); ?>
                <article class="book-card">
                    <div class="book-cover">
                        <img src="<?= e($img) ?>" alt="<?= e($book['judul']) ?>" onerror="this.src='<?= e(url('assets/images/default.svg')) ?>'">
                        <span class="availability available">★ <?= e($book['rating_rata']) ?></span>
                    </div>
                    <div class="book-content">
                        <span class="book-category"><?= e($book['nama_kategori'] ?? 'Umum') ?></span>
                        <h3><?= e($book['judul']) ?></h3>
                        <p class="book-author"><?= e($book['pengarang'] ?: '-') ?></p>
                        <p class="rating-summary">⭐ <?= e($book['rating_rata']) ?>/5 · <?= (int)$book['jumlah_rating'] ?> rating</p>
                        <div class="book-actions">
                            <a class="btn small" href="<?= e(url('buku/detail.php?id=' . (int)$book['id'])) ?>">Detail</a>
                            <a class="btn small primary" href="<?= e(url('transaksi/peminjaman.php?buku_id=' . (int)$book['id'])) ?>">📖 Pinjam</a>
                            <form method="post" action="<?= e(url('aksi/bookmark.php')) ?>">
                                <input type="hidden" name="buku_id" value="<?= (int)$book['id'] ?>">
                                <input type="hidden" name="return" value="bookmark/index.php">
                                <button class="btn small" type="submit">✓ Hapus</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
