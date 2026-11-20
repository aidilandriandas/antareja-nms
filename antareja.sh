#!/usr/bin/env bash
# ANTAREJA NMS — skrip operasional
# Pemakaian: ./antareja.sh {install|start|stop|restart|status|logs|update|backup|backups|restore|health|admin|setup-token|lnms}
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$ROOT/docker"
ENV_FILE="$DOCKER_DIR/.env"
COMPOSE=(docker compose --project-directory "$DOCKER_DIR" -f "$DOCKER_DIR/compose.yml" --env-file "$ENV_FILE")

c_ok() { printf '\033[32m%s\033[0m\n' "$*"; }
c_warn() { printf '\033[33m%s\033[0m\n' "$*"; }
c_err() { printf '\033[31m%s\033[0m\n' "$*" >&2; }
die() { c_err "ERROR: $*"; exit 1; }

env_get() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2-; }

require_env() { [[ -f "$ENV_FILE" ]] || die "docker/.env belum ada. Jalankan: ./antareja.sh install"; }

backup_dir() {
    local dir
    dir="$(env_get BACKUP_DIR)"
    dir="${dir:-./backups}"
    [[ "$dir" = /* ]] || dir="$DOCKER_DIR/${dir#./}"
    mkdir -p "$dir"
    echo "$dir"
}

check_prereq() {
    command -v docker >/dev/null || die "Docker belum terpasang (lihat docs/INSTALL.md)."
    docker compose version >/dev/null 2>&1 || die "Docker Compose v2 belum terpasang."
    docker info >/dev/null 2>&1 || die "Docker daemon tidak berjalan / user tidak punya akses ke Docker."
    command -v openssl >/dev/null || die "openssl belum terpasang (apt install openssl)."
}

gen_secret() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-32}"; }

make_env() {
    if [[ -f "$ENV_FILE" ]]; then
        c_warn "docker/.env sudah ada, tidak ditimpa."
        return
    fi
    # Volume database dari instalasi lain (nama project sama) memakai password lama: .env baru pasti gagal konek
    if docker volume inspect antareja_db-data >/dev/null 2>&1; then
        c_err "Volume database 'antareja_db-data' dari instalasi sebelumnya masih ada, tapi docker/.env belum ada di folder ini."
        c_err "Password baru tidak akan cocok dengan database lama. Pilih salah satu:"
        c_err "  - pakai .env lama: salin docker/.env dari folder instalasi sebelumnya ke $ENV_FILE, lalu ulangi"
        c_err "  - mulai dari nol (DATA HILANG): docker volume ls | grep antareja_  lalu hapus volume tersebut, lalu ulangi"
        exit 1
    fi
    cp "$DOCKER_DIR/.env.example" "$ENV_FILE"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(gen_secret 32)|" "$ENV_FILE"
    sed -i "s|^SNMP_COMMUNITY_LOCAL=.*|SNMP_COMMUNITY_LOCAL=$(gen_secret 20)|" "$ENV_FILE"
    if [[ -n "${ANTAREJA_SERVER_NAME:-}" ]]; then
        sed -i "s|^SERVER_NAME=.*|SERVER_NAME=${ANTAREJA_SERVER_NAME}|" "$ENV_FILE"
    fi
    chmod 600 "$ENV_FILE"
    c_ok "docker/.env dibuat dengan password acak."
}

make_cert() {
    local dir="$DOCKER_DIR/certs" name
    name="$(env_get SERVER_NAME)"
    mkdir -p "$dir"
    if [[ -f "$dir/antareja.crt" && -f "$dir/antareja.key" ]]; then
        c_warn "Sertifikat TLS sudah ada, tidak ditimpa."
        return
    fi
    local ips san="DNS:${name},DNS:localhost,IP:127.0.0.1"
    ips="$(hostname -I 2>/dev/null || true)"
    for ip in $ips; do [[ "$ip" == *:* ]] || san="$san,IP:$ip"; done
    openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout "$dir/antareja.key" -out "$dir/antareja.crt" \
        -subj "/CN=${name}/O=ANTAREJA NMS" -addext "subjectAltName=${san}" >/dev/null 2>&1
    chmod 600 "$dir/antareja.key"
    c_ok "Sertifikat TLS self-signed dibuat ($san). Ganti dengan sertifikat CA perusahaan bila ada."
}

wait_healthy() {
    local timeout="${1:-300}" waited=0 state
    printf 'Menunggu aplikasi siap'
    while (( waited < timeout )); do
        state="$("${COMPOSE[@]}" ps --format '{{.Health}}' app 2>/dev/null || true)"
        if [[ "$state" == "healthy" ]]; then echo; c_ok "Aplikasi siap."; return 0; fi
        printf '.'; sleep 5; waited=$((waited + 5))
    done
    echo; c_err "Aplikasi belum sehat setelah ${timeout}s. Cek: ./antareja.sh logs app"; return 1
}

lnms() { "${COMPOSE[@]}" exec -T --user librenms app lnms "$@"; }

cmd_install() {
    check_prereq
    make_env
    make_cert
    c_ok "Membangun image ANTAREJA..."
    "${COMPOSE[@]}" build app
    "${COMPOSE[@]}" up -d
    wait_healthy 600
    c_ok "Instalasi selesai."
    cmd_setup_token
}

# Kode untuk halaman Setup Awal (/setup) — membuat admin pertama lewat browser
cmd_setup_token() {
    local token ip
    token="$(lnms antareja:setup-token 2>/dev/null | tail -n1 | tr -d '\r')"
    ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    if [[ "$token" =~ ^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$ ]]; then
        echo
        echo "  Buka di browser : https://${ip:-$(env_get SERVER_NAME)}/setup"
        echo "  Kode setup      : $token"
        echo
        echo "  (Peringatan sertifikat self-signed itu wajar: klik Advanced → Proceed.)"
        echo "  Alternatif tanpa browser: ./antareja.sh admin"
    else
        echo "$token"
    fi
}

cmd_admin() {
    local user="${ANTAREJA_ADMIN_USER:-}" pass="${ANTAREJA_ADMIN_PASSWORD:-}" email="${ANTAREJA_ADMIN_EMAIL:-}"
    if [[ -z "$user" ]]; then read -r -p "Username admin [admin]: " user; user="${user:-admin}"; fi
    if [[ -z "$pass" ]]; then
        read -r -s -p "Password admin (kosong = acak): " pass; echo
        if [[ -z "$pass" ]]; then pass="$(gen_secret 16)"; echo "Password admin: $pass  (simpan, lalu ganti setelah login)"; fi
    fi
    local args=("$user" --password="$pass" --role=admin --full-name="ICT Admin")
    [[ -n "$email" ]] && args+=(--email="$email")
    lnms user:add "${args[@]}" && c_ok "User admin '$user' dibuat (role ICT Admin)."
}

cmd_start() { require_env; "${COMPOSE[@]}" up -d; wait_healthy 600; }
cmd_stop() { require_env; "${COMPOSE[@]}" stop; }
cmd_restart() { require_env; "${COMPOSE[@]}" restart; wait_healthy 300; }
cmd_status() { require_env; "${COMPOSE[@]}" ps; }
cmd_logs() { require_env; "${COMPOSE[@]}" logs --tail=200 -f "$@"; }

cmd_backup() {
    require_env
    local dir ts name
    dir="$(backup_dir)"; ts="$(date +%Y%m%d-%H%M%S)"; name="antareja-$ts"
    mkdir -p "$dir/$name"
    c_ok "Backup database..."
    "${COMPOSE[@]}" exec -T db sh -c 'mariadb-dump --single-transaction --quick --routines --triggers -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
        | gzip > "$dir/$name/database.sql.gz"
    c_ok "Backup data aplikasi + RRD..."
    "${COMPOSE[@]}" run --rm --no-deps -T -v "$dir/$name:/backup" --entrypoint tar app \
        czf /backup/data.tar.gz --exclude=./logs --exclude=./antareja/backups -C /data .
    cp "$ENV_FILE" "$dir/$name/env.backup"; chmod 600 "$dir/$name/env.backup"
    [[ -d "$DOCKER_DIR/certs" ]] && cp -r "$DOCKER_DIR/certs" "$dir/$name/certs"
    tar czf "$dir/$name.tar.gz" -C "$dir" "$name" && rm -rf "${dir:?}/$name"
    chmod 600 "$dir/$name.tar.gz"
    local keep; keep="$(env_get BACKUP_KEEP_DAYS)"; keep="${keep:-14}"
    find "$dir" -maxdepth 1 -name 'antareja-*.tar.gz' -mtime +"$keep" -delete
    c_ok "Backup selesai: $dir/$name.tar.gz"
    c_warn "Salin file backup ke luar VM (NAS / share) secara berkala."
}

# Daftar backup yang dibuat dari web (tersimpan di volume data aplikasi)
cmd_backups() {
    require_env
    echo "Backup di volume aplikasi (dibuat dari menu Backup & Pemulihan / jadwal otomatis):"
    "${COMPOSE[@]}" exec -T app sh -c 'ls -lh /data/antareja/backups/antareja-*.tar.gz 2>/dev/null | awk "{print \"  \" \$9 \"  (\" \$5 \")\"}" | sed "s#/data/antareja/backups/##"' || true
    echo "Backup dari ./antareja.sh backup (di $(backup_dir)):"
    ls -lh "$(backup_dir)"/antareja-*.tar.gz 2>/dev/null | awk '{print "  " $9 "  (" $5 ")"}' || true
    echo "Pulihkan: ./antareja.sh restore <nama atau path file>"
}

cmd_restore() {
    require_env
    local file="${1:-}"
    [[ -n "$file" ]] || die "Pemakaian: ./antareja.sh restore <file antareja-YYYYmmdd-HHMMSS.tar.gz>  (lihat: ./antareja.sh backups)"
    local tmp; tmp="$(mktemp -d)"
    if [[ ! -f "$file" ]]; then
        # Nama backup web di volume aplikasi
        [[ "$file" =~ ^antareja-[0-9]{8}-[0-9]{6}\.tar\.gz$ ]] || die "File $file tidak ditemukan."
        c_ok "Mengambil $file dari volume aplikasi..."
        "${COMPOSE[@]}" cp "app:/data/antareja/backups/$file" "$tmp/$file" || die "Backup $file tidak ada di volume (cek: ./antareja.sh backups)."
        file="$tmp/$file"
    fi
    read -r -p "Restore akan MENIMPA database & data saat ini. Lanjut? (ketik YA): " ans
    [[ "$ans" == "YA" ]] || die "Dibatalkan."
    mkdir -p "$tmp/x"
    tar xzf "$file" -C "$tmp/x"
    local src; src="$(find "$tmp/x" -mindepth 1 -maxdepth 1 -type d | head -1)"
    [[ -f "$src/database.sql.gz" && -f "$src/data.tar.gz" ]] || die "Isi backup tidak lengkap."
    local keep_rrd=0
    if [[ -f "$src/manifest.json" ]] && grep -q '"include_rrd": false' "$src/manifest.json"; then
        keep_rrd=1; c_warn "Backup ini tanpa grafik (RRD): grafik yang ada sekarang dipertahankan."
    fi
    "${COMPOSE[@]}" stop app dispatcher nginx >/dev/null 2>&1 || true
    "${COMPOSE[@]}" up -d db redis rrdcached
    sleep 10
    c_ok "Restore database..."
    gunzip -c "$src/database.sql.gz" | "${COMPOSE[@]}" exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
    c_ok "Restore data aplikasi + RRD..."
    # Folder backup di volume (/data/antareja/backups) tidak ikut dihapus
    "${COMPOSE[@]}" run --rm --no-deps -T -e KEEP_RRD="$keep_rrd" -v "$src:/backup:ro" --entrypoint sh app -c '
        find /data -mindepth 1 -maxdepth 1 ! -name rrd ! -name logs ! -name antareja -exec rm -rf {} +
        [ -d /data/antareja ] && find /data/antareja -mindepth 1 -maxdepth 1 ! -name backups -exec rm -rf {} +
        [ "$KEEP_RRD" = "1" ] || find /data/rrd -mindepth 1 -maxdepth 1 -exec rm -rf {} +
        tar xzf /backup/data.tar.gz -C /data'
    rm -rf "$tmp"
    c_warn "File env.backup & certs (khusus backup dari ./antareja.sh backup) TIDAK otomatis dipulihkan; salin manual bila VM baru."
    cmd_start
}

cmd_update() {
    require_env
    c_ok "Backup sebelum update..."
    cmd_backup
    if [[ -d "$ROOT/.git" ]] && git -C "$ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{u}' >/dev/null 2>&1; then
        git -C "$ROOT" pull --ff-only || die "git pull gagal; selesaikan konflik dulu."
    else
        c_warn "Branch git tidak punya remote tracking; lewati git pull (pakai kode lokal)."
    fi
    "${COMPOSE[@]}" build app
    "${COMPOSE[@]}" up -d
    wait_healthy 600
    # Konfigurasi Nginx & go2rtc dipasang sebagai file; container yang sudah jalan tidak membacanya ulang sendiri
    "${COMPOSE[@]}" exec -T nginx nginx -t >/dev/null 2>&1 && "${COMPOSE[@]}" exec -T nginx nginx -s reload >/dev/null 2>&1 || c_warn "Reload Nginx gagal; cek: ./antareja.sh logs nginx"
    "${COMPOSE[@]}" restart go2rtc >/dev/null 2>&1 || true
    cmd_health
}

cmd_health() {
    require_env
    local fail=0
    echo "== Container"
    "${COMPOSE[@]}" ps --format 'table {{.Service}}\t{{.State}}\t{{.Status}}'
    for svc in db redis rrdcached app dispatcher nginx; do
        local st; st="$("${COMPOSE[@]}" ps --format '{{.State}}' "$svc" 2>/dev/null || true)"
        [[ "$st" == "running" ]] || { c_err "  $svc: tidak berjalan"; fail=1; }
    done
    echo "== Database"
    if "${COMPOSE[@]}" exec -T db sh -c 'mariadb-admin ping -u"$MARIADB_USER" -p"$MARIADB_PASSWORD"' >/dev/null 2>&1; then c_ok "  MariaDB OK"; else c_err "  MariaDB gagal"; fail=1; fi
    echo "== Redis"
    if [[ "$("${COMPOSE[@]}" exec -T redis redis-cli ping 2>/dev/null | tr -d '\r')" == "PONG" ]]; then c_ok "  Redis OK"; else c_err "  Redis gagal"; fail=1; fi
    echo "== Web"
    local code; code="$(curl -sk -o /dev/null -w '%{http_code}' "https://127.0.0.1:$(env_get HTTPS_PORT)/login" || true)"
    if [[ "$code" == "200" ]]; then c_ok "  HTTPS /login: 200"; else c_err "  HTTPS /login: $code"; fail=1; fi
    echo "== Poller"
    if lnms config:get rrdcached >/dev/null 2>&1; then c_ok "  lnms OK"; else c_err "  lnms gagal"; fail=1; fi
    echo "== Disk"
    df -h "$DOCKER_DIR" | tail -1
    (( fail == 0 )) && c_ok "SEHAT" || { c_err "ADA MASALAH"; return 1; }
}

usage() {
    cat <<EOF
ANTAREJA NMS — skrip operasional

  install            Pasang pertama kali (buat .env, sertifikat TLS, build, start, buat admin)
  start | stop | restart | status
  logs [service]     Lihat log (app, dispatcher, db, nginx, ...)
  update             Backup -> git pull -> build -> restart -> health
  backup             Backup database + data/RRD ke docker/backups
  backups            Daftar backup (dari web & dari perintah backup)
  restore <file>     Pulihkan dari file backup (path, atau nama backup web)
  health             Cek kesehatan semua komponen
  admin              Buat user admin baru
  setup-token        Tampilkan alamat & kode Setup Awal (selama admin pertama belum dibuat)
  lnms <args>        Jalankan perintah lnms (mis. ./antareja.sh lnms device:add 10.0.0.1)
EOF
}

main() {
    local cmd="${1:-}"; shift || true
    case "$cmd" in
        install) cmd_install ;;
        start) cmd_start ;;
        stop) cmd_stop ;;
        restart) cmd_restart ;;
        status) cmd_status ;;
        logs) cmd_logs "$@" ;;
        update) cmd_update ;;
        backup) cmd_backup ;;
        backups) cmd_backups ;;
        restore) cmd_restore "$@" ;;
        health) cmd_health ;;
        admin) require_env; cmd_admin ;;
        setup-token) require_env; cmd_setup_token ;;
        lnms) require_env; lnms "$@" ;;
        *) usage; [[ -z "$cmd" ]] && exit 0 || exit 1 ;;
    esac
}

main "$@"
