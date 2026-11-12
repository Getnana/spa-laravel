# The Infinity Spa and Massage

Aplikasi monitoring spa hasil migrasi dari CodeIgniter 4 ke Laravel 12. Backend memakai routing, middleware, sesi, validasi, dan Query Builder Laravel. Tampilan SPA dan URL API memakai struktur proyek asal. Aset frontend sudah tersedia di `public/assets`.

## Jalankan di Windows dengan VS Code dan XAMPP

1. Ekstrak ZIP dan buka folder `spa-laravel` di VS Code. Gunakan PHP 8.2 atau lebih baru dan Composer. Jalankan `php -v` dan `composer --version` di terminal untuk mengecek. Jika Composer melaporkan ekstensi PHP yang belum aktif, gunakan `php --ini` untuk menemukan file `php.ini` yang dipakai terminal dan aktifkan ekstensi yang disebutkan.
2. Nyalakan MySQL di XAMPP. Nyalakan Apache juga jika kamu memakai phpMyAdmin untuk impor SQL.
3. Buat database kosong, misalnya `db_spa`, lalu pilih salah satu cara berikut.
   - **Bawa data lama.** Impor `smpdb.sql` dari ZIP CodeIgniter asli atau gunakan salinan database operasional yang sudah lengkap. Akun, booking, dan transaksi ikut terbawa dari sumber yang kamu pilih.
   - **Mulai dari kosong.** Impor `database/schema.sql` dari proyek ini. File tersebut berisi struktur tabel tanpa data pelanggan maupun akun.
4. Jalankan perintah berikut dari folder yang berisi `artisan`. Perintah salin ini untuk PowerShell.

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

5. Buka `.env`. Sesuaikan `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan MySQL kamu. Port bawaan pada contoh adalah `3306`. Jika MySQL di XAMPP kamu memakai `3307`, isi `DB_PORT=3307`. Database yang diisi harus sama dengan tempat kamu mengimpor SQL.
6. Jika menggunakan data lama, login dengan username dan password akun lama. Jika mulai dari kosong atau perlu mengatur ulang password akun owner, jalankan perintah di bawah. Untuk username owner selain `owner`, tambahkan username setelah perintah. Password dimasukkan melalui prompt dan disimpan sebagai hash.

   ```powershell
   php artisan spa:owner
   ```

7. Jalankan server Laravel dan buka **http://127.0.0.1:8000**.

   ```powershell
   php artisan serve
   ```

Untuk instalasi ini, struktur tabel berasal dari file SQL di langkah 3. Untuk akses melalui Apache, arahkan document root ke folder `public` dan layani aplikasi dari root domain karena frontend memakai URL absolut seperti `/api` dan `/assets`.

## Jika login bermasalah

- Jika muncul `Access denied` atau `Connection refused`, periksa nama database, port, username, dan password MySQL di `.env`.
- Jika akun owner lama tidak menerima password yang kamu masukkan, gunakan `php artisan spa:owner` untuk mengatur ulang. Password akun disimpan dalam bentuk hash bcrypt.
- Jika reCAPTCHA aktif pada database salinan dan hostname lokal ditolak, untuk pengujian lokal jalankan ``UPDATE settings SET value = '0' WHERE `key` = 'recaptcha_enabled';`` di database lokal. Di server publik, gunakan site key dan secret key yang terdaftar untuk domain server.
