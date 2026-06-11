<?php
// ============================================
// RuangLatih - Konfigurasi Database
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ruanglatih');
define('APP_NAME', 'RuangLatih');
define('APP_URL', 'http://localhost/ruanglatih');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:40px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;">
                <h2>'.icon('red-x').' Koneksi Database Gagal</h2>
                <p>' . $conn->connect_error . '</p>
                <p>Pastikan XAMPP berjalan dan database <strong>ruanglatih</strong> sudah dibuat.</p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
