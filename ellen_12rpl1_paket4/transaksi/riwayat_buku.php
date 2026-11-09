<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/connection.php';

$userId = (int) $_SESSION['user_id'];

/*
 * Ambil seluruh transaksi milik user yang sedang login.
 * User hanya dapat melihat status buku, bukan melakukan pengembalian.
 */
$rows = $conn->prepare(
    "SELECT
        p.id,
        p.tanggal_pinjam,
        p.lama_pinjam,
        p.status,
        GROUP_CONCAT(
            CONCAT(b.judul, ' (', pd.jumlah, ')')
            SEPARATOR ', '
        ) AS buku,
        pr.tanggal_kembali,
        pr.keterlambatan_hari,
        pr.denda
     FROM peminjaman p
     JOIN peminjaman_detail pd
        ON pd.peminjaman_id = p.id
     JOIN buku b
        ON b.id = pd.buku_id
     LEFT JOIN pengembalian pr
        ON pr.peminjaman_id = p.id
     WHERE p.user_id = ?
     GROUP BY
        p.id,
        p.tanggal_pinjam,
        p.lama_pinjam,
        p.status,
        pr.tanggal_kembali,
        pr.keterlambatan_hari,
        pr.denda
     ORDER BY p.id DESC"
);
$rows->bind_param('i', $userId);
$rows->execute();
$result = $rows->get_result();

$pageTitle = 'Riwayat Buku';
$pageDescription = 'Lihat buku yang masih dipinjam dan yang sudah dikembalikan.';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Riwayat Buku Saya</h3>
            <p class="muted">
                Pengembalian buku hanya dapat diproses oleh admin untuk menjaga
                keakuratan transaksi dan mencegah kecurangan.
            </p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Buku</th>
                    <th>Tanggal Pinjam</th>
                    <th>Batas Pinjam</th>
                    <th>Status</th>
                    <th>Tanggal Kembali</th>
                    <th>Dipinjam Selama</th>
                    <th>Denda</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td data-label="ID">
                                #<?= (int) $row['id'] ?>
                            </td>
                            <td data-label="Buku">
                                <?= e($row['buku']) ?>
                            </td>
                            <td data-label="Tanggal Pinjam">
                                <?= e($row['tanggal_pinjam']) ?>
                            </td>
                            <td data-label="Batas Pinjam">
                                <?= (int) $row['lama_pinjam'] ?> hari
                            </td>
                            <td data-label="Status">
                                <?php if ($row['status'] === 'diproses'): ?>
                                    <span class="badge yellow">Sedang Diproses</span>
                                <?php elseif ($row['status'] === 'dipinjam'): ?>
                                    <span class="badge red">Sedang Dipinjam</span>
                                <?php elseif ($row['status'] === 'ditolak'): ?>
                                    <span class="badge gray">Ditolak</span>
                                <?php else: ?>
                                    <span class="badge green">Sudah Dikembalikan</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tanggal Kembali">
                                <?= $row['tanggal_kembali']
                                    ? e($row['tanggal_kembali'])
                                    : '-' ?>
                            </td>
                            <td data-label="Dipinjam Selama">
                                <?php
                                // Lama pinjam sebenarnya = tanggal kembali - tanggal pinjam
                                if ($row['tanggal_kembali']) {
                                    $hari = (int) (new DateTime($row['tanggal_pinjam']))
                                        ->diff(new DateTime($row['tanggal_kembali']))->days;
                                    echo $hari === 0 ? 'Hari yang sama' : $hari . ' hari';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td data-label="Denda">
                                <?php if ($row['denda'] !== null): ?>
                                    Rp <?= number_format((int) $row['denda'], 0, ',', '.') ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty">
                            Belum ada riwayat peminjaman buku.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
