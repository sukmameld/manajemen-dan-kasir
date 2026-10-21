// ========== STATE ==========
let cart = [];
let allProduk = [];
let lastScanned = { code: "", time: 0 };

// ========== AMBIL PRODUK DARI SERVER ==========
async function loadProduk() {
  try {
    const res = await fetch("api.php?action=get_all_produk");
    const data = await res.json();
    if (data.status === "success") allProduk = data.data;
  } catch (e) {
    console.error(e);
  }
}
loadProduk();

// ========== KERANJANG ==========
function addToCart(produk) {
  const existing = cart.find((i) => i.id === produk.id);
  if (existing) {
    existing.qty++;
  } else {
    cart.push({
      id: produk.id,
      nama: produk.nama_produk,
      harga: parseFloat(produk.harga_jual),
      qty: 1,
      diskon: 0,
    });
  }
  renderTable();
}

function addByKeypad(id) {
  const produk = allProduk.find((p) => p.id === id);
  if (produk) addToCart(produk);
  else alert("Produk ID " + id + " tidak ditemukan. Gunakan scan barcode.");
}

function removeItem(index) {
  cart.splice(index, 1);
  renderTable();
}

function clearCart() {
  if (confirm("Kosongkan keranjang?")) {
    cart = [];
    renderTable();
    document.getElementById("pembayaran").value = "";
    document.getElementById("kembalian").value = "";
  }
}

// ========== RENDER TABEL ==========
function renderTable() {
  const tbody = document.querySelector("#tabel-transaksi tbody");
  tbody.innerHTML = "";
  let total = 0;

  cart.forEach((item, index) => {
    let subtotal = item.harga * item.qty;
    total += subtotal;
    tbody.innerHTML += `
            <tr>
                <td>${index + 1}</td>
                <td>${item.nama}</td>
                <td>Rp ${item.harga.toLocaleString()}</td>
                <td>
                    <button onclick="ubahQty(${index}, -1)">-</button>
                    ${item.qty}
                    <button onclick="ubahQty(${index}, 1)">+</button>
                </td>
                <td>${item.diskon}</td>
                <td>Rp ${subtotal.toLocaleString()}</td>
                <td><button onclick="removeItem(${index})" style="background:red;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;">X</button></td>
            </tr>
        `;
  });

  document.getElementById("total-harga").value = total;
  hitungKembalian();
}

function ubahQty(index, val) {
  cart[index].qty += val;
  if (cart[index].qty <= 0) cart.splice(index, 1);
  renderTable();
}

// ========== KEMBALIAN ==========
function hitungKembalian() {
  const total = parseFloat(document.getElementById("total-harga").value) || 0;
  const bayar = parseFloat(document.getElementById("pembayaran").value) || 0;
  document.getElementById("kembalian").value = (bayar - total).toLocaleString();
}

// ========== SCANNER (pakai Html5QrcodeScanner seperti z.html) ==========
function onScanSuccess(barcode) {
  // Debounce: cegah scan dobel dalam 2 detik
  const now = Date.now();
  if (barcode === lastScanned.code && now - lastScanned.time < 2000) return;
  lastScanned = { code: barcode, time: now };

  console.log("✅ Scanned:", barcode);
  const resultEl = document.getElementById("scan-result");
  resultEl.innerText = "Memproses " + barcode + "...";
  resultEl.style.color = "blue";

  fetch(
    "api.php?action=get_produk_by_barcode&barcode=" +
      encodeURIComponent(barcode),
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        addToCart(data.data);
        resultEl.innerText = "✅ " + data.data.nama_produk + " ditambahkan";
        resultEl.style.color = "green";
      } else {
        resultEl.innerText = "❌ Barcode " + barcode + " tidak ditemukan";
        resultEl.style.color = "red";
      }
    })
    .catch((err) => {
      resultEl.innerText = "Error: " + err;
      resultEl.style.color = "red";
    });
}

function onScanError(err) {
  // Diabaikan — terjadi tiap frame yang gagal baca, terlalu berisik kalau di-log
}

// Init scanner (auto-render tombol "Start Scanning")
const html5QrcodeScanner = new Html5QrcodeScanner(
  "reader",
  { fps: 10, qrbox: 200 },
  /* verbose= */ false,
);
html5QrcodeScanner.render(onScanSuccess, onScanError);

// ========== SIMPAN TRANSAKSI ==========
async function saveTransaction(status) {
  if (cart.length === 0) {
    alert("Keranjang kosong!");
    return;
  }

  const total = parseFloat(document.getElementById("total-harga").value) || 0;
  const bayar = parseFloat(document.getElementById("pembayaran").value) || 0;
  const kembalian = bayar - total;

  if (status === "selesai" && bayar < total) {
    alert("Pembayaran kurang!");
    return;
  }

  const payload = { status, cart, total, bayar, kembalian };

  try {
    const res = await fetch("api.php?action=save_transaction", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await res.json();

    if (result.status === "success") {
      alert(
        `Transaksi ${status.toUpperCase()} berhasil!\nNo: ${result.no_transaksi}`,
      );
      if (status === "selesai") {
        cart = [];
        renderTable();
        document.getElementById("pembayaran").value = "";
        document.getElementById("kembalian").value = "";
      }
    } else {
      alert("Gagal: " + result.message);
    }
  } catch (e) {
    alert("Error: " + e);
  }
}
