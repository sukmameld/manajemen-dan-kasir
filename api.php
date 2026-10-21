<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ========== LOGOUT ==========
if ($action == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// ========== GET ALL PRODUK ==========
if ($action == 'get_all_produk') {
    $res = $conn->query("SELECT * FROM produk");
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

// ========== GET PRODUK BY BARCODE ==========
if ($action == 'get_produk_by_barcode') {
    $barcode = $_GET['barcode'] ?? '';
    $stmt = $conn->prepare("SELECT * FROM produk WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "success", "data" => $result->fetch_assoc()]);
    } else {
        echo json_encode(["status" => "error", "message" => "Produk tidak ditemukan"]);
    }
    exit;
}

// ========== SAVE TRANSAKSI ==========
if ($action == 'save_transaction') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['status'])) {
        echo json_encode(["status" => "error", "message" => "Data tidak valid"]);
        exit;
    }
    $status = $input['status'];
    $total  = $input['total'];
    $bayar  = $input['bayar'];
    $kembalian = $input['kembalian'];
    $kasir_id = $_SESSION['user_id'] ?? 0;
    $no_transaksi = "TRX-" . date('YmdHis');
    
    $stmt = $conn->prepare("INSERT INTO transaksi (no_transaksi, total, bayar, kembalian, status, kasir_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdddsi", $no_transaksi, $total, $bayar, $kembalian, $status, $kasir_id);
    
    if ($stmt->execute()) {
        $transaksi_id = $conn->insert_id;
        foreach ($input['cart'] as $item) {
            $subtotal = $item['harga'] * $item['qty'];
            
            // Jika ID negatif → item manual
            if ($item['id'] < 0) {
                $stmt2 = $conn->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, nama_produk, harga, qty, subtotal) VALUES (?, NULL, ?, ?, ?, ?)");
                $stmt2->bind_param("isddd", $transaksi_id, $item['nama'], $item['harga'], $item['qty'], $subtotal);
            } else {
                $stmt2 = $conn->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, subtotal) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("iiid", $transaksi_id, $item['id'], $item['qty'], $subtotal);
            }
            $stmt2->execute();
        }
        echo json_encode(["status" => "success", "no_transaksi" => $no_transaksi]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Action tidak dikenal"]);
?>