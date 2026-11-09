<?php
// Konfigurasi dasar aplikasi.
// Path dibuat otomatis agar project tetap bisa dijalankan
// dari root AWebServer maupun dari dalam subfolder.

error_reporting(E_ALL);
ini_set("display_errors", "1");

$scriptName = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
$scriptDir = str_replace("\\", "/", dirname($scriptName));

// Folder fitur yang berada satu tingkat di bawah folder utama project.
$appFolders = [
  "auth",
  "buku",
  "anggota",
  "transaksi",
  "pencarian",
  "dashboard",
  "kategori",
  "penerbit",
  "user",
  "bookmark",
  "aksi",
];

$currentFolder = basename(rtrim($scriptDir, "/"));

if (in_array($currentFolder, $appFolders, true)) {
  $baseUrl = dirname($scriptDir);
} else {
  // Dipakai ketika file berada di root project, misalnya index.php.
  $baseUrl = $scriptDir;
}

$baseUrl = rtrim(str_replace("\\", "/", $baseUrl), "/");
if ($baseUrl === ".") {
  $baseUrl = "";
}

define("BASE_URL", $baseUrl);

define("DENDA_PER_BUKU_PER_HARI", 5000);

// Batas maksimal lama pinjam (hari)
define("MAX_LAMA_PINJAM_HARI", 7);

function url($path = "")
{
  $path = ltrim($path, "/");
  // Spasi pada nama folder (mis. "ruang baca") di-encode agar header
  // Location dan atribut src/href tetap valid.
  return str_replace(" ", "%20", BASE_URL . ($path !== "" ? "/" . $path : ""));
}

// Fungsi escape dipusatkan di sini agar bisa dipakai semua halaman.
if (!function_exists("e")) {
  function e($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
  }
}
