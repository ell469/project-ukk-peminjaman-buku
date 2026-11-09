<?php
/*
==========================================================================
1. PERSIAPAN & KONEKSI
--------------------------------------------------------------------------
Menyiapkan pesan error, mengecek apakah user sudah login, 
serta menyambungkan ke database.
==========================================================================
*/
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/connection.php';

$pageTitle = 'Dashboard';
$pageDescription = 'Informasi dan pencarian koleksi ruang baca';


/*
==========================================================================
2. MENGHITUNG ANGKA STATISTIK UTAMA
--------------------------------------------------------------------------
Menghitung total buku, total anggota terdaftar, total kategori,
dan berapa buku yang saat ini sedang dipinjam.
==========================================================================
*/

function getCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

$totalBuku = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM buku
     WHERE status_aktif = 1"
);

$totalAnggota = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM anggota
     WHERE status_aktif = 1"
);

$totalKategori = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM kategori"
);

$totalDipinjam = getCount(
    $conn,
    "SELECT COALESCE(SUM(pd.jumlah), 0) AS total
     FROM peminjaman_detail pd
     INNER JOIN peminjaman p
        ON p.id = pd.peminjaman_id
     INNER JOIN buku b
        ON b.id = pd.buku_id
     WHERE p.status = 'dipinjam'
        AND b.status_aktif = 1"
);


/*
==========================================================================
3. MEMANGGIL HEADER TAMPILAN WEBSITE
==========================================================================
*/
require_once __DIR__ . '/../includes/layout_header.php';

?>

<!-- =====================================================
     TAMPILAN 1: SAMBUTAN DASHBOARD (Berlaku Semua User)
====================================================== -->

<section class="hero-panel">

    <div>

        <span class="page-label">
            SISTEM INFORMASI PERPUSTAKAAN
        </span>

        <h1>
            Halo, <?= e($_SESSION['nama'] ?? 'Pengguna') ?> 👋
        </h1>

        <p>
            Kelola koleksi buku dan cari buku yang kamu butuhkan
            melalui sistem ruang baca.
        </p>

    </div>

    <div class="hero-icon">
        📚
    </div>

</section>


<!-- =====================================================
     TAMPILAN 2: KARTU STATISTIK (Berlaku Semua User)
====================================================== -->

<section class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon blue">
            📚
        </div>

        <div>
            <span>Total Buku</span>
            <strong><?= $totalBuku ?></strong>
            <small>Koleksi aktif</small>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon green">
            👥
        </div>

        <div>
            <span>Total Anggota</span>
            <strong><?= $totalAnggota ?></strong>
            <small>Anggota terdaftar</small>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon orange">
            📖
        </div>

        <div>
            <span>Sedang Dipinjam</span>
            <strong><?= $totalDipinjam ?></strong>
            <small>Eksemplar dipinjam</small>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🗂️
        </div>

        <div>
            <span>Kategori</span>
            <strong><?= $totalKategori ?></strong>
            <small>Kategori buku</small>
        </div>

    </div>

</section>


<!-- =====================================================
     TAMPILAN 3: TOMBOL NAVIGASI CEPAT (Dibatasi per Level)
====================================================== -->

<section class="quick-grid">

    <div class="panel">

        <div class="form-title">

            <h3>Akses Cepat</h3>

            <p>
                Menu yang sering digunakan.
            </p>

        </div>

        <div class="quick-actions">

            <!-- --- KHUSUS ADMIN --- -->
            <?php if (($_SESSION['level'] ?? '') === 'admin'): ?>

                <a
                    class="quick-action"
                    href="<?= e(url('buku/tampil.php')) ?>"
                >
                    <span>📚</span>

                    <div>
                        <b>Kelola Buku</b>
                        <small>Tambah dan kelola koleksi</small>
                    </div>

                    <strong>›</strong>
                </a>


                <a
                    class="quick-action"
                    href="<?= e(url('anggota/anggota.php')) ?>"
                >
                    <span>👥</span>

                    <div>
                        <b>Data Anggota</b>
                        <small>Kelola anggota ruang baca</small>
                    </div>

                    <strong>›</strong>
                </a>

            <?php endif; ?>


            <!-- --- KHUSUS SISWA / USER --- -->
            <?php if (($_SESSION['level'] ?? '') !== 'admin'): ?>
                <a
                    class="quick-action"
                    href="<?= e(url('transaksi/peminjaman.php')) ?>"
                >
                    <span>📖</span>

                    <div>
                        <b>Peminjaman</b>
                        <small>Ajukan peminjaman buku</small>
                    </div>

                    <strong>›</strong>
                </a>
            <?php endif; ?>


            <!-- --- PERSEBARAN MENU SESUAI LEVEL --- -->
            <?php if (($_SESSION['level'] ?? '') === 'admin'): ?>

                <!-- Khusus Admin: Pengembalian & Laporan -->
                <a
                    class="quick-action"
                    href="<?= e(url('transaksi/pengembalian.php')) ?>"
                >
                    <span>↩️</span>

                    <div>
                        <b>Pengembalian</b>
                        <small>Kelola buku yang dikembalikan</small>
                    </div>

                    <strong>›</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= e(url('transaksi/laporan.php')) ?>"
                >
                    <span>🖨️</span>

                    <div>
                        <b>Laporan</b>
                        <small>Lihat dan cetak laporan</small>
                    </div>

                    <strong>›</strong>
                </a>

            <?php else: ?>

                <!-- Khusus Siswa / User: Riwayat Peminjaman -->
                <a
                    class="quick-action"
                    href="<?= e(url('transaksi/riwayat_buku.php')) ?>"
                >
                    <span>📋</span>

                    <div>
                        <b>Riwayat Buku</b>
                        <small>Lihat riwayat peminjaman</small>
                    </div>

                    <strong>›</strong>
                </a>

            <?php endif; ?>


            <!-- --- KHUSUS SISWA / USER --- -->
            <?php if (($_SESSION['level'] ?? '') !== 'admin'): ?>
                <a
                    class="quick-action"
                    href="<?= e(url('pencarian/cari_buku.php')) ?>"
                >
                    <span>🔎</span>

                    <div>
                        <b>Cari Buku</b>
                        <small>Cari koleksi ruang baca</small>
                    </div>

                    <strong>›</strong>
                </a>
            <?php endif; ?>

        </div>

    </div>


    <!-- Kotak Penjelasan Level/Role Akun -->
    <div class="panel role-panel">

        <span class="role-badge">
            <?= e(strtoupper($_SESSION['level'] ?? 'USER')) ?>
        </span>

        <h3>
            Akses Akun
        </h3>

        <?php if (($_SESSION['level'] ?? '') === 'admin'): ?>

            <p>
                Kamu login sebagai administrator.
                Kamu dapat mengelola buku, anggota, kategori,
                penerbit, user, peminjaman, dan pengembalian.
            </p>

        <?php else: ?>

            <p>
                Kamu login sebagai anggota/user.
                Kamu dapat mencari buku, melakukan peminjaman,
                dan melihat riwayat peminjaman sendiri.
            </p>

        <?php endif; ?>

    </div>

</section>


<!-- =====================================================
     4. MEMANGGIL FOOTER TAMPILAN WEBSITE
====================================================== -->
<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>