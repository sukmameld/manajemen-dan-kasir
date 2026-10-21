<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// ========== UPLOAD LOGO ==========
if (isset($_POST['upload_logo'])) {
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if ($_FILES['logo_file']['size'] > 2 * 1024 * 1024) {
            $error_logo = "Ukuran file maksimal 2MB.";
        } elseif (in_array($ext, $allowed)) {
            $oldLogo = getPengaturan('logo');
            if (!empty($oldLogo) && file_exists($oldLogo)) unlink($oldLogo);
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            $newName = 'uploads/logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $newName)) {
                setPengaturan('logo', $newName);
                $success_logo = "Logo berhasil diupload!";
            } else {
                $error_logo = "Gagal menyimpan file.";
            }
        } else {
            $error_logo = "Format harus JPG, PNG, GIF, SVG, atau WEBP.";
        }
    } else {
        $error_logo = "Pilih file logo terlebih dahulu.";
    }
}

// ========== HAPUS LOGO ==========
if (isset($_POST['hapus_logo'])) {
    $oldLogo = getPengaturan('logo');
    if (!empty($oldLogo) && file_exists($oldLogo)) unlink($oldLogo);
    setPengaturan('logo', '');
    header("Location: admin.php?page=logo");
    exit;
}

// ========== TAMBAH PRODUK ==========
if (isset($_POST['tambah_produk'])) {
    $barcode = $_POST['barcode'];
    $nama    = $_POST['nama_produk'];
    $harga   = $_POST['harga_jual'];
    $stok    = $_POST['stok'];
    $stmt = $conn->prepare("INSERT INTO produk (barcode, nama_produk, harga_jual, stok) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $barcode, $nama, $harga, $stok);
    $stmt->execute();
    header("Location: admin.php?page=produk");
    exit;
}

// ========== HAPUS PRODUK ==========
if (isset($_GET['hapus_produk'])) {
    $id = intval($_GET['hapus_produk']);
    $conn->query("DELETE FROM produk WHERE id=$id");
    header("Location: admin.php?page=produk");
    exit;
}

// ========== TAMBAH PELANGGAN ==========
if (isset($_POST['tambah_pelanggan'])) {
    $nama    = $_POST['nama'];
    $alamat  = $_POST['alamat'];
    $no_telp = $_POST['no_telp'];
    $stmt = $conn->prepare("INSERT INTO pelanggan (nama, alamat, no_telp) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama, $alamat, $no_telp);
    $stmt->execute();
    header("Location: admin.php?page=pelanggan");
    exit;
}

// ========== HAPUS PELANGGAN ==========
if (isset($_GET['hapus_pelanggan'])) {
    $id = intval($_GET['hapus_pelanggan']);
    $conn->query("DELETE FROM pelanggan WHERE id=$id");
    header("Location: admin.php?page=pelanggan");
    exit;
}

// ========== STATISTIK ==========
$q_total = $conn->query("SELECT IFNULL(SUM(total),0) AS total FROM transaksi WHERE status='selesai'");
$total_penjualan = $q_total->fetch_assoc()['total'];
$q_item = $conn->query("SELECT IFNULL(SUM(qty),0) AS total FROM transaksi_detail INNER JOIN transaksi ON transaksi_detail.transaksi_id = transaksi.id WHERE transaksi.status='selesai'");
$total_item = $q_item->fetch_assoc()['total'];
$q_trx = $conn->query("SELECT COUNT(*) AS total FROM transaksi WHERE status='selesai'");
$total_transaksi = $q_trx->fetch_assoc()['total'];
$q_pel = $conn->query("SELECT COUNT(*) AS total FROM pelanggan");
$total_pelanggan = $q_pel->fetch_assoc()['total'];
$q_produk = $conn->query("
    SELECT p.nama_produk, IFNULL(SUM(td.qty),0) AS qty_terjual
    FROM produk p
    LEFT JOIN transaksi_detail td ON td.produk_id = p.id
    LEFT JOIN transaksi t ON t.id = td.transaksi_id AND t.status='selesai'
    GROUP BY p.id, p.nama_produk
    HAVING qty_terjual > 0
    ORDER BY qty_terjual DESC
");
$stat_produk = [];
while ($row = $q_produk->fetch_assoc()) $stat_produk[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <div class="logo-box wide">
            <?= renderLogo('logo-img') ?>
        </div>
        <div class="menu-grid">
            <div class="menu-card" onclick="location.href='admin.php?page=produk'">PRODUK</div>
            <div class="menu-card" onclick="location.href='admin.php?page=pelanggan'">PELANGGAN</div>
            <div class="menu-card" onclick="location.href='admin.php?page=statistik'">STATISTIK</div>
            <div class="menu-card" onclick="location.href='admin.php?page=logo'">LOGO</div>
        </div>
        <div style="text-align:right; margin:10px 0;">
            <a href="api.php?action=logout" class="btn-black" style="text-decoration:none; padding:8px 15px;">LOGOUT</a>
        </div>
        <div class="content-area">
            <?php
            $page = $_GET['page'] ?? 'statistik';
            if ($page == 'produk') {
                echo "<h2>Data Produk</h2>";
                echo "<table><tr><th>No</th><th>Barcode</th><th>Nama</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>";
                $res = $conn->query("SELECT * FROM produk ORDER BY id DESC");
                $no = 1;
                while($row = $res->fetch_assoc()) {
                    echo "<tr><td>$no</td><td>{$row['barcode']}</td><td>{$row['nama_produk']}</td><td>Rp " . number_format($row['harga_jual'], 0, ',', '.') . "</td><td>{$row['stok']}</td><td><a href='admin.php?hapus_produk={$row['id']}' onclick='return confirm(\"Hapus?\")' style='color:red;'>Hapus</a></td></tr>";
                    $no++;
                }
                echo "</table>";
                echo "<hr style='margin:20px 0;'><h3>Tambah Produk</h3>";
                echo "<form method='POST' style='display:flex; gap:5px; flex-wrap:wrap;'>
                        <input type='text' name='barcode' placeholder='Barcode' required style='padding:8px;'>
                        <input type='text' name='nama_produk' placeholder='Nama Produk' required style='padding:8px;'>
                        <input type='number' name='harga_jual' placeholder='Harga' required style='padding:8px;'>
                        <input type='number' name='stok' placeholder='Stok' required style='padding:8px;'>
                        <button class='btn-black' name='tambah_produk'>TAMBAH</button>
                      </form>";
            } elseif ($page == 'pelanggan') {
                echo "<h2>Data Pelanggan</h2>";
                echo "<table><tr><th>No</th><th>Nama</th><th>Alamat</th><th>No Telp</th><th>Aksi</th></tr>";
                $res = $conn->query("SELECT * FROM pelanggan ORDER BY id DESC");
                $no = 1;
                if ($res->num_rows == 0) echo "<tr><td colspan='5' style='color:#999;'>Belum ada pelanggan.</td></tr>";
                while($row = $res->fetch_assoc()) {
                    echo "<tr><td>$no</td><td>" . htmlspecialchars($row['nama']) . "</td><td>" . htmlspecialchars($row['alamat']) . "</td><td>" . htmlspecialchars($row['no_telp']) . "</td><td><a href='admin.php?hapus_pelanggan={$row['id']}' onclick='return confirm(\"Hapus?\")' style='color:red;'>Hapus</a></td></tr>";
                    $no++;
                }
                echo "</table>";
                echo "<hr style='margin:20px 0;'><h3>Tambah Pelanggan</h3>";
                echo "<form method='POST' style='display:flex; gap:5px; flex-wrap:wrap;'>
                        <input type='text' name='nama' placeholder='Nama Pelanggan' required style='padding:8px;'>
                        <input type='text' name='alamat' placeholder='Alamat' style='padding:8px;'>
                        <input type='text' name='no_telp' placeholder='No Telp' style='padding:8px;'>
                        <button class='btn-black' name='tambah_pelanggan'>TAMBAH</button>
                      </form>";
            } elseif ($page == 'logo') {
                ?>
                <h2>Pengaturan Logo</h2>
                <?php if (isset($success_logo)): ?><p style="color:green; font-weight:bold;"><?= $success_logo ?></p><?php endif; ?>
                <?php if (isset($error_logo)): ?><p style="color:red; font-weight:bold;"><?= $error_logo ?></p><?php endif; ?>
                <div style="margin:20px 0; padding:20px; background:#f4f4f9; border-radius:10px; text-align:center;">
                    <p style="margin-bottom:15px; font-weight:bold;">Logo Saat Ini:</p>
                    <div style="background:#000; display:inline-block; padding:20px 40px; border-radius:10px; min-width:200px;">
                        <?= renderLogo('logo-preview') ?>
                    </div>
                </div>
                <h3>Upload Logo Baru</h3>
                <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
                    <input type="file" name="logo_file" accept="image/*" required style="padding:8px; border:1px solid #999; border-radius:5px;">
                    <button class="btn-black" name="upload_logo" style="margin-left:10px;">UPLOAD</button>
                </form>
                <p style="color:#666; font-size:12px; margin-top:8px;">Format: JPG, PNG, GIF, SVG, WEBP. Maks 2MB.</p>
                <?php if (!empty(getPengaturan('logo'))): ?>
                    <hr style="margin:20px 0;">
                    <form method="POST" onsubmit="return confirm('Hapus logo? Kembali ke default.');">
                        <button class="btn-black" name="hapus_logo" style="background:#d32f2f;">HAPUS LOGO</button>
                    </form>
                <?php endif;
            } else {
                ?>
                <h2>Statistik</h2>
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="stat-card"><div class="stat-label">Total Penjualan</div><div class="stat-value">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></div></div>
                    <div class="stat-card"><div class="stat-label">Item Terjual</div><div class="stat-value"><?= number_format($total_item, 0, ',', '.') ?></div></div>
                    <div class="stat-card"><div class="stat-label">Total Transaksi</div><div class="stat-value"><?= $total_transaksi ?></div></div>
                    <div class="stat-card"><div class="stat-label">Total Pelanggan</div><div class="stat-value"><?= $total_pelanggan ?></div></div>
                </div>
                <h3 style="margin-top:30px;">Persentase Penjualan per Produk</h3>
                <?php if (count($stat_produk) > 0): ?>
                    <div style="max-width:500px; margin: 20px auto;">
                        <canvas id="chartProduk"></canvas>
                    </div>
                    <table style="max-width:700px; margin: 20px auto;">
                        <tr><th>Produk</th><th>Qty Terjual</th><th>Persentase</th></tr>
                        <?php
                        $grand_total_item = $total_item > 0 ? $total_item : 1;
                        foreach ($stat_produk as $p) {
                            $pct = ($p['qty_terjual'] / $grand_total_item) * 100;
                            echo "<tr><td>{$p['nama_produk']}</td><td>{$p['qty_terjual']}</td><td>" . number_format($pct, 1) . "%</td></tr>";
                        }
                        ?>
                    </table>
                    <script>
                        const ctx = document.getElementById('chartProduk').getContext('2d');
                        new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: <?= json_encode(array_column($stat_produk, 'nama_produk')) ?>,
                                datasets: [{
                                    data: <?= json_encode(array_map('intval', array_column($stat_produk, 'qty_terjual'))) ?>,
                                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#8AC926'],
                                    borderWidth: 2,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const total = context.dataset.data.reduce((a,b) => a+b, 0);
                                                const val = context.parsed;
                                                const pct = ((val / total) * 100).toFixed(1);
                                                return context.label + ': ' + val + ' (' + pct + '%)';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                <?php else: ?>
                    <p style="text-align:center; color:#999; padding:30px;">Belum ada data penjualan.</p>
                <?php endif;
            }
            ?>
        </div>
    </div>
</body>
</html>