#!/usr/bin/with-contenv bash
# shellcheck shell=bash
# ANTAREJA NMS: batasi tiap putaran "artisan schedule:run" maksimal 4 menit.
# crond tidak menjalankan putaran baru selama putaran sebelumnya masih berjalan; satu tugas yang menggantung
# (mis. unduhan internet yang diblok firewall) bisa menghentikan SEMUA tugas terjadwal (probe kualitas, problem,
# notifikasi). Tugas ANTAREJA sendiri berjalan di latar belakang dan tidak ikut terhenti.
set -e
CRONTAB=/var/spool/cron/crontabs/librenms
if [ -f "$CRONTAB" ] && ! grep -q 'timeout -s KILL 240 php /opt/librenms/artisan schedule:run' "$CRONTAB"; then
    sed -i 's#^\* \* \* \* \* php /opt/librenms/artisan schedule:run#* * * * * timeout -s KILL 240 php /opt/librenms/artisan schedule:run#' "$CRONTAB"
    echo "ANTAREJA: schedule:run dibatasi 240 detik per putaran"
fi
