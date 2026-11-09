<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/connection.php';


if (isLogin()) {
    header('Location: ' . url('dashboard/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        // KEAMANAN: Menggunakan Prepared Statement untuk mencari data user berdasarkan username
        $stmt = $conn->prepare("
            SELECT 
                id,
                nama,
                username,
                password,
                level,
                anggota_id
            FROM user
            WHERE username = ?
              AND status_aktif = 1
            LIMIT 1
        ");

        if (!$stmt) {
            $error = 'Terjadi kesalahan pada database.';
        } else {
            // Eksekusi Prepared Statement dengan parameter binding
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();

            //Mengecek apakah data user ditemukan dan pw cocok
            $passwordValid = $u && (
                $password === $u['password'] ||
                password_verify($password, $u['password'])
            );

            // Jika user ditemukan dan passwordnya valid
            if ($passwordValid) {
                // KEAMANAN SESI: Mengganti ID sesi untuk mencegah serangan Session Fixation
                session_regenerate_id(true);

                // Menyimpan data penting user ke dalam variabel sesi (Session)
                $_SESSION['user_id'] = (int) $u['id'];
                $_SESSION['nama'] = $u['nama'];
                $_SESSION['level'] = $u['level'];
                $_SESSION['anggota_id'] = $u['anggota_id'] !== null
                    ? (int) $u['anggota_id']
                    : null;

                // Arahkan user masuk ke halaman dashboard utama
                header('Location: ' . url('dashboard/dashboard.php'));
                exit;
            }

            $error = 'Username atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login RuangBaca</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/login.css')) ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="brand">
            <img class="brand-img" src="<?= e(url('assets/images/logo.png')) ?>" alt="Logo RuangBaca">
            <div>
                <b>RuangBaca</b>
                <small>Portal Koleksi Sekolah</small>
            </div>
        </div>

        <h1>Selamat Datang</h1>
        <p class="muted">Silakan masuk ke akun Anda.</p>

        <!-- Menampilkan pesan error jika login gagal -->
        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Form login dengan teknik Sticky Form pada input username -->
        <form method="post">
            <label>Username</label>
            <input 
                name="username" 
                required 
                autocomplete="username"
                value="<?= e($_POST['username'] ?? '') ?>"
            >

            <label>Password</label>
            <input 
                type="password" 
                name="password" 
                required 
                autocomplete="current-password"
            >

            <button class="btn primary full" type="submit">Login</button>
        </form>

        <p class="login-note">
            Belum punya akun?
            <a href="<?= e(url('auth/daftar.php')) ?>">Daftar sebagai user</a>
        </p>
    </div>
</body>
</html>