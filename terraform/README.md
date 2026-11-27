# Deploy Ion Jaya Motor ke Azure App Service (B1) dengan Terraform

Provision infrastruktur untuk demo semhas ("sistem di online kan"):
**App Service Plan B1 (Linux) + Linux Web App PHP 8.2 + MySQL Flexible Server B1ms.**

> Terraform di sini hanya membuat **infrastruktur**. Kode aplikasi di-deploy
> terpisah (GitHub Actions, lihat `.github/workflows/deploy-azure.yml`).
> `terraform apply` **tidak** dijalankan otomatis — kamu yang menjalankan
> manual setelah membaca estimasi biaya di bawah.

## Yang dibuat

| Resource | Detail |
|---|---|
| Resource Group | `ionjaya-production-rg` (Southeast Asia / Singapura) |
| App Service Plan | B1 Linux |
| Linux Web App | PHP 8.2, Always On, HTTPS only, nama `ionjaya-XXXX` (unik global) |
| MySQL Flexible Server | Burstable `B_Standard_B1ms`, MySQL 8.0.21, storage 20 GB, backup 7 hari |
| Database | `ionjaya_db` (utf8mb4) |
| Firewall MySQL | Izinkan semua layanan Azure + IP laptop (opsional) |
| Password admin MySQL | Dibuat acak oleh Terraform, tampil di output (sensitif) |

Tidak dibuat (sengaja): Redis, queue worker, Application Insights — proyek ini
tidak membutuhkannya (session/cache pakai database, queue = sync).

## Estimasi biaya (harga pay-as-you-go, cek ulang di Azure Pricing Calculator)

| Item | Perkiraan / bulan |
|---|---|
| App Service Plan B1 (Linux) | ± Rp 200.000 (± $13) |
| MySQL Flexible B1ms (20 GB) | ± Rp 190.000–250.000 (± $12–16) |
| **Total** | **± Rp 400.000–450.000 (± $25–30)** |

Kredit **Azure for Students $100 (± Rp 1,6 juta)** cukup untuk **± 3–4 bulan**.
Setelah kredit habis, tagihan jalan terus sampai resource dihapus — hapus dengan
`terraform destroy` kalau sudah tidak dipakai (lihat bawah).

## Prasyarat

1. Akun Azure aktif (Azure for Students, kredit $100).
2. Di laptop (Windows): install [Terraform](https://developer.hashicorp.com/terraform/install)
   dan [Azure CLI](https://learn.microsoft.com/cli/azure/install-azure-cli-windows).
   Atau pakai [Azure Cloud Shell](https://shell.azure.com) (Terraform sudah tersedia).
3. Repo sudah di-pull terbaru (`git pull`).

## Langkah 1 — Login Azure

```powershell
az login
az account show   # pastikan subscription yang aktif adalah yang ada kredit $100
```

## Langkah 2 — Siapkan variabel rahasia

```powershell
cd terraform
copy terraform.tfvars.example terraform.tfvars
```

Isi `terraform.tfvars`:
- `app_key` — dari `.env` laptop: `php artisan key:generate --show`
  (boleh pakai APP_KEY yang sama dengan `.env` lokal).
- `admin_client_ip` — opsional, IP publik laptop (cek di https://ifconfig.me)
  supaya bisa konek MySQL langsung dari laptop. Kosongkan bila tidak perlu.

## Langkah 3 — Provision

```powershell
terraform init
terraform plan    # baca dulu daftar resource yang akan dibuat + biayanya
terraform apply   # ketik `yes` saat diminta
```

Catat outputnya, terutama:
- `webapp_url` — URL publik aplikasi (untuk demo).
- `mysql_admin_password` — simpan baik-baik (tidak bisa dilihat ulang
  kecuali lewat `terraform output -raw mysql_admin_password`).

## Langkah 4 — Deploy kode aplikasi

Otomatis via GitHub Actions (file `.github/workflows/deploy-azure.yml`):
setelah `terraform apply`, ambil publish profile lalu simpan sebagai secret
di repo GitHub:

```powershell
az webapp deployment list-publishing-profiles --name <nama-webapp-dari-output> --resource-group ionjaya-production-rg --xml
```

Di GitHub: repo → Settings → Secrets and variables → Actions → New secret:
- `AZURE_WEBAPP_NAME` = nama Web App (mis. `ionjaya-a1b2`)
- `AZURE_WEBAPP_PUBLISH_PROFILE` = seluruh isi XML publish profile

Setelah itu, setiap `git push` ke `main` otomatis deploy. Deploy manual:
GitHub → Actions → "Deploy ke Azure App Service" → Run workflow.

Catatan: workflow membangun aset frontend (`npm run build`) di Actions,
sedangkan `composer install` dijalankan Oryx di server saat deploy
(`SCM_DO_BUILD_DURING_DEPLOYMENT=true`).

## Langkah 5 — Setup pertama di server (sekali saja)

Masuk SSH lewat Azure Portal (Web App → SSH) atau `az webapp ssh`, lalu:

```bash
cd /home/site/wwwroot
php artisan migrate --force   # buat semua tabel di ionjaya_db
php artisan storage:link      # symlink public/storage
```

Lalu impor data simulasi v5 **lewat fitur Impor Data di aplikasi**
(ke database FRESH — jangan impor dua kali). Setelah itu cek:
- Dashboard menampilkan data, dan
- Analisis Persediaan 1 Jan–31 Okt 2026 menampilkan **83 Fast / 61 Slow / 39 Non**.

## Hapus semua resource (berhenti bayar)

```powershell
cd terraform
terraform destroy   # ketik `yes` — database ikut terhapus, backup dulu bila perlu
```

## Troubleshooting

- **Deploy gagal / halaman 500:** cek Log Stream di Portal (Web App →
  Log stream) dan `storage/logs/laravel.log` via SSH.
- **Aplikasi tidak bisa konek MySQL:** cek `DB_HOST` di Configuration
  (Portal) sama dengan output `mysql_fqdn`; pastikan tidak ada typo.
- **Aset CSS/JS tidak muncul:** pastikan `npm run build` jalan di Actions
  (cek tab Actions) dan folder `public/build` ikut ter-deploy.
- **`storage:link` gagal karena link sudah ada:** abaikan — link sudah dibuat
  di deploy sebelumnya.
- **Kuota kredit habis:** Azure menonaktifkan resource berbayar; segera
  `terraform destroy` atau pindah ke hosting lain (lihat ceklist deploy
  Rumahweb yang sudah dibuat sebelumnya).

## Catatan keamanan (untuk sidang, bila ditanya)

- `APP_DEBUG=false` di server (error detail tidak bocor ke pengunjung).
- MySQL `require_secure_transport=OFF` — disengaja agar Laravel PDO
  bisa konek tanpa sertifikat SSL; trafik tetap di dalam jaringan Azure.
  Kalau ditanya penguji: ini trade-off demo, bukan praktik produksi.
- Firewall MySQL membuka `0.0.0.0` **hanya untuk layanan Azure**
  (bukan internet umum); akses tetap butuh username + password.
- Password admin MySQL dibuat acak oleh Terraform dan tidak tertulis
  di file mana pun (hanya di state + output sensitif).
