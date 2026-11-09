<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/connection.php';

$msg  = '';
$type = 'success';

/*
|--------------------------------------------------------------------------
| Aksi: pulihkan / hapus permanen (hanya untuk data yang sudah di sampah)
|--------------------------------------------------------------------------
*/
$tables = [
    'anggota' => ['label' => 'Anggota',  'fk' => 'anggota_id'],
    'user'    => ['label' => 'Pengguna', 'fk' => 'user_id'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi  = $_POST['aksi']  ?? '';
    $tabel = $_POST['tabel'] ?? '';
    $id    = (int) ($_POST['id'] ?? 0);

    if (!isset($tables[$tabel]) || $id <= 0 || !in_array($aksi, ['pulihkan', 'permanen'], true)) {
        $msg  = 'Permintaan tidak valid.';
        $type = 'danger';
    } elseif ($aksi === 'pulihkan') {
        $stmt = $conn->prepare("UPDATE `$tabel` SET status_aktif = 1 WHERE id = ? AND status_aktif = 0");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $msg = $tables[$tabel]['label'] . ' berhasil dipulihkan.';
    } else {
        // Hapus permanen: ditolak jika masih punya riwayat peminjaman.
        $fk = $tables[$tabel]['fk'];
        $cek = $conn->prepare("SELECT COUNT(*) AS total FROM peminjaman WHERE $fk = ?");
        $cek->bind_param('i', $id);
        $cek->execute();
        $total = (int) $cek->get_result()->fetch_assoc()['total'];

        if ($total > 0) {
            $msg  = $tables[$tabel]['label'] . ' tidak dapat dihapus permanen karena masih memiliki riwayat peminjaman.';
            $type = 'danger';
        } else {
            try {
                $del = $conn->prepare("DELETE FROM `$tabel` WHERE id = ? AND status_aktif = 0");
                $del->bind_param('i', $id);
                $ok = $del->execute();
            } catch (mysqli_sql_exception $e) {
                $ok = false;
            }

            if ($ok) {
                $msg = $tables[$tabel]['label'] . ' dihapus permanen.';
            } else {
                $msg  = $tables[$tabel]['label'] . ' gagal dihapus permanen karena masih terhubung dengan data lain.';
                $type = 'danger';
            }
        }
    }
}

$anggotaRows = $conn->query("SELECT * FROM anggota WHERE status_aktif = 0 ORDER BY id DESC");

$userRows = $conn->query("
    SELECT id, nama, username, level
    FROM user
    WHERE status_aktif = 0
    ORDER BY id DESC
");

// Waktu dihapus diambil dari tabel backup `delete`
$waktu = [];
$w = $conn->query("
    SELECT table_name, original_id, MAX(deleted_at) AS waktu
    FROM `delete`
    WHERE table_name IN ('anggota', 'user')
    GROUP BY table_name, original_id
");
if ($w) {
    while ($r = $w->fetch_assoc()) {
        $waktu[$r['table_name'] . ':' . $r['original_id']] = $r['waktu'];
    }
}

$pageTitle       = 'Tempat Sampah';
$pageDescription = 'Pulihkan atau hapus permanen data anggota dan pengguna.';
require_once __DIR__ . '/../includes/layout_header.php';

// Tombol aksi (form kecil, method POST)
function tombolSampah(string $tabel, int $id, string $aksi, string $label, string $kelas, string $konfirmasi): void
{
    ?>
    <form method="post" class="inline-form" onsubmit="return confirm('<?= e($konfirmasi) ?>')">
        <input type="hidden" name="tabel" value="<?= e($tabel) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="aksi" value="<?= e($aksi) ?>">
        <button type="submit" class="action <?= e($kelas) ?>"><?= e($label) ?></button>
    </form>
    <?php
}
?>

<?php if ($msg): ?>
    <div class="alert <?= e($type) ?>"><?= e($msg) ?></div>
<?php endif; ?>

<section class="page-intro">
    <div>
        <span class="page-label">TEMPAT SAMPAH</span>
        <h1>Tempat Sampah</h1>
        <p>Data yang dihapus masuk ke sini. Pulihkan jika salah hapus, atau hapus permanen jika sudah tidak dibutuhkan.</p>
    </div>
</section>

<section class="panel">
    <h2 style="margin:0 0 12px;">Anggota</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Telpon</th>
                    <th>Dihapus pada</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($anggotaRows && $anggotaRows->num_rows > 0): ?>
                    <?php while ($r = $anggotaRows->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Kode"><span class="book-code"><?= e($r['kode_anggota']) ?></span></td>
                            <td data-label="Nama"><b><?= e($r['nama']) ?></b></td>
                            <td data-label="Telpon"><?= e($r['telpon'] ?? '-') ?></td>
                            <td data-label="Dihapus pada"><?= e($waktu['anggota:' . $r['id']] ?? '-') ?></td>
                            <td data-label="Aksi" class="actions-cell">
                                <?php tombolSampah('anggota', (int) $r['id'], 'pulihkan', 'Pulihkan', 'edit', 'Pulihkan anggota ini?'); ?>
                                <?php tombolSampah('anggota', (int) $r['id'], 'permanen', 'Hapus Permanen', 'delete', 'Hapus permanen? Data tidak bisa dikembalikan.'); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty">Tidak ada anggota di tempat sampah.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <h2 style="margin:0 0 12px;">Akun Pengguna</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Level</th>
                    <th>Dihapus pada</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($userRows && $userRows->num_rows > 0): ?>
                    <?php while ($r = $userRows->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Nama"><b><?= e($r['nama']) ?></b></td>
                            <td data-label="Username"><?= e($r['username']) ?></td>
                            <td data-label="Level"><?= e(ucfirst($r['level'])) ?></td>
                            <td data-label="Dihapus pada"><?= e($waktu['user:' . $r['id']] ?? '-') ?></td>
                            <td data-label="Aksi" class="actions-cell">
                                <?php tombolSampah('user', (int) $r['id'], 'pulihkan', 'Pulihkan', 'edit', 'Pulihkan akun ini?'); ?>
                                <?php tombolSampah('user', (int) $r['id'], 'permanen', 'Hapus Permanen', 'delete', 'Hapus permanen? Data tidak bisa dikembalikan.'); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty">Tidak ada akun di tempat sampah.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
