# ANTAREJA NMS — image aplikasi
# Dibangun di atas image resmi LibreNMS (librenms/docker) dengan versi yang sama
# dengan basis fork, lalu kode ANTAREJA (fork + packages/antareja) ditimpakan.
ARG LIBRENMS_VERSION=26.10.2
FROM librenms/librenms:${LIBRENMS_VERSION}

LABEL org.opencontainers.image.title="ANTAREJA NMS" \
      org.opencontainers.image.description="Mining ICT Infrastructure Management & Unified Network Monitoring Platform (based on LibreNMS)" \
      org.opencontainers.image.licenses="GPL-3.0-or-later"

# Kode fork (lihat .dockerignore: vendor, node_modules, .git, docs tidak ikut)
COPY --chown=librenms:librenms . /opt/librenms/

# Branding default ANTAREJA (dibaca ConfigSeeder saat seed pertama)
COPY --chown=librenms:librenms docker/config/antareja.yaml /opt/librenms/database/seeders/config/antareja.yaml

# Init container: siapkan /data/antareja (evidence) setiap start
COPY --chmod=755 docker/init/03a-antareja.sh /etc/cont-init.d/03a-antareja.sh
# Batasi durasi tiap putaran penjadwal (dijalankan setelah 07-svc-cron.sh membuat crontab)
COPY --chmod=755 docker/init/08-antareja-cron.sh /etc/cont-init.d/08-antareja-cron.sh

# Pasang dependensi tambahan ANTAREJA (anthropic-ai/sdk untuk asisten AI opsional) di atas vendor bawaan image —
# hanya paket yang belum ada yang diunduh (butuh internet saat build) — lalu daftarkan namespace Antareja\ ke
# autoloader dan bangun ulang cache Laravel (image resmi menyertakan cache route/event yang belum berisi route ANTAREJA).
# Jaringan dengan proxy TLS-inspection: taruh CA proxy di docker/build-ca.crt (tidak di-commit).
# Tanpa akses GitHub/Packagist: salin cache Composer (folder files/) dari mesin lain ke docker/composer-cache/.
RUN cd /opt/librenms \
 && rm -f bootstrap/cache/*.php \
 && if [ -s docker/build-ca.crt ]; then export COMPOSER_CAFILE="$PWD/docker/build-ca.crt"; fi \
 && if [ -d docker/composer-cache ]; then export COMPOSER_CACHE_DIR="$PWD/docker/composer-cache"; fi \
 && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --no-progress \
 && rm -rf docker \
 && chown -R librenms:librenms /opt/librenms/vendor/composer /opt/librenms/bootstrap/cache

USER librenms
RUN cd /opt/librenms \
 && php artisan package:discover --no-ansi \
 && php artisan route:cache --no-ansi \
 && php artisan event:cache --no-ansi \
 && php artisan route:list --path=antareja --no-ansi
USER root
