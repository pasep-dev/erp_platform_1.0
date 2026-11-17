# Persiapan Pengujian Manual

Dokumen ini menyiapkan akun, master, dan konfigurasi yang diperlukan sebelum skenario manual dijalankan. Nilai yang disebut **tersedia dari seeder** memang ditemukan di source seeder. Nilai **contoh untuk disiapkan manual** adalah data usulan QA, bukan data yang dibuat otomatis oleh project.

## 1. Akun uji dan hak akses

Seeder project tidak menyediakan seluruh kombinasi peran dan departemen yang diminta untuk pengujian. Khususnya, tidak ada role bawaan petugas gudang, tidak ada akun IT/GA, dan akun dari `ApprovalTestUserSeeder` tidak secara eksplisit diberi role. Pastikan role, permission, perusahaan, departemen, dan penugasan approver diverifikasi pada environment uji sebelum dipakai.

| Profil pengujian | Akun dari seeder | Data login dari source | Hak akses yang diperlukan / catatan |
|---|---|---|---|
| Administrator | `Administrator` | `septian@citragroup.com` / `password` dari `AdminUserSeeder` | `AdminUserSeeder` hanya membuat user; `RolePermissionSeeder` kemudian memberi role Administrator kepada user pertama berdasarkan ID, bukan berdasarkan email. Pastikan akun ini benar-benar mendapat role Administrator. Kredensial ini hanya untuk development/test dan tidak boleh dipakai di production. |
| Atasan / approver | `Manager Test` | `manager.test@company.local` / `Password123!` dari `ApprovalTestUserSeeder` | Berposisi `Manager`, perusahaan CAA, departemen FIN. Posisi tidak otomatis memberi permission. Tetapkan role Manager bila diperlukan dan tambahkan akun sebagai approver aktif pada level workflow yang diuji. |
| Petugas gudang | Tidak ada akun khusus | Buat/siapkan manual di environment uji | Untuk alur fulfillment/penerimaan, siapkan role yang memiliki permission yang dipakai endpoint, sekurangnya `consumable.view`, `consumable.issue`, dan/atau `consumable.update` sesuai tindakan yang diuji. Role Manager hasil seeder memiliki permission tersebut. |
| Pemohon / karyawan | Tidak ada akun khusus IT atau GA | Buat/siapkan manual di environment uji | Gunakan role User untuk membuat PR dan permintaan barang (`purchase-request.create`, `purchase-request.submit`, `consumable.request`) serta melihat data yang diizinkan. Hubungkan ke departemen IT atau GA untuk pengujian pembatasan departemen. |
| Manajemen / approver tingkat akhir | `Director Test` | `director.test@company.local` / `Password123!` dari `ApprovalTestUserSeeder` | Berposisi `Director`, perusahaan CAA, departemen FIN. Position saja tidak memberi akses; assign role/permission dan tempatkan dalam level workflow yang relevan. |
| Purchasing / Finance | `Purchasing Finance Test` | `purchasing.test@company.local` / `Password123!` dari `ApprovalTestUserSeeder` | Berposisi `Purchasing / Finance`, perusahaan CAA, departemen FIN. Role tidak diberikan oleh seeder ini. Cocok sebagai data uji bila diberikan permission sesuai proses PO/GR. |
| Akun departemen IT | Tidak ada akun IT pada `ApprovalTestUserSeeder` | Buat/siapkan manual | Kaitkan dengan `CAA` dan departemen `IT` yang tersedia dari `OrganizationMasterSeeder`; berikan role/permission sesuai profil yang diuji. |
| Akun departemen GA | Tidak ditemukan departemen GA pada `OrganizationMasterSeeder` | Buat/siapkan manual | Buat departemen GA pada perusahaan uji, lalu buat akun dan atur role/permission. |

**Catatan role:** `RolePermissionSeeder` menyediakan role `Administrator`, `Manager`, dan `User`. Administrator mendapat seluruh permission yang terdaftar. Manager dapat melihat serta mengelola sebagian transaksi dan memiliki permission inventory seperti `consumable.issue`, `consumable.update`, dan `consumable.request`. User dapat membuat/mengajukan PR dan permintaan barang, tetapi tidak memiliki permission fulfillment. Role Manager/User tidak otomatis diberikan kepada akun `ApprovalTestUserSeeder`.

### Matriks akun minimal yang disarankan

Sebelum mulai, siapkan setidaknya akun berikut di database uji. Akun bergaris “manual” bukan akun yang dibuat oleh seeder.

| Nama untuk QA | Email yang disarankan | Perusahaan | Departemen | Role/konfigurasi |
|---|---|---|---|---|
| Admin QA | `qa.admin@example.test` | CAA | IT | Administrator |
| Pemohon IT | `qa.requester.it@example.test` | CAA | IT | User |
| Approver IT | `qa.approver.it@example.test` | CAA | IT | Manager dan approver pada workflow IT |
| Petugas Inventory IT | `qa.inventory.it@example.test` | CAA | IT | Manager atau role operasional dengan permission inventory yang diperlukan |
| Pemohon GA | `qa.requester.ga@example.test` | CAA | GA (buat manual) | User |
| Approver GA | `qa.approver.ga@example.test` | CAA | GA (buat manual) | Manager dan approver pada workflow GA |
| Manajemen | `qa.director@example.test` | CAA | FIN atau departemen yang ditetapkan | Role/permission yang sesuai dan approver pada level akhir |

Set kata sandi akun manual melalui mekanisme administrasi aplikasi yang berlaku di environment uji. Jangan memakai atau mencatat kata sandi production dalam dokumen ini.

## 2. Data awal

### Data organisasi tersedia dari seeder

Jalankan `OrganizationMasterSeeder` secara eksplisit bila database uji belum memiliki data ini. Seeder tersebut tidak dipanggil oleh `DatabaseSeeder`.

| Jenis | Kode | Nama | Catatan |
|---|---|---|---|
| Company | `CAA` | `PT Contoh Abadi` | Aktif |
| Branch | `JKT` | `Cabang Jakarta` | Company CAA; alamat Jakarta |
| Branch | `BDG` | `Cabang Bandung` | Company CAA; alamat Bandung |
| Department | `IT` | `Information Technology` | Company CAA; aktif |
| Department | `FIN` | `Finance` | Company CAA; aktif |
| Location | `HO` | `Head Office` | Company CAA; tipe `building`, tanpa branch |
| Location | `JKT-IT` | `Ruang IT Jakarta` | Company CAA, branch JKT, parent HO, tipe `room` |
| Location | `BDG-OFFICE` | `Kantor Bandung` | Company CAA, branch BDG, tipe `building` |
| Cost Center | `CC-IT` | `Cost Center IT` | Company CAA; aktif |
| Cost Center | `CC-FIN` | `Cost Center Finance` | Company CAA; aktif |

> Organization seeder tidak membuat departemen GA, gudang inventory, produk, vendor, atau data workflow approval.

### Data penomoran dan service tersedia dari seeder

| Seeder | Data yang disediakan |
|---|---|
| `DatabaseSeeder` | Menjalankan `RolePermissionSeeder`, `InventoryRequestPermissionSeeder`, `PurchaseOrderNumberSeeder`, dan `AssetNumberSeeder`. |
| `AssetNumberSeeder` | Dokumen nomor `ASSET` dan `ASSET_MAINTENANCE`, jika belum ada. |
| `PurchaseOrderNumberSeeder` | Dokumen nomor `PURCHASE_ORDER`, jika belum ada. |
| `NumberingSeeder` | Menyediakan `ASSET`, `PURCHASE_REQUEST`, `PURCHASE_ORDER`, dan `GOODS_RECEIPT`, tetapi tidak dipanggil `DatabaseSeeder`. |
| `PurchaseRequestNumberSeeder` | Menyediakan `PURCHASE_REQUEST`, tetapi tidak dipanggil `DatabaseSeeder`. |
| `ServiceTypeSeeder` | `SRV-AC` / Service AC, `SRV-PRINTER` / Maintenance Printer, `SRV-LAPTOP` / Service Laptop (semuanya memerlukan aset); `SRV-CLEAN` / Cleaning Service Office dan `SRV-CONSULT` / Consulting Service (tidak memerlukan aset). Seeder ini tidak dipanggil `DatabaseSeeder`. |

`SettingsSeeder` juga tidak dipanggil oleh `DatabaseSeeder`; ia mengatur company name `ERP Platform`, email `admin@example.com`, timezone `Asia/Jakarta`, dan format tanggal. Tidak diperlukan untuk semua skenario, tetapi dapat dijalankan bila pengujian setting memerlukannya.

### Data master untuk skenario

Tidak ditemukan seeder data produk, kategori, type, brand, model, satuan, vendor, gudang, atau stock. `ProductFactory` ada tetapi belum berisi definisi atribut. Siapkan data berikut melalui UI Master Data pada environment uji; contoh ini untuk QA dan bukan klaim nilai bawaan project.

| Jenis data | Contoh nilai konkret untuk disiapkan manual | Kegunaan |
|---|---|---|
| Kategori/type/brand/model | Category `Laptop`; type `Laptop Bisnis`; brand `Lenovo`; model `ThinkPad T14` | Master produk aset dan pengujian korelasi dropdown |
| Produk aset | Code `LAPTOP-T14`, nama `Laptop ThinkPad T14`, group `ASSET` | Registrasi, procurement, assign/return, QR, maintenance |
| Produk consumable kuantitas | Category `Perlengkapan Kantor`; type `ATK`; brand `Umum`; code `PAPER-A4`; nama `Kertas A4`; satuan `RIM`; group `CONSUMABLE`, mode kuantitas | Permintaan, penerimaan, pengeluaran, transfer, retur, koreksi, opname, batas minimum |
| Produk consumable serial | Code `MOUSE-USB-001`, nama `Mouse USB`, group `CONSUMABLE`, mode serial, satuan yang diwajibkan form | Uji nomor seri bila halaman/form mendukungnya |
| Produk license | Code `LIC-OFFICE-STD`, nama `Office Standard`, group `LICENSE` | Uji klasifikasi produk lisensi yang memang tersedia |
| Satuan | Code `PCS` / name `Pieces`; code `RIM` / name `Rim` | Isi satuan produk dan item transaksi |
| Vendor type / vendor | Vendor type `LOCAL`; vendor code `VND-001`; nama `PT Sumber Teknologi`; email `qa.vendor@example.test` | PR, PO, dan GR |
| Gudang / lokasi simpan | Buat lokasi sesuai form inventory, misalnya `WH-JKT` / `Gudang Jakarta`, lalu lokasi turunan `RACK-A1` / `Rak A1` | Penerimaan GR, saldo, pengeluaran, transfer dan opname |
| Data stok | Terima/post `10` rim `PAPER-A4` ke `WH-JKT`; siapkan lokasi tujuan kedua bila menguji transfer | Titik awal untuk pemeriksaan saldo sebelum/sesudah mutasi |
| Minimum stok | Atur minimum `5` rim untuk produk `PAPER-A4` pada department/lokasi yang diuji | Peringatan minimum dan KPI |

> Periksa field wajib yang sesungguhnya pada layar yang sedang diuji. Sebagian alur memerlukan vendor, branch/lokasi, tax/payment term, atau nomor seri sesuai jenis baris.

### Workflow approval yang harus disiapkan manual

Tidak ditemukan workflow approval seeder. Buat workflow aktif di **Administrator → Workflow Setting → Approval Setting** pada perusahaan/departemen uji dan tetapkan level serta approver aktif. Module harus persis sesuai transaksi yang diuji, termasuk `PURCHASE_REQUEST`, `INVENTORY_REQUEST`, dan `INVENTORY_OPENING_BALANCE` bila alurnya digunakan. Pengujian PR/permintaan barang akan terblokir tanpa workflow aktif yang cocok dengan company dan department dokumen.

Untuk menjalankan pengujian approval, pastikan akun approver:

1. Aktif dan berada pada company/departemen yang sesuai dengan workflow.
2. Memiliki permission approval yang diperlukan.
3. Terpilih pada level workflow yang hendak diuji.
4. Dapat membuka menu **My Approval → Inbox**.

## 3. Menyiapkan atau mereset data

### Menjalankan seeder yang relevan

Perintah berikut hanya menyiapkan data; jalankan pada database environment uji yang benar:

```bash
php artisan db:seed --class='Database\Seeders\Master\OrganizationMasterSeeder'
php artisan db:seed --class='Database\Seeders\AdminUserSeeder'
php artisan db:seed --class='Database\Seeders\ApprovalTestUserSeeder'
php artisan db:seed --class='Database\Seeders\RolePermissionSeeder'
php artisan db:seed --class='Database\Seeders\InventoryRequestPermissionSeeder'
php artisan db:seed --class='Database\Seeders\NumberingSeeder'
php artisan db:seed --class='Database\Seeders\ServiceTypeSeeder'
```

`php artisan db:seed` menjalankan `DatabaseSeeder`, termasuk `SettingsSeeder` untuk nilai awal identitas aplikasi dan backup. Seeder organisasi, akun, `NumberingSeeder`, dan `ServiceTypeSeeder` harus dipanggil terpisah jika dibutuhkan. `ApprovalTestUserSeeder` mensyaratkan company `CAA` dan department `FIN` sudah ada, sehingga jalankan `OrganizationMasterSeeder` lebih dahulu.

### Reset database

`php artisan migrate:fresh --seed` menghapus seluruh tabel dan data database yang dikonfigurasi sebelum membuat ulang schema dan menjalankan `DatabaseSeeder`. Gunakan hanya pada database uji sekali pakai atau setelah backup dan verifikasi koneksi; jangan jalankan pada database development bersama atau production. `migrate:fresh --seed` sendiri tetap tidak membuat semua akun/master karena `DatabaseSeeder` tidak memanggil seluruh seeder di project. Setelah reset, jalankan seeder tambahan yang diperlukan sebagaimana di atas, lalu buat master transaksi dan workflow secara manual.

## System Settings dan backup database

- Pastikan role Administrator memiliki permission `system.settings.view`, `system.settings.update`, dan `system.settings.backup`; jalankan `php artisan db:seed --class='Database\Seeders\RolePermissionSeeder'` bila permission belum tersedia.
- Ubah nama sistem dan logo dari menu **Settings**. Logo menerima PNG, JPG, atau WebP hingga 2 MB; setelah deploy, pastikan public storage link tersedia dengan `php artisan storage:link`.
- Tombol **Download Backup** menghasilkan PostgreSQL custom-format dump untuk diunduh. File sementara dibersihkan setelah dikirim.
- Backup otomatis berjalan setiap hari pukul **10:00** menurut timezone aplikasi dan hanya menyimpan **7 file terjadwal terakhir** di `storage/app/private/backups/scheduled`. Nilai waktu, status aktif, dan retensi dapat diubah pada Settings.
- Scheduler Laravel wajib berjalan. Pada Compose gunakan `docker compose up -d scheduler`; sesudah perubahan image PHP, rebuild service dengan `docker compose build app scheduler`.
- Backup mencakup database PostgreSQL, bukan file upload, environment secrets, atau role global PostgreSQL. Penyimpanan lokal tidak melindungi backup dari kerusakan server/disk; salin backup ke lokasi off-site untuk pemulihan bencana.

### Pemeriksaan sebelum mulai

- Pastikan URL aplikasi dan koneksi mengarah ke environment uji, bukan production.
- Login setiap akun dan pastikan role/permission aktual sesuai profil pengujian.
- Pastikan company, department, branch, location/gudang, produk, vendor, satuan, serta workflow aktif yang dibutuhkan sudah tersedia.
- Catat saldo awal produk per departemen/lokasi sebelum menjalankan skenario mutasi.
- Gunakan data unik per putaran, misalnya suffix tanggal atau `QA-01`, untuk menghindari benturan kode unik.
- Siapkan satu produk aset, satu produk consumable kuantitas, dan data serial bila skenario terkait dijalankan.
- Untuk uji departemen, siapkan department GA dan akun GA manual; master seeder hanya membuat IT dan Finance.

## Batasan persiapan yang diketahui

1. Seeder akun approval membuat posisi dan pengguna tetapi tidak memberikan role/permission Spatie kepada pengguna tersebut.
2. `RolePermissionSeeder` memberi role Administrator kepada user pertama berdasarkan ID; hasilnya dapat bergantung pada urutan user yang sudah ada.
3. Tidak ada data workflow, produk, vendor, gudang, atau stok yang di-seed oleh `DatabaseSeeder`.
4. Tidak ada kredensial khusus untuk petugas gudang, pemohon, manajemen, IT, atau GA yang secara konsisten siap dipakai tanpa penyesuaian role dan data organisasi.
5. Data uji manual pada tabel di atas adalah rekomendasi konkret untuk membangun prasyarat, bukan representasi isi database saat ini.
