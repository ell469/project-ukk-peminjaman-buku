<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdmin();
require_once __DIR__ . "/../config/connection.php";

$msg = "";
if (isset($_GET["hapus"])) {
    $id = (int) $_GET["hapus"];
    
    // Soft delete: data tidak dihapus permanen, hanya disembunyikan (status_aktif = 0),
    // sehingga riwayat peminjaman tetap utuh.
    backupSebelumHapus($conn, 'anggota', $id);
    $q = $conn->prepare("UPDATE anggota SET status_aktif = 0 WHERE id = ? AND status_aktif = 1");
    $q->bind_param("i", $id);
    $q->execute();
    $msg = "Anggota dipindahkan ke Tempat Sampah.";
}

$rows = $conn->query("SELECT * FROM anggota WHERE status_aktif = 1 ORDER BY id DESC");
$pageTitle = "Data Anggota";
$pageDescription = "Kelola data anggota ruang baca.";
require_once __DIR__ . "/../includes/layout_header.php";
?>

<?php if ($msg): ?>
    <div class="alert <?= strpos($msg, "tidak") !== false ? "danger" : "success" ?>">
        <?= e($msg) ?>
    </div>
<?php endif; ?>

<section class="page-intro">
    <div>
        <span class="page-label">ANGGOTA</span>
        <h1>Data Anggota</h1>
        <p>Kelola anggota yang terdaftar di ruang baca.</p>
    </div>
    <a href="<?= e(url("anggota/anggota_form.php")) ?>" class="btn primary">＋ Tambah Anggota</a>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Jenis Kelamin</th>
                    <th>Tempat/Tgl Lahir</th>
                    <th>Telpon</th>
                    <th>Alamat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows && $rows->num_rows > 0): ?>
                    <?php while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Kode"><span class="book-code"><?= e($r["kode_anggota"]) ?></span></td>
                            <td data-label="Nama"><b><?= e($r["nama"]) ?></b></td>
                            <td data-label="Jenis Kelamin"><?= e(ucfirst($r["jenis_kelamin"] ?? "-")) ?></td>
                            <td data-label="Tempat/Tgl Lahir"><?= e($r["tempat_lahir"] ?? "-") ?> / <?= e($r["tanggal_lahir"] ?? "-") ?></td>
                            <td data-label="Telpon"><?= e($r["telpon"] ?? "-") ?></td>
                            <td data-label="Alamat"><?= e($r["alamat"] ?? "-") ?></td>
                            <td data-label="Aksi" class="actions-cell">
                                <a class="action edit" href="<?= e(url("anggota/anggota_form.php")) ?>?id=<?= $r["id"] ?>">Edit</a>
                                <a class="action delete" href="<?= e(url("anggota/anggota.php")) ?>?hapus=<?= $r["id"] ?>" onclick="return confirm('Pindahkan anggota ini ke Tempat Sampah?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">Belum ada anggota.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . "/../includes/layout_footer.php"; ?>