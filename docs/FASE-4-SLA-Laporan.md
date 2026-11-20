# ANTAREJA NMS — Fase 4: SLA, Trigger, Dashboard Manajemen & Laporan

Versi 0.5.0. Fitur, rumus, dan batasan.

## 1. Ringkasan

| Menu | URL | Isi |
|---|---|---|
| SLA Management | `/antareja/sla` | Service tree, target SLA, availability bulanan, MTTR / MTBF, gangguan per layanan, riwayat 12 bulan |
| Availability perangkat | `/antareja/sla/devices` | Availability semua perangkat per bulan, filter site |
| Trigger | `/antareja/triggers` | Trigger expression ala Zabbix; trigger yang terpenuhi menjadi problem |
| Dashboard Manajemen | `/antareja/management` | KPI bulan berjalan, tren 6 bulan, per site, SLA layanan, incident aktif, perangkat paling sering terganggu |
| Reports | `/antareja/reports` | Laporan bulanan (semua site / per site): tampilan web, cetak / PDF, Excel, CSV |

## 2. SLA & service tree

**Layanan** berisi perangkat dan/atau layanan anak (bertingkat sampai 10 level). Aturan status:

| Aturan | Layanan down bila … | Contoh |
|---|---|---|
| Seri | salah satu anggota down | switch core → radio PTP → switch pit |
| Redundan | semua anggota down | dua jalur uplink (FO + radio) |
| Minimal N up | anggota yang up kurang dari N | minimal 3 dari 4 AP |

### Rumus
- **Downtime perangkat** = outage poller LibreNMS (`device_outages`); outage yang belum selesai dihitung sampai sekarang.
- Downtime yang jatuh di **jendela maintenance** dengan opsi *kecualikan dari SLA* tidak dihitung, dicatat sebagai *dikecualikan*. Maintenance yang selesai lebih cepat dihitung sampai waktu selesainya.
- **Downtime layanan**: interval down anggota digabung sesuai aturan (seri = gabungan, redundan = irisan, minimal N = saat jumlah anggota down > anggota − N).
- **Availability** = (periode − downtime) ÷ periode × 100%. Periode bulan berjalan dihitung sampai sekarang. Untuk perangkat, periode dimulai saat perangkat ditambahkan ke monitoring (bulan sebelum itu tidak dihitung, tidak dianggap 100%).
- **MTTR** = downtime ÷ jumlah gangguan. **MTBF** = waktu up ÷ jumlah gangguan.
- Bulan yang sudah lewat **dibekukan** di `ant_sla_monthly` (otomatis tiap hari 00:35 lewat `antareja:sla`, atau saat bulan itu pertama kali dibuka), supaya laporan bulan lalu tidak berubah bila data outage dibersihkan. Hitung ulang: `./antareja.sh lnms antareja:sla --month=2026-10 --refresh`.

## 3. Trigger expression

Bentuk terstruktur dari ekspresi Zabbix: `fungsi(item · metrik, jendela) operator ambang`, mis. `avg(PTP Pit A · Packet loss, 15m) > 5 %`.

| Sumber | Metrik |
|---|---|
| Quality Probe | latency median, latency rata-rata, packet loss, jitter |
| Port / interface | utilisasi masuk/keluar (%), traffic masuk/keluar (Mbps), error masuk/keluar (/s) |
| Sensor hardware | nilai sensor (suhu, tegangan, fan, daya, …) |
| Sensor wireless | nilai sensor (RSSI, SNR, CCQ, client, …) |
| Perangkat | uptime (deteksi reboot), durasi polling SNMP |

- Fungsi: `last`, `avg`, `min`, `max`. Jendela 1 menit – 7 hari. Operator `> ≥ < ≤ = ≠`.
- **Ambang pemulihan (histeresis)**: mis. problem bila > 80, pulih bila ≤ 70 — mencegah problem naik-turun.
- Dievaluasi tiap menit di awal `antareja:problems`. Nilai Quality Probe dibaca langsung dari sampelnya; sumber lain dicatat ke `ant_trigger_samples` tiap evaluasi (disimpan 8 hari).
- Trigger berstatus problem masuk Event & Alert Center sebagai sumber **Trigger expression** (kunci `trigger:{id}`), ikut korelasi, maintenance, incident otomatis, dan eskalasi Fase 3.
- *Tanpa data* (mis. port terhapus, perangkat down untuk metrik uptime) tidak membuat problem.
- Waktu respons ICMP tidak tersedia sebagai metrik karena LibreNMS 26 menyimpannya hanya di RRD; gunakan Quality Probe untuk latency.

## 4. Dashboard Manajemen

KPI bulan berjalan (availability rata-rata perangkat, layanan sesuai SLA, incident, MTTA, MTTR, gangguan aktif), grafik availability & jumlah incident 6 bulan (dengan tabel), availability per site, SLA layanan, incident aktif, 8 perangkat paling sering terganggu, 5 link dengan packet loss tertinggi. Tren bulan-bulan lalu di-cache 1 jam.

## 5. Laporan bulanan

Sembilan bagian: ringkasan, SLA layanan, availability per site, perangkat dengan gangguan, incident (dengan waktu respons/pulih & root cause), maintenance, kualitas jaringan (ketersediaan ping, loss, latency, jitter), utilisasi IP, problem per jenis. Bisa difilter per site.

| Format | Cara |
|---|---|
| PDF | Tombol **Cetak / PDF** membuka versi cetak A4 → dialog cetak browser → *Save as PDF*. Tidak butuh layanan tambahan dan bisa offline. |
| Excel (.xlsx) | Satu file, satu sheet per bagian, dibuat native tanpa library tambahan. |
| CSV | Per bagian, pemisah `;`, UTF-8 dengan BOM (langsung terbaca Excel). Sel yang diawali `= + - @` diberi `'` untuk mencegah formula injection. |

Setiap export dicatat di Audit Log.

## 6. Hak akses

| Permission | ICT Network | ICT CCTV/Support | ICT Admin | Management |
|---|---|---|---|---|
| `ant-sla.view` / `.update` | ✓ / ✓ | ✓ / — | ✓ / ✓ | ✓ / — |
| `ant-trigger.view` / `.update` | ✓ / ✓ | ✓ / — | ✓ / ✓ | — |
| `ant-dashboard.management` | ✓ | — | ✓ | ✓ |
| `ant-report.view` / `.export` | ✓ / ✓ | ✓ / ✓ | ✓ / ✓ | ✓ / ✓ |

## 7. Perintah

```bash
./antareja.sh lnms antareja:sla                       # bekukan SLA bulan lalu
./antareja.sh lnms antareja:sla --month=2026-10 --refresh
./antareja.sh lnms antareja:triggers                  # evaluasi & tampilkan semua trigger
```

## 8. Batasan

- Belum ada jam operasional / business hours untuk SLA (dihitung 24×7).
- Trigger belum mendukung ekspresi gabungan (`and` / `or` antar item) dan fungsi `count`, `nodata`, `change`.
- Laporan belum dikirim otomatis lewat email terjadwal.
- PDF dibuat lewat cetak browser, bukan di server.

## 9. Uji

Diuji end-to-end di browser: service tree seri & redundan dengan outage sintetis (downtime 2 j 30 m, 1 j dikecualikan maintenance), penolakan induk melingkar, urutan availability perangkat, pembekuan SLA bulan lalu, trigger perangkat / port / Quality Probe, histeresis & validasinya, trigger → problem → pulih saat dinonaktifkan, laporan 9 bagian, Excel 9 sheet, CSV, versi cetak & PDF, dashboard manajemen, serta pembatasan role Management dan CCTV.
