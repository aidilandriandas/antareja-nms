<p align="center">
  <img src="html/images/antareja/antareja-mark-128.png" alt="Logo ANTAREJA NMS" width="96">
</p>

<h1 align="center">ANTAREJA NMS</h1>

<p align="center">
  <strong>Mining ICT Infrastructure Management &amp; Unified Network Monitoring Platform</strong><br>
  Satu aplikasi web untuk memantau dan mengelola jaringan, CCTV, dan infrastruktur ICT di area tambang.
</p>

<p align="center">
  Versi 0.9.0 · Berbasis LibreNMS 26.10.2 · Lisensi GPL-3.0 · Bahasa antarmuka: Indonesia
</p>

---

## Daftar isi

1. [Tentang ANTAREJA NMS](#1-tentang-antareja-nms)
2. [Fitur](#2-fitur)
3. [Tampilan](#3-tampilan)
4. [Arsitektur singkat](#4-arsitektur-singkat)
5. [Kebutuhan sistem](#5-kebutuhan-sistem)
6. [Instalasi di Proxmox (langkah demi langkah)](#6-instalasi-di-proxmox-langkah-demi-langkah)
7. [Konfigurasi awal setelah login](#7-konfigurasi-awal-setelah-login)
8. [Peran pengguna (role)](#8-peran-pengguna-role)
9. [Operasional sehari-hari](#9-operasional-sehari-hari)
10. [Fitur opsional: asisten AI, proxy, offline](#10-fitur-opsional-asisten-ai-proxy-offline)
11. [Tugas terjadwal otomatis](#11-tugas-terjadwal-otomatis)
12. [Keamanan](#12-keamanan)
13. [Pemecahan masalah](#13-pemecahan-masalah)
14. [Struktur repositori](#14-struktur-repositori)
15. [Dokumentasi lengkap](#15-dokumentasi-lengkap)
16. [Lisensi & atribusi](#16-lisensi--atribusi)

---

## 1. Tentang ANTAREJA NMS

ANTAREJA NMS menggabungkan kemampuan tiga alat monitoring populer dalam **satu aplikasi dan satu login**:

| Asal fitur | Yang dibawa ke ANTAREJA |
|---|---|
| **LibreNMS** (basis fork) | Auto-discovery, polling SNMP/ICMP, status perangkat & port, sensor, wireless, grafik, alert rule, transport notifikasi (Telegram, email, dll.) |
| **Zabbix** (dibuat ulang secara native) | Problem & korelasi event, trigger expression, acknowledge, eskalasi, maintenance, SLA & service tree |
| **SmokePing** (dibuat ulang secara native) | Quality Probe: latency, packet loss, jitter, grafik "smoke" |

Ditambah modul khusus kebutuhan operasional tambang: inventaris resmi per site/area, verifikasi perangkat hasil discovery,
device mapping terverifikasi, IPAM, CCTV & live view, incident & work order, change request, backup konfigurasi,
capacity planning, manajemen fiber optic, laporan bulanan, dashboard manajemen, **layar NOC** dengan peta topologi
dan trafik live, serta **backup & pemulihan NMS** dari web.

**Profil NOC (bawaan):** ANTAREJA dipasang sebagai NMS/NOC murni. Modul manajemen aset dan pekerjaan lapangan —
Inventaris aset, Discovery (verifikasi aset), Fiber Optic, Work Order, Change Request — **dimatikan** dan tidak tampil di
menu. Admin bisa menyalakannya kapan saja di *Administrasi → Modul Aktif* tanpa kehilangan data.

Tampilannya dibuat sendiri (bukan tampilan LibreNMS): menu samping berbahasa Indonesia yang dikelompokkan per pekerjaan,
halaman awal **Pusat Operasi**, tema terang/gelap, dan nyaman dipakai di HP.

**Prinsip desain**
- Semua fitur berjalan **di server Anda sendiri** (VM Proxmox) dan tetap berfungsi **tanpa internet**. Hanya asisten AI yang opsional butuh internet.
- ANTAREJA **tidak pernah mengubah konfigurasi perangkat jaringan**. Backup konfigurasi hanya memakai perintah baca; AI hanya menulis draf teks.
- Topologi tidak ditebak: sambungan antar perangkat berasal dari **mapping yang diverifikasi engineer** dan tetangga
  **LLDP/CDP** yang dilaporkan perangkat. Saran yang belum diverifikasi selalu ditandai (garis putus-putus).
- Setiap perubahan data penting tercatat di **audit log**.

---

## 2. Fitur

### Pusat kendali
| Modul | Isi |
|---|---|
| **Pusat Operasi** | Halaman awal setelah login: status keseluruhan dalam satu kalimat, ketersediaan, perangkat down, problem aktif per severity, status per site, pekerjaan tim, CCTV, kualitas link, kelengkapan data — diperbarui otomatis tiap 60 detik |
| **Layar NOC** | Tampilan layar penuh untuk TV/monitor ruang kontrol: peta topologi live (trafik mengalir, perangkat down berdenyut), ketersediaan, feed problem, perangkat down, status per site & grup — diperbarui tiap 15 detik tanpa reload |
| **Peta Topologi** | Siapa terhubung ke siapa (mapping terverifikasi + LLDP/CDP), **trafik live di tiap garis** (▼ ke bawah / ▲ ke atas, dibaca langsung dari perangkat tiap 5 detik), warna garis = pemakaian bandwidth port, filter site/grup, cari perangkat, simpan tata letak; **hubungkan perangkat manual** langsung di peta (klik 2 perangkat → pilih port & jenis sambungan) |

### Monitoring
| Modul | Isi |
|---|---|
| **Network Monitoring** | Status perangkat (SNMP v2c/v3, ICMP), uptime, sensor hardware, grafik — dari LibreNMS |
| **Interface Monitoring** | Status port, traffic, bandwidth, error, utilisasi |
| **Wireless Monitoring** | AP, controller, PTP/PTMP: client, RSSI, SNR, frekuensi, channel, rate, CCQ, jarak, pasangan link; nilai buruk disorot |
| **CCTV Infrastructure** | Kamera & NVR: status, IP, MAC, port switch, NVR perekam, lokasi, riwayat gangguan |
| **Live view CCTV** | Grid snapshot & video langsung dari NVR Hikvision di browser (tanpa iVMS); cek otomatis gambar gelap, polos, beku, buram, bergeser |
| **Network Quality** | Quality Probe (20 ping tiap 5 menit): latency, loss, jitter, grafik smoke 3 jam – 30 hari |

### Perangkat & inventaris
| Modul | Isi |
|---|---|
| **Tambah perangkat** | Form ANTAREJA: nama perangkat, grup, site/area, SNMP v2c/v3 atau ping saja; tambah banyak sekaligus (tempel dari Excel: `IP, Nama, Grup`) |
| **Uji koneksi** | Tes ping + SNMP dari server ANTAREJA (form tambah perangkat & halaman inventaris), lengkap dengan diagnosis penyebab; perangkat "ping saja" bisa dialihkan ke SNMP dengan satu klik |
| **Grup perangkat** | Grup otomatis per kategori (CCTV, Access Point, Switch, NVR, …) beserta status online/down; juga grup buatan sendiri |
| **Site & Area** | Office, workshop, plant, pit, ROM, jetty, mess, remote, beserta area di dalamnya |
| **Pengaturan perangkat** | Nama, grup, site/area, sambungan, uji koneksi & aktifkan SNMP — satu halaman ringkas per perangkat |
| **Device Inventory** *(opsional)* | Kategori, site/area, rak, serial, PIC, garansi, lifecycle, evidence foto/PDF, kelengkapan data, export CSV |
| **Device Discovery** *(opsional)* | Perangkat baru masuk antrean verifikasi: Pending → Verified / Incomplete / Rejected / Retired |
| **Device Mapping** | Relasi port ↔ perangkat yang diverifikasi; saran dari LLDP/CDP & tabel MAC; dipakai untuk korelasi induk/anak |
| **IP Address Management** | Subnet, VLAN, alokasi IP, IP kosong, peta alamat, deteksi konflik/duplikat, histori per IP, utilisasi |

### Gangguan & operasional
| Modul | Isi |
|---|---|
| **Event & Alert Center** | Problem dari semua sumber dalam satu daftar; gangguan yang sama digabung; perangkat anak ditekan bila induknya down |
| **Trigger** | Kondisi ambang ala Zabbix, mis. "rata-rata packet loss 15 menit > 5%", "utilisasi uplink > 80% (pulih ≤ 70%)" |
| **Incident Management** | Nomor INC, severity, PIC, status, root cause & corrective action wajib saat ditutup, work note, evidence, MTTA/MTTR |
| **Eskalasi & Notifikasi** | Incident otomatis & notifikasi bertahap lewat Telegram/email/dll. sampai di-acknowledge |
| **Maintenance** | Kalender preventive/corrective/change; alert perangkat ditekan selama jendela; bisa dikecualikan dari SLA |
| **Work Order** *(opsional)* | Pekerjaan lapangan dengan checklist, material, evidence, tenggat per prioritas, verifikasi supervisor |
| **Change Request** *(opsional)* | Rencana, dampak, rollback; disetujui orang lain; otomatis jadi jendela maintenance |
| **Backup Konfigurasi** | SSH (perintah baca saja) ke 10 profil vendor; versi & diff; deteksi perubahan tanpa change request |

### Analisis & laporan
| Modul | Isi |
|---|---|
| **SLA Management** | Service tree (seri / redundan / minimal N up), target SLA, availability bulanan, MTTR/MTBF |
| **Capacity Planning** | Utilisasi uplink (p95 harian), storage NVR/server, pool IP, perkiraan tanggal menyentuh ambang |
| **Fiber Optic** *(opsional)* | Kabel & core (warna TIA-598), ODF, joint & splice, uji OTDR, jalur end-to-end, lokasi gangguan dari jarak OTDR |
| **Deteksi Anomali** | Latency/loss/traffic tidak wajar & perangkat flapping, dibanding jam yang sama hari sebelumnya — offline, tanpa AI |
| **Asisten AI** (opsional) | Draf laporan serah terima shift & analisis incident — hanya teks, tanpa akses ke perangkat |
| **Dashboard Manajemen** | KPI availability, SLA, incident, MTTA/MTTR, tren 6 bulan, per site |
| **Reports** | Laporan bulanan 9 bagian per site: cetak/PDF, Excel, CSV |

### Administrasi
| Modul | Isi |
|---|---|
| **Setup Awal** | Admin pertama dibuat di halaman `/setup` yang dijaga kode setup dari server (bukan wizard LibreNMS) |
| **Backup & Pemulihan** | Backup NMS lengkap (database, kredensial, grup, site, inventaris, user, grafik) harian & manual, unduh dari browser, salinan otomatis ke NAS/server lain lewat SFTP, pulihkan dengan satu perintah |
| **Modul Aktif** | Nyalakan/matikan modul opsional; tombol *Profil NOC* dan *Nyalakan semua* |
| **User & Role** | Akun dan hak akses per modul (grup permission `ant-*`) |
| **Audit Log** | Jejak login & perubahan data (sebelum/sesudah) |

---

## 3. Tampilan

> Gambar memakai data uji.

Semua halaman memakai kerangka ANTAREJA: menu samping dikelompokkan per pekerjaan (Monitoring, Alert & Insiden, Perangkat & Jaringan, Operasional, Laporan, Administrasi), pencarian global (tekan `/`), penanda jumlah problem aktif, tema terang/gelap, dan tampilan HP. Halaman awal setelah login adalah **Pusat Operasi**; dashboard widget LibreNMS tetap ada di menu *Dashboard Widget*.

| Layar NOC (layar penuh untuk TV ruang kontrol) | Peta topologi |
|---|---|
| ![Layar NOC](docs/screenshots/layar-noc.png) | ![Peta topologi](docs/screenshots/peta-topologi.png) |
| **Trafik live di setiap sambungan** | **Hubungkan perangkat langsung dari peta** |
| ![Trafik live](docs/screenshots/trafik-live.png) | ![Hubungkan perangkat](docs/screenshots/hubungkan-perangkat.png) |
| **Grup perangkat** | **Tambah perangkat (satu / banyak sekaligus)** |
| ![Grup perangkat](docs/screenshots/grup-perangkat.png) | ![Tambah perangkat](docs/screenshots/tambah-perangkat.png) |
| **Pusat Operasi (halaman awal)** | **Pusat Operasi — tema gelap** |
| ![Pusat Operasi](docs/screenshots/pusat-operasi.png) | ![Pusat Operasi gelap](docs/screenshots/pusat-operasi-gelap.png) |
| **Uji koneksi (ping + SNMP)** | **Backup & pemulihan NMS** |
| ![Uji koneksi](docs/screenshots/uji-koneksi.png) | ![Backup](docs/screenshots/backup.png) |
| **Daftar perangkat dalam kerangka ANTAREJA** | **Halaman login** |
| ![Perangkat](docs/screenshots/perangkat.png) | ![Login](docs/screenshots/login.png) |
| **Dashboard manajemen** | **Incident** |
| ![Dashboard manajemen](docs/screenshots/dashboard-manajemen.png) | ![Incident](docs/screenshots/incident.png) |
| **SLA layanan** | **Capacity planning** |
| ![SLA](docs/screenshots/sla.png) | ![Capacity](docs/screenshots/capacity.png) |
| **Jalur fiber & lokasi gangguan OTDR** | **Backup konfigurasi & diff** |
| ![Jalur fiber](docs/screenshots/fiber-jalur.png) | ![Backup konfigurasi](docs/screenshots/backup-konfigurasi.png) |
| **Live view CCTV** | **Cek kesehatan gambar CCTV** |
| ![Live view CCTV](docs/screenshots/cctv-live.png) | ![Kesehatan gambar CCTV](docs/screenshots/cctv-kesehatan.png) |

---

## 4. Arsitektur singkat

ANTAREJA berjalan sebagai beberapa container Docker di **satu VM**:

```
 Pengguna (browser) ──HTTPS 443──▶ nginx ──▶ app (ANTAREJA + LibreNMS, PHP/Laravel)
                                              │        ├─ scheduler (problem, SLA, kapasitas, anomali, backup konfigurasi, grup, backup NMS)
                                              │        └─ Quality Probe (fping)
                                              ├─▶ db (MariaDB)        ├─▶ redis (cache & sesi)
                                              ├─▶ rrdcached (grafik)  └─▶ dispatcher (poller SNMP/ICMP)
                                              └─▶ go2rtc (gateway video CCTV, internal) ──RTSP 554──▶ NVR Hikvision
                                                         │
                         Perangkat jaringan ◀── SNMP UDP 161 / ICMP / SSH 22 (opsional, backup konfigurasi)
                         NAS / server backup ◀── SFTP 22 (opsional, salinan backup NMS)
```

- Kode ANTAREJA berada di `packages/antareja/` sebagai paket Laravel; perubahan pada kode LibreNMS dibuat seminimal mungkin
  dan dicatat di [`MODIFICATIONS.md`](MODIFICATIONS.md).
- Data tersimpan di volume Docker (database, RRD, evidence, backup NMS) dan ikut di-backup oleh menu *Backup & Pemulihan*
  maupun `./antareja.sh backup`.

---

## 5. Kebutuhan sistem

| Skala | vCPU | RAM | Disk (SSD) |
|---|---|---|---|
| sampai ±150 perangkat | 4 | 8 GB | 120 GB |
| ±500 perangkat | 6–8 | 16 GB | 200–250 GB |

- **OS:** Ubuntu Server 24.04 LTS (VM di Proxmox VE, atau server Linux lain yang bisa menjalankan Docker).
- **Jaringan:** IP statis di **VLAN manajemen** yang punya rute ke semua subnet perangkat.
- **Firewall:**
  - Pengguna → VM: TCP 443 (dan 80, otomatis dialihkan ke 443).
  - VM → perangkat: ICMP dan UDP 161 (SNMP). TCP 22 bila memakai backup konfigurasi. TCP 554 (RTSP) ke NVR untuk live view CCTV. Opsional: UDP 162 masuk (SNMP trap).
  - VM → NAS/server backup: TCP 22 (SFTP) bila salinan backup NMS diaktifkan.
  - Di perangkat, **izinkan IP VM ANTAREJA** pada daftar akses SNMP (mis. `addresses=` di community MikroTik) dan jangan blok ICMP darinya.
- **Internet:** dibutuhkan **saat instalasi/update** (mengunduh image Docker dan paket). Setelah berjalan, internet tidak wajib.

---

## 6. Instalasi di Proxmox (langkah demi langkah)

### 6.1 Buat VM

1. Unduh ISO **Ubuntu Server 24.04 LTS**, lalu di Proxmox: storage `local` → **ISO Images** → **Upload**.
2. Klik **Create VM**:

   | Tab | Isian |
   |---|---|
   | General | Name: `antareja` |
   | OS | ISO Ubuntu Server 24.04 |
   | System | Qemu Agent: ✓ |
   | Disks | Bus VirtIO SCSI, 120 GB (atau 200 GB untuk ±500 perangkat), storage SSD, Discard ✓ |
   | CPU | 4 core (6–8 untuk ±500 perangkat), Type: `host` |
   | Memory | 8192 MB (16384 untuk ±500 perangkat) |
   | Network | Bridge ke VLAN manajemen (isi *VLAN Tag* bila memakai VLAN), Model VirtIO |

3. **Start** VM, buka **Console**, lalu instal Ubuntu:
   - Atur **IP statis** pada VLAN manajemen.
   - Centang **Install OpenSSH server**.
   - Jangan memilih snap Docker di layar *Featured snaps* (Docker dipasang di langkah berikut).
4. Setelah selesai, masuk lewat SSH dari laptop:

```bash
ssh <user>@<IP-VM>
```

### 6.2 Pasang Docker

```bash
sudo apt update && sudo apt install -y ca-certificates curl git openssl qemu-guest-agent
```

```bash
curl -fsSL https://get.docker.com | sudo sh
```

```bash
sudo usermod -aG docker $USER && newgrp docker
```

### 6.3 Ambil kode ANTAREJA

```bash
sudo mkdir -p /opt/antareja && sudo chown $USER /opt/antareja
```

```bash
git clone https://github.com/aidilandriandas/antareja-nms.git /opt/antareja && cd /opt/antareja
```

> Semua perintah di README ini dijalankan dari folder `/opt/antareja`. Bila kode di-clone ke folder lain
> (mis. `/opt/antareja/antareja-nms`), masuk ke folder itu dan sesuaikan path pada contoh.

> **Repo private?** Saat diminta login, isi username GitHub dan tempelkan **Personal Access Token** sebagai password
> (GitHub → Settings → Developer settings → Personal access tokens, cukup izin baca repo).

### 6.4 Jalankan instalasi

```bash
ANTAREJA_SERVER_NAME=antareja.local ./antareja.sh install
```

Ganti `antareja.local` dengan nama DNS yang akan dipakai pengguna (atau biarkan bila akses lewat IP). Skrip akan:

1. membuat `docker/.env` dengan password database acak,
2. membuat sertifikat HTTPS self-signed (sudah mencakup IP VM),
3. membangun image dan menjalankan semua container (pertama kali ±10–20 menit),
4. menampilkan **alamat Setup Awal (`https://<IP-VM>/setup`) dan kode setup**.

> Install ulang di VM yang sama? Jangan hapus `docker/.env` lama — password database tersimpan di sana. Bila skrip berhenti
> dengan pesan "Volume database ... dari instalasi sebelumnya masih ada", salin `docker/.env` lama ke folder ini, atau
> pulihkan dari backup (bagian 9).

### 6.5 Buka aplikasi

Buka `https://<IP-VM>/setup`. Browser akan memperingatkan sertifikat self-signed — klik **Advanced → Proceed**. Isi kode setup, nama, username, dan password admin pertama, lalu klik **Buat admin & masuk**. Kode setup bisa dilihat lagi kapan saja dengan `./antareja.sh setup-token` (hanya berlaku sampai admin pertama dibuat).

Selalu buka ANTAREJA lewat `https://` — semua tautan dan form dibuat dengan `https://` sehingga login tidak memicu peringatan "informasi tidak aman".
Cek kesehatan sistem:

```bash
./antareja.sh health
```

Hasil yang diharapkan diakhiri tulisan **SEHAT**.

> Punya sertifikat dari CA perusahaan? Ganti `docker/certs/antareja.crt` dan `docker/certs/antareja.key`, lalu `./antareja.sh restart`.

---

## 7. Konfigurasi awal setelah login

Urutan yang disarankan (semua lewat menu di sidebar):

1. **Community SNMP bersama:** *Administrasi → Pengaturan Sistem → Poller → SNMP*. Isi community v2c read-only (jangan
   `public`) atau kredensial SNMPv3. Di setiap perangkat, aktifkan SNMP dan izinkan IP VM ANTAREJA.
2. **Site & Area:** *Perangkat & Jaringan → Site & Area* — buat site (Kantor, Pit A, Workshop, Mess, …) dan area di dalamnya.
3. **Tambah perangkat:** *Perangkat & Jaringan → Tambah Perangkat*.
   - Isi **Nama perangkat**, IP, **Grup** (CCTV, Access Point, Switch, Router, Radio PTP, …), site/area, dan cara pemantauan
     (SNMP v2c/v3, atau *Ping saja* untuk kamera tanpa SNMP).
   - Klik **Uji koneksi** dulu: ping & SNMP dites dari server, lengkap dengan saran bila gagal.
   - Banyak perangkat sekaligus: tab **Banyak sekaligus**, tempel dari Excel dengan format `IP, Nama, Grup` (maks. 50 baris).
   - Perangkat yang sudah terdaftar tapi masih tampil sebagai IP: buka perangkatnya → **Pengaturan & uji koneksi** → isi *Nama perangkat*.
4. **Grup perangkat:** *Perangkat & Jaringan → Grup Perangkat* → **Sinkronkan grup** sekali. Setelah itu grup (CCTV berisi
   semua kamera, Access Point berisi semua AP, dst.) diperbarui otomatis tiap 10 menit.
5. **Sambungan antar perangkat:** aktifkan **LLDP/CDP** di switch & router (MikroTik: *IP → Neighbors → Discovery Settings*).
   Sambungan yang tidak terdeteksi dibuat manual di *Peta Topologi* → **Hubungkan perangkat** → klik dua perangkat → pilih
   port. Pilih port agar trafik tampil di garis dan alert perangkat di bawahnya diredam saat induknya down.
   Atur posisi perangkat lalu **Simpan tata letak** — tata letak ini juga dipakai Layar NOC.
6. **Modul opsional:** butuh inventaris aset, work order, atau fiber optic? Nyalakan di *Administrasi → Modul Aktif*.
7. **Kualitas link:** *Monitoring → Kualitas Jaringan* — tambahkan target link penting (PTP, uplink antar site, gateway internet).
8. **Notifikasi:** buat transport di *Fitur Lainnya → Transport Alert* (mis. Telegram), lalu kebijakan di
   *Alert & Insiden → Eskalasi & Notifikasi*.
9. **SLA:** *Laporan → SLA* — buat layanan seperti "Jaringan Pit A" dan pilih perangkatnya.
10. **Live view CCTV:** di NVR Hikvision buat user dengan hak *Live View* saja, lalu di *CCTV → Sumber stream* simpan
    kredensialnya dan tambahkan kamera (pilih NVR + nomor channel). Tekan *Cek sekarang*, lalu *Jadikan acuan* saat gambar normal.
11. **Backup NMS:** *Administrasi → Backup & Pemulihan* — atur jam backup dan aktifkan **salinan SFTP** ke NAS/server lain.
12. **Layar NOC:** buka *Layar NOC* di PC/TV ruang kontrol, tekan `F` untuk layar penuh. Login dengan centang
    *Ingat saya* agar tampilan tidak keluar sendiri.
13. **User:** buat akun tim dan beri role yang sesuai (lihat bagian berikut).

---

## 8. Peran pengguna (role)

| Role | Kode | Cakupan utama |
|---|---|---|
| **ICT Network** | `ict-network` | Monitoring, inventaris, IPAM, mapping, problem & incident, trigger, SLA, work order, change, backup konfigurasi, kapasitas, fiber, asisten AI |
| **ICT CCTV / Support** | `ict-cctv-support` | CCTV, inventaris, incident & work order, maintenance, fiber, change (mengajukan), asisten AI |
| **ICT Admin** | `admin` | Semua akses, termasuk pengaturan sistem & user |
| **Management** | `management` | Dashboard manajemen, laporan & export, SLA, problem/incident (lihat saja), menyetujui change request |

Hak akses bisa disesuaikan per permission di *Administrasi → User & Role* (grup `ant-*`). Menu yang tidak boleh dibuka
tidak ditampilkan di sidebar. *Backup & Pemulihan* hanya untuk admin, karena file backup berisi seluruh kredensial.

---

## 9. Operasional sehari-hari

| Perintah | Fungsi |
|---|---|
| `./antareja.sh start` / `stop` / `restart` / `status` | Kontrol layanan |
| `./antareja.sh logs app` | Lihat log (`app`, `dispatcher`, `db`, `nginx`) |
| `./antareja.sh health` | Cek container, database, Redis, HTTPS, poller, disk |
| `./antareja.sh backup` | Backup database + data + RRD ke `docker/backups/` (rotasi 14 hari) |
| `./antareja.sh backups` | Daftar backup (dari web & dari perintah `backup`) |
| `./antareja.sh restore <file>` | Pulihkan dari backup (path file, atau nama backup web) |
| `./antareja.sh update` | Backup → git pull → build → restart → cek kesehatan |
| `./antareja.sh admin` | Buat user admin baru |
| `./antareja.sh setup-token` | Tampilkan alamat & kode Setup Awal (sebelum admin pertama dibuat) |
| `./antareja.sh lnms <perintah>` | Jalankan perintah LibreNMS/ANTAREJA (mis. `device:add`, `antareja:problems`) |

**Backup NMS (menu Administrasi → Backup & Pemulihan, khusus admin):**

- Backup otomatis setiap hari (jam bisa diatur, bawaan 01:30) + tombol **Backup sekarang**. Isi: seluruh database
  (perangkat, kredensial SNMP/SSH, grup, site, inventaris, alert, user), file `.env` berisi APP_KEY, data ANTAREJA,
  dan — opsional — grafik historis (RRD).
- **Salinan otomatis ke server lain lewat SFTP** (NAS Synology/QNAP, server Linux, Windows OpenSSH) — supaya backup
  selamat walau VM rusak. Tombol *Uji koneksi* memeriksa login & hak tulis; host key server dicatat dan diperiksa.
- File bisa **diunduh** dari browser. Rotasi otomatis di VM maupun di server tujuan.

**Memulihkan di VM baru** (VM lama rusak): pasang ANTAREJA (`./antareja.sh install`), salin file backup ke VM
(mis. WinSCP), lalu:

```bash
./antareja.sh restore /opt/antareja/antareja-YYYYmmdd-HHMMSS.tar.gz
```

Di VM yang sama cukup sebut namanya (lihat daftarnya dengan `./antareja.sh backups`):

```bash
./antareja.sh restore antareja-YYYYmmdd-HHMMSS.tar.gz
```

Semua perangkat, grup, site, user, dan grafik kembali seperti saat backup — tidak perlu menambah perangkat ulang.
Aktifkan juga backup VM mingguan di Proxmox (*Datacenter → Backup → Add*) sebagai lapisan kedua.

**Update ke versi terbaru:**

```bash
cd /opt/antareja && git pull && ./antareja.sh update
```

`update` otomatis membuat backup dulu, membangun ulang image, menjalankan migrasi database, lalu memeriksa kesehatan.
Setelah update, tekan **Ctrl+F5** di browser agar tampilan terbaru termuat.

**Cek koneksi ke perangkat dari terminal** (sama dengan tombol *Uji koneksi*):

```bash
cd /opt/antareja/docker && docker compose exec app ping -c 4 <IP-perangkat>
```

```bash
cd /opt/antareja/docker && docker compose exec app snmpget -v2c -c <community> <IP-perangkat> 1.3.6.1.2.1.1.5.0
```

---

## 10. Fitur opsional: asisten AI, proxy, offline

### Asisten AI (Claude API)
Membuat draf laporan serah terima shift dan analisis incident. Butuh internet dari VM ke `api.anthropic.com` dan
menimbulkan biaya API per draf. Tanpa kunci, fitur ini nonaktif dan semua fitur lain tetap berjalan.

1. Buat API key di console Anthropic.
2. Isi di `docker/.env`:
   ```
   ANTAREJA_AI_KEY=<api key>
   ANTAREJA_AI_MODEL=claude-opus-5-5
   ```
3. Terapkan:
   ```bash
   ./antareja.sh restart
   ```

Yang **tidak pernah** dikirim ke AI: isi konfigurasi perangkat, kredensial, email/telepon. Alamat IP & MAC disamarkan.
Tombol **Lihat data** menampilkan persis teks yang akan dikirim.

### Build lewat proxy kantor
Isi di `docker/.env` sebelum `install`/`update`:
```
BUILD_HTTPS_PROXY=http://proxy.kantor:3128
DOCKER_BUILD_NETWORK=default
```
Bila proxy memeriksa TLS, simpan sertifikat CA proxy di `docker/build-ca.crt`.

### VM tanpa internet
Bangun image di mesin yang punya internet, lalu pindahkan:
```bash
docker save antareja-nms:0.9.0 | gzip > antareja-nms-0.9.0.tar.gz
```
```bash
gunzip -c antareja-nms-0.9.0.tar.gz | docker load
```
Tag image mengikuti `ANTAREJA_TAG` di `docker/.env`. Image pihak ketiga (`mariadb`, `redis`, `crazymax/rrdcached`, `nginx`,
`librenms/librenms`, `alexxit/go2rtc`) dipindahkan dengan cara yang sama.

---

## 11. Tugas terjadwal otomatis

Berjalan sendiri di dalam container `app` — tidak perlu cron tambahan. Trafik live di peta & Layar NOC tidak dijadwalkan:
dibaca hanya selama halaman itu terbuka (SNMP GET tiap 5 detik, maks. 40 port).

| Tugas | Jadwal |
|---|---|
| Problem engine, trigger, maintenance, eskalasi | tiap menit |
| Quality Probe | tiap 5 menit |
| Sinkron inventaris & IPAM | tiap 15 menit |
| Deteksi anomali | tiap 15 menit |
| Saran mapping (LLDP/CDP) | tiap jam |
| Backup konfigurasi (perangkat yang sudah waktunya) | tiap jam |
| Cek kesehatan gambar CCTV | tiap 15 menit |
| Sinkron grup perangkat (per kategori) | tiap 10 menit |
| Baca ulang tabel ARP router/L3 + cek konflik IP | tiap 30 menit |
| Backup NMS (+ salinan SFTP bila aktif) | harian, bawaan 01:30 (diatur di menu Backup & Pemulihan) |
| Capacity planning | tiap 6 jam |
| Rollup kualitas harian | 00:20 |
| Pembekuan SLA bulan lalu | 00:35 |

---

## 12. Keamanan

- Hanya Nginx yang terbuka (443/80); database, Redis, dan rrdcached berada di jaringan Docker internal.
- Password database dibuat acak saat instalasi; `docker/.env` tidak pernah di-commit.
- Kredensial SSH backup konfigurasi dan isi konfigurasi disimpan **terenkripsi**; password tidak pernah ditampilkan kembali
  dan disamarkan di audit log. Host key SSH diverifikasi.
- Backup konfigurasi hanya menjalankan perintah **baca** dari daftar tetap per vendor. Gunakan akun read-only di perangkat.
- Change request tidak bisa disetujui oleh pengajunya sendiri; work order tidak bisa diverifikasi oleh pelaksananya.
- Kunci API AI hanya disimpan di server dan tidak pernah dikirim ke browser.
- Admin pertama hanya bisa dibuat dengan **kode setup** yang dibaca dari server; halaman `/setup` tertutup setelahnya.
- File backup NMS berisi seluruh database & APP_KEY: hanya admin yang bisa membuat/mengunduhnya, file di VM ber-permission 600,
  password SFTP disimpan terenkripsi, dan host key server SFTP diperiksa di setiap salinan.
- Uji koneksi dan trafik live hanya membaca (ping, SNMP GET). Community/password tidak pernah dikirim ke browser.
- Gunakan SNMPv3 (authPriv) bila perangkat mendukung; untuk v2c gunakan community read-only, bukan `public`.
- Mintalah persetujuan tertulis atasan/IT Security sebelum memindai (discovery) jaringan perusahaan.

---

## 13. Pemecahan masalah

| Gejala | Penyebab & solusi |
|---|---|
| `./antareja.sh install` gagal saat build, "Could not authenticate against github.com" / timeout | VM tidak bisa mengunduh paket. Periksa internet/proxy (bagian 10) |
| Halaman tidak bisa dibuka | `./antareja.sh status` dan `./antareja.sh logs nginx`; pastikan firewall membuka TCP 443 |
| Peringatan Chrome "The information you're about to submit is not secure" | Buka lewat `https://`, lalu `git pull && ./antareja.sh update` (versi lama membuat form ber-`http://`) |
| Container `app` tidak sehat, log: "Failed to connect to database on db" | Password di `docker/.env` tidak cocok dengan volume database lama (install ulang / clone kedua). Salin `docker/.env` lama, atau mulai dari nol dengan `cd docker && docker compose down -v` (**semua data hilang**) |
| Perangkat selalu down | Buka perangkat → **Pengaturan & uji koneksi**. Bila ping gagal: cek rute dan firewall ICMP dari IP VM |
| SNMP sudah diaktifkan di perangkat tapi tidak terbaca | Buka perangkat → **Uji koneksi** → lihat saran. Tersering: community salah (huruf besar/kecil), IP VM belum diizinkan di community, UDP 161 diblok. Bila perangkat terdaftar "ping saja", klik **Aktifkan SNMP** setelah uji berhasil |
| Perangkat tampil sebagai IP saja | Buka perangkat → **Pengaturan & uji koneksi** → *Nama perangkat*, atau ⚙ perangkat → *Device* → *Display Name* |
| Menu Inventaris / Work Order / Fiber / Change Request tidak ada | Dimatikan oleh profil NOC. Nyalakan di *Administrasi → Modul Aktif* |
| Peta topologi tanpa garis | Aktifkan LLDP/CDP di switch/router, atau buat sambungan dengan **Hubungkan perangkat** di peta |
| Garis topologi abu-abu / trafik tidak muncul | Sambungan belum punya port: hapus lalu hubungkan ulang dengan memilih port. Perangkat "ping saja" tidak punya data port — ukur dari port switch/router lawannya |
| Banyak "Konflik IP" padahal di router IP-nya tidak bentrok | Versi lama menghitung MAC berbeda dari tabel ARP router yang berbeda (cache ARP lama / DHCP) sebagai konflik. Update ke versi terbaru: konflik palsu selesai otomatis pada sinkron berikutnya. Di *IPAM → Konflik IP* klik **Perbarui data ARP & cek ulang** untuk mempercepat |
| Layar NOC menampilkan "Sesi berakhir" | Login ulang dengan centang *Ingat saya* |
| Backup gagal "Ruang disk tersisa kurang dari 512 MB" | Kurangi jumlah backup yang disimpan, hapus backup lama, atau perbesar disk VM |
| Uji SFTP gagal "Host key server tujuan berubah" | Server tujuan memang diganti: centang *lupakan host key* di pengaturan backup, simpan, uji lagi |
| Grafik kosong | Perangkat baru butuh 2× polling (±10 menit) sebelum traffic port muncul |
| Setelah login kembali lagi ke halaman login | Akses selalu lewat `https://` dengan alamat yang sama (IP atau nama), lalu hapus cookie browser untuk alamat itu. Bila `DOCKER_SUBNET` diubah, jalankan `./antareja.sh restart` |
| Subnet `172.30.250.0/24` bentrok dengan jaringan tambang | Ubah `DOCKER_SUBNET` di `docker/.env` **sebelum** instalasi |
| Backup konfigurasi gagal "Host key berubah" | Perangkat diganti/di-reset: buka halaman konfigurasi perangkat → centang *Reset host key* |
| Live view CCTV "Tidak ada gambar" | Buka detail stream → *Cek sekarang*: pesan menyebut penyebab. Periksa user/password NVR (hak Live View), nomor channel, TCP 554 dari VM ke NVR, dan `./antareja.sh logs go2rtc` |
| Video live tidak tampil tetapi snapshot ada | Kamera memakai H.265: player otomatis beralih ke MJPEG (lebih berat). Untuk hemat, set sub-stream NVR ke H.264 |
| Asisten AI nonaktif | Isi `ANTAREJA_AI_KEY` di `docker/.env` lalu `./antareja.sh restart`; VM harus bisa ke `api.anthropic.com` |

Kirim keluaran `./antareja.sh health` dan `./antareja.sh logs app` saat meminta bantuan.

---

## 14. Struktur repositori

```
packages/antareja/      Semua fitur ANTAREJA (paket Laravel)
  ├─ src/               Model, service, controller, perintah artisan
  ├─ resources/views/   Halaman (Blade)
  ├─ resources/css/     Design system ANTAREJA
  ├─ database/          Migrasi tabel ant_*
  └─ routes/web.php     Route /antareja/...
docker/                 Dockerfile, compose.yml, Nginx, konfigurasi branding
antareja.sh             Skrip operasional (install, backup, update, …)
docs/                   Panduan instalasi, dokumen per fase, arsitektur, screenshot
html/images/antareja/   Logo & favicon
(lainnya)               Kode LibreNMS (fork; perubahan dicatat di MODIFICATIONS.md)
```

---

## 15. Dokumentasi lengkap

| Dokumen | Isi |
|---|---|
| [`docs/INSTALL.md`](docs/INSTALL.md) | Panduan instalasi & operasional |
| [`docs/FASE-1-Inventaris.md`](docs/FASE-1-Inventaris.md) | Site, inventaris, discovery, mapping, audit, kelengkapan data |
| [`docs/FASE-2-IPAM-Kualitas.md`](docs/FASE-2-IPAM-Kualitas.md) | IPAM, Network Quality, Wireless, CCTV |
| [`docs/FASE-3-Problem-Incident.md`](docs/FASE-3-Problem-Incident.md) | Event & Alert Center, incident, eskalasi, maintenance |
| [`docs/FASE-4-SLA-Laporan.md`](docs/FASE-4-SLA-Laporan.md) | SLA, trigger, dashboard manajemen, laporan |
| [`docs/FASE-5-Lanjutan.md`](docs/FASE-5-Lanjutan.md) | Work order, change, backup konfigurasi, capacity, fiber, anomali, AI, live view CCTV |
| [`docs/ANTAREJA-NMS-Arsitektur-v0.2-Fork.md`](docs/ANTAREJA-NMS-Arsitektur-v0.2-Fork.md) | Arsitektur & keputusan desain |
| [`MODIFICATIONS.md`](MODIFICATIONS.md) | Daftar perubahan terhadap kode LibreNMS & prosedur sinkron upstream |
| [`ANTAREJA.md`](ANTAREJA.md) | Ringkasan proyek |

---

## 16. Lisensi & atribusi

ANTAREJA NMS adalah **fork dari [LibreNMS](https://www.librenms.org)** dan didistribusikan di bawah
**GNU General Public License v3.0 atau lebih baru** — lihat [`LICENSE.txt`](LICENSE.txt).

- Hak cipta kode LibreNMS tetap milik para kontributor LibreNMS. README asli LibreNMS, termasuk ketentuan lisensi dan
  pengecualian GPL-nya, disimpan utuh di [`README-LibreNMS.md`](README-LibreNMS.md).
- Fitur yang terinspirasi Zabbix dan SmokePing **dibuat ulang** (konsep & perilaku), bukan menyalin kode keduanya.
  "LibreNMS", "Zabbix", dan "SmokePing" adalah nama/merek milik pemiliknya masing-masing dan tidak dipakai sebagai merek ANTAREJA.
- Font IBM Plex dan Barlow memakai lisensi SIL Open Font License.
- Asisten AI memakai SDK resmi `anthropic-ai/sdk` (Apache-2.0).
- Gateway video memakai [go2rtc](https://github.com/AlexxIT/go2rtc) (MIT) sebagai container terpisah; pemutar video `video-rtc.js` milik go2rtc.
