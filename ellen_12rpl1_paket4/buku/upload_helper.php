<?php
// Batas ukuran cover. Foto dari HP sering 2-5 MB, jadi batasnya dinaikkan
// dan gambar besar otomatis diperkecil (jika ekstensi GD aktif).
const COVER_MAX_BYTES = 5 * 1024 * 1024;
const COVER_MAX_SIDE = 1000;

function pesanErrorUpload(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Ukuran file melebihi batas server (upload_max_filesize = '
                . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Ukuran file melebihi batas yang diizinkan form.';
        case UPLOAD_ERR_PARTIAL:
            return 'File hanya terunggah sebagian. Silakan coba lagi.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Folder sementara PHP tidak ditemukan (upload_tmp_dir).';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server gagal menulis file ke disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload dihentikan oleh ekstensi PHP.';
        default:
            return 'Upload gambar gagal. Kode error: ' . $code;
    }
}

// Jika total data POST melebihi post_max_size, PHP mengosongkan $_POST
// dan $_FILES tanpa pesan error. Fungsi ini mendeteksi kondisi tersebut.
function requestMelebihiBatasPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && empty($_POST)
        && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function pesanBatasPost(): string
{
    return 'Ukuran data yang dikirim melebihi batas server (post_max_size = '
        . ini_get('post_max_size') . '). Gunakan gambar yang lebih kecil.';
}

function hapusFileGambar(?string $filename): void
{
    if (!$filename || basename($filename) === 'default.svg') {
        return;
    }

    $path = __DIR__ . '/../assets/images/' . basename($filename);

    if (is_file($path)) {
        @unlink($path);
    }
}

// Memperkecil gambar dengan GD. Mengembalikan false bila tidak perlu
// atau tidak bisa, sehingga pemanggil memakai file asli apa adanya.
function optimalkanGambar(
    string $tmp,
    string $dest,
    string $mime,
    int $w,
    int $h
): bool {
    if (!extension_loaded('gd') || $w < 1 || $h < 1) {
        return false;
    }

    // Hindari kehabisan memori pada gambar yang sangat besar.
    if ($w * $h > 25000000) {
        return false;
    }

    $perluDiperkecil = max($w, $h) > COVER_MAX_SIDE;
    $terlaluBerat = filesize($tmp) > 1.5 * 1024 * 1024;

    if (!$perluDiperkecil && !$terlaluBerat) {
        return false;
    }

    // JPEG dari HP menyimpan arah putar di EXIF. Jika di-encode ulang tanpa
    // membaca EXIF, gambar bisa jadi miring, jadi lewati bila tidak bisa.
    if ($mime === 'image/jpeg' && !function_exists('exif_read_data')) {
        return false;
    }

    $img = false;

    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($tmp);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $img = @imagecreatefrompng($tmp);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($tmp);
    }

    if (!$img) {
        return false;
    }

    if ($mime === 'image/jpeg') {
        $exif = @exif_read_data($tmp);
        $orientasi = (int) ($exif['Orientation'] ?? 1);
        $sudut = [3 => 180, 6 => -90, 8 => 90][$orientasi] ?? 0;

        if ($sudut !== 0) {
            $putar = imagerotate($img, $sudut, 0);
            if ($putar) {
                $img = $putar;
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $skala = min(1, COVER_MAX_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $skala));
    $nh = max(1, (int) round($h * $skala));

    $dst = imagecreatetruecolor($nw, $nh);

    if ($mime === 'image/jpeg') {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    } else {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }

    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    if ($mime === 'image/jpeg') {
        $ok = imagejpeg($dst, $dest, 85);
    } elseif ($mime === 'image/png') {
        $ok = imagepng($dst, $dest, 6);
    } else {
        $ok = function_exists('imagewebp') && imagewebp($dst, $dest, 85);
    }

    if (!$ok || !is_file($dest) || filesize($dest) === 0) {
        @unlink($dest);
        return false;
    }

    return true;
}

function uploadGambarBuku($fieldName = 'gambar', $required = false)
{
    if (requestMelebihiBatasPost()) {
        return ['success' => false, 'error' => pesanBatasPost()];
    }

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        if ($required) {
            return ['success' => false, 'error' => 'Silakan pilih gambar buku.'];
        }

        return ['success' => true, 'filename' => null];
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => pesanErrorUpload($file['error'])];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'File upload tidak valid.'];
    }

    if ($file['size'] > COVER_MAX_BYTES) {
        return [
            'success' => false,
            'error' => 'Ukuran gambar '
                . number_format($file['size'] / 1048576, 1, ',', '.')
                . ' MB, maksimal ' . (COVER_MAX_BYTES / 1048576) . ' MB.',
        ];
    }

    // getimagesize tidak butuh ekstensi fileinfo, jadi lebih aman dipakai
    // untuk memeriksa tipe sekaligus memastikan file benar-benar gambar.
    $info = @getimagesize($file['tmp_name']);

    if ($info === false) {
        return [
            'success' => false,
            'error' => 'File yang dipilih bukan gambar yang valid.',
        ];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = $info['mime'] ?? '';

    if (!isset($allowed[$mime])) {
        return [
            'success' => false,
            'error' => 'Format gambar harus JPG, JPEG, PNG, atau WEBP.',
        ];
    }

    $folder = __DIR__ . '/../assets/images/';

    if (!is_dir($folder) && !@mkdir($folder, 0777, true)) {
        return [
            'success' => false,
            'error' => 'Folder assets/images tidak ada dan gagal dibuat.',
        ];
    }

    if (!is_writable($folder)) {
        return [
            'success' => false,
            'error' => 'Folder assets/images tidak dapat ditulis. '
                . 'Periksa izin folder tersebut.',
        ];
    }

    $filename = 'buku_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destination = $folder . $filename;

    $tersimpan = optimalkanGambar(
        $file['tmp_name'],
        $destination,
        $mime,
        (int) $info[0],
        (int) $info[1]
    );

    if (!$tersimpan && !move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => false,
            'error' => 'Gambar gagal disimpan ke server. '
                . 'Pastikan folder assets/images dapat ditulis.',
        ];
    }

    return ['success' => true, 'filename' => $filename, 'path' => $destination];
}
