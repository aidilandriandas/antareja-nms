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

# Daftarkan namespace Antareja\ ke autoloader (tanpa unduhan; vendor bawaan image dipakai ulang)
# lalu bangun ulang cache Laravel (image resmi menyertakan cache route/event yang belum berisi route ANTAREJA).
RUN cd /opt/librenms \
 && rm -f bootstrap/cache/*.php \
 && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize --no-interaction --no-scripts --quiet \
 && rm -rf docker \
 && chown -R librenms:librenms /opt/librenms/vendor/composer /opt/librenms/bootstrap/cache

USER librenms
RUN cd /opt/librenms \
 && php artisan package:discover --no-ansi \
 && php artisan route:cache --no-ansi \
 && php artisan event:cache --no-ansi \
 && php artisan route:list --path=antareja --no-ansi
USER root
