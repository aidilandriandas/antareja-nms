# ANTAREJA NMS

**Mining ICT Infrastructure Management & Unified Network Monitoring Platform**

Satu aplikasi web untuk monitoring dan manajemen infrastruktur ICT tambang, dibangun di atas
LibreNMS (fork), dengan fitur ala Zabbix (problem, trigger, SLA) dan SmokePing (kualitas jaringan)
yang diimplementasikan ulang secara native.

- Versi: **0.5.0 (Fase 4: SLA, Trigger, Dashboard Manajemen, Laporan)** · Basis: LibreNMS 26.10.2 · Lisensi: GPLv3
- Instalasi: [`docs/INSTALL.md`](docs/INSTALL.md)
- Fase 1 (Site, Inventory, Discovery, Mapping, Audit, Data Quality): [`docs/FASE-1-Inventaris.md`](docs/FASE-1-Inventaris.md)
- Fase 2 (IPAM, Network Quality, Wireless, CCTV): [`docs/FASE-2-IPAM-Kualitas.md`](docs/FASE-2-IPAM-Kualitas.md)
- Fase 3 (Event & Alert Center, Incident, Eskalasi, Maintenance): [`docs/FASE-3-Problem-Incident.md`](docs/FASE-3-Problem-Incident.md)
- Fase 4 (SLA & service tree, Trigger, Dashboard Manajemen, Laporan): [`docs/FASE-4-SLA-Laporan.md`](docs/FASE-4-SLA-Laporan.md)
- Arsitektur: [`docs/ANTAREJA-NMS-Arsitektur-v0.2-Fork.md`](docs/ANTAREJA-NMS-Arsitektur-v0.2-Fork.md)
- Perubahan terhadap LibreNMS: [`MODIFICATIONS.md`](MODIFICATIONS.md)

## Struktur

```
packages/antareja/   Semua fitur ANTAREJA (paket Laravel)
docker/              Dockerfile, compose.yml, Nginx, branding
antareja.sh          Skrip operasional
docs/                Dokumen ANTAREJA
(lainnya)            Kode LibreNMS (fork, perubahan minimal)
```

## Perintah cepat

```bash
./antareja.sh install      # pasang pertama kali
./antareja.sh health       # cek kesehatan
./antareja.sh backup       # backup DB + data + RRD
./antareja.sh lnms device:add 10.10.1.1 --v2c -c <community>
```

## Role awal

| Role | Kode | Cakupan |
|---|---|---|
| ICT Network | `ict-network` | Monitoring, inventaris jaringan, IPAM, mapping, incident jaringan |
| ICT CCTV / Support | `ict-cctv-support` | CCTV, inventaris CCTV, incident support, maintenance |
| ICT Admin | `admin` | Semua akses |
| Management | `management` | Dashboard eksekutif, laporan, SLA (baca saja) |

README.md asli LibreNMS dan LICENSE.txt dipertahankan sesuai kewajiban lisensi.
