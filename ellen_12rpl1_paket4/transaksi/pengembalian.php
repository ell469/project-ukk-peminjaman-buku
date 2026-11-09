<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireAdmin();

require_once __DIR__ . '/../config/connection.php';

$msg = '';
$err = '';

// Tiga aksi admin: approve (ACC), reject (tolak), return (buku balik)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $peminjamanId = (int) ($_POST['peminjaman_id'] ?? 0);

    if ($peminjamanId < 1) {
        $err = 'Transaksi tidak valid.';
    } elseif (!in_array($action, ['approve', 'reject', 'return'], true)) {
        $err = 'Aksi tidak valid.';
    } else {
        $conn->begin_transaction();

        try {
            if ($action === 'approve') {
                // Ambil permintaan yang masih nunggu ACC.
                // Stok dicek lagi di sini, takut udah keburu abis.
                $stmt = $conn->prepare(
                    "SELECT
                        p.id,
                        p.lama_pinjam,
                        p.status,
                        COALESCE(SUM(pd.jumlah), 0) AS jumlah_buku
                     FROM peminjaman p
                     JOIN peminjaman_detail pd
                        ON pd.peminjaman_id = p.id
                     WHERE p.id = ?
                       AND p.status = 'diproses'
                     GROUP BY p.id
                     FOR UPDATE"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();
                $peminjaman = $stmt->get_result()->fetch_assoc();

                if (!$peminjaman) {
                    throw new Exception('Permintaan tidak ditemukan atau sudah diproses.');
                }

                $stmt = $conn->prepare(
                    "SELECT
                        pd.buku_id,
                        pd.jumlah,
                        b.judul,
                        b.status_aktif,
                        b.jumlah_stok,
                        COALESCE((
                            SELECT SUM(pd2.jumlah)
                            FROM peminjaman_detail pd2
                            JOIN peminjaman p2
                                ON p2.id = pd2.peminjaman_id
                            WHERE pd2.buku_id = pd.buku_id
                              AND p2.status = 'dipinjam'
                        ), 0) AS sedang_dipinjam
                     FROM peminjaman_detail pd
                     JOIN buku b
                        ON b.id = pd.buku_id
                     WHERE pd.peminjaman_id = ?
                     FOR UPDATE"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();
                $details = $stmt->get_result();

                while ($detail = $details->fetch_assoc()) {
                    if ((int) $detail['status_aktif'] !== 1) {
                        throw new Exception(
                            'Buku "' . $detail['judul'] . '" sudah diarsipkan dan tidak bisa disetujui.'
                        );
                    }

                    $tersedia = (int) $detail['jumlah_stok'] - (int) $detail['sedang_dipinjam'];
                    if ($tersedia < (int) $detail['jumlah']) {
                        throw new Exception(
                            'Stok buku "' . $detail['judul'] . '" tidak mencukupi untuk persetujuan.'
                        );
                    }
                }

                $stmt = $conn->prepare(
                    "UPDATE peminjaman
                     SET status = 'dipinjam'
                     WHERE id = ?
                       AND status = 'diproses'"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();

                if ($stmt->affected_rows < 1) {
                    throw new Exception('Permintaan gagal disetujui.');
                }

                $conn->commit();
                $msg = 'Permintaan peminjaman berhasil di-setujui. Buku sekarang berstatus sedang dipinjam.';
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare(
                    "UPDATE peminjaman
                     SET status = 'ditolak'
                     WHERE id = ?
                       AND status = 'diproses'"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();

                if ($stmt->affected_rows < 1) {
                    throw new Exception('Permintaan tidak ditemukan atau sudah diproses.');
                }

                $conn->commit();
                $msg = 'Permintaan peminjaman ditolak.';
            } else {
                // Balikin buku cuma bisa kalau udah di-ACC (status dipinjam)
                $stmt = $conn->prepare(
                    "SELECT
                        p.id,
                        p.tanggal_pinjam,
                        p.lama_pinjam,
                        COALESCE(SUM(pd.jumlah), 0) AS jumlah_buku
                     FROM peminjaman p
                     JOIN peminjaman_detail pd
                        ON pd.peminjaman_id = p.id
                     WHERE p.id = ?
                       AND p.status = 'dipinjam'
                     GROUP BY p.id
                     FOR UPDATE"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();
                $peminjaman = $stmt->get_result()->fetch_assoc();

                if (!$peminjaman) {
                    throw new Exception('Transaksi tidak ditemukan atau belum berstatus sedang dipinjam.');
                }

                $tanggalPinjam = new DateTime($peminjaman['tanggal_pinjam']);
                $tanggalKembali = new DateTime(date('Y-m-d'));
                $tanggalJatuhTempo = clone $tanggalPinjam;
                $tanggalJatuhTempo->modify('+' . (int) $peminjaman['lama_pinjam'] . ' days');

                // Telat = lewat dari tanggal jatuh tempo, denda per buku per hari
                $terlambat = 0;
                if ($tanggalKembali > $tanggalJatuhTempo) {
                    $terlambat = (int) $tanggalJatuhTempo->diff($tanggalKembali)->days;
                }

                $jumlahBuku = (int) $peminjaman['jumlah_buku'];
                $denda = $jumlahBuku * $terlambat * DENDA_PER_BUKU_PER_HARI;
                $userId = (int) $_SESSION['user_id'];
                $tanggalKembaliSql = $tanggalKembali->format('Y-m-d');

                $stmt = $conn->prepare(
                    'INSERT INTO pengembalian (
                        peminjaman_id,
                        tanggal_kembali,
                        keterlambatan_hari,
                        denda,
                        user_id
                    ) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'isiii',
                    $peminjamanId,
                    $tanggalKembaliSql,
                    $terlambat,
                    $denda,
                    $userId
                );
                $stmt->execute();

                $stmt = $conn->prepare(
                    "UPDATE peminjaman
                     SET status = 'sudah dikembalikan'
                     WHERE id = ?
                       AND status = 'dipinjam'"
                );
                $stmt->bind_param('i', $peminjamanId);
                $stmt->execute();

                $conn->commit();
                $msg = $denda > 0
                    ? 'Buku berhasil dikembalikan. Denda: Rp ' . number_format($denda, 0, ',', '.')
                    : 'Buku berhasil dikembalikan tanpa denda.';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}

// Antrean permintaan yang belum di-ACC
$pending = $conn->query(
    "SELECT
        p.id,
        p.tanggal_pinjam,
        p.lama_pinjam,
        a.nama AS anggota,
        a.kode_anggota,
        GROUP_CONCAT(
            CONCAT(b.judul, ' (', pd.jumlah, ')')
            SEPARATOR ', '
        ) AS buku
     FROM peminjaman p
     JOIN anggota a ON a.id = p.anggota_id
     JOIN peminjaman_detail pd ON pd.peminjaman_id = p.id
     JOIN buku b ON b.id = pd.buku_id
     WHERE p.status = 'diproses'
     GROUP BY p.id, p.tanggal_pinjam, p.lama_pinjam, a.nama, a.kode_anggota
     ORDER BY p.tanggal_pinjam ASC, p.id ASC"
);

// Buku yang lagi dipinjam
$rows = $conn->query(
    "SELECT
        p.id,
        p.tanggal_pinjam,
        p.lama_pinjam,
        a.nama AS anggota,
        COALESCE(SUM(pd.jumlah), 0) AS jumlah_buku,
        GROUP_CONCAT(
            CONCAT(b.judul, ' (', pd.jumlah, ')')
            SEPARATOR ', '
        ) AS buku
     FROM peminjaman p
     JOIN anggota a ON a.id = p.anggota_id
     JOIN peminjaman_detail pd ON pd.peminjaman_id = p.id
     JOIN buku b ON b.id = pd.buku_id
     WHERE p.status = 'dipinjam'
     GROUP BY p.id, p.tanggal_pinjam, p.lama_pinjam, a.nama
     ORDER BY p.tanggal_pinjam DESC, p.id DESC"
);

// Yang udah selesai atau ditolak
$riwayat = $conn->query(
    "SELECT
        p.id,
        a.nama AS anggota,
        p.tanggal_pinjam,
        p.lama_pinjam,
        p.status,
        pr.tanggal_kembali,
        pr.keterlambatan_hari,
        pr.denda
     FROM peminjaman p
     JOIN anggota a ON a.id = p.anggota_id
     LEFT JOIN pengembalian pr ON pr.peminjaman_id = p.id
     WHERE p.status IN ('sudah dikembalikan', 'ditolak')
     ORDER BY p.id DESC"
);

$pageTitle = 'Pengembalian';
$pageDescription = 'Setujui peminjaman, kelola buku yang sedang dipinjam, dan proses pengembalian.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($msg): ?>
    <div class="alert success">✓ <?= e($msg) ?></div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert danger">! <?= e($err) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Permintaan Peminjaman — Menunggu Persetujuan</h3>
            <p class="muted">User mengajukan peminjaman terlebih dahulu. Admin harus menekan Persetujuan sebelum buku dianggap sedang dipinjam.</p>
        </div>
        <span class="badge yellow">Menunggu persetujuan</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Anggota</th>
                    <th>Buku</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Lama</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pending->num_rows): ?>
                    <?php while ($row = $pending->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int) $row['id'] ?></td>
                            <td>
                                <?= e($row['kode_anggota']) ?> - <?= e($row['anggota']) ?>
                            </td>
                            <td><?= e($row['buku']) ?></td>
                            <td><?= e($row['tanggal_pinjam']) ?></td>
                            <td><?= (int) $row['lama_pinjam'] ?> hari</td>
                            <td>
                                <div class="form-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="peminjaman_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn small primary" onclick="return confirm('Persetujuan permintaan peminjaman ini?')">
                                            ✓ Setujui Peminjaman
                                        </button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="peminjaman_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn small danger" onclick="return confirm('Tolak permintaan peminjaman ini?')">
                                            Tolak
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty">✓ Tidak ada permintaan peminjaman yang menunggu persetujuan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Daftar Buku Sedang Dipinjam</h3>
            <p class="muted">Hanya peminjaman yang sudah di-setujui yang muncul di daftar ini.</p>
        </div>
        <span class="badge red">Aktif</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Anggota</th>
                    <th>Buku</th>
                    <th>Tanggal Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Estimasi Denda</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows->num_rows): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <?php
                        $tanggalPinjam = new DateTime($row['tanggal_pinjam']);
                        $jatuhTempo = clone $tanggalPinjam;
                        $jatuhTempo->modify('+' . (int) $row['lama_pinjam'] . ' days');
                        $hariIni = new DateTime(date('Y-m-d'));
                        $hariTerlambat = $hariIni > $jatuhTempo
                            ? (int) $jatuhTempo->diff($hariIni)->days
                            : 0;
                        $estimasiDenda = (int) $row['jumlah_buku'] * $hariTerlambat * DENDA_PER_BUKU_PER_HARI;
                        ?>
                        <tr>
                            <td>#<?= (int) $row['id'] ?></td>
                            <td><?= e($row['anggota']) ?></td>
                            <td><?= e($row['buku']) ?></td>
                            <td><?= e($row['tanggal_pinjam']) ?></td>
                            <td><?= e($jatuhTempo->format('Y-m-d')) ?></td>
                            <td>
                                <?php if ($estimasiDenda > 0): ?>
                                    <span class="badge red">Rp <?= number_format($estimasiDenda, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="badge green">Rp 0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="action" value="return">
                                    <input type="hidden" name="peminjaman_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn small primary" onclick="return confirm('Kembalikan buku pada transaksi ini?')">
                                        Kembalikan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">✓ Tidak ada buku yang sedang dipinjam.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Riwayat Selesai / Ditolak</h3>
            <p class="muted">Riwayat pengembalian dan permintaan yang ditolak.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Anggota</th>
                    <th>Tanggal Pinjam</th>
                    <th>Status</th>
                    <th>Tanggal Kembali</th>
                    <th>Terlambat</th>
                    <th>Denda</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($riwayat->num_rows): ?>
                    <?php while ($row = $riwayat->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int) $row['id'] ?></td>
                            <td><?= e($row['anggota']) ?></td>
                            <td><?= e($row['tanggal_pinjam']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'ditolak'): ?>
                                    <span class="badge gray">Ditolak</span>
                                <?php else: ?>
                                    <span class="badge green">Sudah Dikembalikan</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['tanggal_kembali'] ? e($row['tanggal_kembali']) : '-' ?></td>
                            <td><?= $row['keterlambatan_hari'] !== null ? (int) $row['keterlambatan_hari'] . ' hari' : '-' ?></td>
                            <td><?= $row['denda'] !== null ? 'Rp ' . number_format((int) $row['denda'], 0, ',', '.') : '-' ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">Belum ada riwayat selesai atau ditolak.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
