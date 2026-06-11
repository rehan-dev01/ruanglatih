-- =========================================
-- RuangLatih - Database Schema v2.0
-- Update: +NISN, +No Absen, +Kelas Siswa
--         +Kelas Materi, Icon Kategori Tabler
-- =========================================

CREATE DATABASE IF NOT EXISTS ruanglatih CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ruanglatih;

-- =====================
-- TABLE: users
-- =====================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('user','admin','guru') DEFAULT 'user',
    nisn        VARCHAR(10)  DEFAULT NULL COMMENT '10 digit Nomor Induk Siswa Nasional',
    no_absen    TINYINT UNSIGNED DEFAULT NULL COMMENT 'Nomor absen di kelas',
    kelas       VARCHAR(30)  DEFAULT NULL  COMMENT 'Contoh: X IPA 1, XI RPL 2',
    avatar      VARCHAR(255) DEFAULT NULL,
    total_points INT DEFAULT 0,
    streak      INT DEFAULT 0,
    last_active DATE DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================
-- TABLE: categories
-- =====================
CREATE TABLE categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    icon       VARCHAR(50)  DEFAULT 'books' COMMENT 'Nama ikon Tabler Icons, misal: calculator, microscope',
    color      VARCHAR(50)  DEFAULT 'blue',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================
-- TABLE: kelas
-- =====================
CREATE TABLE kelas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(30) NOT NULL UNIQUE COMMENT 'Contoh: X IPA 1, XI RPL 2',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================
-- TABLE: materials
-- =====================
CREATE TABLE materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title       VARCHAR(200) NOT NULL,
    content     LONGTEXT NOT NULL,
    kelas       VARCHAR(30)  DEFAULT 'Semua' COMMENT 'Semua | X | XI | XII | X IPA 1 | dst',
    thumbnail   VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: quizzes
-- =====================
CREATE TABLE quizzes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    material_id      INT NOT NULL,
    title            VARCHAR(200) NOT NULL,
    description      TEXT,
    duration_minutes INT DEFAULT 10,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: questions
-- =====================
CREATE TABLE questions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id       INT NOT NULL,
    question_text TEXT NOT NULL,
    explanation   TEXT,
    order_num     INT DEFAULT 1,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: options
-- =====================
CREATE TABLE options (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct  TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: quiz_results
-- =====================
CREATE TABLE quiz_results (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    quiz_id         INT NOT NULL,
    score           INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    completed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: user_answers
-- =====================
CREATE TABLE user_answers (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    result_id          INT NOT NULL,
    question_id        INT NOT NULL,
    selected_option_id INT,
    is_correct         TINYINT(1) DEFAULT 0,
    FOREIGN KEY (result_id)  REFERENCES quiz_results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: flashcards
-- =====================
CREATE TABLE flashcards (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    question    TEXT NOT NULL,
    answer      TEXT NOT NULL,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: streak_log
-- =====================
CREATE TABLE streak_log (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    log_date  DATE NOT NULL,
    UNIQUE KEY unique_user_date (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: activity_log
-- =====================
CREATE TABLE activity_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description   VARCHAR(255),
    score         INT DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Admin & Sample Users (password: admin123)
INSERT INTO users (name, email, password, role, nisn, no_absen, kelas, total_points, streak) VALUES
('Admin RuangLatih', 'admin@ruanglatih.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, 0, 0),
('Budi Santoso',     'budi@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '0087654321', 5,  'X IPA 1',   850, 7),
('Siti Rahayu',      'siti@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '0087654322', 12, 'XI IPS 2',  720, 5),
('Andi Wijaya',      'andi@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '0087654323', 8,  'X RPL 1',   650, 3),
('Dewi Lestari',     'dewi@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '0087654324', 3,  'XII IPA 1', 900, 10);

-- Categories (icon = nama Tabler Icons)
INSERT INTO categories (name, icon, color) VALUES
('Matematika',       'calculator',  'blue'),
('Bahasa Indonesia', 'language',    'purple'),
('IPA',              'microscope',  'green'),
('IPS',              'globe',       'orange'),
('Pemrograman',      'code',        'indigo');

-- Kelas
INSERT INTO kelas (nama) VALUES
('X IPA 1'),('X IPA 2'),('X IPS 1'),('X IPS 2'),
('X RPL 1'),('X RPL 2'),('X TKJ 1'),
('XI IPA 1'),('XI IPA 2'),('XI IPS 1'),('XI IPS 2'),
('XI RPL 1'),('XI RPL 2'),('XI TKJ 1'),
('XII IPA 1'),('XII IPA 2'),('XII IPS 1'),
('XII RPL 1'),('XII TKJ 1');

-- Materials (dengan kelas)
INSERT INTO materials (category_id, title, content, kelas) VALUES
(1, 'Aljabar Dasar', '<h2>Aljabar Dasar</h2><p>Aljabar adalah cabang matematika yang menggunakan huruf untuk mewakili bilangan yang tidak diketahui.</p><h3>Operasi Dasar</h3><p>Penjumlahan, pengurangan, perkalian, dan pembagian pada variabel.</p><h3>Contoh</h3><p>Jika x + 5 = 10, maka x = 5</p><p>Jika 2x = 14, maka x = 7</p>', 'X'),
(1, 'Geometri: Luas dan Keliling', '<h2>Geometri</h2><p>Geometri mempelajari bentuk, ukuran, dan sifat ruang.</p><h3>Persegi</h3><p>Luas = sisi x sisi | Keliling = 4 x sisi</p><h3>Persegi Panjang</h3><p>Luas = panjang x lebar | Keliling = 2(p+l)</p>', 'X'),
(2, 'Teks Narasi', '<h2>Teks Narasi</h2><p>Teks yang menceritakan peristiwa secara kronologis.</p><h3>Struktur</h3><p>1. Orientasi 2. Komplikasi 3. Resolusi 4. Koda</p>', 'X'),
(3, 'Sistem Tata Surya', '<h2>Sistem Tata Surya</h2><p>Sistem planet yang mengelilingi matahari.</p><h3>8 Planet</h3><p>Merkurius, Venus, Bumi, Mars, Jupiter, Saturnus, Uranus, Neptunus.</p>', 'Semua'),
(5, 'Dasar Pemrograman PHP', '<h2>Dasar PHP</h2><p>PHP adalah bahasa server-side untuk web dinamis.</p><h3>Variabel</h3><p>Diawali $ contoh: $nama = "Budi"</p>', 'XI');

-- Quizzes
INSERT INTO quizzes (material_id, title, description, duration_minutes) VALUES
(1, 'Quiz Aljabar Dasar',      'Uji pemahamanmu tentang aljabar dasar!', 10),
(2, 'Quiz Geometri',           'Tes luas dan keliling bangun datar', 10),
(3, 'Quiz Teks Narasi',        'Pahami struktur teks narasi', 8),
(4, 'Quiz Tata Surya',         'Jelajahi pengetahuan tata surya', 10),
(5, 'Quiz PHP Dasar',          'Uji kemampuan dasar PHP', 12);

-- Questions Quiz 1
INSERT INTO questions (quiz_id, question_text, explanation, order_num) VALUES
(1,'Jika x + 8 = 15, maka x adalah...','x = 15 - 8 = 7',1),
(1,'Jika 3x = 21, maka x adalah...','x = 21 / 3 = 7',2),
(1,'2x + 3 = 11, maka x adalah...','2x = 8, x = 4',3),
(1,'Manakah persamaan linear?','Persamaan linear: variabel pangkat satu.',4),
(1,'5x - 2 = 18, maka x adalah...','5x = 20, x = 4',5);
INSERT INTO options (question_id, option_text, is_correct) VALUES
(1,'5',0),(1,'6',0),(1,'7',1),(1,'8',0),
(2,'5',0),(2,'6',0),(2,'7',1),(2,'8',0),
(3,'3',0),(3,'4',1),(3,'5',0),(3,'6',0),
(4,'x^2+1=0',0),(4,'2x+3=7',1),(4,'x^3=8',0),(4,'x^2-x=0',0),
(5,'3',0),(5,'4',1),(5,'5',0),(5,'6',0);

-- Questions Quiz 2
INSERT INTO questions (quiz_id, question_text, explanation, order_num) VALUES
(2,'Luas persegi sisi 6 cm adalah...','6 x 6 = 36 cm2',1),
(2,'Keliling persegi panjang p=8 l=5 adalah...','2(8+5) = 26 cm',2),
(2,'Luas segitiga alas 10 tinggi 6 adalah...','1/2 x 10 x 6 = 30 cm2',3),
(2,'Rumus luas lingkaran adalah...','Luas = pi x r2',4),
(2,'Keliling lingkaran r=7 (pi=22/7) adalah...','2 x 22/7 x 7 = 44 cm',5);
INSERT INTO options (question_id, option_text, is_correct) VALUES
(6,'24 cm2',0),(6,'30 cm2',0),(6,'36 cm2',1),(6,'42 cm2',0),
(7,'20 cm',0),(7,'24 cm',0),(7,'26 cm',1),(7,'28 cm',0),
(8,'25 cm2',0),(8,'30 cm2',1),(8,'35 cm2',0),(8,'40 cm2',0),
(9,'pi x d',0),(9,'2 x pi x r',0),(9,'pi x r2',1),(9,'4 x sisi',0),
(10,'22 cm',0),(10,'44 cm',1),(10,'66 cm',0),(10,'88 cm',0);

-- Questions Quiz 5
INSERT INTO questions (quiz_id, question_text, explanation, order_num) VALUES
(5,'Simbol variabel PHP adalah...','Variabel PHP diawali $',1),
(5,'Loop untuk iterasi array PHP adalah...','foreach khusus array',2),
(5,'Fungsi output di PHP adalah...','echo menampilkan ke layar',3),
(5,'Ekstensi file PHP adalah...','File PHP ber-ekstensi .php',4),
(5,'Komentar satu baris PHP adalah...','// atau # untuk komentar',5);
INSERT INTO options (question_id, option_text, is_correct) VALUES
(11,'#',0),(11,'@',0),(11,'$',1),(11,'&',0),
(12,'for',0),(12,'while',0),(12,'foreach',1),(12,'loop',0),
(13,'print_out()',0),(13,'display()',0),(13,'echo',1),(13,'show()',0),
(14,'.html',0),(14,'.py',0),(14,'.php',1),(14,'.js',0),
(15,'/* komentar */',0),(15,'<!-- komentar -->',0),(15,'// komentar',1),(15,'## komentar',0);

-- Flashcards
INSERT INTO flashcards (material_id, question, answer) VALUES
(1,'Apa itu variabel dalam aljabar?','Simbol huruf yang mewakili nilai tidak diketahui.'),
(1,'Bagaimana menyelesaikan x + 5 = 12?','x = 12 - 5 = 7'),
(2,'Rumus luas persegi?','Luas = sisi x sisi'),
(2,'Rumus keliling lingkaran?','Keliling = 2 x pi x r'),
(3,'Struktur teks narasi?','Orientasi - Komplikasi - Resolusi - Koda'),
(4,'8 planet tata surya?','Merkurius, Venus, Bumi, Mars, Jupiter, Saturnus, Uranus, Neptunus'),
(5,'Kepanjangan PHP?','PHP = Hypertext Preprocessor'),
(5,'Fungsi echo di PHP?','Menampilkan output/teks ke browser');

-- Sample results
INSERT INTO quiz_results (user_id, quiz_id, score, total_questions, completed_at) VALUES
(2,1,80,5,NOW()-INTERVAL 2 DAY),(2,2,100,5,NOW()-INTERVAL 1 DAY),
(3,1,60,5,NOW()-INTERVAL 3 DAY),(3,5,80,5,NOW()-INTERVAL 1 DAY),
(4,2,60,5,NOW()-INTERVAL 2 DAY),(5,1,100,5,NOW()-INTERVAL 1 DAY),(5,5,100,5,NOW());

UPDATE users SET total_points=850 WHERE id=2;
UPDATE users SET total_points=720 WHERE id=3;
UPDATE users SET total_points=650 WHERE id=4;
UPDATE users SET total_points=900 WHERE id=5;

-- Streak log
INSERT INTO streak_log (user_id, log_date) VALUES
(2,CURDATE()-INTERVAL 6 DAY),(2,CURDATE()-INTERVAL 5 DAY),(2,CURDATE()-INTERVAL 4 DAY),
(2,CURDATE()-INTERVAL 3 DAY),(2,CURDATE()-INTERVAL 2 DAY),(2,CURDATE()-INTERVAL 1 DAY),(2,CURDATE()),
(5,CURDATE()-INTERVAL 9 DAY),(5,CURDATE()-INTERVAL 8 DAY),(5,CURDATE()-INTERVAL 7 DAY),
(5,CURDATE()-INTERVAL 6 DAY),(5,CURDATE()-INTERVAL 5 DAY),(5,CURDATE()-INTERVAL 4 DAY),
(5,CURDATE()-INTERVAL 3 DAY),(5,CURDATE()-INTERVAL 2 DAY),(5,CURDATE()-INTERVAL 1 DAY),(5,CURDATE());

-- =====================
-- TABLE: guru_assignments
-- =====================
-- Menyimpan mapping: guru mengajar kategori apa di kelas mana
CREATE TABLE guru_assignments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    guru_id     INT NOT NULL,
    category_id INT NOT NULL,
    kelas       VARCHAR(30) NOT NULL COMMENT 'Nama kelas spesifik, mis: X IPA 1',
    UNIQUE KEY unique_assignment (guru_id, category_id, kelas),
    FOREIGN KEY (guru_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLE: announcements
-- =====================
CREATE TABLE announcements (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    guru_id      INT NOT NULL,
    title        VARCHAR(200) NOT NULL,
    content      TEXT NOT NULL,
    target_kelas VARCHAR(30) DEFAULT 'Semua' COMMENT 'Semua atau nama kelas spesifik',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- SAMPLE: Guru
-- =====================
-- Guru sample (password: admin123)
INSERT INTO users (name, email, password, role, nisn, no_absen, kelas, total_points, streak) VALUES
('Pak Andi Guru',   'guru.mat@ruanglatih.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru', NULL, NULL, NULL, 0, 0),
('Bu Sari Guru',    'guru.ipa@ruanglatih.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru', NULL, NULL, NULL, 0, 0);

-- Penugasan: Pak Andi → Matematika, X IPA 1 & X IPA 2
INSERT INTO guru_assignments (guru_id, category_id, kelas)
SELECT u.id, c.id, k.nama
FROM users u, categories c, kelas k
WHERE u.email = 'guru.mat@ruanglatih.com'
  AND c.name  = 'Matematika'
  AND k.nama IN ('X IPA 1','X IPA 2');

-- Penugasan: Bu Sari → IPA, XI IPA 1 & XI IPA 2
INSERT INTO guru_assignments (guru_id, category_id, kelas)
SELECT u.id, c.id, k.nama
FROM users u, categories c, kelas k
WHERE u.email = 'guru.ipa@ruanglatih.com'
  AND c.name  = 'IPA'
  AND k.nama IN ('XI IPA 1','XI IPA 2');

-- Contoh pengumuman
INSERT INTO announcements (guru_id, title, content, target_kelas)
SELECT id, 'Selamat Datang di RuangLatih!',
    'Halo siswa X IPA 1 dan X IPA 2! Mulai belajar Matematika lewat fitur Materi dan Quiz ya. Semangat!',
    'Semua'
FROM users WHERE email = 'guru.mat@ruanglatih.com' LIMIT 1;

-- ALTER untuk database yang sudah ada (jalankan jika tabel users sudah ada)
-- ALTER TABLE users MODIFY role ENUM('user','admin','guru') DEFAULT 'user';
