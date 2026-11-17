# Inventaris Fitur untuk Manual Testing

## Ringkasan teknis

- Backend: Laravel, PHP 8.4.
- Frontend: Vue 3 dengan TypeScript, Inertia.js, Vite, dan Tailwind CSS.
- Database aplikasi: PostgreSQL.
- Navigasi berasal dari `resources/js/config/menu.ts`; endpoint dan nama route ada di `routes/web.php`.
- Validasi dilakukan di Laravel Form Request atau validasi controller. Hak akses endpoint diperiksa dengan permission Spatie dan pembatasan perusahaan/departemen pada controller/service.

## Peran yang ditemukan di konfigurasi

| Peran | Akses yang diberikan di seeder | Catatan |
|---|---|---|
| Administrator | Seluruh permission yang terdaftar | Seeder mengubah user pertama menjadi Administrator. |
| Manager | Lihat master; kelola sebagian transaksi, inventory, aset, dan approval; tanpa permission hapus master | Bukan peran khusus “petugas gudang”. Operasi gudang dikendalikan oleh permission seperti `consumable.issue`. |
| User | Lihat master, buat/ubah/ajukan PR, lihat aset/registrasi/lisensi, buat permintaan barang | Hak dibuat terbatas melalui permission. |
| Approver workflow | Approve/reject permintaan yang ditugaskan pada level aktif | Penugasan berasal dari konfigurasi Workflow Setting, bukan role tetap. |

> Nama peran di atas berasal dari `database/seeders/RolePermissionSeeder.php`. Pengujian harus memeriksa permission aktual akun karena permission dapat diubah pada data aplikasi. Akun khusus IT, GA, atau petugas gudang tidak dibuat sebagai peran bawaan oleh seeder tersebut.

## Peta menu, halaman, aksi, dan akses

| Modul | Menu/Sub menu | Fitur (halaman) | Aksi (tombol) | Peran yang boleh akses |
|---|---|---|---|---|
| Umum | Dashboard | `/` · `dashboard` | Membuka ringkasan dashboard | Semua user terautentikasi pada route; permission `dashboard.view` tersedia di seeder. |
| Administrator | Workflow Setting → Approval Setting | `/administrator/approval-workflows` · `administrator.approval-workflows.*` | Daftar, buat, lihat detail, ubah, hapus workflow; atur module, company, department, status aktif, level, approver | Administrator; Manager memiliki `approval.workflow.view`; aksi tulis perlu permission workflow terkait. |
| Master Data | Organization → Companies | `/master/organization/companies` · resource CRUD tanpa show | Daftar, cari/filter/paginasi, tambah, ubah, hapus | `master.organization.*`; Administrator penuh, Manager/User memiliki akses lihat sesuai seeder. |
| Master Data | Organization → Branches | `/master/organization/branches` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.organization.*`. |
| Master Data | Organization → Departments | `/master/organization/departments` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.organization.*`. |
| Master Data | Organization → Locations | `/master/organization/locations` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus; lokasi dapat memiliki induk | `master.organization.*`. |
| Master Data | Organization → Cost Center | `/master/organization/cost-centers` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.organization.*`. |
| Master Data | Product → Categories | `/master/product/categories` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.product.*`. |
| Master Data | Product → Types | `/master/product/types` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus; type terkait category | `master.product.*`. |
| Master Data | Product → Brands | `/master/product/brands` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus; brand dapat terkait banyak category | `master.product.*`. |
| Master Data | Product → Models | `/master/product/models` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus; model terkait brand/type | `master.product.*`. |
| Master Data | Product → License Types | `/master/product/license-types` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.product.*`. |
| Master Data | Product → Products | `/master/product/products` · resource CRUD | Daftar, cari/filter/paginasi, lihat detail, tambah, ubah, hapus | `master.product.*`; aturan terkait Group, kategori, type, brand, model, metode pelacakan, dan satuan stok. |
| Master Data | Procurement → Vendor Types | `/master/procurement/vendor-types` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.procurement.*`. |
| Master Data | Procurement → Vendors | `/master/procurement/vendors` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.procurement.*`. |
| Master Data | Procurement → Unit of Measure | `/master/procurement/units-of-measure` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.procurement.*`. |
| Master Data | Procurement → Payment Terms | `/master/procurement/payment-terms` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.procurement.*`. |
| Master Data | Procurement → Tax | `/master/procurement/taxes` · resource CRUD tanpa show | Daftar, tambah, ubah, hapus | `master.procurement.*`. |
| Procurement | Purchase Request | `/transaction/procurement/purchase-requests` · index/create/store/show/edit/update/submit/cancel/delete/mark-approved/approval-document | Cari/filter/paginasi, tambah, lihat, ubah draft, ajukan, batalkan, persetujuan manual, unduh dokumen approval, hapus sesuai status/permission | `purchase-request.view/create/update/submit/cancel/delete`; persetujuan manual memerlukan kewenangan dan kondisi status yang sesuai. |
| Procurement | Purchase Order | `/transaction/procurement/purchase-orders` · index/show/edit/update/submit/cancel | Cari/filter/paginasi, lihat, ubah harga saat draft, ajukan, batalkan, cetak | `purchase-order.view/update/submit/cancel`; submit/cancel dibatasi status. PO dibuat dari PR yang disetujui sesuai alur. |
| Procurement | Goods Receipt | `/transaction/procurement/goods-receipts` · resource CRUD plus submit | Cari/filter/paginasi, buat dari PO, lihat, ubah/hapus draft, submit/post, cetak | `good-receipt.view/create/update/delete/submit`; penerimaan memilih cabang/gudang dan lokasi opsional; hanya draft dapat diubah/dihapus. |
| Inventory Management | Ringkasan Stok | `/transaction/inventory` · `transaction.inventory.index` | Pilih departemen, lihat KPI/peringatan, buka Stok Barang/Riwayat Mutasi/Pengaturan Inventori, mulai PR pengadaan dari peringatan minimum | `consumable.view`; departemen dibatasi oleh aturan akses user. |
| Inventory Management | Stok Barang | `/transaction/inventory/stocks` · `transaction.inventory.stocks.index` | Cari, filter departemen/gudang-lokasi/kategori, pagination, buka kartu stok | `consumable.view`. Saldo berasal dari mutasi yang sudah diposting. |
| Inventory Management | Pengaturan Inventori | `/transaction/inventory/settings` dan PUT `minimum-stock.update` | Atur batas minimum per produk dan lokasi; nilai kosong menonaktifkan peringatan | `consumable.update`. |
| Inventory Management | Permintaan Barang | `/transaction/inventory/requests` · index/create/store/show/submit/fulfill | Buat draft, lihat, ajukan approval, approver approve/reject di inbox, petugas penuhi sebagian/penuh dengan sumber gudang dan tujuan user/aset/lokasi | Pemohon: `consumable.request`; lihat: `consumable.view`; pemenuhan: `consumable.issue`; approval: approver aktif. |
| Inventory Management | Penerimaan dari GR | `/transaction/inventory/receipts` | Lihat integrasi GR dan penerimaan manual; buat saldo awal manual; lampirkan dokumen approval/approval manual; retry integrasi/masukkan nomor seri jika relevan | `consumable.view`, `consumable.update`, atau permission penerimaan sesuai aksi. |
| Inventory Management | Transaksi Stok | `/transaction/inventory/transactions`; form di `/issues/create`, `/transfers/create`, `/returns/create`, `/adjustments/create` | Lihat riwayat; buat pengeluaran, transfer, retur, koreksi; buka kartu stok | Lihat `consumable.view`; posting operasi `consumable.issue` atau `consumable.update` sesuai jenis transaksi. |
| Inventory Management | Stock Opname | `/transaction/inventory/stocktakes` · index/create/store/show/update/post | Buat sesi, input hasil hitung, tandai hitung, lihat, posting hasil | Lihat `consumable.view`; kelola `consumable.update`; status Draft → Counted → Posted. |
| Asset Management | Registration | `/transaction/asset-management/registrations` · index/create/store/show | Daftar, tambah registrasi manual, lihat detail | `registration.view/create`; pengguna hasil PR/PO/GR juga terkait alur registrasi aset. |
| Asset Management | Asset Directory | `/transaction/asset-management/assets` | Cari/filter daftar aset, impor CSV, unduh template, ekspor CSV, cetak QR, buka detail | `asset.view`; impor/maintenance `asset.create`; disposal `asset.dispose`. |
| Asset Management | Asset Assignment | `/transaction/asset-management/assignments` | Assign, lihat riwayat, return aset, cetak bukti serah terima | Lihat `asset.view`; assign/return `asset.transfer`; pilihan aset dibatasi status/penugasan. |
| Asset Management | Maintenance & Service | `/transaction/asset-management/maintenance` | Buat, lihat, ubah, ajukan, tandai approval/rejection, selesaikan/batalkan, cetak, unduh dokumen approval | `asset.view/create/update`; aksi approval/status sesuai permission, workflow atau mekanisme manual yang tampak pada controller. |
| Asset Management | Audit / Stock Opname | `/transaction/asset-management/audits` | Buat audit, scan aset, ubah/selesaikan item, cetak laporan | Lihat `asset.view`; kelola `asset.create/update`. |
| Asset Management | Insiden Aset | `/transaction/asset-management/incidents` | Buat laporan, investigasi, selesaikan, dismiss, lihat/unduh bukti | Buat/investigasi/selesaikan sesuai `asset.create/update`; lihat `asset.view`. |
| Asset Management | Perpindahan Aset | `/transaction/asset-management/transfers` | Buat dan lihat perpindahan | `asset.view/transfer`; aturan validasi membatasi aset, perusahaan, departemen, lokasi, tanggal, dan alasan. |
| Asset Management | Pelepasan Aset | `/transaction/asset-management/disposals` | Buat, lihat, approve/reject, unduh bukti, cetak | Lihat `asset.view`; kelola `asset.dispose`; status pending approval/approved/rejected. |
| My Approval | Inbox | `/approval/inbox` | Lihat tugas approval, approve, reject dengan catatan | `approval.view/approve/reject` dan harus menjadi approver aktif pada level permintaan. |
| My Approval | History | `/approval/history` | Cari/filter history, pagination, buka detail | `approval.view`; data dibatasi sesuai scope permintaan/approver. |
| Report | Tidak ada menu Report umum; aset memiliki laporan dari Asset Directory | `/transaction/asset-management/reports` dan `/transaction/asset-management/assets/export` | Lihat laporan aset, filter, ekspor CSV; beberapa halaman menyediakan cetak/PDF browser | Mengikuti izin asset/directory pada controller. Tidak ditemukan route report khusus Inventory/Procurement. |
| Settings | Settings | `/settings` GET/PUT | Lihat dan ubah pengaturan akun yang tersedia di halaman | Semua user terautentikasi; validasi/aksi ditentukan SettingsController. |

## Peta form, aturan, dan status yang terlihat di kode

Berikut field dan validasi utama yang tercatat di request/controller. Nama label layar akan dicocokkan lagi pada saat skenario tiap modul ditulis.

| Form | Field yang divalidasi | Aturan yang teridentifikasi |
|---|---|---|
| Company | `code`, `name`, `short_name`, `is_active` | Code/name wajib; code unik/format dibatasi; short name opsional. |
| Branch | `company_id`, `code`, `name`, `address`, `is_active` | Company/code/name wajib; code unik; address opsional. |
| Department | `company_id`, `code`, `name`, `is_active` | Company/code/name wajib; company harus tersedia. |
| Location | `company_id`, `branch_id`, `code`, `name`, `parent_id`, `location_type`, `is_active` | Branch harus berada pada company terpilih; parent pada company yang sama dan bukan dirinya sendiri; code unik di company. |
| Cost Center | `company_id`, `code`, `name`, `is_active` | Company/code/name wajib; code unik sesuai scope company. |
| Category | `parent_id`, `code`, `name`, `description`, `is_active` | Code/name wajib; parent opsional; code unik. |
| Type | `category_id`, `code`, `name`, `description`, `is_active` | Category/code/name wajib; code unik sesuai aturan master. |
| Brand | `code`, `name`, `category_ids[]`, `description`, `is_active` | Brand dapat dipetakan ke beberapa category; category pilihan harus ada/aktif. |
| Model | `brand_id`, `type_id`, `code`, `name`, `description`, `is_active` | Brand/type/code/name wajib; model berhubungan dengan kombinasi brand dan type. |
| License Type | `code`, `name`, `description`, `is_active` | Code/name wajib; description opsional. |
| Product | `category_id`, `type_id`, `brand_id`, `model_id`, `group`, `inventory_tracking_mode`, `base_uom_id`, `code`, `name`, `description`, `is_active` | Group wajib (`ASSET`/`CONSUMABLE`/`LICENSE`); category/type/brand harus saling cocok; model opsional tetapi bila diisi harus cocok dengan brand/type; code unik dan alpha-dash; satuan stok wajib bagi `CONSUMABLE`. |
| Vendor Type / Unit of Measure | `code`, `name`, `description`, `is_active` | Code/name wajib; description opsional. |
| Vendor | `vendor_type_id`, `code`, `name`, `tax_number`, `email`, `phone`, `address`, `description`, `is_active` | Vendor type/code/name wajib; email dan data kontak opsional dengan validasi format/ukuran. |
| Payment Term | `code`, `name`, `due_days`, `description`, `is_active` | Code/name/due days wajib. |
| Tax | `code`, `name`, `rate`, `description`, `is_active` | Code/name/rate wajib; rate numerik. |
| Purchase Request | `company_id`, `department_id`, `request_date`, `purpose`, `remarks`, `asset_maintenance_record_id`, `items[]` | Company aktif; department aktif dan di company yang sama; minimal satu item; tipe `PRODUCT` atau `SERVICE`; produk/service type wajib sesuai tipe; quantity/UOM wajib; maintenance record hanya untuk kondisi `WAITING_PARTS`. Baris memuat deskripsi, harga estimasi, vendor/contact, requires PO, pajak, tanggal kebutuhan, remarks, delivery branch. |
| Purchase Order edit/approval | Baris `items[].id`, `unit_price`, `reason`; cancellation `reason`; approval PR `remarks`, `signed_document` | Harga wajib numerik dan tidak negatif; perubahan harga menyertakan alasan opsional; alasan batal wajib; dokumen approval mengikuti batas tipe/ukuran di request. |
| Goods Receipt | `source_type`, `source_id`, `vendor_id`, `warehouse_location_id`, `storage_location_id`, `received_at`, `delivery_note_number`, `remarks`, `items[].source_item_id`, quantity received/accepted/rejected, item remarks | Gudang dan tanggal wajib; minimal satu item; source item unik; received > 0; accepted/rejected >= 0; storage location opsional dan harus sesuai gudang di service. Status `DRAFT` → `POSTED`. |
| Inventory Request | `department_id`, `requested_at`, `required_at`, `remarks`, `items[].product_id`, `requested_quantity`, item remarks | Minimal satu item; produk harus consumable aktif; quantity > 0 hingga 3 desimal; required date tidak boleh sebelum requested date. Status `DRAFT`, `PENDING_APPROVAL`, `APPROVED`, `PARTIALLY_FULFILLED`, `FULFILLED`, `REJECTED`, `CANCELLED`. |
| Inventory operation | `department_id`, `occurred_at`, `remarks`, `items[].product_id`, quantity, serial numbers, reason; tergantung tipe ada source/target/location, arah, tujuan user/aset/lokasi dan specification changes | Minimal satu item; kuantitas > 0; maksimal 100 item; jenis tujuan dibatasi `USER`/`ASSET`/`LOCATION`; koreksi memerlukan arah dan reason. Rincian kondisional ada di `InventoryController::validateOperation`. |
| Manual opening balance | Departemen, gudang tujuan, tanggal cut-off, alasan/referensi, items; approval mencakup remarks dan dokumen tanda tangan | Form meminta alasan dan data item; workflow atau mark approval bergantung mode; bukti approval PDF/JPG/PNG, maksimal 20 MB pada request. |
| Asset registration | Produk aset, company/department/location, custodian opsional, units, serial number, specifications, warranty, acquisition date/cost, remarks | Manual registration meminta company/department/product dan tanggal perolehan; procurement registration melarang beberapa field manual; minimal 1 dan maksimal 500 unit; serial number unik; warranty 0.5–100 tahun. |
| Asset transfer | `asset_id`, `company_id`, `department_id`, `location_id`, `effective_at`, `reason` | Company/department/location aktif dan selaras; tanggal tidak boleh di masa depan; reason 5–5000 karakter. |
| Asset incident | Asset, type, severity, occurred date, reported condition, description, evidence | Type/severity wajib; tanggal tidak boleh masa depan; description 5–10000 karakter. Investigasi/resolve memiliki field temuan dan penyelesaian wajib. |
| Asset maintenance | Tipe, mode eksekusi, prioritas, tanggal, deskripsi masalah, kondisi, vendor/cost/schedule, components | Vendor wajib bila mode vendor; schedule tidak sebelum requested date; component name/quantity/cost/date divalidasi. Status termasuk requested, waiting approval/parts, ready, in progress, completed, cancelled. |

### Status dokumen dan aturan bisnis teridentifikasi

- **Purchase Request:** `DRAFT`, `SUBMITTED`, `CANCELLED`; approval workflow menyimpan `WAITING`, `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`. PO generation tersedia setelah persetujuan.
- **Purchase Order:** `DRAFT`, `SUBMITTED`, `CANCELLED`; perubahan harga hanya tersedia saat draft.
- **Goods Receipt:** `DRAFT` dan `POSTED`; GR memerlukan sumber yang valid dan hanya draft dapat diubah/dihapus.
- **Inventory Request:** `DRAFT`, `PENDING_APPROVAL`, `APPROVED`, `PARTIALLY_FULFILLED`, `FULFILLED`, `REJECTED`, `CANCELLED`.
- **Receipt integration:** `PENDING_WAREHOUSE`, `PENDING_SERIALS`, `PROCESSED`, `FAILED`.
- **Inventory document:** `POSTED`, `PENDING_APPROVAL`, `REJECTED` sesuai jenis dokumen; stock movement adalah ledger mutasi.
- **Stocktake:** `DRAFT`, `COUNTED`, `POSTED`.
- **Asset:** status model yang teridentifikasi `ACTIVE`, `MISSING`, `DISPOSAL_PENDING`, `DISPOSED`. Insiden: `REPORTED`, `INVESTIGATING`, `RESOLVED`, `DISMISSED`. Disposal: pending approval/approved/rejected.
- **Approval workflow:** harus aktif dan cocok untuk module, company, department; level harus berurutan dan memiliki approver. `INVENTORY_REQUEST` serta `INVENTORY_OPENING_BALANCE` ada di daftar module workflow.

## Catatan batas cakupan dan hal yang perlu dicermati

1. Menu didefinisikan statis di Vue; izin aksi diperiksa endpoint. Pengujian akses perlu mencoba membuka URL langsung, bukan hanya mengamati menu.
2. Role bawaan hanya Administrator, Manager, User. Peran QA seperti petugas gudang, pemohon, atau karyawan perlu dipetakan ke permission aktual atau disiapkan sebagai role khusus.
3. `users.*` dan `roles.*` tersedia sebagai permission di seeder, tetapi tidak ditemukan menu/route pengelolaan user/role di `routes/web.php` saat inventaris dibuat.
4. Tidak ditemukan modul lisensi transaksi terpisah atau report umum. Master License Type dan group Product LICENSE ada; cakupan transaksi lisensi perlu dikonfirmasi dari fitur yang tersedia pada halaman aset/product.
5. Cetak pada beberapa layar menggunakan halaman print/browser dan “Simpan PDF”; ekspor CSV yang teridentifikasi jelas pada Asset Directory/Report. Tidak akan dibuat skenario untuk ekspor yang tidak ditemukan.
6. Langkah ini hanya inventaris. Belum ada skenario manual, data persiapan, smoke test, atau CSV test case yang dibuat.
