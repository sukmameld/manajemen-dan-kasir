<?php
session_start();
include 'db.php'; // <--- INI WAJIB ADA

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'kasir') {
    header("Location: index.php");
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kasir - Matcha FT</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body class="kasir-body">

    <!-- PANEL KIRI: SCANNER -->
    <div class="panel panel-scanner">
        <div class="logo-box small">
            <?= renderLogo('logo-img') ?>
        </div>
        <h3 style="margin-top:15px; text-align:center;">SCAN QR / BARCODE</h3>
        
        <!-- Kotak Scanner -->
        <div id="reader" style="width: 100%; border-radius: 10px; overflow: hidden;"></div>
        
        <p id="scan-result" style="text-align:center; font-weight:bold; margin-top:10px; color:#333;"></p>
        <a href="api.php?action=logout" class="btn-black logout-btn">LOGOUT</a>
    </div>

    <!-- PANEL KANAN: KERANJANG -->
    <div class="panel panel-keranjang">
        <div class="header-keranjang">
            <h2>Keranjang Transaksi</h2>
            <button class="btn-black" onclick="openManualModal()">+ TAMBAH MANUAL</button>
        </div>
        <div class="info-row">
            <span>Tanggal: <?= date('Y-m-d H:i') ?></span>
            <span>Petugas: <?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>

        <table id="tabel-produk">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="cart-body"></tbody>
        </table>

        <div class="total-section">
            <div><label>Total:</label><input type="text" id="total-harga" readonly value="0"></div>
            <div><label>Pembayaran:</label><input type="number" id="pembayaran" onkeyup="hitungKembalian()" placeholder="0"></div>
            <div><label>Kembalian:</label><input type="text" id="kembalian" readonly value="0"></div>
        </div>

        <div class="action-buttons">
            <button class="btn-black" onclick="clearCart()">CLEAR</button>
            <button class="btn-black" onclick="saveTransaction('draft')">DRAFT</button>
            <button class="btn-black" onclick="saveTransaction('selesai')">SAVE</button>
            <button class="btn-black" onclick="window.print()">PRINT</button>
        </div>
    </div>

    <!-- MODAL TAMBAH MANUAL -->
    <div id="manual-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeManualModal()">&times;</span>
            <h3>Tambah Produk Manual</h3>
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="switchTab('pilih')">Pilih Produk</button>
                <button class="tab-btn" onclick="switchTab('manual')">Input Manual</button>
            </div>
            <div id="tab-pilih" class="tab-content">
                <input type="text" id="search-produk" placeholder="Cari nama produk..." onkeyup="filterProduk()"
                    style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #999; border-radius:5px;">
                <div id="produk-list" class="produk-list"></div>
            </div>
            <div id="tab-manual" class="tab-content" style="display:none;">
                <input type="text" id="manual-nama" placeholder="Nama Produk" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #999; border-radius:5px;">
                <input type="number" id="manual-harga" placeholder="Harga (Rp)" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #999; border-radius:5px;">
                <input type="number" id="manual-qty" placeholder="Qty" value="1" min="1" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #999; border-radius:5px;">
                <button class="btn-black" style="width:100%;" onclick="addManualItem()">TAMBAH KE KERANJANG</button>
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        let lastScanned = { code: "", time: 0 };
        let allProduk = [];

        async function loadProduk() {
            try {
                const res = await fetch('api.php?action=get_all_produk');
                const data = await res.json();
                if (data.status === 'success') {
                    allProduk = data.data;
                    renderProdukList(allProduk);
                }
            } catch (e) { console.error(e); }
        }

        function renderTable() {
            const tbody = document.getElementById("cart-body");
            let total = 0;
            tbody.innerHTML = "";
            if (cart.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="color:#999; padding:20px; text-align:center;">Keranjang kosong. Silakan scan barcode atau tambah manual.</td></tr>`;
            }
            cart.forEach((item, index) => {
                let subtotal = item.harga * item.qty;
                total += subtotal;
                tbody.innerHTML += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.nama}</td>
                        <td>Rp ${item.harga.toLocaleString('id-ID')}</td>
                        <td>
                            <button class="btn-qty" onclick="ubahQty(${index}, -1)">−</button>
                            <span style="margin:0 8px; font-weight:bold;">${item.qty}</span>
                            <button class="btn-qty" onclick="ubahQty(${index}, 1)">+</button>
                        </td>
                        <td>Rp ${subtotal.toLocaleString('id-ID')}</td>
                        <td><button class="btn-red" onclick="removeItem(${index})">X</button></td>
                    </tr>`;
            });
            document.getElementById('total-harga').value = total.toLocaleString('id-ID');
            hitungKembalian();
        }

        function ubahQty(index, val) {
            cart[index].qty += val;
            if (cart[index].qty <= 0) cart.splice(index, 1);
            renderTable();
        }

        function removeItem(index) {
            cart.splice(index, 1);
            renderTable();
        }

        function clearCart() {
            if (cart.length === 0) return;
            if (confirm("Kosongkan keranjang?")) {
                cart = [];
                renderTable();
                document.getElementById('pembayaran').value = "";
                document.getElementById('kembalian').value = "0";
            }
        }

        function hitungKembalian() {
            const totalStr = document.getElementById('total-harga').value.replace(/\./g, '');
            const total = parseFloat(totalStr) || 0;
            const bayar = parseFloat(document.getElementById('pembayaran').value) || 0;
            document.getElementById('kembalian').value = (bayar - total).toLocaleString('id-ID');
        }

        function onScanSuccess(barcode) {
            const now = Date.now();
            if (barcode === lastScanned.code && (now - lastScanned.time) < 2000) return;
            lastScanned = { code: barcode, time: now };

            const resultEl = document.getElementById('scan-result');
            resultEl.innerText = "Memproses " + barcode + "...";
            resultEl.style.color = "blue";

            fetch("api.php?action=get_produk_by_barcode&barcode=" + encodeURIComponent(barcode))
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const produk = data.data;
                        addToCart(produk.id, produk.nama_produk, parseFloat(produk.harga_jual));
                        resultEl.innerText = "✅ " + produk.nama_produk + " ditambahkan";
                        resultEl.style.color = "green";
                    } else {
                        resultEl.innerText = "❌ Barcode " + barcode + " tidak ditemukan";
                        resultEl.style.color = "red";
                    }
                })
                .catch(err => {
                    resultEl.innerText = "⚠️ Error koneksi server";
                    resultEl.style.color = "red";
                });
        }

        function onScanError() {}

        function addToCart(id, nama, harga) {
            const existing = cart.find(i => i.id === id);
            if (existing) existing.qty++;
            else cart.push({ id, nama, harga, qty: 1 });
            renderTable();
        }

        function openManualModal() {
            document.getElementById('manual-modal').style.display = 'block';
            document.getElementById('search-produk').value = '';
            document.getElementById('manual-nama').value = '';
            document.getElementById('manual-harga').value = '';
            document.getElementById('manual-qty').value = '1';
            renderProdukList(allProduk);
            switchTab('pilih');
        }

        function closeManualModal() {
            document.getElementById('manual-modal').style.display = 'none';
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            if (tab === 'pilih') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('tab-pilih').style.display = 'block';
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('tab-manual').style.display = 'block';
            }
        }

        function renderProdukList(produkArr) {
            const container = document.getElementById('produk-list');
            if (produkArr.length === 0) {
                container.innerHTML = '<p style="color:#999; text-align:center;">Tidak ada produk.</p>';
                return;
            }
            container.innerHTML = '';
            produkArr.forEach(p => {
                const harga = parseFloat(p.harga_jual);
                container.innerHTML += `
                    <div class="produk-item" onclick="pilihProduk(${p.id}, '${p.nama_produk.replace(/'/g, "\\'")}', ${harga})">
                        <div>
                            <strong>${p.nama_produk}</strong><br>
                            <small style="color:#666;">Barcode: ${p.barcode || '-'}</small>
                        </div>
                        <div style="text-align:right;">
                            <strong>Rp ${harga.toLocaleString('id-ID')}</strong><br>
                            <small style="color:#666;">Stok: ${p.stok}</small>
                        </div>
                    </div>`;
            });
        }

        function filterProduk() {
            const keyword = document.getElementById('search-produk').value.toLowerCase();
            const filtered = allProduk.filter(p =>
                p.nama_produk.toLowerCase().includes(keyword) ||
                (p.barcode && p.barcode.toLowerCase().includes(keyword))
            );
            renderProdukList(filtered);
        }

        function pilihProduk(id, nama, harga) {
            addToCart(id, nama, harga);
            closeManualModal();
        }

        function addManualItem() {
            const nama = document.getElementById('manual-nama').value.trim();
            const harga = parseFloat(document.getElementById('manual-harga').value) || 0;
            const qty = parseInt(document.getElementById('manual-qty').value) || 1;
            if (!nama) { alert("Nama produk wajib diisi!"); return; }
            if (harga <= 0) { alert("Harga harus lebih dari 0!"); return; }
            if (qty <= 0) { alert("Qty harus lebih dari 0!"); return; }
            cart.push({ id: -Date.now(), nama, harga, qty });
            renderTable();
            closeManualModal();
        }

        async function saveTransaction(status) {
            if (cart.length === 0) { alert("Keranjang kosong!"); return; }
            const totalStr = document.getElementById('total-harga').value.replace(/\./g, '');
            const total = parseFloat(totalStr) || 0;
            const bayar = parseFloat(document.getElementById('pembayaran').value) || 0;
            const kembalian = bayar - total;
            if (status === 'selesai' && bayar < total) {
                alert("Pembayaran kurang! Total: Rp " + total.toLocaleString('id-ID'));
                return;
            }
            const payload = { status, cart, total, bayar, kembalian };
            try {
                const res = await fetch('api.php?action=save_transaction', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (result.status === 'success') {
                    alert(`Transaksi ${status.toUpperCase()} berhasil!\nNo: ${result.no_transaksi}`);
                    if (status === 'selesai') {
                        cart = [];
                        renderTable();
                        document.getElementById('pembayaran').value = "";
                        document.getElementById('kembalian').value = "0";
                    }
                } else {
                    alert("Gagal: " + result.message);
                }
            } catch (e) { alert("Error: " + e); }
        }

        // === INIT SCANNER (Paling Stabil) ===
        const html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { fps: 10, qrbox: 200, rememberLastUsedCamera: true }, 
            false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanError);

        loadProduk();
        renderTable();
    </script>
</body>
</html>