# Panduan Instalasi ANTAREJA NMS (VM Proxmox, Ubuntu Server LTS)

## 1. Siapkan VM di Proxmox

| Skala | vCPU | RAM | Disk (SSD) |
|---|---|---|---|
| ≤150 perangkat | 4 | 8 GB | 120 GB |
| 500 perangkat | 6–8 | 16 GB | 200–250 GB |

- OS: Ubuntu Server 24.04 LTS, IP statis di **VLAN manajemen** yang punya rute ke semua subnet perangkat.
- Firewall: dari pengguna ke VM hanya TCP 443 (dan 80 → redirect). Dari VM ke perangkat: ICMP, UDP 161 (SNMP),
  opsional UDP 162 masuk (SNMP trap).
- Aktifkan backup VM Proxmox (vzdump) mingguan.

## 2. Pasang Docker

```bash
sudo apt update && sudo apt install -y ca-certificates curl git openssl
```

```bash
curl -fsSL https://get.docker.com | sudo sh
```

```bash
sudo usermod -aG docker $USER && newgrp docker
```

Bila VM tidak punya akses internet, pasang Docker dari paket offline dan muat image dengan
`docker load` (minta bantuan untuk menyiapkan paket image offline).

## 3. Ambil kode & pasang

```bash
sudo mkdir -p /opt/antareja && sudo chown $USER /opt/antareja
```

```bash
git clone <URL-repo-antareja-nms-Anda> /opt/antareja && cd /opt/antareja
```

```bash
./antareja.sh install
```

Skrip akan: membuat `docker/.env` dengan password acak, membuat sertifikat TLS self-signed,
membangun image, menjalankan semua container, dan meminta username/password admin.

Buka `https://<IP-VM>/` lalu login. Browser akan memperingatkan sertifikat self-signed;
ganti `docker/certs/antareja.crt` dan `antareja.key` dengan sertifikat dari CA perusahaan bila ada,
lalu `./antareja.sh restart`.

## 4. Konfigurasi awal setelah login

1. **Settings → Global Settings → Poller → SNMP**: isi community v2c (read-only, jangan `public`)
   atau kredensial SNMPv3.
2. Tambah perangkat: menu **Devices → Add Device**, atau
   ```bash
   ./antareja.sh lnms device:add 10.10.1.1 --v2c -c <community>
   ```
3. Auto-discovery subnet (hanya setelah ada izin tertulis IT Security):
   ```bash
   ./antareja.sh lnms config:set nets.+ '10.10.0.0/16'
   ```
4. Buat user dan beri role `ict-network`, `ict-cctv-support`, `management`, atau `admin`
   (menu **ANTAREJA → User Management**).

## 5. Operasional

| Perintah | Fungsi |
|---|---|
| `./antareja.sh start` / `stop` / `restart` / `status` | Kontrol layanan |
| `./antareja.sh logs app` | Lihat log (app, dispatcher, db, nginx) |
| `./antareja.sh health` | Cek container, database, Redis, HTTPS, poller, disk |
| `./antareja.sh backup` | Backup DB + data + RRD ke `docker/backups/` (rotasi 14 hari) |
| `./antareja.sh restore <file>` | Pulihkan dari backup |
| `./antareja.sh update` | Backup → git pull → build → restart → health |

Jadwalkan backup harian dengan cron:

```bash
(crontab -l 2>/dev/null; echo "30 1 * * * cd /opt/antareja && ./antareja.sh backup >> /var/log/antareja-backup.log 2>&1") | crontab -
```

Salin isi `docker/backups/` ke NAS/share di luar VM secara berkala.

## 6. Catatan

- `docker/.env` berisi password database: jangan dibagikan atau di-commit.
- IPv6 nonaktif secara default (`LISTEN_IPV6=false`). Ubah di `docker/.env` bila diperlukan.
- Bila subnet `172.30.250.0/24` bentrok dengan jaringan tambang, ubah `DOCKER_SUBNET` di `docker/.env`
  sebelum instalasi.
- Saat membuat user, LibreNMS mencoba mengecek password ke `api.pwnedpasswords.com`. Di jaringan
  tanpa internet pengecekan ini gagal (tercatat di log) tetapi user tetap dibuat.
- Image pihak ketiga yang dipakai: `librenms/librenms:26.10.2`, `mariadb:11.4.13`, `redis:8.6.8-alpine`,
  `crazymax/rrdcached:1.9.0-r4`, `nginx:1.30.7-alpine`.
