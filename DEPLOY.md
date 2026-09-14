# Deploy — SC Online (production-lab-online-hs)

Alur: **push ke `main`** → GitHub Actions build image Docker → push ke GHCR →
deploy otomatis ke VPS produksi via SSH.

App ini jalan di **VPS yang sama dengan POS-Harumnya**, tapi terisolasi
(DB, assets, domain sendiri). `nginx-proxy` + `acme-companion` milik stack
POS-Harumnya dipakai bersama — routing by domain, SSL otomatis lewat
Let's Encrypt.

```
push main ──► [deploy.yml] build image (:<sha> + :latest) ──► deploy ke VPS (sc.harumnya.cloud)
```

---

## Setup satu kali (VPS + GitHub)

### 1. DNS
Tambah A record domain app ini (ganti `sc.harumnya.cloud` sesuai domain final)
→ **IP VPS yang sama** dengan POS-Harumnya.
Cek: `dig +short sc.harumnya.cloud` harus mengembalikan IP VPS.

### 2. Pastikan stack POS-Harumnya sudah jalan
`nginx-proxy` + `acme-companion` dipakai bersama dari stack POS-Harumnya.
```bash
docker ps --filter name=nginx-proxy   # harus ada & running
```
Workflow deploy otomatis mendeteksi nama network dari container `nginx-proxy`.
(Kalau nama container proxy bukan `nginx-proxy`, sesuaikan di `.github/workflows/deploy.yml`.)

### 3. Clone repo di VPS
```bash
ssh <user>@<vps>
sudo mkdir -p /var/www/production-lab-online-hs
sudo chown $USER:$USER /var/www/production-lab-online-hs
git clone https://github.com/itdevelopmenths/production-lab-online-hs.git /var/www/production-lab-online-hs
```

### 4. Buat `.env` production
```bash
cd /var/www/production-lab-online-hs
cp .env.production.example .env
# edit .env: isi DB_PASSWORD, APP_URL, APP_DOMAIN sesuai domain final.
# APP_KEY biarkan kosong — workflow generate otomatis saat deploy pertama.
nano .env
```

### 5. GitHub Secrets
Repo GitHub ini terpisah dari repo POS-Harumnya, jadi secrets **perlu ditambah
baru** (meski VPS-nya sama) di Settings → Secrets and variables → Actions:
- `VPS_HOST` — IP / hostname VPS
- `VPS_USERNAME` — user SSH di VPS
- `VPS_SSH_KEY` — private key SSH (yang public key-nya sudah ada di `~/.ssh/authorized_keys` VPS)

`GITHUB_TOKEN` untuk push ke GHCR otomatis tersedia, tidak perlu ditambah.

### 6. (Opsional) Gate approval production
GitHub → Settings → Environments → **production** → **Required reviewers**.
Efek: setiap push ke `main` butuh approval manual sebelum deploy jalan.

### 7. Image GHCR privat → izinkan VPS pull
Image di GHCR default privat. Repo dan VPS memakai akun/organisasi yang sama
(`itdevelopmenths`), jadi `GITHUB_TOKEN` bawaan workflow cukup untuk
login+pull di VPS (dilakukan otomatis oleh langkah SSH di `deploy.yml`).

---

## Pemakaian sehari-hari

Cukup `git push origin main`. Workflow **Deploy to Production** jalan otomatis:
1. Build image (tag = commit SHA + `latest`), push ke GHCR.
2. SSH ke VPS, checkout commit yang sama, pull image, `docker compose up -d`.
3. Jalankan migration, `storage:link`, `optimize`.

Cek hasil deploy:
```bash
cd /var/www/production-lab-online-hs
docker compose ps
docker compose logs -f app
```

---

## Catatan

- **Redis / queue worker tidak dipakai** — cache, session, dan queue app ini
  memakai driver `database` (lihat `.env.production.example`), jadi tidak ada
  container Redis di `docker-compose.yml`. Tambahkan sendiri kalau nanti
  dibutuhkan (mis. untuk queue job berat).
- Cert HTTPS di-issue otomatis oleh `acme-companion` milik POS-Harumnya begitu
  DNS resolve + container nginx app ini naik (lewat `nginx-proxy` di port 80/443).
- Backup DB harian otomatis (`db-backup` service, jam 03:00, retensi 7 hari)
  disimpan di `./backups` pada VPS.
- Kelola stack manual:
  ```bash
  cd /var/www/production-lab-online-hs
  docker compose ps
  docker compose logs -f app
  docker compose exec app php artisan tinker
  ```
