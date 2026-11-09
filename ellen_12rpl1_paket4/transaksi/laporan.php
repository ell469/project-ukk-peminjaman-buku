<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/connection.php';

// Filter dari form (GET) -- semuanya opsional
$dari   = trim($_GET['dari'] ?? '');
$sampai = trim($_GET['sampai'] ?? '');
$status = trim($_GET['status'] ?? '');

$statusList = [
    'diproses'           => 'Menunggu Persetujuan',
    'dipinjam'           => 'Sedang dipinjam',
    'sudah dikembalikan' => 'Sudah dikembalikan',
    'ditolak'            => 'Ditolak',
];

// Tanggal yang formatnya ngaco diabaikan aja
$tglValid = static fn(string $t): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false;
if (!$tglValid($dari))   { $dari = ''; }
if (!$tglValid($sampai)) { $sampai = ''; }
if (!isset($statusList[$status])) { $status = ''; }

$where  = [];
$types  = '';
$params = [];

if ($dari !== '')   { $where[] = 'p.tanggal_pinjam >= ?'; $types .= 's'; $params[] = $dari; }
if ($sampai !== '') { $where[] = 'p.tanggal_pinjam <= ?'; $types .= 's'; $params[] = $sampai; }
if ($status !== '') { $where[] = 'p.status = ?';           $types .= 's'; $params[] = $status; }

$sql = "SELECT
            p.id,
            p.tanggal_pinjam,
            p.lama_pinjam,
            p.status,
            a.kode_anggota,
            a.nama AS anggota,
            GROUP_CONCAT(CONCAT(b.judul, ' (', pd.jumlah, ')') SEPARATOR ', ') AS buku,
            pr.tanggal_kembali,
            pr.keterlambatan_hari,
            pr.denda
        FROM peminjaman p
        JOIN anggota a ON a.id = p.anggota_id
        JOIN peminjaman_detail pd ON pd.peminjaman_id = p.id
        JOIN buku b ON b.id = pd.buku_id
        LEFT JOIN pengembalian pr ON pr.peminjaman_id = p.id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " GROUP BY p.id, p.tanggal_pinjam, p.lama_pinjam, p.status, a.kode_anggota, a.nama,
                  pr.tanggal_kembali, pr.keterlambatan_hari, pr.denda
        ORDER BY p.tanggal_pinjam DESC, p.id DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Rekap singkat buat kotak ringkasan di atas tabel
$rekap = ['total' => count($data), 'dipinjam' => 0, 'kembali' => 0, 'denda' => 0];
foreach ($data as $d) {
    if ($d['status'] === 'dipinjam')           { $rekap['dipinjam']++; }
    if ($d['status'] === 'sudah dikembalikan') { $rekap['kembali']++; }
    $rekap['denda'] += (int) ($d['denda'] ?? 0);
}

$rp = static fn(int $n): string => 'Rp ' . number_format($n, 0, ',', '.');

// "5 Okt 2026" -- biar enak dibaca di kertas
$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tgl = static function (?string $t) use ($bulan): string {
    if (!$t) { return '-'; }
    $ts = strtotime($t);
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};

$periode = 'Semua tanggal';
if ($dari !== '' && $sampai !== '') { $periode = $tgl($dari) . ' – ' . $tgl($sampai); }
elseif ($dari !== '')               { $periode = 'Mulai ' . $tgl($dari); }
elseif ($sampai !== '')             { $periode = 'Sampai ' . $tgl($sampai); }

$pageTitle = 'Laporan';
$pageDescription = 'Rekap peminjaman dan pengembalian. Bisa difilter lalu dicetak.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="panel no-print">
    <form method="get" class="form-grid filter-laporan">
        <div>
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="<?= e($dari) ?>">
        </div>
        <div>
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua status</option>
                <?php foreach ($statusList as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $status === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary">Tampilkan</button>
            <a href="<?= e(url('transaksi/laporan.php')) ?>" class="btn">Reset</a>
            <button type="button" class="btn" onclick="window.print()">🖨 Cetak</button>
        </div>
    </form>
</section>

<section class="panel laporan">
    <!-- Kop ini cuma muncul pas dicetak -->
    <div class="kop print-only">
        <img src="<?= e(url('assets/images/logo.png')) ?>" alt="" class="kop-logo">
        <div>
            <h2>Laporan Peminjaman Buku</h2>
            <p>RuangBaca · Perpustakaan Sekolah</p>
        </div>
    </div>

    <div class="laporan-info">
        <span>Periode: <strong><?= e($periode) ?></strong></span>
        <span>Status: <strong><?= e($status !== '' ? $statusList[$status] : 'Semua') ?></strong></span>
        <span>Dicetak: <strong><?= e($tgl(date('Y-m-d')) . ', ' . date('H:i')) ?></strong></span>
    </div>

    <div class="rekap">
        <div><small>Transaksi</small><strong><?= $rekap['total'] ?></strong></div>
        <div><small>Sedang dipinjam</small><strong><?= $rekap['dipinjam'] ?></strong></div>
        <div><small>Sudah kembali</small><strong><?= $rekap['kembali'] ?></strong></div>
        <div><small>Total denda</small><strong><?= e($rp($rekap['denda'])) ?></strong></div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Anggota</th>
                    <th>Buku</th>
                    <th>Tgl Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Tgl Kembali</th>
                    <th>Status</th>
                    <th>Denda</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($data): ?>
                    <?php foreach ($data as $i => $r): ?>
                        <?php
                        $tempo = date('Y-m-d', strtotime($r['tanggal_pinjam'] . ' +' . (int) $r['lama_pinjam'] . ' days'));
                        $badge = ['diproses' => 'yellow', 'dipinjam' => 'red', 'ditolak' => 'gray'][$r['status']] ?? 'green';
                        ?>
                        <tr>
                            <td data-label="No"><?= $i + 1 ?></td>
                            <td data-label="Anggota"><?= e($r['kode_anggota']) ?> - <?= e($r['anggota']) ?></td>
                            <td data-label="Buku"><?= e($r['buku']) ?></td>
                            <td data-label="Tgl Pinjam"><?= e($tgl($r['tanggal_pinjam'])) ?></td>
                            <td data-label="Jatuh Tempo"><?= e($tgl($tempo)) ?></td>
                            <td data-label="Tgl Kembali"><?= e($tgl($r['tanggal_kembali'])) ?></td>
                            <td data-label="Status"><span class="badge <?= $badge ?>"><?= e($statusList[$r['status']] ?? $r['status']) ?></span></td>
                            <td data-label="Denda"><?= $r['denda'] !== null ? e($rp((int) $r['denda'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty">Nggak ada data untuk filter ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tanda tangan, cuma muncul pas dicetak -->
    <div class="ttd print-only">
        <div>
            <p>Mengetahui,</p>
            <p class="ttd-jabatan">Kepala Perpustakaan</p>
            <span></span>
        </div>
        <div>
            <p><?= e($tgl(date('Y-m-d'))) ?></p>
            <p class="ttd-jabatan">Petugas</p>
            <span><?= e($_SESSION['nama'] ?? '') ?></span>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
