<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();

require_once __DIR__ . '/../config/connection.php';

$cari = trim($_GET['q'] ?? '');
$kategoriId = (int) ($_GET['kategori'] ?? 0);

$kategoriQuery = $conn->query(
    "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC"
);

$sql = "SELECT
            b.id,
            b.judul,
            b.pengarang,
            b.jumlah_stok,
            b.gambar,
            k.nama_kategori,
            COALESCE(
                (
                    SELECT SUM(pd.jumlah)
                    FROM peminjaman_detail pd
                    JOIN peminjaman p
                        ON p.id = pd.peminjaman_id
                    WHERE pd.buku_id = b.id
                      AND p.status = 'dipinjam'
                ),
                0
            ) AS dipinjam
        FROM buku b
        LEFT JOIN kategori k
            ON k.id = b.kategori_id
        WHERE b.status_aktif = 1";

$params = [];
$types = '';

if ($cari !== '') {
    $sql .= " AND (
                b.judul LIKE ?
                OR b.pengarang LIKE ?
              )";

    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($kategoriId > 0) {
    $sql .= " AND b.kategori_id = ?";
    $params[] = $kategoriId;
    $types .= 'i';
}

$sql .= " ORDER BY b.judul";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$rows = $stmt->get_result();

$pageTitle = 'Cari Buku';
$pageDescription = 'Cari dan filter buku berdasarkan judul, pengarang, atau kategori.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="search-hero">
    <div>
        <span class="page-label">PENCARIAN</span>
        <h1>Temukan Buku</h1>
        <p>
            Cari buku berdasarkan judul atau pengarang, lalu gunakan filter kategori.
        </p>
    </div>

    <div class="search-big">
        <form method="get">
            <input
                type="search"
                name="q"
                value="<?= e($cari) ?>"
                placeholder="Cari judul atau pengarang..."
            >

            <select name="kategori" aria-label="Filter kategori buku">
                <option value="0">Semua Kategori</option>

                <?php while ($kategori = $kategoriQuery->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $kategori['id'] ?>"
                        <?= $kategoriId === (int) $kategori['id'] ? 'selected' : '' ?>
                    >
                        <?= e($kategori['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="btn primary">
                🔎 Cari
            </button>

            <?php if ($cari !== '' || $kategoriId > 0): ?>
                <a
                    href="<?= e(url('pencarian/cari_buku.php')) ?>"
                    class="btn"
                >
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="search-results">
    <div class="panel-head">
        <div>
            <h3>Hasil Pencarian</h3>

            <p class="muted">
                <?php if ($cari !== '' && $kategoriId > 0): ?>
                    Menampilkan buku dengan kata kunci “<?= e($cari) ?>”
                    pada kategori yang dipilih.
                <?php elseif ($cari !== ''): ?>
                    Menampilkan hasil untuk “<?= e($cari) ?>”.
                <?php elseif ($kategoriId > 0): ?>
                    Menampilkan buku berdasarkan kategori yang dipilih.
                <?php else: ?>
                    Menampilkan semua buku.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="book-grid compact-grid">
        <?php if ($rows->num_rows): ?>

            <?php while ($row = $rows->fetch_assoc()): ?>
                <?php
                $tersedia = max(
                    0,
                    (int) $row['jumlah_stok'] - (int) $row['dipinjam']
                );

                $gambar = !empty($row['gambar'])
                    ? url('assets/images/' . $row['gambar'])
                    : url('assets/images/default.svg');
                ?>

                <article class="book-card">
                    <div class="book-cover">
                        <img
                            src="<?= e($gambar) ?>"
                            alt="<?= e($row['judul']) ?>"
                            onerror="this.src='<?= e(url('assets/images/default.svg')) ?>'"
                        >

                        <span
                            class="availability <?= $tersedia > 0 ? 'available' : 'unavailable' ?>"
                        >
                            <?= $tersedia > 0 ? 'Tersedia' : 'Habis' ?>
                        </span>
                    </div>

                    <div class="book-content">
                        <span class="book-category">
                            <?= e($row['nama_kategori'] ?? 'Umum') ?>
                        </span>

                        <h3><?= e($row['judul']) ?></h3>

                        <p class="book-author">
                            ✍ <?= e($row['pengarang'] ?? '-') ?>
                        </p>

                        <div class="book-meta">
                            <span>📦 <?= $tersedia ?></span>
                        </div>

                        <a
                            class="btn primary full-btn"
                            href="<?= e(url('buku/detail.php')) ?>?id=<?= (int) $row['id'] ?>"
                        >
                            Lihat Detail
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty-card">
                <div>🔎</div>
                <h3>Buku tidak ditemukan</h3>
                <p>Coba gunakan kata kunci atau kategori lain.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
