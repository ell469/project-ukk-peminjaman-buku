<?php
require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

$query = $conn->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$data = [];

while ($row = $query->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>