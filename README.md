# RuangLatih

> Platform Belajar Interaktif untuk Siswa — PHP Native + MySQL + Tailwind CSS

---

## Cara Setup

### Kebutuhan Sistem

- XAMPP (Apache + PHP 8.0+ + MySQL)
- Browser modern

### Langkah Instalasi

1. **Copy folder ke XAMPP**

   ```
   Salin folder `ruanglatih/` ke:
   C:\xampp\htdocs\ruanglatih\
   ```

2. **Buat Database**
   - Buka `http://localhost/phpmyadmin`
   - Buat database baru bernama `ruanglatih`
   - Import file `database.sql`

3. **Sesuaikan Konfigurasi**
   - Buka `config/database.php`
   - Sesuaikan `DB_USER`, `DB_PASS`, dan `APP_URL`

4. **Akses Aplikasi**
   ```
   http://localhost/ruanglatih/
   ```

---

<!-- untuk buat admin baru register sebagai siswa lalu bisa ubah role di database sql -->
<!-- untuk membuat akun guru bisa di buat oleh admin terlebih dahulu di panel admin>data penguna>data guru -->
<!-- untuk murid cukup registrasi dan login seperti biasa di awal login-->

## 🔑 Demo Login

| Role  | Email                       | Password |
| ----- | --------------------------- | -------- |
| murid | ahmadkasim@gmail.com        | 123456   |
| guru  | mtk@gmail.com               | 123456   |
| Admin | admin12@gmail.com           | 123456   |

---

## 📁 Struktur Folder

```
ruanglatih/
├── 📄 index.php                   → Redirect otomatis
├── 📄 dashboard.php               → Dashboard user
├── 📄 database.sql                → Schema + sample data
│
├── 📂 config/
│   └── database.php               → Koneksi DB + konstanta
│
├── 📂 includes/
│   ├── functions.php              → Auth, helper functions
│   ├── header.php                 → Layout header + navbar
│   ├── sidebar.php                → Sidebar navigasi
│   └── footer.php                 → Footer + JS
│
├── 📂 auth/
│   ├── login.php                  → Halaman login
│   ├── register.php               → Halaman register
│   └── logout.php                 → Logout + clear session
│
├── 📂 pages/
│   ├── materi.php                 → Daftar materi + search
│   ├── materi-detail.php          → Detail materi + quiz
│   ├── quiz.php                   → Daftar quiz
│   ├── quiz-play.php              → Main quiz interaktif
│   ├── quiz-result.php            → Hasil + review jawaban
│   ├── flashcard.php              → Flashcard flip animasi
│   ├── progress.php               → Progress tracking
│   └── leaderboard.php            → Papan peringkat
│
└── 📂 admin/
    ├── dashboard.php              → Admin overview
    ├── materi.php                 → CRUD materi
    ├── quiz.php                   → CRUD quiz & soal
    └── users.php                  → Kelola data siswa
```

---

## Fitur Lengkap

### User

- ✅ Register & Login dengan session
- ✅ Dashboard dengan statistik & chart (Chart.js)
- ✅ Materi belajar dengan search & filter kategori
- ✅ Quiz interaktif pilihan ganda + timer countdown
- ✅ Feedback jawaban benar/salah + penjelasan
- ✅ Flashcard dengan animasi flip CSS 3D
- ✅ Progress tracking dengan grafik
- ✅ Leaderboard dengan podium Top 3
- ✅ Streak system otomatis

### Admin

- ✅ Dashboard admin dengan statistik platform
- ✅ CRUD materi belajar (modal form)
- ✅ CRUD quiz + tambah soal pilihan ganda
- ✅ Kelola data siswa (reset poin, hapus user)

---

## Teknologi

| Layer    | Stack                       |
| -------- | --------------------------- |
| Frontend | HTML5, Tailwind CSS CDN     |
| Backend  | PHP 8 Native (no framework) |
| Database | MySQL / MariaDB             |
| Chart    | Chart.js 4.x                |
| Font     | Plus Jakarta Sans (Google)  |
| Server   | Apache (XAMPP)              |

---

## Konfigurasi `config/database.php`

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // sesuaikan
define('DB_PASS', '');            // sesuaikan
define('DB_NAME', 'ruanglatih');
define('APP_NAME', 'RuangLatih');
define('APP_URL',  'http://localhost/ruanglatih');  // sesuaikan
```

---

## Warna & Desain

- **Primary**: Indigo (#4F46E5)
- **Secondary**: Violet (#7C3AED)
- **Font**: Plus Jakarta Sans
- **Style**: Modern minimal, card-based, responsive

---

_Dibuat dengan PHP Native — cocok untuk belajar backend dasar tanpa framework._
# ruanglatih
