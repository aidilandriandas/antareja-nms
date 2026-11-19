# ANTAREJA NMS — Fase 3: Problem, Incident, Eskalasi & Maintenance

Versi 0.4.0. Fitur, aturan, dan batasan.

## 1. Ringkasan

| Menu | URL | Isi |
|---|---|---|
| Event & Alert Center | `/antareja/problems` | Problem ala Zabbix dari semua sumber, dedup, korelasi induk/anak, acknowledge (tunggal & massal), riwayat event |
| Incident Management | `/antareja/incidents` | Nomor `INC-YYMM-####`, severity, PIC, status, root cause, corrective action, work note, evidence, MTTA / MTTR |
| Eskalasi & Notifikasi | `/antareja/escalation` | Kebijakan incident otomatis + notifikasi bertahap lewat transport alert LibreNMS (Telegram, email, Slack, API, dll.) |
| Maintenance Management | `/antareja/maintenance` | Kalender preventive / corrective / change, penekanan alert, laporan penyelesaian, evidence, pengecualian SLA |

Semua dijalankan oleh `antareja:problems` setiap menit (scheduler): mulai/akhiri maintenance → sinkron problem → eskalasi.

## 2. Problem (Event & Alert Center)

### Sumber
| Sumber | Kondisi problem | Kunci korelasi | Severity |
|---|---|---|---|
| Status polling perangkat | `status=0`, tidak disabled/ignore, tidak Rejected/Retired | `device:{id}:down` | Disaster bila perangkat induk perangkat lain, selain itu High |
| Port terverifikasi (Device Mapping) | Admin up, oper bukan up | `port:{id}:down` | Average |
| Quality Probe | Status warning / critical / down | `quality:{id}`; target *down* yang terhubung ke perangkat → `device:{id}:down` | Warning / Average / High |
| IPAM | IP konflik | `ip:{alamat}` | Warning |
| Alert rule LibreNMS | Alert aktif (state 1–5) | Rule "device down / unreachable / no ICMP" → `device:{id}:down`; lainnya `alert:{id}` | Dari severity rule |

**Dedup:** beberapa sumber dengan kunci sama menjadi satu problem dengan banyak sumber (event `source_added`). Problem selesai otomatis bila semua sumbernya pulih.

### Korelasi & penekanan
- **Induk/anak:** dari `device_relationships` (ditulis oleh Device Mapping terverifikasi). Bila induk down, problem anak ditandai *ditekan: induk* dan menautkan ke problem induk. Problem anak ikut incident induk.
- **Maintenance:** perangkat dalam jendela maintenance aktif → *ditekan: maintenance* (prioritas di atas induk).
- Problem yang ditekan disembunyikan secara default (`?suppressed=1` untuk menampilkan) dan tidak dieskalasi.

### Acknowledge
Ack di ANTAREJA juga meng-acknowledge alert LibreNMS terkait (state 2, dengan catatan), sehingga notifikasi rule bawaan berhenti. Ack dari halaman Alerts LibreNMS juga terbaca balik.

## 3. Incident

- Status: Open → Acknowledged → In Progress ⇄ Pending → Resolved → Closed; Resolved / Closed dapat dibuka kembali.
- **Menutup incident wajib mengisi root cause dan corrective action.**
- Waktu acknowledge dan resolve dicatat otomatis → MTTA, MTTR.
- Dibuat manual (dari menu, halaman inventaris perangkat, atau problem) atau otomatis oleh kebijakan eskalasi.
- Severity incident dari problem: Information/Warning → Low, Average → Medium, High → High, Disaster → Critical.

## 4. Eskalasi

Kebijakan: nama, severity minimum, site (opsional), incident otomatis setelah N menit, tahap notifikasi (menit ke-N → transport), notifikasi pemulihan.

- Problem terbuka, tidak ditekan, belum di-ack, belum punya incident, dan sudah berumur ≥ N menit → incident otomatis.
- Tiap tahap dikirim sekali selama incident masih **Open**; berhenti begitu di-acknowledge.
- Saat incident Resolved, notifikasi pemulihan dikirim sekali.
- Pengiriman memakai transport alert LibreNMS yang sudah dikonfigurasi (Alerts → Alert Transports). Kredensial tetap di LibreNMS, tidak ada di ANTAREJA. Tombol **Uji kirim** tersedia di daftar kebijakan.
- Isi pesan: `[INCIDENT {severity}] {nomor} {judul}` / `[PULIH] {nomor}` + tautan ke incident (`ANTAREJA_PUBLIC_URL`).

## 5. Maintenance

- Jenis: preventive, corrective, change. Waktu diisi dalam zona `TZ` server (default Asia/Jakarta).
- Membuat jadwal juga membuat **Alert Schedule LibreNMS** (perilaku *mute alerts*) untuk perangkat terpilih, sehingga rule bawaan ikut diam. Edit / batal / selesai lebih cepat ikut memperbarui jadwal tersebut.
- Laporan penyelesaian: pekerjaan dilakukan, hasil (berhasil / sebagian / gagal), evidence foto/PDF.
- Opsi *kecualikan dari SLA* disimpan untuk dipakai perhitungan SLA Fase 4.

## 6. Hak akses

| Permission | ICT Network | ICT CCTV/Support | ICT Admin | Management |
|---|---|---|---|---|
| `ant-problem.view` / `.acknowledge` | ✓ / ✓ | ✓ / ✓ | ✓ / ✓ | ✓ / — |
| `ant-incident.view` / `.update` | ✓ / ✓ | ✓ / ✓ | ✓ / ✓ | ✓ / — |
| `ant-escalation.view` / `.update` | ✓ / — | — | ✓ / ✓ | — |
| `ant-maintenance.view` / `.update` | ✓ / ✓ | ✓ / ✓ | ✓ / ✓ | ✓ / — |

(Lihat `antareja:roles` untuk daftar lengkap.)

## 7. Perintah

```bash
./antareja.sh lnms antareja:problems                  # satu siklus penuh (dijalankan scheduler tiap menit)
./antareja.sh lnms antareja:problems --no-escalation  # hanya sinkron problem & maintenance
```

## 8. Batasan

- Trigger expression bebas ala Zabbix (`avg(/host/key,5m)>x`) belum ada; problem berasal dari sumber di atas dan alert rule LibreNMS (yang sudah mendukung kondisi kustom). Direncanakan di Fase 4.
- Eskalasi tahap 2+ hanya untuk incident yang belum di-ack; belum ada jadwal on-call/shift.
- Penekanan maintenance per perangkat, bukan per port/layanan.

## 9. Uji

Diuji end-to-end di browser (29 skenario): kebijakan & uji kirim, incident otomatis dari switch induk
dengan kamera anak ikut tertaut, problem warning tidak dieskalasi, ack diteruskan ke alert LibreNMS,
siklus status incident + penolakan tutup tanpa corrective action, notifikasi pemulihan, incident manual
+ work note + evidence, maintenance membuat Alert Schedule dan menekan problem, serta pembatasan role Management.
