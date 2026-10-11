#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

log() { printf '\033[1;32m==>\033[0m %s\n' "$1"; }
err() { printf '\033[1;31m!!\033[0m %s\n' "$1" >&2; exit 1; }

rand() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "${1:-24}"; }

# set_env KEY VALUE -> update/append di .env
set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        # pakai | sebagai delimiter, escape karakter spesial
        local esc
        esc=$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')
        sed -i.bak "s|^${key}=.*|${key}=${esc}|" .env && rm -f .env.bak
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

get_env() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }

# --- cek prasyarat ---
command -v docker >/dev/null 2>&1 || err "Docker belum terpasang. Install: https://docs.docker.com/engine/install/"
docker compose version >/dev/null 2>&1 || err "Docker Compose plugin (v2) belum terpasang."

# --- siapkan .env ---
if [ ! -f .env ] || [ "${FORCE_ENV:-0}" = "1" ]; then
    log "Membuat .env dari .env.production.example"
    cp .env.production.example .env

    DEFAULT_HOST=$(hostname -I 2>/dev/null | awk '{print $1}' || true)
    DEFAULT_HOST=${DEFAULT_HOST:-localhost}
    read -rp "Domain / IP server [${DEFAULT_HOST}]: " HOST
    HOST=${HOST:-$DEFAULT_HOST}
    read -rp "Pakai HTTPS di depan (reverse proxy/Cloudflare)? [y/N]: " SSL
    SCHEME=http; [[ "${SSL,,}" == "y" ]] && SCHEME=https

    set_env APP_URL "${SCHEME}://${HOST}"
    set_env DB_PASSWORD "$(rand 24)"
    set_env REDIS_PASSWORD "$(rand 24)"
    set_env AWS_SECRET_ACCESS_KEY "$(rand 32)"
    set_env AWS_URL "http://${HOST}:$(get_env MINIO_PORT)/$(get_env AWS_BUCKET)"
else
    log ".env sudah ada, dipakai apa adanya (FORCE_ENV=1 untuk membuat ulang)"
fi

# --- build & jalankan ---
log "Build image"
docker compose build

# APP_KEY di-generate sekali pakai container sementara
if [ -z "$(get_env APP_KEY)" ]; then
    log "Generate APP_KEY"
    KEY=$(docker compose run --rm --no-deps --entrypoint php app -r 'echo "base64:".base64_encode(random_bytes(32));')
    set_env APP_KEY "$KEY"
fi

log "Menjalankan container"
docker compose up -d

log "Selesai."
echo "  Aplikasi      : $(get_env APP_URL):$(get_env APP_PORT)"
echo "  MinIO console : http://<host>:$(get_env MINIO_CONSOLE_PORT)  (user: $(get_env AWS_ACCESS_KEY_ID))"
echo "  Log           : docker compose logs -f app"
echo "  Buat user awal: docker compose exec app php artisan tinker"
echo "  Update        : git pull && docker compose up -d --build"
