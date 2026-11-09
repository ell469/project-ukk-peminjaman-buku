<?php
// Menu navigasi utama (ditampilkan di bagian atas halaman).
$currentPage   = basename($_SERVER['PHP_SELF']);
$currentFolder = basename(dirname($_SERVER['SCRIPT_NAME']));
$level   = $_SESSION['level'] ?? '';
$isAdmin = $level === 'admin';

$inFolder = static function (string $folder) use ($currentFolder): bool {
    return $currentFolder === $folder;
};

$nav = [];

/*
==========================================================================
1. MENU PERTAMA: BERANDA (Umum untuk Admin & Siswa)
==========================================================================
*/
$nav[] = [
    'label'  => 'Beranda',
    'href'   => 'dashboard/dashboard.php',
    'active' => $currentPage === 'dashboard.php',
];


/*
==========================================================================
2. MENU KEDUA KHUSUS SISWA: EKSPLORASI & DISIMPAN
==========================================================================
*/
if (!$isAdmin) {
    // Menu Eksplorasi / Cari Buku
    $nav[] = [
        'label'  => 'Eksplorasi',
        'href'   => 'pencarian/cari_buku.php',
        'active' => $currentPage === 'cari_buku.php',
    ];

    // Menu Disimpan / Bookmark Buku
    $nav[] = [
        'label'  => 'Disimpan',
        'href'   => 'bookmark/index.php',
        'active' => $inFolder('bookmark'),
    ];
}


/*
==========================================================================
3. MENU KHUSUS ADMIN (Hanya muncul jika yang login adalah Admin)
==========================================================================
*/
if ($isAdmin) {
    // Menu Dropdown Koleksi (Buku, Kategori, Penerbit)
    $nav[] = [
        'label'    => 'Koleksi',
        'active'   => $inFolder('buku') || $inFolder('kategori') || $inFolder('penerbit'),
        'children' => [
            ['label' => 'Koleksi Buku', 'href' => 'buku/tampil.php',      'active' => $inFolder('buku')],
            ['label' => 'Kategori',     'href' => 'kategori/index.php',   'active' => $inFolder('kategori')],
            ['label' => 'Penerbit',     'href' => 'penerbit/index.php',   'active' => $inFolder('penerbit')],
        ],
    ];
    
    // Menu Dropdown Pengguna (Anggota & User)
    $nav[] = [
        'label'    => 'Data & akun',
        'active'   => $inFolder('anggota') || $inFolder('user'),
        'children' => [
            ['label' => 'Data pengguna',  'href' => 'anggota/anggota.php', 'active' => $inFolder('anggota') && $currentPage !== 'sampah.php'],
            ['label' => 'Akun Pengguna', 'href' => 'user/index.php',      'active' => $inFolder('user')],
            ['label' => 'Tempat Sampah', 'href' => 'anggota/sampah.php',  'active' => $currentPage === 'sampah.php'],
        ],
    ];
}


/*
==========================================================================
4. TRANSAKSI (Isinya otomatis beda untuk Admin & Siswa)
==========================================================================
*/
$transaksi = [];
if ($isAdmin) {
    // ---> Isi Transaksi Khusus ADMIN <---
    $transaksi[] = ['label' => 'Kelola Transaksi', 'href' => 'transaksi/pengembalian.php', 'active' => $currentPage === 'pengembalian.php'];
    $transaksi[] = ['label' => 'Laporan',           'href' => 'transaksi/laporan.php',      'active' => $currentPage === 'laporan.php'];
} else {
    // ---> Isi Transaksi Khusus SISWA <---
    $transaksi[] = ['label' => 'Ajukan Pinjam', 'href' => 'transaksi/peminjaman.php',   'active' => $currentPage === 'peminjaman.php'];
    $transaksi[] = ['label' => 'Riwayat',       'href' => 'transaksi/riwayat_buku.php', 'active' => $currentPage === 'riwayat_buku.php'];
}

$nav[] = [
    'label'    => 'Transaksi',
    'active'   => $inFolder('transaksi'),
    'children' => $transaksi,
];
?>


<!-- ==========================================================
     STRUKTUR HTML TAMPILAN NAVIGASI DI LAYAR
=========================================================== -->
<nav class="main-nav" id="mainNav" aria-label="Menu utama">
    <ul class="nav-list">
        <?php foreach ($nav as $i => $item): ?>
            <?php $hasChildren = !empty($item['children']); ?>
            <li class="nav-item <?= $hasChildren ? 'has-dropdown' : '' ?> <?= !empty($item['active']) ? 'active' : '' ?>">
                <?php if ($hasChildren): ?>
                    <button type="button" class="nav-link nav-toggle" aria-expanded="false" aria-controls="dd<?= $i ?>">
                        <?= e($item['label']) ?><span class="caret" aria-hidden="true"></span>
                    </button>
                    <ul class="dropdown" id="dd<?= $i ?>">
                        <?php foreach ($item['children'] as $child): ?>
                            <li>
                                <a class="<?= !empty($child['active']) ? 'active' : '' ?>" href="<?= e(url($child['href'])) ?>"><?= e($child['label']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Bagian Informasi Akun & Tombol Keluar (Logout) di Bawah Sidebar/Header -->
    <div class="nav-account">
        <div class="user-pill">
            <span class="user-dot"></span>
            <span class="user-name"><?= e($_SESSION['nama'] ?? '') ?></span>
            <span class="user-level"><?= e(ucfirst($level)) ?></span>
        </div>
        <a href="<?= e(url('auth/logout.php')) ?>" class="logout-btn">Keluar</a>
    </div>
</nav>