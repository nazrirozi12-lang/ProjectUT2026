# Deploy ke Vercel

0. `composer.lock` sudah diperbarui ke Symfony 7.4 (kompatibel PHP 8.3 Vercel) dan `composer.json` memakai `platform.php = 8.3.0`.
   Jangan jalankan `composer update` dengan PHP 8.4+ tanpa `platform` tersebut, karena Symfony 8 akan masuk lagi.
1. Vercel > Project Settings > General: **Framework Preset = Other**, kosongkan Build/Output Directory override.
2. Isi Environment Variables sesuai `.env.vercel.example`.
3. Jalankan migrasi + seeder SEKALI dari komputer lokal ke database eksternal:
   `DB_CONNECTION=pgsql DB_URL="..." ADMIN_PASSWORD="..." APP_ENV=production php artisan migrate --force --seed`
4. Upload bukti transfer: tambahkan paket S3 lalu commit `composer.lock` yang baru:
   `composer require league/flysystem-aws-s3-v3 "^3.0"`
   (Batas body request Vercel ~4,5 MB; validasi upload sudah diturunkan ke 4 MB.)
5. Push ke Git / `vercel --prod`.
