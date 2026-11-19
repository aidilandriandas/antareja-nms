# ANTAREJA NMS — Daftar Perubahan terhadap Kode Inti LibreNMS

ANTAREJA NMS adalah karya turunan dari [LibreNMS](https://github.com/librenms/librenms)
(GPLv3). Dokumen ini memenuhi kewajiban GPLv3 untuk mencatat perubahan, dan menjadi
panduan saat menggabungkan update dari upstream.

- **Basis upstream:** LibreNMS `26.10.2` (commit upstream `17387db`), disimpan di branch `upstream-sync`
- **Prinsip:** semua fitur baru berada di `packages/antareja/`. File inti hanya disentuh
  bila tidak ada hook, dan setiap perubahan dicatat di bawah ini.

## Perubahan file inti (harus dicek ulang setiap sinkron upstream)

| File | Perubahan | Alasan |
|---|---|---|
| `composer.json` | Tambah autoload PSR-4 `"Antareja\\": "packages/antareja/src/"` | Memuat kode paket ANTAREJA |
| `bootstrap/providers.php` | Tambah `Antareja\AntarejaServiceProvider::class` | Mendaftarkan route, view, migration, perintah ANTAREJA |
| `resources/views/layouts/menu.blade.php` | Sisipkan `@includeIf('antareja::partials.navbar')` di awal navbar | Menu modul ANTAREJA di navigasi utama |
| `resources/css/app.css` | Tambah `@source` untuk `packages/antareja/resources/views` | Kelas Tailwind milik ANTAREJA ikut dikompilasi |
| `resources/definitions/permissions.yaml` | Tambah 13 grup permission `ant-*` | Permission ANTAREJA tampil di editor role |
| `html/build/*` | Dibangun ulang (`npm run build`) | Hasil kompilasi dari perubahan `app.css` |

## File baru (tidak bentrok dengan upstream)

| Path | Isi |
|---|---|
| `packages/antareja/` | Paket Laravel ANTAREJA (service provider, modul, view, migration, perintah `antareja:roles`) |
| `html/images/antareja/` | Logo & favicon ANTAREJA |
| `docker/` | Dockerfile, `compose.yml`, konfigurasi Nginx, branding default (`docker/config/antareja.yaml`), init container `docker/init/03a-antareja.sh` (folder evidence di `/data/antareja`) |
| `antareja.sh` | Skrip operasional (install, start, stop, restart, update, backup, restore, health) |
| `docs/` | Dokumen analisis & arsitektur ANTAREJA |
| `.dockerignore`, `MODIFICATIONS.md`, `ANTAREJA.md` | Pendukung |

## Integrasi tanpa mengubah file inti (Fase 1–2)

| Mekanisme | Dipakai untuk |
|---|---|
| Tabel baru `ant_*` dengan foreign key ke `devices`, `ports`, `users`, `locations` | Site/area, detail inventaris, mapping, evidence, audit log |
| Event Eloquent `Device::created` | Device baru otomatis masuk antrean verifikasi |
| Plugin hook resmi `DeviceOverviewHook` (plugin `antareja`) | Panel inventaris di halaman device |
| Scheduler Laravel (`schedule:run` di container app) | `antareja:sync-inventory` & `antareja:ipam-sync` (15 menit), `antareja:quality-probe` (5 menit), `antareja:suggest-mappings` (per jam), `antareja:quality-maintenance` (harian) |
| Tabel `ipv4_addresses`, `ipv6_addresses`, `ipv4_mac`, `wireless_sensors`, `device_outages`, `vlans` (baca saja) | IPAM, Wireless, CCTV |
| `fping` bawaan image + `Eventlog::log()` | Quality Probe & pencatatan perubahan status kualitas |
| Tabel `device_relationships` (fitur dependensi bawaan) | Ditulis dari mapping terverifikasi agar alert anak ditekan |

## Prosedur sinkron upstream

Branch `upstream-sync` berisi **snapshot murni** kode LibreNMS (dimulai dari commit
"Import LibreNMS 26.10.2"); riwayatnya tidak terhubung langsung dengan repo LibreNMS.
Setiap versi baru diimpor sebagai satu commit snapshot, lalu digabung ke `main`:

```bash
TAG=26.11.0   # ganti dengan rilis bertag terbaru
git fetch --depth 1 upstream tag $TAG
git checkout upstream-sync
git rm -rq . && git checkout $TAG -- .          # ganti isi dengan kode upstream $TAG
git commit -m "Import LibreNMS $TAG"
git checkout main && git merge upstream-sync    # selesaikan konflik pada file di tabel di atas
npm ci && npm run build                         # bangun ulang html/build
# Ubah LIBRENMS_VERSION di docker/.env.example & docker/Dockerfile ke $TAG
./antareja.sh update
```

Gunakan hanya **rilis bertag** LibreNMS, bukan branch master.
