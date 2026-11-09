<?php
require_once __DIR__ . "/../includes/auth.php";
requireAdmin();
require_once __DIR__ . "/../config/connection.php";

$id = (int) ($_GET["id"] ?? ($_POST["id"] ?? 0));
$d = [
    "kode_anggota" => "",
    "nama" => "",
    "jenis_kelamin" => "",
    "tempat_lahir" => "",
    "tanggal_lahir" => "",
    "telpon" => "",
    "alamat" => "",
];

if ($id) {
    $q = $conn->prepare("SELECT * FROM anggota WHERE id=?");
    $q->bind_param("i", $id);
    $q->execute();
    $d = $q->get_result()->fetch_assoc() ?: $d;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $v = [
        trim($_POST["kode_anggota"] ?? ""),
        trim($_POST["nama"] ?? ""),
        $_POST["jenis_kelamin"] ?? "",
        trim($_POST["tempat_lahir"] ?? ""),
        ($_POST["tanggal_lahir"] ?? "") !== "" ? $_POST["tanggal_lahir"] : null,
        trim($_POST["telpon"] ?? ""),
        trim($_POST["alamat"] ?? ""),
    ];
    
    $error = "";
    
    // Validasi backend: pastikan tidak ada data yang kosong
    if (empty($v[0]) || empty($v[1]) || empty($v[2]) || empty($v[3]) || empty($v[4]) || empty($v[5]) || empty($v[6])) {
        $error = "Semua kolom wajib diisi dan tidak boleh kosong!";
    } else {
        try {
            if ($id) {
                $q = $conn->prepare(
                    "UPDATE anggota SET kode_anggota=?, nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, telpon=?, alamat=? WHERE id=?"
                );
                $q->bind_param("sssssssi", $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $id);
            } else {
                $q = $conn->prepare(
                    "INSERT INTO anggota (kode_anggota, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, telpon, alamat) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $q->bind_param("sssssss", $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6]);
            }
            $q->execute();
            header("Location: " . url("anggota/anggota.php"));
            exit();
        } catch (mysqli_sql_exception $ex) {
            // 1062 = duplicate entry (kode anggota sudah dipakai)
            $error = (int) $ex->getCode() === 1062
                ? 'Kode anggota "' . $v[0] . '" sudah digunakan. Gunakan kode lain.'
                : "Data anggota gagal disimpan: " . $ex->getMessage();
        }
    }

    // Pertahankan isian pengguna agar tidak perlu mengetik ulang saat terjadi error
    $d = array_merge($d, [
        "kode_anggota" => $v[0],
        "nama" => $v[1],
        "jenis_kelamin" => $v[2],
        "tempat_lahir" => $v[3],
        "tanggal_lahir" => $v[4] ?? "",
        "telpon" => $v[5],
        "alamat" => $v[6],
    ]);
}

$pageTitle = $id ? "Edit Anggota" : "Tambah Anggota";
$pageDescription = $id ? "Perbarui data anggota." : "Tambahkan anggota baru.";
require_once __DIR__ . "/../includes/layout_header.php";
?>

<?php if (!empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="form-title">
        <span class="page-label">ANGGOTA</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Lengkapi informasi anggota dengan benar.</p>
    </div>
    
    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= $id ?>">
        
        <div>
            <label>Kode Anggota</label>
            <input name="kode_anggota" value="<?= e($d["kode_anggota"]) ?>" required <?= $id ? 'readonly style="background-color: #e9ecef; cursor: not-allowed;"' : '' ?> title="<?= $id ? 'Kode anggota tidak dapat diubah' : '' ?>">
            <?php if($id): ?>
                <small style="color: #6c757d; font-size: 0.8rem;">Kode anggota terkunci dan tidak dapat diubah.</small>
            <?php endif; ?>
        </div>
        
        <div>
            <label>Nama</label>
            <input name="nama" value="<?= e($d["nama"]) ?>" required>
        </div>
        
        <div>
            <label>Jenis Kelamin</label>
            <select name="jenis_kelamin" required>
                <option value="">Pilih</option>
                <option value="pria" <?= $d["jenis_kelamin"] === "pria" ? "selected" : "" ?>>Pria</option>
                <option value="wanita" <?= $d["jenis_kelamin"] === "wanita" ? "selected" : "" ?>>Wanita</option>
            </select>
        </div>
        
        <div>
            <label>Tempat Lahir</label>
            <input name="tempat_lahir" value="<?= e($d["tempat_lahir"]) ?>" required>
        </div>
        
        <div>
            <label>Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir" value="<?= e($d["tanggal_lahir"]) ?>" required>
        </div>
        
        <div>
            <label>Telpon</label>
            <input type="tel" name="telpon" value="<?= e($d["telpon"]) ?>" required>
        </div>
        
        <div class="full">
            <label>Alamat</label>
            <textarea name="alamat" rows="4" required><?= e($d["alamat"]) ?></textarea>
        </div>
        
        <div class="full form-actions">
            <button type="submit" class="btn primary">Simpan</button>
            <a href="<?= e(url("anggota/anggota.php")) ?>" class="btn">Kembali</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . "/../includes/layout_footer.php"; ?>