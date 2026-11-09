<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/connection.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . url('dashboard/dashboard.php'));
    exit;
}

$error = '';

// Mengambil dan membersihkan inputan form registrasi dari potensi spasi berlebih
$nama          = trim($_POST['nama'] ?? '');
$username      = trim($_POST['username'] ?? '');
$jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
$telepon       = trim($_POST['telepon'] ?? '');
$alamat        = trim($_POST['alamat'] ?? '');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? ''; // Ambil password mentah dari form


    if ($nama === '' || $username === '' || $password === '' || $jenis_kelamin === '' || $telepon === '' || $alamat === '') {
        $error = 'Semua data wajib diisi.';
    } elseif (strlen($password) < 5) {

        $error = 'Password minimal 5 karakter.';
    } else {
        // KEAMANAN: Cek apakah username sudah terdaftar di database
        $cek = $conn->prepare('SELECT id FROM user WHERE username = ? LIMIT 1');
        $cek->bind_param('s', $username);
        $cek->execute();

        if ($cek->get_result()->num_rows > 0) {
            $error = 'Username sudah digunakan.';
        } else {
            // TRANSAKSI DATABASE: Mulai transaksi agar aman jika terjadi kegagalan di tengah proses
            $conn->begin_transaction();

            try {
                // LANGKAH 1: Masukkan data awal ke tabel anggota
                $kodeSementara = 'TMP' . uniqid();
                $stmtAnggota = $conn->prepare(
                    'INSERT INTO anggota (kode_anggota, nama, jenis_kelamin, telpon, alamat) VALUES (?, ?, ?, ?, ?)'
                );
                $stmtAnggota->bind_param('sssss', $kodeSementara, $nama, $jenis_kelamin, $telepon, $alamat);
                $stmtAnggota->execute();

                // Ambil ID anggota yang baru saja di-generate oleh database (Auto Increment)
                $anggotaId = (int) $conn->insert_id;

                // LANGKAH 2: Buat kode anggota final yang rapi, contoh: AGT0001
                $kodeAnggota = sprintf('AGT%04d', $anggotaId);
                $stmtKode = $conn->prepare('UPDATE anggota SET kode_anggota = ? WHERE id = ?');
                $stmtKode->bind_param('si', $kodeAnggota, $anggotaId);
                $stmtKode->execute();

                // LANGKAH 3: Buat akun user dan hubungkan dengan 'anggota_id'
                // KEAMANAN: Enkripsi password menggunakan password_hash()
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $conn->prepare(
                    'INSERT INTO user (nama, username, password, level, anggota_id)
                     VALUES (?, ?, ?, \'user\', ?)'
                );
                $stmtUser->bind_param('sssi', $nama, $username, $passwordHash, $anggotaId);
                $stmtUser->execute();

                // Jika semua langkah di atas berhasil tanpa error, simpan permanen (Commit)
                $conn->commit();

                // Redirect kembali ke halaman login dengan parameter sukses registrasi
                header('Location: ' . url('auth/login.php') . '?registered=1');
                exit;

            } catch (Throwable $ex) {
                // Jika ada error di tengah jalan, batalkan semua perintah database (Rollback)
                $conn->rollback();
                $error = 'Pendaftaran gagal: ' . $ex->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Daftar - RuangBaca</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/login.css')) ?>">
</head>
<body class="login-body">
    <div class="login-card" style="max-width: 500px;">
        <div class="brand">
            <img class="brand-img" src="<?= e(url('assets/images/logo.png')) ?>" alt="Logo RuangBaca">
            <div><b>RuangBaca</b><small>Buat Akun & Profil Anggota</small></div>
        </div>

        <h1>Daftar Akun</h1>

        <!-- Menampilkan pesan error jika validasi gagal -->
        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Form registrasi menggunakan metode POST dengan teknik Sticky Form (mempertahankan inputan jika gagal) -->
        <form method="post">
            <label for="nama">Nama Lengkap</label>
            <input id="nama" name="nama" value="<?= e($nama) ?>" required>

            <label for="username">Username</label>
            <input id="username" name="username" value="<?= e($username) ?>" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required minlength="5" autocomplete="new-password">

            <label for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin" required style="width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
                <option value="">Pilih Jenis Kelamin</option>
                <option value="L" <?= $jenis_kelamin === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= $jenis_kelamin === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>

            <label for="telepon">No. Telepon / WhatsApp</label>
            <input id="telepon" name="telepon" value="<?= e($telepon) ?>" required>

            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat" rows="3" required style="width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; resize: vertical;"><?= e($alamat) ?></textarea>

            <button class="btn primary full" type="submit">Daftar</button>
        </form>

        <p class="login-note">
            <a href="<?= e(url('auth/login.php')) ?>">Kembali ke login</a>
        </p>
    </div>
</body>
</html>