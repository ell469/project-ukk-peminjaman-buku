<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/connection.php';

$msg = '';
$type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    $id = (int) $_POST['hapus_id'];

    $conn->begin_transaction();

    try {
        $check = $conn->prepare('SELECT id FROM kategori WHERE id = ? FOR UPDATE');
        $check->bind_param('i', $id);
        $check->execute();
        $category = $check->get_result()->fetch_assoc();

        if (!$category) {
            throw new RuntimeException('Kategori tidak ditemukan.');
        }

        $check = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM buku
            WHERE kategori_id = ?
              AND status_aktif = 1
        ");
        $check->bind_param('i', $id);
        $check->execute();
        $total = (int) $check->get_result()->fetch_assoc()['total'];

        if ($total > 0) {
            throw new RuntimeException('Kategori tidak dapat dihapus karena masih digunakan oleh buku aktif.');
        }

        // Buku terarsip tetap disimpan untuk riwayat, tetapi tidak lagi memerlukan kategori.
        $detach = $conn->prepare("
            UPDATE buku
            SET kategori_id = NULL
            WHERE kategori_id = ?
              AND status_aktif = 0
        ");
        $detach->bind_param('i', $id);
        $detach->execute();

        backupSebelumHapus($conn, 'kategori', $id);
        $delete = $conn->prepare('DELETE FROM kategori WHERE id = ?');
        $delete->bind_param('i', $id);
        $delete->execute();

        $conn->commit();
        $msg = 'Kategori berhasil dihapus.';
    } catch (RuntimeException $e) {
        $conn->rollback();
        $msg = $e->getMessage();
        $type = 'danger';
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $msg = 'Kategori gagal dihapus: ' . $e->getMessage();
        $type = 'danger';
    }
}

$rows = $conn->query(
    'SELECT k.id, k.kode_kategori, k.nama_kategori,
            COUNT(CASE WHEN b.status_aktif = 1 THEN b.id END) AS jumlah_buku
     FROM kategori k
     LEFT JOIN buku b ON b.kategori_id = k.id
     GROUP BY k.id, k.kode_kategori, k.nama_kategori
     ORDER BY k.id DESC'
);

$pageTitle = 'Data Kategori';
$pageDescription = 'Kelola kategori buku ruang baca.';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($msg): ?>
    <div class="alert <?= e($type) ?>"><?= e($msg) ?></div>
<?php endif; ?>

<section class="page-intro">
    <div>
        <span class="page-label">DATA MASTER</span>
        <h1>Data Kategori</h1>
        <p>Kelola kategori yang digunakan pada koleksi buku.</p>
    </div>
    <a href="<?= e(url('kategori/form.php')) ?>" class="btn primary">＋ Tambah Kategori</a>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Buku Aktif</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows->num_rows): ?>
                <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Kode">
                            <span class="book-code"><?= e($row['kode_kategori']) ?></span>
                        </td>
                        <td data-label="Nama Kategori"><b><?= e($row['nama_kategori']) ?></b></td>
                        <td data-label="Jumlah Buku Aktif"><?= (int) $row['jumlah_buku'] ?> buku</td>
                        <td data-label="Aksi" class="actions-cell">
                            <a class="action edit" href="<?= e(url('kategori/form.php')) ?>?id=<?= (int) $row['id'] ?>">Edit</a>
                            <form method="post" class="inline-form" onsubmit="return confirm('Hapus kategori ini?')">
                                <input type="hidden" name="hapus_id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="action delete">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="empty">Belum ada kategori.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
