# CaborNation: Platform Pendaftaran Semua Cabang Olahraga

CaborNation adalah sistem web yang menyediakan solusi lengkap untuk pendaftaran dan manajemen berbagai turnamen olahraga. Platform ini dirancang untuk memfasilitasi komunikasi dan transaksi antara **Event Organizer (EO)** dan **Peserta (User)**.

---

## Fitur Utama Platform

### 1. Manajemen Turnamen (Event Organizer)
* **Pembuatan Event:** EO dapat membuat, mengedit, dan mempublikasikan turnamen baru.
* **Pengaturan Detail:** Mengatur jadwal, lokasi, biaya pendaftaran, dan persyaratan turnamen.
* **Pengelolaan Peserta:** Melihat dan memproses pendaftar (tim/individu), termasuk fungsi **Menerima (Accept)** dan **Mengeluarkan (Kick)** peserta.
* **Kontrol Status:** Mengatur status turnamen secara real-time (Open, Closed, Ongoing, Finished).

### 2. Sistem Role Otorisasi
Sistem ini memiliki tiga tingkat akses utama:

* **Admin:** Kontrol sistem penuh. Manajemen pengguna, semua event, dan data master cabang olahraga.
* **Event Organizer (EO):** Fokus pada pengelolaan event yang dibuatnya sendiri.
* **User (Peserta):** Melakukan pendaftaran ke event yang tersedia dan melihat riwayat partisipasi.

---

## Teknologi dan Lingkungan

| Aspek | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | PHP Native | Logika sisi server dan proses bisnis. |
| **Database** | MySQL (Port 3307) | Sistem penyimpanan data relasional. |
| **Frontend** | HTML, CSS, JavaScript | Struktur dan interaksi antarmuka pengguna. |
| **Lingkungan** | XAMPP (Localhost) | Server pengembangan lokal. |

---

## Panduan Instalasi Lokal

Ikuti langkah-langkah berikut untuk menjalankan CaborNation di sistem lokal Anda.

### 1. Persiapan Server

1.  Pastikan **XAMPP** sudah terinstal di komputer Anda.
2.  Buka XAMPP Control Panel dan jalankan service **Apache** dan **MySQL**.

### 2. Konfigurasi Database

1.  Akses **phpMyAdmin** Anda.
2.  **Penting:** Verifikasi bahwa MySQL Anda berjalan di **Port 3307**.
3.  Buat database baru dengan nama: `cabornation`.
4.  Import file `.sql` dari folder `/database/` ke database `cabornation`.
5.  **Perhatian Kritis:** Pastikan tabel **`tournament_registrations`** memiliki kolom **`status`** dengan nilai `pending`, `accepted`, dan `rejected`.

### 3. Konfigurasi Koneksi PHP

1.  Sesuaikan file konfigurasi database, biasanya di `config.php` (terletak di `/php/config.php`):

    ```php
    $host = "localhost:3307";
    $user = "root";
    $pass = "";
    $db   = "cabornation";
    ```

### 4. File Backend Kritis EO Dashboard

Pastikan tiga file berikut ada di direktori `eventorganizers/` untuk mendukung fungsi manajemen peserta:

* `fetch_eo_tournaments.php` (Menampilkan turnamen)
* `fetch_tournament_registrations.php` (Mengambil data pendaftar)
* `accept_participant.php` dan `kick_participant.php` (Mengubah status pendaftaran)

### 5. Akses Aplikasi

1.  Pindahkan semua file proyek ke folder `htdocs` XAMPP Anda.
2.  Akses aplikasi melalui browser: `http://localhost/cabornation/`
