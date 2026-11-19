# ANTAREJA NMS — Daftar Perubahan terhadap Kode Inti LibreNMS

ANTAREJA NMS adalah karya turunan dari [LibreNMS](https://github.com/librenms/librenms)
(GPLv3). Dokumen ini memenuhi kewajiban GPLv3 untuk mencatat perubahan, dan menjadi
panduan saat menggabungkan update dari upstream.

- **Basis upstream:** LibreNMS `26.10.2` (commit `17387db`), branch `upstream-sync`
- **Prinsip:** semua fitur baru berada di `packages/antareja/`. File inti hanya disentuh
  bila tidak ada hook, dan setiap perubahan dicatat di bawah ini.

## Perubahan file inti (harus dicek ulang setiap sinkron upstream)

| File | Perubahan | Alasan |
|---|---|---|
| `composer.json` | Tambah autoload PSR-4 `"Antareja\\": "packages/antareja/src/"` | Memuat kode paket ANTAREJA |
| `bootstrap/providers.php` | Tambah `Antareja\AntarejaServiceProvider::class` | Mendaftarkan route, view, migration, perintah ANTAREJA |
| `resources/views/layouts/menu.blade.php` | Sisipkan `@includeIf('antareja::partials.navbar')` di awal navbar | Menu modul ANTAREJA di navigasi utama |
| `resources/css/app.css` | Tambah `@source` untuk `packages/antareja/resources/views` | Kelas Tailwind milik ANTAREJA ikut dikompilasi |
| `resources/definitions/permissions.yaml` | Tambah 12 grup permission `ant-*` | Permission ANTAREJA tampil di editor role |
| `html/build/*` | Dibangun ulang (`npm run build`) | Hasil kompilasi dari perubahan `app.css` |

## File baru (tidak bentrok dengan upstream)

| Path | Isi |
|---|---|
| `packages/antareja/` | Paket Laravel ANTAREJA (service provider, modul, view, migration, perintah `antareja:roles`) |
| `html/images/antareja/` | Logo & favicon ANTAREJA |
| `docker/` | Dockerfile, `compose.yml`, konfigurasi Nginx, branding default (`docker/config/antareja.yaml`) |
| `antareja.sh` | Skrip operasional (install, start, stop, restart, update, backup, restore, health) |
| `docs/` | Dokumen analisis & arsitektur ANTAREJA |
| `.dockerignore`, `MODIFICATIONS.md`, `ANTAREJA.md` | Pendukung |

## Prosedur sinkron upstream

```bash
git fetch upstream --tags
git checkout upstream-sync && git reset --hard <tag-rilis-baru>
git checkout main && git merge upstream-sync
# Selesaikan konflik pada file di tabel "Perubahan file inti" di atas
npm ci && npm run build            # bangun ulang html/build
# Ubah LIBRENMS_VERSION di docker/.env.example & docker/Dockerfile ke tag baru
./antareja.sh update
```

Gunakan hanya **rilis bertag** LibreNMS, bukan branch master.
