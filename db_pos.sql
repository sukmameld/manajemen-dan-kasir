CREATE DATABASE db_pos;
USE db_pos;

-- Tabel Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kasir') NOT NULL
);
INSERT INTO users (username, password, role) VALUES 
('admin', 'admin123', 'admin'),
('kasir', 'kasir123', 'kasir');

-- Tabel Produk
CREATE TABLE produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) UNIQUE,
    nama_produk VARCHAR(100) NOT NULL,
    harga_jual DECIMAL(10,2) NOT NULL,
    stok INT NOT NULL
);
INSERT INTO produk (barcode, nama_produk, harga_jual, stok) VALUES 
('123456', 'Kopi Hitam', 15000, 50),
('789012', 'Roti Bakar', 20000, 30),
('111222', 'Teh Manis', 8000, 100);

-- Tabel Pelanggan
CREATE TABLE pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    alamat TEXT,
    no_telp VARCHAR(20)
);

-- Tabel Transaksi
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_transaksi VARCHAR(50) NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    bayar DECIMAL(10,2) NOT NULL,
    kembalian DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'selesai') DEFAULT 'draft',
    kasir_id INT
);

-- Tabel Transaksi Detail (produk_id boleh NULL untuk item manual)
CREATE TABLE transaksi_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT,
    produk_id INT NULL,
    nama_produk VARCHAR(100) NULL,
    harga DECIMAL(10,2) NULL,
    qty INT,
    subtotal DECIMAL(10,2),
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id)
);

-- Tabel Pengaturan (untuk logo)
CREATE TABLE pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_key VARCHAR(50) UNIQUE NOT NULL,
    nilai TEXT
);
INSERT INTO pengaturan (nama_key, nilai) VALUES ('logo', '');