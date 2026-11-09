<?php

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/connection.php';

$msg = '';
$type = 'success';


/*
|--------------------------------------------------------------------------
| Hapus user
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['hapus_id'])
) {

    $id = (int) $_POST['hapus_id'];


    if ($id === (int) $_SESSION['user_id']) {

        $msg = 'Akun yang sedang digunakan tidak dapat dihapus.';
        $type = 'danger';

    } else {

        // Soft delete: akun tidak dihapus permanen, hanya dinonaktifkan
        // (status_aktif = 0), sehingga riwayat peminjaman tetap utuh.
        backupSebelumHapus($conn, 'user', $id);

        $delete = $conn->prepare("
            UPDATE user
            SET status_aktif = 0
            WHERE id = ?
              AND status_aktif = 1
        ");

        $delete->bind_param('i', $id);

        if ($delete->execute()) {

            $msg = 'User dipindahkan ke Tempat Sampah.';

        } else {

            $msg = 'User gagal dihapus: ' . $delete->error;
            $type = 'danger';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Ambil data user + anggota
|--------------------------------------------------------------------------
*/

$rows = $conn->query("
    SELECT
        u.id,
        u.nama,
        u.username,
        u.level,
        u.anggota_id,
        a.kode_anggota,
        a.nama AS nama_anggota
    FROM user u

    LEFT JOIN anggota a
        ON a.id = u.anggota_id

    WHERE u.status_aktif = 1

    ORDER BY u.id DESC
");


$pageTitle = 'Data Pengguna';

$pageDescription =
    'Kelola akun administrator dan user '
    . 'ruang baca.';

require_once __DIR__ . '/../includes/layout_header.php';

?>


<?php if ($msg): ?>

    <div class="alert <?= e($type) ?>">
        <?= e($msg) ?>
    </div>

<?php endif; ?>


<section class="page-intro">

    <div>

        <span class="page-label">
            DATA MASTER
        </span>

        <h1>
            Data Pengguna
        </h1>

        <p>
            Kelola akun dan hubungan akun
            dengan anggota ruang baca.
        </p>

    </div>


    <a
        href="<?= e(url('user/form.php')) ?>"
        class="btn primary"
    >
        ＋ Tambah User
    </a>

</section>


<section class="panel">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Nama</th>

                    <th>Username</th>

                    <th>Level</th>

                    <th>Anggota</th>

                    <th>Aksi</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($rows->num_rows): ?>

                <?php while (
                    $row = $rows->fetch_assoc()
                ): ?>

                    <tr>

                        <td data-label="Nama">

                            <b>
                                <?= e($row['nama']) ?>
                            </b>

                        </td>


                        <td data-label="Username">

                            <?= e(
                                $row['username']
                            ) ?>

                        </td>


                        <td data-label="Level">

                            <span
                                class="badge <?= $row['level'] === 'admin'
                                    ? 'blue'
                                    : 'green' ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $row['level']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td data-label="Anggota">

                            <?php if (
                                !empty(
                                    $row['kode_anggota']
                                )
                            ): ?>

                                <?= e(
                                    $row['kode_anggota']
                                    . ' - '
                                    . $row['nama_anggota']
                                ) ?>

                            <?php else: ?>

                                <span class="muted">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                        <td
                            data-label="Aksi"
                            class="actions-cell"
                        >

                            <a
                                class="action edit"
                                href="<?= e(
                                    url('user/form.php')
                                ) ?>?id=<?= (int) $row['id'] ?>"
                            >
                                Edit
                            </a>


                            <?php if (
                                (int) $row['id'] !==
                                (int) $_SESSION['user_id']
                            ): ?>

                                <form
                                    method="post"
                                    class="inline-form"
                                    onsubmit="return confirm('Pindahkan user ini ke Tempat Sampah?')"
                                >

                                    <input
                                        type="hidden"
                                        name="hapus_id"
                                        value="<?= (int) $row['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action delete"
                                    >
                                        Hapus
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="empty"
                    >
                        Belum ada user.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>