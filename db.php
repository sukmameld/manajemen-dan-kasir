<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_pos";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// === AUTO-CREATE TABLE PENGATURAN JIKA BELUM ADA ===
$conn->query("CREATE TABLE IF NOT EXISTS pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_key VARCHAR(50) UNIQUE NOT NULL,
    nilai TEXT
)");

// Cek apakah key 'logo' sudah ada, kalau belum, buat
$cek = $conn->query("SELECT * FROM pengaturan WHERE nama_key='logo'");
if ($cek->num_rows == 0) {
    $conn->query("INSERT INTO pengaturan (nama_key, nilai) VALUES ('logo', '')");
}

function getPengaturan($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE nama_key = ?");
        if (!$stmt) return $default;
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['nilai'];
        }
    } catch (Exception $e) {
        return $default;
    }
    return $default;
}

function setPengaturan($key, $value) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO pengaturan (nama_key, nilai) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
}

function renderLogo($class = 'logo-img') {
    $logo = getPengaturan('logo');
    if (!empty($logo) && file_exists(__DIR__ . '/' . $logo)) {
        $v = filemtime(__DIR__ . '/' . $logo);
        return '<img src="' . htmlspecialchars($logo) . '?v=' . $v . '" alt="Logo" class="' . $class . '">';
    }
    $defaultLogo = 'assets/logo.png';
    if (file_exists(__DIR__ . '/' . $defaultLogo)) {
        $v = filemtime(__DIR__ . '/' . $defaultLogo);
        return '<img src="' . $defaultLogo . '?v=' . $v . '" alt="Logo Default" class="' . $class . '">';
    }
    return '<span class="logo-fallback">LOGO</span>';
}
?>