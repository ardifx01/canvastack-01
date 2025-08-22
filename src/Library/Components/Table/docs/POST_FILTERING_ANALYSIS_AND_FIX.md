# Datatables Server-Side Filtering: GET vs POST Analysis and Fix

## Ringkasan
- GET filter bekerja stabil karena parameter dikirim via query string dan diproses sebagai klausa WHERE.
- POST sebelumnya tidak mengirim nilai filter dalam body request; server menerima hanya parameter kontrol (renderDataTables, difta, dll) sehingga `processFilters()` tidak menemukan filter valid.
- Fix: Menyakinkan POST AJAX selalu mengirim nilai form filter ke body (application/x-www-form-urlencoded) dan menyertakan CSRF header/token.

## Rantai Eksekusi Terkait
- Client (JS)
  - File: `src/Publisher/public/assets/templates/default/js/datatables/filter.js`
  - Fungsi: `diyDataTableFilters` (GET query-string); fallback JS juga diinjeksikan oleh `Scripts.php`.
- Server (PHP)
  - File: `src/Library/Components/Table/Craft/Datatables.php`
  - Fungsi: `applyFilters()` -> `processFilters()` dengan `RESERVED_PARAMETERS` untuk membedakan mana parameter yang dianggap filter.
- Konfigurasi AJAX
  - File: `src/Library/Components/Table/Craft/Scripts.php`
  - Fungsi: `buildAjaxConfiguration()` -> `buildPostAjaxConfig()`/`buildGetAjaxConfig()`.

## Penyebab Akar
- Payload POST tidak membawa pasangan `key=value` untuk field filter; hanya membawa flag kontrol dan difta. Akibatnya `processFilters()` tidak memiliki filter untuk diterapkan dan query kembali ke WHERE default.

## Perubahan/Fix yang Diterapkan
- Memperbarui `buildPostAjaxConfig()` supaya:
  - Menambahkan header `X-Requested-With: XMLHttpRequest`.
  - Menggabungkan nilai input dari form filter `#<table_id>_cdyFILTERForm` ke dalam body request POST.
  - Menghindari nama-nama yang bentrok dengan `RESERVED_PARAMETERS` (`renderDataTables, draw, columns, order, start, length, search, difta, _token, _, filters`).
  - Meng-inject CSRF token ke body jika ada.

Catatan: Jika implementasi form filter berbeda dari konvensi `<table_id>_cdyFILTERForm`, pastikan id-nya disesuaikan atau perbarui selektor.

## Dampak
- Konsistensi antara GET dan POST tercapai: server menerima filter dari POST body persis seperti dari query string pada GET.
- Tidak mengubah cara GET bekerja; GET tetap kompatibel.

## Cara Uji
1. Buka halaman DataTable server-side dengan method POST.
2. Isi form filter, submit.
3. Periksa Network tab:
   - Request POST berisi pasangan `field=value` untuk setiap filter (bukan hanya `renderDataTables`, `difta`, dll).
4. Cek Laravel log dari `Datatables::applyFilters()`:
   - `processedFilters` tidak kosong.
5. Hasil tabel ter-filter sesuai input.

## Catatan Nama Field
- Hindari pemakaian nama yang ada dalam `RESERVED_PARAMETERS` untuk nama field filter.

## Rujukan Kode
- `src/Library/Components/Table/Craft/Datatables.php`
  - `applyFilters()`, `processFilters()`, `RESERVED_PARAMETERS`
- `src/Publisher/public/assets/templates/default/js/datatables/filter.js`
  - `diyDataTableFilters`
- `src/Library/Components/Table/Craft/Scripts.php`
  - `buildAjaxConfiguration()`, `buildPostAjaxConfig()` (ditingkatkan), dan fallback JS

## Status
- Implementasi fix telah diterapkan di `Scripts.php` (POST AJAX merge filter fields). Jika ada halaman dengan form filter yang id-nya tidak mengikuti pola default, sesuaikan selektor di merge logic atau gunakan fallback JS yang sudah di-injeksi secara otomatis.