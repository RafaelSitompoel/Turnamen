# Sistem Turnamen Game (PHP + MySQL)

Sesuai ERD `erd_turnamen_v3.drawio` dan skema `database/db_turnamen.sql`.
Tidak ada data dummy: semua tabel kosong saat pertama dipasang.

## Instalasi (XAMPP / Laragon)
1. Salin folder `turnamen` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Buka phpMyAdmin, impor `database/db_turnamen.sql`.
3. Sesuaikan `DB_USER` / `DB_PASS` di `config.php` bila perlu (default: root, tanpa sandi).
4. Buka `http://localhost/turnamen/register.php`.
   **Akun pertama yang mendaftar otomatis menjadi Admin.** Akun berikutnya menjadi Pemain
   (admin dapat mengubah peran lewat menu Pemain).
5. Alur awal: Admin tambah Game -> buat Turnamen -> pemain buat Tim -> daftar turnamen ->
   admin tandai lunas -> admin jadwalkan pertandingan & input skor -> lihat Klasemen.

## Fitur
- Login / register (Bootstrap 5 berwarna; layout dalam memakai AdminLTE 4 (via CDN)), session aman, password_hash, proteksi CSRF
- Dashboard statistik, turnamen aktif, jadwal mendatang, tim saya
- CRUD Pemain & peran (admin), CRUD Game (admin), CRUD Turnamen (admin)
- Tim: buat/ubah/hapus, upload logo, kapten otomatis jadi anggota
- Anggota tim: tambah, ubah posisi, keluarkan (kapten/admin)
- Pendaftaran turnamen oleh kapten, konfirmasi lunas oleh admin, pembatalan
- Pertandingan: jadwal, input skor, pemenang otomatis, validasi tim terdaftar
- Klasemen otomatis (menang 3 poin, seri 1 poin, selisih skor)
- Profil & ganti kata sandi
