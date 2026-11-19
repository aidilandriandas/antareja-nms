# ANTAREJA NMS — Fase 1: Inventaris Tambang

Versi 0.2.0. Dokumen ini menjelaskan fitur Fase 1, alur kerja engineer, aturan perhitungan, dan batasannya.

## 1. Ringkasan fitur

| Menu | URL | Isi |
|---|---|---|
| Site & Area | `/antareja/sites` | Site operasional (office, workshop, plant, pit, ROM, jetty, warehouse, mess, remote) + area di dalamnya, PIC, koordinat, pemetaan ke sysLocation |
| Device Inventory | `/antareja/inventory` | Inventaris resmi: kategori, site/area, departemen, lokasi instalasi, rak, serial, asset tag, MAC, model, protokol manajemen, PIC, garansi, lifecycle, catatan, evidence, export CSV |
| Device Discovery | `/antareja/discovery` | Antrean verifikasi perangkat hasil discovery, aksi massal, reject, retire, restore |
| Device Mapping | `/antareja/mapping` | Relasi perangkat/port terverifikasi + saran dari LLDP/CDP & tabel MAC |
| Audit Log | `/antareja/audit` | Login, logout, login gagal, perubahan inventaris, mapping, site, verifikasi, evidence |
| Kualitas data | Beranda ANTAREJA | 11 indikator, masing-masing membuka daftar perangkat yang bermasalah |
| Panel device | Halaman device LibreNMS | Panel "ANTAREJA Inventaris" di tab Overview |

## 2. Alur verifikasi discovery

```
Perangkat ditemukan (auto-discovery / tambah manual / SNMP scan)
        │  otomatis (event + jadwal 15 menit)
        ▼
  DISCOVERED ──engineer mulai mengisi──► PENDING VERIFICATION
        │                                        │
        │ Reject (alasan wajib, opsional nonaktifkan polling)
        ▼                                        ▼ Verify (syarat: kategori + site + PIC)
    REJECTED                                 VERIFIED ◄──► INCOMPLETE MAPPING
        │                                        │          (otomatis bila data belum lengkap)
        └──── Restore ◄──── RETIRED ◄── Retire ──┘
```

- Perangkat yang belum diverifikasi **tetap dimonitor**, hanya belum dianggap inventaris resmi.
- **Saran kategori** dibuat dari OS/tipe perangkat yang dikenali poller (mis. `hikvision-cam` → CCTV Camera,
  `airos-af` → PTP, tipe `wireless` → Access Point). Saran tidak pernah disimpan sebagai kategori tanpa konfirmasi engineer.
- **Saran site** diambil dari site yang dipetakan ke sysLocation perangkat.

## 3. Aturan kelengkapan data (completeness)

Skor = persentase item yang terisi:

| Item | Terpenuhi bila |
|---|---|
| Kategori | kategori dipilih |
| Site | site dipilih |
| Lokasi instalasi / rak | salah satu terisi |
| PIC | PIC user **atau** nama PIC terisi |
| Serial number | serial manual **atau** serial dari SNMP ada |
| Mapping terverifikasi | hanya untuk Access Switch, Access Point, CCTV, PTP, PTMP: ada minimal satu mapping terverifikasi |

Perangkat **Verified** dengan skor < 100% ditampilkan sebagai **Incomplete Mapping**.

## 4. Device mapping

- Mapping **bukan topologi otomatis**. Konvensi: **A = upstream / parent**, **B = downstream / child**.
- Sumber saran (jadwal setiap jam menit ke-17, atau tombol "Cari saran sekarang"):
  - **LLDP/CDP**: neighbour yang dikenali poller. Sisi upstream ditentukan dari kategori (switch/router/firewall).
  - **FDB/ARP**: MAC perangkat ujung (CCTV, AP, radio, server) dicari di tabel MAC switch.
    Port dengan lebih dari 4 MAC dianggap trunk/uplink dan tidak dipakai.
- Saran yang **ditolak tidak disarankan ulang**.
- Mapping terverifikasi bertipe *uplink, AP, CCTV, PTMP, NVR* otomatis ditulis sebagai **dependensi parent/child**
  di poller, sehingga alert perangkat anak ditekan saat parent-nya down. Dependensi yang dibuat manual di
  LibreNMS tidak dihapus oleh ANTAREJA.

## 5. Indikator kualitas data

Belum diverifikasi · Tanpa site · Tanpa kategori · Tanpa PIC · Tanpa lokasi/rak · Tanpa serial ·
AP/CCTV/radio tanpa mapping · Serial duplikat · IP manajemen duplikat · Data monitoring basi (> 30 menit tidak terpoll) ·
Garansi habis. Perangkat Rejected/Retired tidak dihitung.

## 6. Evidence

- JPG, PNG, WEBP, PDF; maksimal 10 MB per file.
- Disimpan di volume `/data/antareja/evidence` (ikut `./antareja.sh backup`).
- Hanya user yang boleh melihat perangkat tersebut yang dapat membuka file evidence.

## 7. Hak akses (permission baru)

| Permission | ICT Network | ICT CCTV/Support | Management |
|---|---|---|---|
| `ant-site.view` / `ant-site.update` | ✓ / ✓ | ✓ / — | ✓ / — |
| `ant-inventory.view` / `.update` | ✓ / ✓ | ✓ / ✓ | ✓ / — |
| `ant-discovery.view` / `.approve` | ✓ / ✓ | ✓ / — | — |
| `ant-mapping.view` / `.update` / `.verify` | ✓ / ✓ / ✓ | ✓ / ✓ / — | — |
| `ant-audit.view` | — (admin) | — | — |

ICT Admin (role `admin`) memiliki semua akses. Permission dapat diubah per role di **User Management → Roles**.

## 8. Perintah

```bash
./antareja.sh lnms antareja:sync-inventory     # masukkan device yang belum terdaftar ke antrean
./antareja.sh lnms antareja:suggest-mappings   # buat saran mapping dari LLDP/CDP & FDB
./antareja.sh lnms antareja:roles              # sinkronkan permission role ANTAREJA
```

## 9. Batasan yang diketahui

- Saran FDB/ARP bergantung pada switch yang mendukung SNMP BRIDGE-MIB/Q-BRIDGE-MIB dan router/L3 switch yang
  menyediakan tabel ARP. Perangkat tanpa SNMP (mis. kamera murah) tetap bisa dipetakan manual.
- Pembatasan data per kategori (mis. role CCTV hanya melihat perangkat CCTV) belum diterapkan; saat ini memakai
  izin perangkat LibreNMS (viewAll / per device group). Direncanakan pada fase berikutnya.
- Antarmuka LibreNMS bawaan tetap berbahasa Inggris; halaman ANTAREJA berbahasa Indonesia.
