# ANTAREJA NMS — Fase 5: Work Order, Change, Konfigurasi, Capacity, Fiber, Anomali & AI

Versi 0.6.0. Fitur, aturan, keamanan, dan batasan.

## 1. Ringkasan

| Menu | URL | Isi |
|---|---|---|
| Work Order | `/antareja/workorders` | Pekerjaan lapangan: checklist per jenis, material, evidence, tenggat dari prioritas, verifikasi supervisor |
| Change Request | `/antareja/changes` | RFC: rencana, dampak, rollback, uji → disetujui orang lain → otomatis jadi jendela maintenance |
| Backup Konfigurasi | `/antareja/config` | Ambil konfigurasi via SSH (perintah baca saja), riwayat versi & diff, deteksi perubahan tanpa change request |
| Capacity Planning | `/antareja/capacity` | Uplink, storage NVR/server, pool IP: puncak harian, tren, perkiraan tanggal menyentuh ambang |
| Fiber Optic | `/antareja/fiber` | Kabel & core (TIA-598), ODF, joint & splice, uji OTDR, jalur end-to-end, lokasi gangguan, loss budget |
| Asisten & Anomali | `/antareja/assistant`, `/antareja/anomalies` | Deteksi anomali statistik (offline) + draf serah terima shift & analisis incident oleh AI (opsional) |

## 2. Work Order & Change Request

**Work order** — jenis: perbaikan, preventive, instalasi, relokasi, inspeksi, kabel/fiber. Tenggat otomatis:
urgent 4 jam, high 24 jam, normal 3 hari, low 7 hari. Checklist standar dibuat sesuai jenis dan bisa diubah.
Status: Open → Ditugaskan → Dikerjakan ⇄ Ditahan → Selesai → Terverifikasi.
- "Selesai" hanya bila checklist lengkap dan hasil pekerjaan diisi.
- "Terverifikasi" oleh pemegang `ant-workorder.verify` yang **bukan** pelaksana.
- Dibuat dari incident, maintenance, change request, atau halaman perangkat.

**Change request** — Draft → Diajukan → Disetujui / Ditolak → Dilaksanakan → Selesai / Gagal.
- Pengajuan butuh jadwal, perangkat, rencana implementasi, dan rollback (kecuali jenis *standard*).
- **Pengaju tidak bisa menyetujui sendiri** (dicek di server).
- Persetujuan membuat jendela maintenance jenis *change* untuk perangkat terkait (alert ditekan, opsi kecualikan dari SLA).
- ANTAREJA tidak pernah menerapkan perubahan ke perangkat; pelaksanaan tetap oleh engineer.

## 3. Backup konfigurasi

- Profil vendor tetap: Cisco IOS/IOS-XE, NX-OS, Ruijie, HPE Aruba/ProCurve, Huawei VRP, H3C/Comware, MikroTik, Juniper,
  FortiOS, EdgeOS/VyOS. Setiap profil hanya berisi **perintah baca** (`show running-config`, `/export`,
  `display current-configuration`, …) dan tidak bisa diisi bebas dari UI.
- Gunakan akun **read-only** di perangkat. Kredensial disimpan terenkripsi (APP_KEY), tidak pernah ditampilkan lagi,
  dan disamarkan di audit log.
- **Host key SSH** disimpan saat pengambilan pertama; bila berubah, pengambilan ditolak sampai di-reset manual.
- Versi baru hanya disimpan bila isi berubah. Baris yang selalu berubah (timestamp, "Last configuration change") diabaikan.
  Isi konfigurasi disimpan terkompresi & terenkripsi.
- Tampilan & unduhan menyamarkan password/community/key; "Tampilkan rahasia" hanya untuk `ant-config.update` dan dicatat di audit log.
- **Perubahan tanpa change request**: bila konfigurasi berubah dan tidak ada change request (disetujui/dilaksanakan/selesai) atau
  maintenance jenis *change* untuk perangkat itu yang jendelanya beririsan (±30 menit sebelum, +6 jam sesudah),
  versi ditandai dan menjadi problem sampai diverifikasi dengan keterangan.
- Jadwal: tiap jam memeriksa perangkat yang sudah waktunya (interval bawaan 24 jam per perangkat).

## 4. Capacity planning

- Item otomatis: port uplink dari Device Mapping terverifikasi, storage ≥ 1 GiB (mount sistem diabaikan), pool IP (subnet IPv4).
  Port lain bisa ditambahkan manual.
- Data dari **histori RRD poller** (dibaca lewat rrdcached, tanpa menulis):
  6 hari terakhir dari data 5 menit → rata-rata & **persentil-95** harian; lebih lama dari RRA harian → rata-rata & maksimum.
  Item baru langsung punya histori hingga 90 hari bila RRD-nya ada.
- Prakiraan: regresi linear puncak harian 60 hari (minimal 7 hari data); ditampilkan bila tercapai ≤ 365 hari.
- Ambang bawaan: link 70/90%, storage 80/90%. Dihitung tiap 6 jam (`antareja:capacity`).

## 5. Fiber optic

- **Kabel** antara dua titik (ODF atau joint) dengan core 1..N; warna TIA-598 per tube
  (Biru, Oranye, Hijau, Cokelat, Abu, Putih, Merah, Hitam, Kuning, Ungu, Merah muda, Aqua).
- **ODF**: port belakang dipasang ke core kabel; port depan di-patch ke port perangkat.
- **Joint / ODP**: splice core ↔ core antar-kabel dengan loss terukur.
- **Jalur end-to-end**: dari port perangkat → ODF → core → splice … → ODF → port perangkat di ujung lain, beserta panjang
  kumulatif. Jalur hanya disusun dari data yang dicatat; bagian yang belum tercatat ditandai.
- **Lokasi gangguan**: masukkan jarak event OTDR (dan faktor slack serat 1–3%) → segmen kabel dan jarak dari titik terdekat.
- **Loss budget**: estimasi (SM 0,35/0,25 dB/km di 1310/1550 nm; MM 3,0/1,0 dB/km; splice terukur atau 0,1 dB; konektor 0,5 dB).
- Problem port down pada port yang terpatch ke ODF menampilkan tautan "jalur fiber".

## 6. Deteksi anomali (offline)

Tiap 15 menit, nilai 30 menit terakhir dibanding **jam yang sama (±1 jam)** pada hari-hari sebelumnya memakai median & MAD
(robust z-score), sehingga pola shift harian tidak dianggap anomali:

| Jenis | Aturan |
|---|---|
| Latency | median naik dengan skor ≥ 6 dan ≥ 5 ms di atas normal (Quality Probe) |
| Packet loss | rata-rata naik dengan skor ≥ 6 dan ≥ 5 poin (loss 100% sudah ditangani status "Putus") |
| Traffic turun drastis | median < 30% dari normal, skor ≤ −4, normal ≥ 5% (uplink di Capacity Planning) — mis. stream CCTV berhenti |
| Traffic melonjak | skor ≥ 6 dan ≥ 20 poin di atas normal |
| Flapping | perangkat down ≥ 3 kali dalam 6 jam |

Butuh minimal 24 sampel baseline di jam yang sama. Anomali masuk Event & Alert Center sebagai *information* (flapping: *warning*),
sehingga tidak memicu eskalasi kecuali kebijakan diatur untuk severity itu.

## 7. Asisten AI (opsional)

Memakai Claude API lewat SDK resmi `anthropic-ai/sdk` (model bawaan `claude-opus-5-5`, effort *medium*, fallback server-side
bila model utama menolak). Aktif bila `ANTAREJA_AI_KEY` diisi — lihat `docs/INSTALL.md` §5.

| Fitur | Hasil |
|---|---|
| Serah terima shift (8 / 12 / 24 jam) | Ringkasan kondisi, gangguan aktif, yang selesai, tindak lanjut, risiko |
| Analisis incident | Kronologi, dugaan penyebab beserta buktinya, langkah pengecekan, hal yang tidak diketahui |

Batasan & keamanan:
- AI **hanya menulis draf teks**. Tidak ada tool, tidak ada akses ke perangkat, tidak pernah mengubah konfigurasi.
  Root cause dan status incident tidak pernah diisi otomatis.
- Data yang dikirim disusun ANTAREJA: problem, incident & catatannya, work order, change, maintenance, anomali, statistik perubahan konfigurasi.
  **Tidak pernah** dikirim: isi konfigurasi, kredensial, kunci, email/telepon. Alamat IP & MAC disamarkan secara bawaan.
- Tombol **Lihat data** menampilkan persis teks yang akan dikirim tanpa mengirim apa pun. Setiap draf menyimpan data yang dikirim,
  model, dan jumlah token, serta tercatat di audit log.
- Kunci API hanya di server. Draf dirender sebagai Markdown aman (HTML dibuang, tautan berbahaya diblokir).
- Tanpa internet / tanpa kunci: tombol nonaktif dengan penjelasan; semua fitur lain tetap berjalan.

## 8. Hak akses

| Permission | ICT Network | ICT CCTV/Support | ICT Admin | Management |
|---|---|---|---|---|
| `ant-workorder.view` / `.update` / `.verify` | ✓ / ✓ / ✓ | ✓ / ✓ / — | ✓ | ✓ / — / — |
| `ant-change.view` / `.update` / `.approve` | ✓ / ✓ / — | ✓ / ✓ / — | ✓ | ✓ / — / ✓ |
| `ant-config.view` / `.update` | ✓ / ✓ | — | ✓ | — |
| `ant-capacity.view` / `.update` | ✓ / ✓ | ✓ / — | ✓ | ✓ / — |
| `ant-fiber.view` / `.update` | ✓ / ✓ | ✓ / ✓ | ✓ | ✓ / — |
| `ant-ai.use` | ✓ | ✓ | ✓ | — |

## 9. Perintah

```bash
./antareja.sh lnms antareja:config-backup --force            # ambil semua konfigurasi sekarang
./antareja.sh lnms antareja:config-backup --device=12        # satu perangkat
./antareja.sh lnms antareja:capacity                         # perbarui kapasitas & prakiraan
./antareja.sh lnms antareja:anomalies                        # deteksi anomali sekarang
```

## 10. Batasan

- Backup konfigurasi memakai SSH exec channel; perangkat yang hanya menerima shell interaktif (sebagian Huawei/H3C lama) belum didukung. Belum ada Telnet/SCP/TFTP.
- Capacity planning belum memodelkan pola musiman; port tanpa histori RRD mulai dari nol.
- Fiber: belum ada peta GIS; koordinat joint disimpan untuk keperluan berikutnya. File trace OTDR (.sor) belum bisa diunggah (gunakan PDF laporan).
- Asisten AI butuh internet ke api.anthropic.com dan menimbulkan biaya API per draf.

## 11. Uji

Diuji end-to-end di browser terhadap stack Docker:
- 5a (38 skenario): switch SSH tiruan (hanya perintah baca yang dikirim, host key TOFU & penolakan host key berubah,
  enkripsi kredensial & konfigurasi, penyamaran rahasia, diff, deteksi tanpa CHG → problem → verifikasi), alur change dengan
  persetujuan orang lain dan perubahan sah dalam jendela, siklus work order lengkap.
- 5b (24 skenario): histori RRD sintetis 90 hari (p95, tren, prakiraan), jaringan fiber 2 kabel + joint + 2 ODF (jalur 5.500 m,
  lokasi gangguan dari jarak OTDR, loss budget, core putus).
- 5c (23 skenario): anomali latency/loss/traffic/flapping; asisten AI terhadap endpoint tiruan (isi request: model, effort,
  fallback, tanpa tools, IP tersamar, tanpa konfigurasi/kredensial; fallback, refusal, kunci ditolak).
