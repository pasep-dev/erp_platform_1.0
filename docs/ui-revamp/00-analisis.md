# Analisis UI Revamp Asset Management

## Status dan ruang lingkup

Dokumen ini adalah hasil Tahap 0. Tidak ada route, data menu, logika aplikasi,
atau source UI yang diubah pada tahap analisis ini. Laporan dan delapan
screenshot baseline disimpan di `docs/ui-revamp`; perubahan lokal yang sudah
ada tetap dipertahankan.

Analisis membaca working tree saat ini, bukan hanya `HEAD`. Working tree
sebelumnya sudah memiliki banyak perubahan dan penghapusan yang belum di-commit,
termasuk `AGENTS.md`, `resources/css/app.css`, `resources/js/app.ts`,
`resources/js/pages/Dashboard/Index.vue`, dan direktori layout baru yang belum
di-track. Ada pula perubahan backend dan manifest Composer. File-file tersebut
tidak di-stage atau diubah oleh analisis ini. Cabang analisis:
`ui-revamp/phase-0-analysis`.

## 0.1 Analisis project

### Versi dan konfigurasi

Versi frontend berikut berasal dari paket yang terpasang di `node_modules`;
versi backend berasal dari container aplikasi.

| Bagian | Versi/konfigurasi saat ini |
|---|---|
| PHP | 8.4.26 di container aplikasi; PHP tidak tersedia langsung di host |
| Laravel | 13.30.1 |
| Vue | 3.5.42 |
| Inertia Vue dan Vite | `@inertiajs/vue3` 3.7.0, `@inertiajs/vite` 3.7.0 |
| TypeScript | 6.0.3 |
| Tailwind CSS | 4.3.3, konfigurasi CSS-first melalui `@theme` dan `@custom-variant` |
| PrimeVue | 4.5.5, mode styled (bukan unstyled) |
| PrimeVue preset | Aura sebagai preset awal; configurator juga menyediakan Lara dan Nora |
| PrimeVue theme | `@primeuix/themes` 2.0.3; dark selector `.app-dark` |
| Vite | 8.2.2; plugin Laravel, Vue, Inertia, dan Tailwind |
| Test framework | PHPUnit 12.5.34 |

`package.json` hanya menyediakan script `build` dan `dev`; tidak ada script
frontend khusus untuk type-check atau lint. `vue-tsc` memang terpasang sehingga
type-check dapat dijalankan langsung.

### Titik masuk dan peta layout

Blade `resources/views/app.blade.php` memuat `resources/css/app.css` dan
`resources/js/app.ts`. Entry TypeScript mengimpor
`resources/js/assets/tailwind.css` serta `resources/js/assets/styles.scss`,
membuat aplikasi Inertia, memasang PrimeVue Aura, ToastService, dan
ConfirmationService.

| Fungsi | Berkas/komponen |
|---|---|
| Layout halaman aplikasi | `resources/js/layout/AppLayout.vue` |
| Sidebar dan footer versi | `resources/js/layout/AppSidebar.vue` |
| Render grup menu | `resources/js/layout/AppMenu.vue` |
| Render item/menu rekursif | `resources/js/layout/AppMenuItem.vue` |
| Navbar/topbar | `resources/js/layout/AppTopbar.vue` |
| Theme/preset/surface/menu-mode configurator | `resources/js/layout/AppConfigurator.vue` |
| Footer halaman (komponen terpisah; tidak dipasang pada template AppLayout saat ini) | `resources/js/layout/AppFooter.vue` |
| State menu, tema, dan dark mode | `resources/js/layout/composables/layout.ts` |
| Struktur menu dan routeName | `resources/js/config/menu.ts` |
| Notifikasi Inertia/flash | `resources/js/components/common/FlashNotification.vue` dan PrimeVue `Toast` |
| Token PrimeVue/layout | `resources/js/assets/layout/variables/_common.scss`, `_light.scss`, `_dark.scss` |
| Layout SCSS | `resources/js/assets/layout/_core.scss`, `_main.scss`, `_menu.scss`, `_topbar.scss`, `_responsive.scss`, `_typography.scss` |
| Header halaman yang dipakai ulang sebagian halaman | `resources/js/components/content/Header.vue` |
| Tabel CRUD/PrimeVue DataTable | `resources/js/components/crud/CrudDataTable.vue`, `resources/js/components/table/DataTable.vue` |
| Filter pencarian | `resources/js/components/common/SearchFilterBar.vue` |
| Dashboard | `resources/js/pages/Dashboard/Index.vue` |

### Halaman yang memakai AppLayout

Ada 102 file halaman yang menetapkan `AppLayout` sebagai layout Inertia dan
`Settings/Index.vue` yang merender `AppLayout` langsung (103 halaman secara
total). Berkas Vue lain di `resources/js/pages` mencakup halaman autentikasi
dan komponen pendukung.

| Area | Cakupan halaman |
|---|---|
| Dashboard | `Dashboard/Index.vue` |
| Approval | `Approval/Inbox/Index.vue`, `Approval/History/Index.vue` |
| Administrator | `Administrator/Workflow/Approval/{Index,Create,Edit}.vue` |
| Settings | `Settings/Index.vue` (layout dirender langsung di template) |
| Master Organization | `Master/Organization/{Branches,Companies,CostCenters,Departments,Locations}/**` |
| Master Procurement | `Master/Procurement/{PaymentTerms,Taxes,UnitsOfMeasure,VendorTypes,Vendors}/**` |
| Master Product | `Master/Product/{Brands,Categories,LicenseTypes,Models,Products,Types}/**` |
| Asset Management | `Transaction/AssetManagement/{Assignment,Audit,Directory,Disposal,Incident,Maintenance,Registration,Transfer}/**` |
| Inventory | `Transaction/Inventory/**` |
| Transaction Procurement | `Transaction/Procurement/{GoodReceive,PurchaseOrder,PurchaseRequest}/**` |

Pemetaan di atas tidak mengubah `menuItems`, `menuGroups`, `id`, atau
`routeName`. Dashboard berada pada route bernama `dashboard` di path `/`;
`/dashboard` bukan path route dan menghasilkan 404.

### Struktur menu dan perilaku layout saat ini

- Tiga label grup menu adalah `Main Menu`, `Transactions`, dan
  `Administration`. Isi dan urutannya berasal dari `menuGroups` di
  `resources/js/config/menu.ts`.
- `AppMenuItem.vue` merender item secara rekursif. Item level pertama memiliki
  ikon; level turunan menggunakan markup submenu. Route aktif dicocokkan dengan
  path Ziggy, leluhur route aktif dibuka otomatis, dan chevron berputar.
- Mode desktop awal adalah `static`. Tombol navbar mengubah
  `staticMenuInactive`; CSS saat ini menyembunyikan/mentranslasikan sidebar,
  bukan menyisakan mode ikon. Mode `overlay` juga disediakan configurator.
- Di breakpoint 991px ke bawah, sidebar menjadi drawer selebar 20rem dan mask
  menutupnya saat area luar diklik. Tombol ellipsis topbar membuka kumpulan
  kontrol mobile yang sudah ada.
- Dark mode menambah/menghapus `.app-dark` pada elemen root. Toast dan flash
  message tersedia. Tidak ditemukan dropdown notifikasi khusus.
- Logo/nama aplikasi saat ini berada di topbar. Footer sidebar hanya memuat nama
  sistem, versi, dan copyright; tidak ada aksi di dalamnya. `AppFooter.vue`
  tidak dirender oleh `AppLayout.vue`.
- Tombol tema gelap, theme configurator, menu toggle, dan tombol ellipsis
  memiliki aksi. Tombol Calendar, Messages, dan Profile pada topbar saat ini
  tidak memiliki handler/link yang terlihat dalam komponen. Karena perubahan
  yang diminta hanya visual, tidak boleh menambahkan perilaku baru pada
  tombol-tombol tersebut.
- Tidak ditemukan field pencarian di topbar atau menu notifikasi dropdown;
  pencarian yang ada berada pada komponen filter halaman. Tidak ada kolom
  pencarian topbar yang perlu ditambahkan.

### Tema, warna, tipografi, dan ukuran

Sumber gaya saat ini tersebar di dua entry CSS Tailwind, SCSS layout, dan
PrimeVue:

1. `resources/css/app.css` mendefinisikan `--color-brand-*` ungu/magenta
   (brand-700 `#9e2d9e`, brand-800 `#862885`) dan `--color-primary-*`
   (primary-500 `#9e2d9e`), serta palette aksen cyan dan info biru
   (info-500 `#3b82f6`). Dashboard juga memakai kelas biru Tailwind secara
   langsung.
2. `resources/js/layout/composables/layout.ts` menginisialisasi
   `layoutConfig.primary` sebagai `emerald`. `AppConfigurator.vue` menyediakan
   banyak pilihan warna primary dan surface; pemilihan tersebut mengubah preset
   PrimeVue melalui `@primeuix/themes`.
3. Topbar memiliki warna magenta tersendiri (`#a626a6`), sedangkan beberapa
   elemen memakai warna Tailwind biru/hijau/ungu langsung.
4. `resources/js/assets/layout/variables/_common.scss` memetakan primary,
   teks, surface, radius, dan focus ke semantic token PrimeVue (`--p-*`).
5. `resources/css/app.css` mengimpor Outfit dari Google Fonts; `vite.config.js`
   mengonfigurasi Bunny font Instrument Sans; `resources/js/assets/layout/_core.scss`
   menetapkan body ke Lato. Tidak ditemukan font file Public Sans lokal pada
   `public/fonts` atau `resources`. Sumber font dan font-family efektif saat
   ini tidak tunggal.
6. `resources/js/assets/tailwind.css` mendefinisikan dark variant untuk
   `.app-dark`, sedangkan `resources/css/app.css` mendefinisikan `dark:` untuk
   `.dark`. Karena tombol tema saat ini hanya mengubah `.app-dark`, kedua
   entry tersebut berpotensi membuat kelas dark Tailwind bekerja berbeda.
7. Breakpoint Tailwind juga berbeda: stylesheet di `resources/js/assets/tailwind.css`
   memakai `sm:576px` dan `lg:992px`; `resources/css/app.css` memakai
   `sm:640px` dan `lg:1024px`. Ini perlu disatukan untuk hasil responsif yang
   konsisten.

Ukuran global yang relevan:

- `_core.scss` menetapkan `html { font-size: 14px; }`, bukan 16px. `rem` saat
  ini dihitung dari 14px.
- `_typography.scss` menetapkan gaya global h1–h6; khususnya `h2` memakai
  `font-size: 2rem` dan heading menerima margin global. Dashboard memakai
  heading seperti `<h2 class="text-base">`; aturan SCSS tidak berada pada
  cascade layer Tailwind, sehingga dapat mengalahkan utility Tailwind yang
  berada di layer. Ini adalah penyebab kuat judul panel tampak lebih besar
  daripada ukuran utility yang diminta.
- Skala root 80% yang direncanakan berarti sekitar 12.8px bila dihitung dari
  default browser 16px. Dibanding root project yang sekarang 14px, perubahan
  numeriknya adalah menjadi 12.8px; ukuran layar juga dipengaruhi zoom browser.
- Ukuran `px` tidak ikut mengecil bersama `rem`. Contoh yang tampak langsung:
  logo topbar `50x45px`, judul brand `18px`, subtitle `8px`, ukuran item menu
  `13px`, ikon sidebar `17px`, dan beberapa teks footer `8px`. Semua nilai tetap
  perlu ditinjau agar teks efektif tidak turun di bawah sekitar 12px.

### Sampling gambar referensi

Sampling dilakukan pada PNG yang diberikan, bukan mengambil warna dari ingatan
atau menyalin asset template. Koordinat di bawah relatif terhadap gambar penuh
(termasuk chrome browser), sehingga hanya berlaku untuk gambar referensi
tersebut. Warna antialias pada teks dapat menghasilkan piksel campuran.

| Token/elemen visual | Gambar project saat ini | Gambar target | Sampel target (x, y) |
|---|---:|---:|---:|
| Latar halaman | `#F1F5F9` | `#F5F5F9` | (230, 160) |
| Surface kartu | `#FFFFFF` | `#FFFFFF` | (1000, 180) |
| Aksen/primary aktif | `#7F2982` pada item aktif | `#696CFF` | (206, 177) |
| Latar item aktif | `#FAF5FF` | `#E7E7FF` | (20, 175) |
| Teks utama target | — | `#384551` | warna dominan pada teks sidebar |
| Pastel success | — | `#E8F9DF` | (1000, 190) |
| Pastel info/cyan | — | `#D6F5FC` | (1190, 190) |
| Pastel danger | — | `#FFE0DB` | (1000, 370) |
| Pastel violet | — | `#E7E7FF` | (1190, 370) |

Gambar target menunjukkan kartu putih dengan bayangan lembut, pemisahan visual
tanpa garis tepi yang kuat, dan sudut sekitar 6–8 piksel gambar. Secara geometri
visual, jarak/padding kartu sekitar 20 piksel gambar pada screenshot zoom 80%,
setara kurang lebih 24px CSS. Angka radius dan spacing adalah pembacaan geometri
gambar, bukan token warna hasil sampling.

### Screenshot baseline sebelum implementasi

Belum ada perubahan UI yang dilakukan. Gambar project yang dilampirkan
(1363x726) menjadi baseline yang tersedia untuk Dashboard desktop, sidebar
terbuka, tema terang, pada zoom 80%. Screenshot target yang dilampirkan
(1365x721) menjadi referensi visual Sneat.

Percobaan awal membuka aplikasi melalui browser terintegrasi mengarah ke
`/login`. Setelah sesi autentikasi tersedia, seluruh halaman baseline yang
dipilih berhasil dibuka dan diambil sebelum perubahan UI. Route `/dashboard`
menghasilkan 404 karena Dashboard memakai path `/`.

| Halaman/state baseline | Status |
|---|---|
| Dashboard, desktop, sidebar terbuka, terang | Tersimpan: `screenshots/baseline-dashboard-desktop-open-light.png` (1365x768) |
| Dashboard, desktop, sidebar collapsed, terang | Tersimpan: `screenshots/baseline-dashboard-desktop-collapsed-light.png` (1365x768) |
| Dashboard, desktop, sidebar terbuka, dark mode | Tersimpan: `screenshots/baseline-dashboard-desktop-open-dark.png` (1365x768) |
| Dashboard, mobile, drawer tertutup | Tersimpan: `screenshots/baseline-dashboard-mobile-drawer-closed-light.png` (390x844) |
| Dashboard, mobile, drawer terbuka | Tersimpan: `screenshots/baseline-dashboard-mobile-drawer-open-light.png` (390x844) |
| Halaman daftar Asset Directory | Tersimpan: `screenshots/baseline-asset-directory-desktop-light.png` (1365x768) |
| Halaman form Asset Registration | Tersimpan: `screenshots/baseline-asset-registration-form-desktop-light.png` (1365x768) |
| Halaman detail Asset Assignment | Tersimpan: `screenshots/baseline-asset-assignment-detail-desktop-light.png` (1365x768) |

Halaman yang dipakai sebagai baseline representatif adalah Dashboard `/`,
`Transaction/AssetManagement/Directory/Index.vue` untuk daftar,
`Transaction/AssetManagement/Registration/Create.vue` untuk form, dan
`Transaction/AssetManagement/Assignment/Show.vue` untuk detail. Gambar-gambar
di atas disimpan sebagai PNG di folder `screenshots/` sebelum perubahan global.

## 0.2 Target visual

Target berikut merupakan pembacaan gambar Sneat yang dilampirkan, bukan salinan
kode atau asset. Warna yang dapat dibaca langsung disertai hasil sampling di
tabel sebelumnya.

| Token | Nilai/arah target | Dasar |
|---|---|---|
| Primary | `#696CFF` | Sampel piksel pada aksen aktif |
| Page background | `#F5F5F9` | Sampel piksel latar halaman |
| Surface | `#FFFFFF` | Sampel area kartu |
| Active menu background | `#E7E7FF` | Sampel piksel latar item aktif |
| Main text | sekitar `#384551` | Sampel dominan teks/sidebar; verifikasi kontras saat implementasi |
| Status pastel | success `#E8F9DF`, info `#D6F5FC`, danger `#FFE0DB`, violet `#E7E7FF` | Sampel kotak ikon |
| Border | tipis dan samar; kartu target lebih mengandalkan bayangan | Pengamatan visual; tidak menetapkan nilai hex tanpa area piksel yang tegas |
| Card radius | sekitar 8px CSS sebagai kandidat | Radius tampak 6–8px pada screenshot 80% |
| Card shadow | lembut dan menyebar, bukan outline keras | Pengamatan visual |
| Card padding/gap | sekitar 24px CSS | Pengukuran geometri visual pada skala gambar 80% |
| Font | Public Sans, dimuat lokal | Persyaratan eksplisit; file font belum ditemukan di project |
| Skala root | `80%` / sekitar `12.8px` | Persyaratan eksplisit; tanpa CSS `zoom` |

## 0.3 Analisis gap dan urutan pengerjaan

| Area | Kondisi saat ini | Target dan gap | Risiko | Urutan |
|---|---|---|---|---:|
| Sidebar | Lebar 20rem, berada di bawah topbar, logo masih di topbar; item aktif magenta | Brand di bagian atas sidebar; active pill lavender/primary, ikon seragam, separator dan grup lebih terbaca | Sedang | 2 |
| Struktur menu | Tiga tingkat rekursif dan auto-open ancestor sudah ada; collapse desktop menyembunyikan sidebar sepenuhnya | Restyle tanpa mengubah isi/ID/route; mode ikon hanya bila dapat dipertahankan dengan aman; flyout tidak terdeteksi saat ini | Tinggi | 2 |
| Navbar | Fixed topbar setinggi 4rem, melebar di atas seluruh halaman; beberapa tombol kanan tidak memiliki aksi | Kartu floating/sticky di area konten, restyle hanya kontrol yang sudah ada; jangan menambahkan search/notification palsu | Sedang | 3 |
| Konten/page header | Layout main memakai margin/sidebar tetap dan padding; header halaman bervariasi; `Header.vue` memakai `h2` dan bar aksen | Wrapper maksimum dan page header konsisten tanpa mengganti isi/props | Sedang | 4 |
| Kartu | Dashboard memakai border gray dan `rounded-xl`; beberapa bagian digabung dalam satu panel | Kartu tanpa border tegas, bayangan lembut, radius/padding token; penerapan bertahap pada halaman | Sedang | 4 |
| Tabel | `CrudDataTable` memakai DataTable PrimeVue `stripedRows`, `showGridlines`, `scrollable`; filter terpisah | Sel/paginasi lebih lapang dan terbaca; responsif dan horizontal scroll tidak boleh rusak | Tinggi | 6 |
| Form/filter | SearchFilterBar memakai input tinggi `h-10`, border gray, brand focus; gaya form beragam | Token radius/tinggi/focus/spacing bersama melalui PrimeVue dan komponen reusable | Sedang | 6 |
| Tombol/badge | Warna utama magenta, biru, emerald, dan variasi semantic digunakan sekaligus; terdapat nilai hard-coded | Satu semantic primary konsisten dan status pastel; tinggi kontrol tetap nyaman pada skala baru | Sedang | 1, 6 |
| Dashboard | Statistik berupa satu panel grid bersekat; panel approval/attention berborder; ada data demo dan role gating | Kartu angka terpisah dan panel ber-token; hanya restyle, jangan mengubah props, fallback, route, role, atau sumber data | Tinggi | 5 |
| Dark mode | `.app-dark` di root; ada dark variables, tetapi definisi `dark:` pada dua stylesheet berbeda | Satu token light/dark dan selector seragam; wajib cek seluruh overlay PrimeVue | Tinggi | 1 |
| Responsif | Breakpoint 991/992 dan breakpoint Tailwind 576/640 serta 992/1024 bercampur; drawer mobile sudah ada | Satu sumber breakpoint; nav, tabel, overlay, dan konten diuji di desktop/tablet/mobile | Tinggi | 1, 2–6 |

Urutan yang disarankan mengikuti tahap yang diminta: fondasi token/skala; sidebar;
navbar; kerangka konten; Dashboard; komponen PrimeVue; lalu validasi lintas
halaman, dark mode, viewport, dan aksesibilitas. Setiap tahap harus menunggu
persetujuan sebelum mulai tahap berikutnya.

## Verifikasi Tahap 0

Validasi dilakukan pada working tree yang telah berisi perubahan lokal tersebut;
tidak ada source UI yang diubah oleh analisis ini.

| Pemeriksaan | Hasil |
|---|---|
| `npm run build` | Lulus (`vite v8.2.2`, 9.05 detik). Ada warning opsional bahwa paket `fontaine` tidak tersedia untuk optimized font fallbacks; build tetap sukses. |
| `vue-tsc --noEmit -p tsconfig.json` | Gagal pada TypeScript 6 karena `baseUrl` deprecated. |
| `vue-tsc --noEmit -p tsconfig.json --ignoreDeprecations 6.0` | Tetap gagal dengan banyak error yang sudah ada pada working tree, termasuk deklarasi Inertia/axios, tipe layout/sidebar, file layout lama yang hilang, dan tipe beberapa halaman. Tidak diperbaiki karena berada di luar analisis dan menyentuh pekerjaan lokal yang belum di-commit. |
| Lint frontend | Tidak tersedia sebagai script pada `package.json`. |
| `php artisan test --compact` | Gagal pada banyak test dengan error SQLite `no such column: uom_id` pada query migrasi/update produk. Kegagalan ini terjadi pada baseline working tree dan tidak diubah oleh analisis UI. |
| Console/browser | Tidak ditemukan console error pada halaman baseline yang dibuka. Ada warning Vue yang sudah muncul di baseline: directive `styleclass` dan `tooltip` serta komponen `SelectButton` tidak terdaftar. |

## Ringkasan dan hal yang menahan Tahap 0

Gambar target dapat diukur untuk warna utama, latar, surface, active tint, dan
kotak ikon pastel. Penyebab utama heading panel yang terlalu besar juga telah
teridentifikasi pada aturan global heading SCSS yang tidak masuk cascade layer
Tailwind. Peta tema dan struktur layout tersedia di atas.

Screenshot baseline sudah lengkap. Pemilik perubahan mengonfirmasi bahwa
perubahan lokal yang ada harus dipertahankan dan menjadi basis kerja; type-check
dan kegagalan tes saat ini dicatat sebagai baseline exception, bukan diperbaiki
di luar scope visual; informasi footer sidebar dipertahankan dan direstyle.

Sebelum mulai perubahan visual, masih perlu keputusan tentang warning Vue yang
ada: apakah pendaftaran minimal directive `styleclass`/`tooltip` dan komponen
`SelectButton` boleh diperbaiki pada surface layout yang sama agar kontrol yang
dimaksud tetap berfungsi dan warning hilang, atau warning ini dicatat sebagai
baseline exception tanpa perubahan kode? Theme configurator disarankan tetap
mempertahankan pilihan warna yang berfungsi, dengan satu semantic primary token
aktif dan dipakai bersama oleh semua komponen.

Tahap 0 selesai pada cabang `ui-revamp/phase-0-analysis`. Tidak ada tahap visual
berikutnya yang dimulai sebelum persetujuan pengguna.
