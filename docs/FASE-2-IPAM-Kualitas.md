# ANTAREJA NMS — Fase 2: IPAM, Network Quality, Wireless & CCTV

Versi 0.3.0. Fitur, aturan perhitungan, dan batasan.

## 1. Ringkasan

| Menu | URL | Isi |
|---|---|---|
| IP Address Management | `/antareja/ipam` | Subnet (IPv4 & IPv6), VLAN plan, alokasi IP (static / reserved / DHCP), IP terpakai otomatis, IP kosong, peta alamat, konflik & duplikat, histori per IP, utilisasi |
| Network Quality | `/antareja/quality` | Quality Probe pengganti SmokePing: latency, packet loss, jitter, grafik smoke 3 jam / 30 jam / 10 hari / 30 hari |
| Wireless Monitoring | `/antareja/wireless` | AP, controller, PTP, PTMP: status, client, RSSI, SNR, frekuensi, channel, rate, CCQ, jarak, pasangan link |
| CCTV Infrastructure | `/antareja/cctv` | Kamera & NVR: status, IP, MAC, port switch, NVR perekam, lokasi, PIC, gangguan 30 hari |

## 2. IPAM

### Sumber data otomatis (setiap 15 menit, `antareja:ipam-sync`)
| Sumber | Dipakai untuk |
|---|---|
| IP interface perangkat (`ipv4_addresses`, `ipv6_addresses`) | IP terpakai + perangkat & port pemilik |
| IP manajemen perangkat (`devices.ip`) | IP terpakai + perangkat |
| Tabel ARP router / L3 switch (`ipv4_mac`) | IP terpakai + MAC, **hanya untuk IP di dalam subnet IPAM** (agar PC/HP user tidak membanjiri IPAM) |

Tidak ada pemindaian (scan) tambahan ke jaringan; semua dari data yang sudah dikumpulkan poller.

### Status IP
| Status | Arti | Diubah sinkron otomatis? |
|---|---|---|
| Used | Terlihat aktif di jaringan | Ya |
| Static | Dialokasikan engineer | Tidak |
| Reserved | Dicadangkan, belum boleh dipakai | Tidak (bila aktif → konflik) |
| DHCP | IP DHCP (record manual) | Tidak |
| Available | Tidak tercatat, bukan gateway, di luar pool DHCP | — |

### Rumus
- **Usable** IPv4 = 2^(32−prefix) − 2 (untuk /31 dan /32: tanpa pengurangan).
- **Allocated** = jumlah record IP di subnet + alamat pool DHCP yang belum tercatat.
- **Utilisasi** = Allocated ÷ Usable. Warna bar: kuning ≥ 80%, merah ≥ 90%.
- **Available** = Usable − Allocated.
- IPv6: disimpan & dicatat, tetapi perhitungan IP kosong / peta alamat saat ini hanya IPv4.

### Deteksi konflik / duplikat IP
IP ditandai **konflik** bila: (1) **satu** router/L3 melihat IP itu dengan lebih dari satu MAC di tabel ARP-nya sendiri
(per VRF/context), (2) terpasang di interface lebih dari satu perangkat, (3) berstatus reserved tetapi aktif, atau
(4) berstatus static dan MAC yang teramati berbeda. Konflik otomatis dinyatakan selesai bila tidak lagi teramati.
Setiap kejadian tercatat di histori IP.

MAC berbeda di tabel ARP perangkat yang **berbeda** tidak dihitung konflik: biasanya cache ARP lama (IP DHCP sudah
berpindah ke klien lain tetapi router kedua belum memperbarui ARP-nya) atau MAC interface VLAN/bridge yang berbeda dari
port fisik. Tabel ARP router/L3 dibaca ulang tiap 30 menit (`antareja:arp-refresh`, SNMP read-only) — tidak menunggu
discovery penuh LibreNMS yang hanya tiap 6 jam — dan bisa dipicu manual dengan tombol *Perbarui data ARP & cek ulang*
di halaman Konflik IP.

> Catatan: IP virtual VRRP/HSRP yang muncul di interface beberapa router dapat terdeteksi sebagai konflik (2).
> Tandai dengan keterangan pada record IP tersebut.

## 3. Network Quality (Quality Probe)

- Setiap **5 menit** (`antareja:quality-probe`), server ANTAREJA mengirim **20 ping** (jeda 1 detik) ke setiap target
  menggunakan `fping -C`. Semua target diukur paralel.
- Disimpan per siklus: sent, received, loss %, min, p10, p25, median, rata-rata, p75, p90, max, **jitter**, stddev.
- **Jitter** = rata-rata selisih absolut RTT berurutan (paket hilang dilewati).
- **Retensi**: sampel 5 menit 30 hari; ringkasan harian (`ant_quality_daily`) disimpan jangka panjang untuk laporan.
  Rollup & pembersihan berjalan setiap hari pukul 00:20 (`antareja:quality-maintenance`).

### Status target
| Status | Aturan (default, bisa diubah per target) |
|---|---|
| Putus | seluruh ping dalam satu siklus hilang |
| Buruk | loss ≥ 20% **atau** median ≥ 250 ms |
| Menurun | loss ≥ 5% **atau** median ≥ 100 ms **atau** jitter ≥ 30 ms |
| Baik | selain di atas |

Perubahan status pada target yang terhubung ke perangkat dicatat di **event log perangkat** (`type: antareja`).
Untuk link VSAT/radio jarak jauh, naikkan ambang latency.

### Grafik smoke
- Pita tipis = p10–p90, pita tebal = p25–p75, garis = median (warna = packet loss), kolom merah = putus.
- 3 jam & 30 jam: data 5 menit asli. 10 hari: rata-rata per jam. 30 hari: rata-rata per 3 jam.
  Bucket yang berisi siklus putus diberi **penanda merah di atas grafik** agar gangguan singkat tidak tertelan rata-rata.
- Grafik dirender sebagai SVG di server: tanpa library JavaScript eksternal, berfungsi di jaringan tanpa internet.
- **Uptime jalur** = persentase siklus 5 menit yang tidak loss 100%.

### Batasan
- Pengukuran dari satu titik (server ANTAREJA). Pengukuran dari site remote (multi-probe) direncanakan memakai
  distributed poller pada fase berikutnya.
- Probe saat ini ICMP. Probe TCP/HTTP/DNS direncanakan.

## 4. Wireless Monitoring
- Perangkat tampil bila punya sensor wireless **atau** berkategori Access Point / Wireless Controller / PTP / PTMP.
- Agregasi antar radio: client = jumlah; RSSI, SNR, CCQ, kualitas, kapasitas = nilai terburuk; frekuensi & channel = radio pertama.
- Sorotan kuning: RSSI < −75 dBm, SNR < 20 dB, CCQ < 80%, kualitas < 70%, kapasitas < 50%.
- **Tidak semua vendor menyediakan metrik yang sama**; kolom tanpa data ditampilkan "—", tidak pernah diisi tebakan.
- Pasangan link (link partner) dari Device Mapping bertipe PTP / PTMP yang terverifikasi.

## 5. CCTV Infrastructure
- Kamera = kategori *CCTV Camera*, NVR = kategori *NVR* di Device Inventory.
- Port switch dari mapping terverifikasi tipe *CCTV ↔ port switch*; NVR dari mapping *NVR ↔ kamera*.
- MAC: dari inventaris, lalu dari IPAM (tabel ARP).
- Gangguan 30 hari dari data outage poller (jumlah, total durasi, waktu terakhir).
- Kamera tanpa SNMP dapat dimonitor sebagai perangkat **ping-only**.

## 6. Hak akses
| Permission | ICT Network | ICT CCTV/Support | Management |
|---|---|---|---|
| `ant-ipam.view` / `.update` | ✓ / ✓ | ✓ / — | — |
| `ant-quality.view` / `.update` | ✓ / ✓ | ✓ / — | ✓ / — |
| `ant-cctv.view` | ✓ | ✓ | ✓ |
| Wireless (`ant-inventory.view`) | ✓ | ✓ | ✓ |

## 7. Perintah
```bash
./antareja.sh lnms antareja:ipam-sync
./antareja.sh lnms antareja:quality-probe
./antareja.sh lnms antareja:quality-probe --target=3
./antareja.sh lnms antareja:quality-maintenance --days=7
```

## 8. Perkiraan penyimpanan
100 target × 288 sampel/hari × 30 hari ≈ 864 ribu baris (± 120 MB MariaDB termasuk index).
