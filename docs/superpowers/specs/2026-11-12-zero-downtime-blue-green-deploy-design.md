# Design Spec: Zero-Downtime Blue-Green Deployment (Approach 1)

- **Tanggal:** 2026-11-12
- **Status:** Draft (Menunggu Review & Approval)
- **Pendekatan Terpilih:** Pendekatan 1 — Blue-Green `app` Containers (`app_blue` & `app_green`) di Belakang `sc_online_nginx` Persisten

---

## 1. Intent & Why

Saat ini setiap push ke branch `main` memicu `.github/workflows/deploy.yml` yang menimbulkan **tiga sumber downtime berurutan**:
1. **`php artisan down --render="errors::503"`** dijalankan sebelum `git fetch`, `git checkout`, `docker compose up -d`, hardcoded `sleep 15`, `migrate`, `optimize`, dan `restart app` — menyebabkan pengguna langsung mendapat `503 Service Unavailable` selama puluhan detik, dan berisiko tersangkut permanen di `storage/framework/down` apabila script gagal sebelum `php artisan up || true`.
2. **Container tunggal `sc_online_app`** harus dihentikan dan dibuat ulang (`docker compose up -d` / `docker compose restart app`) setiap ada image GHCR atau perubahan kode PHP, menyebabkan Nginx (`fastcgi_pass sc_online_app:9000`) merespons **`502 Bad Gateway`** selama beberapa detik sementara PHP-FPM mati dan booting ulang.
3. **Live host bind-mount (`./app`, `./routes`, `./resources`, `./config`, `./bootstrap/cache`) dan volume bersama `view_cache` / `public_data`** membuat kode aplikasi dan cache Laravel berubah di bawah request yang masih berjalan saat `git checkout -f` dan `artisan optimize:clear` dieksekusi.

Tujuan desain ini adalah menghilangkan jendela downtime (`503` dan `502`) menjadi **0 ms** pada setiap deploy normal setelah migrasi awal, menggunakan **image container immutable GHCR** pada dua slot `app` (`blue` dan `green`) di belakang container `sc_online_nginx` yang tetap hidup terus tanpa restart.

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
1. **Zero HTTP Errors Saat Deploy Normal:** Tidak ada `php artisan down`, tidak ada restart `sc_online_nginx`, dan tidak ada cutover sebelum container target lolos healthcheck penuh + smoke check HTTP `/up`.
2. **Isolasi Runtime Antar Slot (`blue` vs `green`):**
   - Kedua slot menjalankan image immutable GHCR masing-masing (`BLUE_IMAGE` / `GREEN_IMAGE`) tanpa bind-mount `./app`, `./routes`, `./resources`, `./config`, sehingga `git checkout -f` di host tidak pernah menginterupsi request aktif.
   - Cache framework (`/var/www/bootstrap/cache` dan `/var/www/view-cache` via `VIEW_COMPILED_PATH`) terisolasi per container agar `artisan optimize` dan `artisan view:cache` pada slot pasif tidak menyentuh slot aktif.
3. **Aset Vite Tanpa 404 Selama Transisi Browser:** File statis hasil build Vite (`/var/www/public/build` & `/var/www/public/vendor`) disalin secara **aditif** ke volume `public_data` ketika slot pasif boot, sehingga tab browser pengguna yang masih memegang HTML versi lama tetap dapat memuat hash JS/CSS lama sementara pengguna baru langsung menerima aset baru; aset usang dipangkas aman (`mtime +7 hari`) setelah drain.
4. **Cutover Atomik & Auto-Rollback Cepat (< 2 detik):** Perpindahan traffic memakai file konfigurasi upstream terpisah yang di-include Nginx (`nginx -t && nginx -s reload`). Jika healthcheck, reload Nginx, atau verifikasi HTTP `/up` gagal pasca-cutover, workflow otomatis mengembalikan upstream ke slot sebelumnya dan menghentikan slot gagal.
5. **Menghapus `sleep 15` Buta & Menjamin Kebersihan `down` File:** Menunggu kesiapan Postgres lewat polling healthcheck (`pg_isready` / Docker healthcheck) alih-alih tidur 15 detik, dan memastikan legacy `storage/framework/down` dibersihkan otomatis.

### Non-Goals
- Tidak mengganti arsitektur `nginx-proxy` + `acme-companion` milik stack `POS-Harumnya` di level host.
- Tidak menambah service baru yang belum ada di `docker-compose.yml` (seperti queue worker terpisah, Redis, atau Horizon).
- Tidak mengubah runtime `POS-Harumnya` atau memisahkan VPS staging vs produksi (satu stack Compose yang di-deploy workflow tetap memakai nama/domain saat ini).
- Tidak mengotomatisasi validasi kode SQL/migrasi di CI — **aturan disiplin *expand-and-contract* pada migration Laravel menjadi tanggung jawab pengembang** (migration selama overlap slot tidak boleh langsung me-drop/rename kolom atau tabel yang masih dibaca kode versi sebelumnya pada deploy yang sama).

### Anti-Goals
- **Jangan pernah** merestart `sc_online_nginx` atau `db` pada deploy kode normal setelah transisi perdana selesai.
- **Jangan pernah** me-share `./bootstrap/cache` atau `view_cache` antar slot `blue` dan `green`.
- **Jangan pernah** membiarkan deploy sukses secara diam-diam dengan `|| true` pada langkah kritis cutover, sync aset publik, atau rollback.

---

## 3. Approaches Considered

| Pendekatan | Mekanisme | Trade-off | Status |
|---|---|---|---|
| **1. Blue-Green `app` di Belakang `sc_online_nginx` Persisten** | `sc_online_nginx` tetap hidup; slot pasif (`app_blue`/`app_green`) boot dari image GHCR, migrate + optimize + smoke check `/up`, lalu `nginx -s reload` switch upstream FastCGI secara atomik + drain slot lama | Memerlukan tracking state slot (`.deploy/`), perubahan layout `docker-compose.yml` + `default.conf`, serta overlap RAM 2 container `app` selama ~15–30 detik (sudah dikonfirmasi kapasitas VPS memadai) | **Dipilih *(Recommended)*** |
| **2. Blue-Green Penuh di `nginx-proxy`** | Dua stack penuh (`app + nginx`) mendaftarkan `VIRTUAL_HOST` di `nginx-proxy` eksternal | Menggandakan container Nginx, memicu regenerasi config `nginx-proxy` bersama `POS-Harumnya`, dan rentan timing window round-robin `docker-gen` saat kedua Nginx hidup bersamaan | Ditolak |
| **3. Single Container + Symlink + `USR2` Reload** | Satu container `app`, hapus `artisan down`, ganti release symlink | Setiap push membangun image GHCR baru sehingga `docker compose up -d` tetap mematikan container `app` (~3–8 detik `502 Bad Gateway`), melanggar target zero-downtime | Ditolak |

---

## 4. Arsitektur & Kontrak Teknis

### 4.1. Docker Compose Topology (`docker-compose.yml`)
1. **`db` & `db-backup`**: Tidak berubah pada jaringan `internal`, menjaga volume `pgdata` dan backup harian.
2. **Dua Slot PHP-FPM dengan Docker Compose `profiles`:**
   - Service `app_blue` (`container_name: sc_online_app_blue`, `profiles: ["blue"]`, `image: ${BLUE_IMAGE:-ghcr.io/itdevelopmenths/production-lab-online-hs:latest}`).
   - Service `app_green` (`container_name: sc_online_app_green`, `profiles: ["green"]`, `image: ${GREEN_IMAGE:-ghcr.io/itdevelopmenths/production-lab-online-hs:latest}`).
   - Kedua service menggunakan Compose anchor/YAML block yang identik (kecuali `container_name`, `profiles`, dan `image`):
     - `environment`: `VIEW_COMPILED_PATH=/var/www/view-cache`.
     - `volumes` (HANYA state persisten — **tanpa** `./app`, `./routes`, `./resources`, `./config`, `./bootstrap/cache`, maupun shared `view_cache`):
       - `./.env:/var/www/.env`
       - `./storage:/var/www/storage`
       - `public_data:/var/www/public-shared`
     - `networks`: `internal`.
     - `healthcheck`: TCP check FastCGI port `9000` (`interval: 3s`, `timeout: 2s`, `retries: 10`, `start_period: 5s`), plus kesiapan `/up` diverifikasi langsung dari host/script sebelum cutover.
     - `stop_grace_period: 30s` dan `stop_signal: SIGQUIT` (PHP-FPM memproses sisa request sebelum exit saat drain).
3. **`nginx` (`sc_online_nginx`) Persisten:**
   - **Hapus `depends_on: app: condition: service_healthy`** agar `docker compose up -d nginx` (saat startup/perubahan config) tidak bergantung pada kedua slot atau memicu restart slot `app`. Startup sequence dikendalikan oleh workflow deploy (memastikan slot aktif sudah `healthy` sebelum `nginx` dijalankan/di-reload).
   - Mount volume:
     - `./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro`
     - `./.deploy/nginx-upstream.conf:/etc/nginx/conf.d/upstream.inc:ro` *(dipasang di luar `conf.d/*.conf` agar tidak ikut di-glob sebagai `server {}` top-level, atau bernama `.inc` yang di-`include` dari `default.conf`)* — berisi definisi `upstream php_backend { server sc_online_app_<slot>:9000; }` di root context atau di-include via `/etc/nginx/conf.d/00-upstream.conf`.
       - *Keputusan teknis (builder detail):* Cara paling standar Nginx adalah menaruh `./.deploy/upstream.conf` pada `/etc/nginx/conf.d/00-upstream.conf:ro` (file `.conf` terpisah berisi blok `upstream php_backend { server sc_online_app_blue:9000; }`), dan `default.conf` memanggil `fastcgi_pass php_backend;`. File `.deploy/` di-gitignore agar `git checkout -f` tidak pernah menimpanya.
     - `public_data:/var/www/public`
     - `./storage/app/public:/var/www/public/storage:ro`
     - `./logs/nginx:/var/log/nginx`

### 4.2. Entrypoint & Image Behavior (`Dockerfile`, `docker/scripts/entrypoint.sh`)
- `Dockerfile`: pastikan `/var/www/bootstrap/cache` dan `/var/www/view-cache` di image bersih (bukan stale cache lokal) dan milik `www-data:www-data` mode `775`. Tambahkan binary `fcgi` / atau `curl` (atau script PHP FastCGI smoke test ringan memakai `php -r` / socket FastCGI — `php` CLI sudah tersedia di container, serta `nginx` / `curl` untuk smoke test `/up` di `nginx` container atau script helper).
- `docker/scripts/entrypoint.sh`:
  - Menyalin `/var/www/public/.` ke `/var/www/public-shared/` **tanpa `2>/dev/null || true`** supaya kegagalan copy aset menggagalkan startup container lebih awal sebelum cutover.
  - Penting: Copy file statis memakai `cp -a /var/www/public/vendor /var/www/public-shared/` & `cp -a /var/www/public/build /var/www/public-shared/` secara aditif (chunk lama dengan hash berbeda tidak dihapus saat copy). Jangan menimpa `/var/www/public-shared/storage` bila direktori storage sudah dimount atau abaikan symlink `storage` agar tidak menabrak mount point `./storage/app/public` di container `nginx`.
  - Setelah sync `public-shared` selesai, jalankan `exec php-fpm`.

### 4.3. Alur Deploy & State Directory (`.deploy/` di Host + `.github/workflows/deploy.yml`)
File state host di `/var/www/production-lab-online-hs/.deploy/` (di-gitignore agar aman dari `git clean`/`git checkout`):
- `.deploy/active_slot` berisi `blue` atau `green`.
- `.deploy/slot.env` berisi `BLUE_IMAGE=...` dan `GREEN_IMAGE=...` (dipassing ke `docker compose --env-file .env --env-file .deploy/slot.env` atau diekspor sebagai environment variable sebelum memanggil `docker compose`, sehingga Docker Compose tahu persis image tag slot aktif & pasif tanpa tabrakan tag `:latest`!).
- `.deploy/upstream.conf` berisi `upstream php_backend { server sc_online_app_<slot>:9000; }`.

**Urutan Step SSH Deploy (Zero-Downtime):**
1. **Concurreny & Image Pull:** `concurrency: group: production-deploy` di GitHub Actions tetap `cancel-in-progress: false`. Di VPS: login GHCR, `docker pull "$IMAGE:$GIT_SHA"`. Deteksi `PROXY_NETWORK` dari `nginx-proxy` (fail fast jika tidak ditemukan, sebelum menyentuh file/container).
2. **Git Checkout & Inisialisasi `.deploy/` State:**
   - `git fetch origin && git checkout -f "$GIT_SHA"` (aman terhadap container aktif karena kode container aktif terisolasi dalam image-nya).
   - Pastikan `.env` ada. Jika `APP_KEY` belum bertipe `base64:`, generate langsung sebelum slot jalan (via container `docker run --rm` sementara atau helper) sehingga `.env` siap bagi kedua slot.
   - Siapkan direktori `logs/nginx`, `storage/...`, `.deploy/`. Jika `.deploy/active_slot` belum ada (transisi perdana atau fresh VPS), deteksi apakah legacy container `sc_online_app` sedang berjalan (`HAS_LEGACY=true`), set default `ACTIVE_SLOT=""` dan `TARGET_SLOT="blue"`, lalu tulis `.deploy/upstream.conf` awal bila belum ada (`sc_online_app_blue:9000`).
   - Jika `ACTIVE_SLOT` adalah `blue`, maka `TARGET_SLOT="green"` (dan sebaliknya). Muat `BLUE_IMAGE`/`GREEN_IMAGE` saat ini dan perbarui variabel image milik `TARGET_SLOT` menjadi `"$IMAGE:$GIT_SHA"`.
3. **Pastikan Infra Dasar (`db`, `db-backup`) Aktif & Sehat:**
   - Jalankan `docker compose up -d db db-backup`.
   - Polling kesiapan database hingga sehat (misalnya loop maksimal 30x tiap 1 detik memeriksa `pg_isready`, **keluar instan dalam < 1 detik bila DB sudah running** — hapus `sleep 15`).
4. **Pastikan Permissions Host Storage Aman Sebelum Boot Slot Target:**
   - Pastikan `storage` dan subdirektorinya dimiliki `www-data:${HOST_GID}` (`2775`/`664`), tanpa menyentuh ownership container aktif secara destruktif dan hapus sisa file `storage/framework/down` / `storage/framework/maintenance.php` bila masih ada dari deploy lama.
5. **Boot Slot Pasif (`app_${TARGET_SLOT}`):**
   - `docker compose --profile "$TARGET_SLOT" up -d --no-deps --force-recreate "app_${TARGET_SLOT}"`
   - Polling status container `sc_online_app_${TARGET_SLOT}` hingga `healthy` (maks ~45 detik; jika gagal, collect container logs lalu exit 1 — **slot aktif `app_${ACTIVE_SLOT}` tidak tersentuh sedikit pun!**).
6. **Warmup, Migrasi, & Internal Smoke Test pada Slot Pasif (`app_${TARGET_SLOT}`):**
   - Jalankan di `app_${TARGET_SLOT}` (belum menerima traffic luar):
     - `php artisan migrate --force`
     - `php artisan storage:link --force`
     - `php artisan optimize` (`config:cache`, `route:cache`, `events:cache` masuk ke cache container target)
     - `php artisan view:cache` (masuk ke `/var/www/view-cache` container target)
   - Catat: karena `app_${TARGET_SLOT}` belum menerima request publik saat `optimize`/`view:cache`, tidak perlu `optimize:clear` di container live; dan saat cutover, workers PHP-FPM container target otomatis melayani dengan OPcache & cache bersih.
   - Jalankan verifikasi aplikasi pada slot target **sebelum cutover** (memastikan Laravel boot, DB terhubung, tidak ada syntax/autoload/config fatal error): misal mengeksekusi script Artisan / PHP request `/up` di `app_${TARGET_SLOT}` via socket FastCGI port `9000` atau `php -r` / CLI health check ke `127.0.0.1:9000`.
7. **Pastikan `nginx` (`sc_online_nginx`) Tersedia & Cutover Atomik + Auto-Rollback:**
   - **Kasus normal (`sc_online_nginx` sudah memakai mount `00-upstream.conf`):**
     - Simpan backup `.deploy/upstream.conf.bak`.
     - Tulis `upstream php_backend { server sc_online_app_${TARGET_SLOT}:9000; }` ke `.deploy/upstream.conf`.
     - Validasi & reload atomik: `docker compose exec -T nginx nginx -t && docker compose exec -T nginx nginx -s reload`.
     - Smoke test live endpoint lewat container `nginx` (`http://127.0.0.1/up` via `wget`/`curl` di dalam `nginx` atau check internal `sc_online_nginx`) -> pastikan HTTP `200`.
     - Jika validasi / reload / smoke test pasca-cutover gagal **dan** `$ACTIVE_SLOT` sebelumnya aktif: otomatis kembalikan `.deploy/upstream.conf.bak` ke `.deploy/upstream.conf`, jalankan `nginx -t && nginx -s reload`, hentikan slot gagal `app_${TARGET_SLOT}`, dan exit `1`!
   - **Kasus transisi perdana dari stack legacy (`HAS_LEGACY=true` / `nginx` belum memakai mount `00-upstream.conf`):**
     - `app_blue` sudah `healthy`, termigrasi, ter-cache, dan lulus smoke check.
     - Tulis `.deploy/upstream.conf` mengarah ke `sc_online_app_blue:9000`.
     - Recreate satu kali `docker compose up -d --no-deps --force-recreate nginx` (inilah satu-satunya jendela singkat ~1–2 detik saat transisi perdana dari konfigurasi legacy; seluruh deploy berikutnya tidak menyentuh `nginx`).
     - Verifikasi `nginx` HTTP `/up` = `200`, lalu stop & remove legacy container `sc_online_app`.
8. **Drain Graceful, Persist State, & Cleanup Slot Lama:**
   - Tunggu grace drain (`sleep 10`) agar worker Nginx lama menuntaskan seluruh request in-flight ke `app_${ACTIVE_SLOT}`.
   - Persist `.deploy/active_slot` = `${TARGET_SLOT}` dan simpan `.deploy/slot.env` terbaru.
   - Hentikan dan hapus container slot lama (`docker compose --profile "$ACTIVE_SLOT" stop -t 15 "app_${ACTIVE_SLOT}"` lalu `rm -f "app_${ACTIVE_SLOT}"`) jika `ACTIVE_SLOT` terdefinisi.
   - Hapus file build Vite usang pada volume `public_data` (misal file di `/var/www/public-shared/build/assets` yang berumur > 7 hari) tanpa mengganggu aset aktif, serta jalankan cleanup image lama aman (`docker image prune -f` hanya untuk dangling atau filter `until=168h` agar image slot sebelumnya tetap tersedia bila diperlukan rollback manual).

---

## 5. Penanganan Edge Case & Ketahanan

1. **Deploy Kedua Saat Deploy Pertama Sedang Berjalan:** `concurrency: group: production-deploy` dengan `cancel-in-progress: false` memastikan deploy selalu antre berurutan, tidak pernah paralel di VPS.
2. **Rollback Manual atau Otomatis:** Karena image slot sebelumnya masih ada di VPS (`$IMAGE:<prev_sha>`), jika terjadi error tepat setelah cutover, auto-rollback mengembalikan Nginx upstream ke slot lama (yang masih hidup saat cutover karena drain belum berjalan!) dalam **< 2 detik**.
3. **Konfigurasi PHP-FPM Memori Saat Overlap 2 Slot:** Selama ~15–30 detik di Step 5–8, dua container `app` aktif bersamaan (`pm.max_children = 50` per container). Di dalam slot pasif yang belum menerima trafik, PHP-FPM hanya memakan `pm.start_servers = 10` (~300–400 MB). Tetap aman di VPS dengan headroom RAM memadai.
4. **Perubahan `.env` di VPS Host:** Jika admin mengubah `.env` di VPS, `.deploy/` workflow berikutnya langsung me-mount `.env` terbaru ke container pasif dan `php artisan optimize` mengkompilasi ulang `config` cache khusus untuk slot baru tersebut.

---

## 6. Yang Disisakan untuk Builder / Implementation Plan

- Detail sintaks exact YAML pada `docker-compose.yml` (anchor `&app-base`, definisi `profiles`, `stop_signal: SIGQUIT`, `stop_grace_period`).
- Implementasi helper pengujian otomatis (misal unit/config test atau bash/compose validation script di CI / PHPUnit test untuk memastikan invariant zero-downtime pada `deploy.yml`, `docker-compose.yml`, `default.conf`, dan `entrypoint.sh` tidak terhapus oleh regresi di masa depan, plus simulasi syntax Nginx & YAML).
- Urutan task bertahap lengkap dengan verifikasi lokal (Compose config check, Nginx config validation, Dockerfile build check, PHPUnit test check) dalam `docs/superpowers/plans/`.

