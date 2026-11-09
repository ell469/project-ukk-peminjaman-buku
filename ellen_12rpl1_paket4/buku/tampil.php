<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../config/connection.php';

[$flash] = takeFlash();

// 1. Tangkap input pencarian dan filter kategori
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// 2. Susun kondisi WHERE secara dinamis
$whereClauses = ["buku.status_aktif = 1"];
$params = [];
$types = "";

if ($cari !== '') {
    $whereClauses[] = "(buku.judul LIKE ? OR buku.pengarang ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $types .= "ss";
}

if ($kategori_filter !== '') {
    $whereClauses[] = "buku.kategori_id = ?";
    $params[] = $kategori_filter;
    $types .= "i";
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

$sql = "SELECT 
            buku.*,
            kategori.nama_kategori,
            penerbit.nama_penerbit,
            COALESCE(
                SUM(
                    CASE
                        WHEN peminjaman.status = 'dipinjam'
                        THEN peminjaman_detail.jumlah
                        ELSE 0
                    END
                ),
                0
            ) AS jumlah_dipinjam
        FROM buku
        LEFT JOIN kategori
            ON buku.kategori_id = kategori.id
        LEFT JOIN penerbit
            ON buku.penerbit_id = penerbit.id
        LEFT JOIN peminjaman_detail
            ON buku.id = peminjaman_detail.buku_id
        LEFT JOIN peminjaman
            ON peminjaman_detail.peminjaman_id = peminjaman.id
        $whereSql
        GROUP BY buku.id
        ORDER BY buku.id DESC";

// Eksekusi query menggunakan Prepared Statement agar aman dari SQL Injection
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die('Query gagal: ' . e($conn->error));
}

$total = $result->num_rows;

// Ambil data kategori untuk dropdown filter
$kategoriResult = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");

$pageTitle = 'Data Buku';
$pageDescription = 'Kelola koleksi buku ruang baca.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($flash): ?>
    <div class="alert <?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<section class="page-intro">
    <div>
        <span class="page-label">KOLEKSI</span>
        <h1>Data Buku</h1>
        <p>
            Daftar koleksi dengan informasi utama dan ketersediaan stok.
        </p>
    </div>

    <a
        href="<?= e(url('buku/form_tambah.php')) ?>"
        class="btn primary"
    >
        ＋ Tambah Buku
    </a>
</section>

<!-- Form Pencarian dan Filter -->
<div class="search-filter-section" style="margin-bottom: 2rem;">
    <form action="" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <input 
                type="text" 
                name="cari" 
                class="form-control" 
                placeholder="Cari judul buku atau pengarang..." 
                value="<?= e($cari) ?>"
                style="width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 4px;"
            >
        </div>
        
        <div style="min-width: 200px;">
            <select 
                name="kategori" 
                class="form-control" 
                style="width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 4px;"
            >
                <option value="">Semua Kategori</option>
                <?php while ($kat = $kategoriResult->fetch_assoc()): ?>
                    <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>>
                        <?= e($kat['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn primary" style="padding: 0.6rem 1.2rem;">Filter / Cari</button>
            <?php if ($cari !== '' || $kategori_filter !== ''): ?>
                <a href="tampil.php" class="btn" style="padding: 0.6rem 1.2rem; background: #e0e0e0; text-decoration: none; color: #333; display: inline-block; border-radius: 4px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="summary-row">
    <div class="summary-card">
        <span>📚</span>

        <div>
            <small>Total buku ditemukan</small>
            <strong><?= $total ?></strong>
        </div>
    </div>
</div>

<section class="book-grid">
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php
        $stok = (int) $row['jumlah_stok'];
        $dipinjam = (int) $row['jumlah_dipinjam'];
        $tersedia = max(0, $stok - $dipinjam);

        $gambar = !empty($row['gambar'])
            ? url('assets/images/' . $row['gambar'])
            : url('assets/images/default.svg');

        $sinopsis = $row['sinopsis'] ?? 'Belum ada sinopsis.';

        if (strlen($sinopsis) > 120) {
            $sinopsis = substr($sinopsis, 0, 120) . '...';
        }
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

                <p class="book-description">
                    <?= e($sinopsis) ?>
                </p>

                <div class="book-meta">
                    <span>
                        📅 <?= e($row['tahun_terbit'] ?? '-') ?>
                    </span>

                    <span>
                        📦 <?= $tersedia ?>/<?= $stok ?>
                    </span>
                </div>

                <div class="book-actions">
                    <a
                        class="btn primary"
                        href="<?= e(url('buku/detail.php')) ?>?id=<?= (int) $row['id'] ?>"
                    >
                        Detail
                    </a>

                    <a
                        class="btn"
                        href="<?= e(url('buku/edit.php')) ?>?id=<?= (int) $row['id'] ?>"
                    >
                        Edit
                    </a>

                    <a
                        class="btn danger"
                        href="<?= e(url('buku/hapus.php')) ?>?id=<?= (int) $row['id'] ?>"
                        onclick="return confirm('Buku akan diarsipkan. Riwayat peminjaman tetap disimpan. Lanjutkan?')"
                    >
                        Hapus
                    </a>
                </div>
            </div>
        </article>
    <?php endwhile; ?>

    <?php if ($total === 0): ?>
        <div class="empty-card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
            <div>📚</div>
            <h3>Buku tidak ditemukan</h3>
            <p>Tidak ada koleksi buku yang cocok dengan kriteria pencarian Anda.</p>

            <a
                href="<?= e(url('buku/form_tambah.php')) ?>"
                class="btn primary"
                style="margin-top: 1rem; display: inline-block;"
            >
                Tambah Buku Baru
            </a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>