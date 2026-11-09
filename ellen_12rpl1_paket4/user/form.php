<?php

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/connection.php';

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

$data = [
    'nama' => '',
    'username' => '',
    'password' => '',
    'level' => 'user',
    'anggota_id' => null,
];

$error = '';

/*
|--------------------------------------------------------------------------
| Ambil data user ketika edit
|--------------------------------------------------------------------------
*/

if ($id > 0) {

    $query = $conn->prepare("
        SELECT
            id,
            nama,
            username,
            password,
            level,
            anggota_id
        FROM user
        WHERE id = ?
        LIMIT 1
    ");

    $query->bind_param('i', $id);
    $query->execute();

    $result = $query->get_result();

    $found = $result->fetch_assoc();

    if ($found) {
        $data = $found;
        $data['password'] = '';
    }
}


/*
|--------------------------------------------------------------------------
| Proses form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data['nama'] = trim($_POST['nama'] ?? '');
    $data['username'] = trim($_POST['username'] ?? '');
    $data['level'] = $_POST['level'] ?? 'user';

    $password = $_POST['password'] ?? '';

    $anggota_id = (int) ($_POST['anggota_id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | ADMIN tidak perlu terhubung ke anggota
    |--------------------------------------------------------------------------
    */

    if ($data['level'] === 'admin') {
        $anggota_id = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Validasi dasar
    |--------------------------------------------------------------------------
    */

    if ($data['nama'] === '') {

        $error = 'Nama wajib diisi.';

    } elseif ($data['username'] === '') {

        $error = 'Username wajib diisi.';

    } elseif (
        !in_array(
            $data['level'],
            ['admin', 'user'],
            true
        )
    ) {

        $error = 'Level akun tidak valid.';

    } elseif (
        $id === 0 &&
        $password === ''
    ) {

        $error = 'Password wajib diisi untuk user baru.';

    } elseif (
        $data['level'] === 'user' &&
        $anggota_id < 1
    ) {

        $error = 'User harus dihubungkan dengan anggota.';

    }


    /*
    |--------------------------------------------------------------------------
    | Cek username
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $check = $conn->prepare("
            SELECT id
            FROM user
            WHERE username = ?
            AND id <> ?
            LIMIT 1
        ");

        $check->bind_param(
            'si',
            $data['username'],
            $id
        );

        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $error = 'Username sudah digunakan.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Cek anggota
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $data['level'] === 'user'
    ) {

        $check = $conn->prepare("
            SELECT id
            FROM anggota
            WHERE id = ?
            LIMIT 1
        ");

        $check->bind_param(
            'i',
            $anggota_id
        );

        $check->execute();

        if ($check->get_result()->num_rows === 0) {

            $error = 'Anggota tidak ditemukan.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Pastikan satu data anggota tidak digunakan dua akun user
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $data['level'] === 'user'
    ) {

        $check = $conn->prepare("
            SELECT id
            FROM user
            WHERE anggota_id = ?
            AND id <> ?
            LIMIT 1
        ");

        $check->bind_param(
            'ii',
            $anggota_id,
            $id
        );

        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $error = 'Data anggota sudah digunakan oleh akun pengguna lain';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Simpan data
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        /*
        |--------------------------------------------------------------------------
        | EDIT
        |--------------------------------------------------------------------------
        */

        if ($id > 0) {

            if ($password !== '') {

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $query = $conn->prepare("
                    UPDATE user
                    SET
                        nama = ?,
                        username = ?,
                        password = ?,
                        level = ?,
                        anggota_id = NULLIF(?, 0)
                    WHERE id = ?
                ");

                $query->bind_param(
                    'ssssii',
                    $data['nama'],
                    $data['username'],
                    $passwordHash,
                    $data['level'],
                    $anggota_id,
                    $id
                );

            } else {

                $query = $conn->prepare("
                    UPDATE user
                    SET
                        nama = ?,
                        username = ?,
                        level = ?,
                        anggota_id = NULLIF(?, 0)
                    WHERE id = ?
                ");

                $query->bind_param(
                    'sssii',
                    $data['nama'],
                    $data['username'],
                    $data['level'],
                    $anggota_id,
                    $id
                );
            }

            if (!$query->execute()) {

                $error = 'Gagal memperbarui user: ' . $query->error;

            } else {

                if (
                    $id ===
                    (int) $_SESSION['user_id']
                ) {

                    $_SESSION['nama'] = $data['nama'];
                    $_SESSION['level'] = $data['level'];
                }

                header(
                    'Location: ' .
                    url('user/index.php')
                );

                exit;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | TAMBAH USER
        |--------------------------------------------------------------------------
        */

        else {

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $query = $conn->prepare("
                INSERT INTO user
                (
                    nama,
                    username,
                    password,
                    level,
                    anggota_id
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    NULLIF(?, 0)
                )
            ");

            $query->bind_param(
                'ssssi',
                $data['nama'],
                $data['username'],
                $passwordHash,
                $data['level'],
                $anggota_id
            );

            if (!$query->execute()) {

                $error = 'Gagal menambahkan user: ' . $query->error;

            } else {

                header(
                    'Location: ' .
                    url('user/index.php')
                );

                exit;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Ambil data anggota
|--------------------------------------------------------------------------
*/

$anggotaRows = $conn->query("
    SELECT
        id,
        kode_anggota,
        nama
    FROM anggota
    WHERE status_aktif = 1
    ORDER BY nama ASC
");


$pageTitle = $id > 0
    ? 'Edit Pengguna'
    : 'Tambah Pengguna';

$pageDescription = $id > 0
    ? 'Perbarui data akun pengguna.'
    : 'Tambahkan akun administrator atau user.';

require_once __DIR__ . '/../includes/layout_header.php';

?>

<?php if ($error): ?>

    <div class="alert danger">
        <?= e($error) ?>
    </div>

<?php endif; ?>


<section class="panel form-panel">

    <div class="form-title">

        <span class="page-label">
            DATA MASTER
        </span>

        <h1>
            <?= e($pageTitle) ?>
        </h1>

        <p>
            Kelola akun dan hubungan akun dengan data anggota.
        </p>

    </div>


    <form method="post" class="form-grid">

        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >


        <div>

            <label>Nama</label>

            <input
                type="text"
                name="nama"
                value="<?= e($data['nama']) ?>"
                required
            >

        </div>


        <div>

            <label>Username</label>

            <input
                type="text"
                name="username"
                value="<?= e($data['username']) ?>"
                required
            >

        </div>


        <div>

            <label>
                Password
                <?= $id
                    ? '(kosongkan jika tidak diubah)'
                    : '' ?>
            </label>

            <input
                type="password"
                name="password"
                <?= $id ? '' : 'required' ?>
                autocomplete="new-password"
            >

        </div>


        <div>

            <label>Level</label>

            <select
                name="level"
                id="level"
                required
            >

                <option
                    value="user"
                    <?= $data['level'] === 'user'
                        ? 'selected'
                        : '' ?>
                >
                    User
                </option>

                <option
                    value="admin"
                    <?= $data['level'] === 'admin'
                        ? 'selected'
                        : '' ?>
                >
                    Admin
                </option>

            </select>

        </div>


        <div
            class="full"
            id="anggota-wrapper"
        >

            <label>
                Anggota
            </label>

            <select
                name="anggota_id"
                id="anggota_id"
            >

                <option value="">
                    Pilih data anggota
                </option>


                <?php while (
                    $anggota = $anggotaRows->fetch_assoc()
                ): ?>

                    <option
                        value="<?= (int) $anggota['id'] ?>"
                        <?= (
                            (int) $data['anggota_id'] ===
                            (int) $anggota['id']
                        )
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e(
                            $anggota['kode_anggota']
                            . ' - '
                            . $anggota['nama']
                        ) ?>

                    </option>

                <?php endwhile; ?>

            </select>

            <small class="muted">

                Satu akun hanya memiliki satu data profil

            </small>

        </div>


        <div class="full form-actions">

            <button
                type="submit"
                class="btn primary"
            >
                Simpan
            </button>

            <a
                href="<?= e(url('user/index.php')) ?>"
                class="btn"
            >
                Kembali
            </a>

        </div>

    </form>

</section>


<script>

const level =
    document.getElementById('level');

const anggotaWrapper =
    document.getElementById('anggota-wrapper');

const anggotaSelect =
    document.getElementById('anggota_id');


function updateAnggota() {

    if (level.value === 'admin') {

        anggotaWrapper.style.display = 'none';

        anggotaSelect.value = '';

        anggotaSelect.removeAttribute(
            'required'
        );

    } else {

        anggotaWrapper.style.display = 'block';

        anggotaSelect.setAttribute(
            'required',
            'required'
        );
    }
}


level.addEventListener(
    'change',
    updateAnggota
);

updateAnggota();

</script>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>