# Panduan UI Revamp

Dokumen ini merangkum fondasi visual dan struktur layout setelah Tahap 0–6.
Perubahan UI tetap terbatas pada presentasi; struktur menu dan route aplikasi
berada di `resources/js/config/menu.ts` dan tidak menjadi bagian dari panduan
styling ini.

## Token visual

Token global berada di `resources/css/app.css`, dalam selector `:root` dan
`:root.app-dark`. Nilai primary berasal dari palette di
`resources/js/layout/theme.ts`, yang dipakai oleh preset PrimeVue di
`resources/js/app.ts`.

| Token | Light | Dark | Penggunaan |
|---|---|---|---|
| Primary | `#696cff` | `#696cff` | Aksi utama, tautan, fokus, dan item aktif |
| Latar halaman | `#f5f5f9` | `#25293c` | Permukaan utama halaman |
| Surface | `#ffffff` | `#2f3349` | Kartu dan kontrol |
| Surface raised | `#ffffff` | `#34394f` | Permukaan yang ditinggikan |
| Border | `#e7e7f0` | `#434968` | Pemisah dan garis kontrol |
| Teks utama | `#384551` | `#e7e7f0` | Judul dan konten utama |
| Teks sekunder | `#6f7784` | `#b6bee3` | Deskripsi dan label |
| Teks muted | `#9299a5` | `#8f9bba` | Informasi tambahan |
| Success pastel | `#e8f9df` | `#263d31` | Status sukses |
| Info pastel | `#d6f5fc` | `#203a49` | Status informasi |
| Warning pastel | `#fff2d6` | `#483b2c` | Status peringatan |
| Danger pastel | `#ffe0db` | `#4b3038` | Status bahaya |
| Radius kartu | `0.5rem` | `0.5rem` | Kartu dan overlay |
| Radius kontrol | `0.375rem` | `0.375rem` | Tombol dan input |
| Shadow kartu | `0 2px 6px rgb(67 89 113 / 12%)` | `0 2px 6px rgb(0 0 0 / 24%)` | Kartu, dialog, dan overlay |
| Jarak kartu | `1.5rem` | `1.5rem` | Padding dan jarak antarbagian |

Font dasar adalah Public Sans lokal dari `public/fonts/PublicSans[wght].ttf`.
Skala root 80% (12.8px dari ukuran default browser 16px) ditetapkan di
`resources/js/assets/layout/_core.scss`.

## Mengubah skala, warna, atau permukaan

- **Skala root:** ubah aturan `html` di
  `resources/js/assets/layout/_core.scss`. Ukuran yang ditulis dalam `px`
  tidak mengikuti skala `rem`; periksa juga logo, ikon, dan target interaksi.
- **Warna primary:** ubah palette `citraPrimaryPalette` di
  `resources/js/layout/theme.ts`. Preset PrimeVue dan utility warna membaca
  palette tersebut melalui token `--p-primary-*`.
- **Token umum atau dark mode:** ubah custom properties di `:root` dan
  `:root.app-dark` pada `resources/css/app.css`. Pertahankan nama token agar
  halaman dan komponen bersama tetap menggunakan nilai yang sama.
- **Radius atau shadow PrimeVue/layout:** ubah token di `app.css`; deklarasi
  komponen yang mengonsumsi token berada di
  `resources/js/assets/styles.scss`.

Jalankan `npm run build` setelah perubahan stylesheet atau token. Project saat
ini tidak menyediakan script frontend terpisah untuk lint.

## Struktur layout

`resources/js/layout/AppLayout.vue` menyusun topbar, sidebar, kontainer konten,
toast, dan flash notification. Komponen terkait:

- `AppSidebar.vue`: brand, toggle sidebar, drawer mobile, footer versi, dan
  pemanggil menu.
- `AppMenu.vue` dan `AppMenuItem.vue`: grup menu dan render item bertingkat.
- `AppTopbar.vue`: kontrol tema, configurator, dan aksi topbar yang telah ada.
- `composables/layout.ts`: state sidebar, dark mode, dan konfigurasi layout.
- `config/menu.ts`: definisi grup, item, ID, serta nama route; ubah hanya bila
  memang ada perubahan kebutuhan navigasi, bukan untuk styling.
- `assets/styles.scss`: aturan layout responsif dan styling bersama PrimeVue.
- `css/app.css`: token warna, tipografi, radius, shadow, dan spacing.
- `pages/Dashboard/Index.vue`: kartu KPI serta panel dashboard.

Gunakan token dan komponen bersama terlebih dahulu sebelum menambah style
khusus halaman. Pertahankan props, struktur data, route, dan perilaku kontrol
ketika mengubah presentasi.

## Batasan yang sengaja belum dikerjakan

- Grafik dashboard dan library grafik baru tidak ditambahkan.
- Kontrol topbar yang belum memiliki aksi tetap tidak diberi perilaku palsu.
- Data fallback/demo dashboard tidak diubah menjadi sumber data baru.
- Route, controller, validasi, props Inertia, database, izin, dan struktur menu
tidak diubah oleh pekerjaan visual.
- Audit visual runtime seluruh halaman, state dark/collapsed/mobile, dan seluruh
menu masih memerlukan sesi aplikasi yang terautentikasi. Pada pemeriksaan
Tahap 7, tab browser yang tersedia diarahkan ke halaman login.
- Teks putih pada primary `#696cff` memiliki kontras sekitar `4.05:1`, di bawah
target `4.5:1` untuk teks normal. Menggelapkan warna utama akan menyimpang dari
warna yang disampling dari referensi; gunakan shade `#5f61e6` (sekitar
`4.84:1` terhadap putih) bila aksesibilitas kontras didahulukan daripada
kecocokan warna persis.
