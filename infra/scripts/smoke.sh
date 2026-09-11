#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_dir="$script_dir/../.."
compose_file="${WAFLOW_COMPOSE_FILE:-$project_dir/infra/compose.yml}"
env_file="${WAFLOW_ENV_FILE:-$project_dir/infra/.env}"
project_name="${WAFLOW_SMOKE_PROJECT:-waflow-smoke}"
http_port="${WAFLOW_SMOKE_HTTP_PORT:-18080}"

case "$project_name" in
    ''|waflow|*[!a-zA-Z0-9_-]*)
        printf '%s\n' 'WAFLOW_SMOKE_PROJECT deve ser um nome isolado e nunca pode ser waflow.' >&2
        exit 2
        ;;
esac

case "$http_port" in
    ''|*[!0-9]*) printf '%s\n' 'WAFLOW_SMOKE_HTTP_PORT deve ser numérica.' >&2; exit 2 ;;
esac

[ -f "$env_file" ] || { printf 'Arquivo não encontrado: %s\n' "$env_file" >&2; exit 1; }
command -v docker >/dev/null 2>&1 || { printf '%s\n' 'Docker é obrigatório.' >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { printf '%s\n' 'curl é obrigatório.' >&2; exit 1; }

compose() {
    WAFLOW_HTTP_PORT="$http_port" docker compose -p "$project_name" --env-file "$env_file" -f "$compose_file" "$@"
}

cleanup() {
    cleanup_status="$?"
    if [ "$cleanup_status" -ne 0 ]; then
        printf '%s\n' 'Smoke reprovado; estado e logs finais:' >&2
        compose ps >&2 || true
        compose logs --no-color --tail=120 openwa migrate laravel gateway \
            | sed -E 's/owa_k1_[[:xdigit:]]{64}/[REDACTED_API_KEY]/g; s/[[:xdigit:]]{64}/[REDACTED_SECRET]/g' >&2 || true
    fi
    compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

if [ "${WAFLOW_SMOKE_BUILD:-false}" = "true" ]; then
    compose build gateway laravel openwa
fi

printf '%s\n' '1/5 Subindo stack efêmera e aguardando health checks...'
compose up -d --wait --wait-timeout 240

printf '%s\n' '2/5 Validando health público e headers de segurança...'
health="$(curl -fsS "http://127.0.0.1:$http_port/api/v1/health")"
printf '%s' "$health" | grep -Eq '"status"[[:space:]]*:[[:space:]]*"ok"' || {
    printf '%s\n' 'Health público não retornou status ok.' >&2
    exit 1
}
headers="$(curl -fsSI "http://127.0.0.1:$http_port/api/v1/health")"
for header in content-security-policy strict-transport-security x-content-type-options x-frame-options referrer-policy permissions-policy; do
    printf '%s\n' "$headers" | grep -Eiq "^$header:" || {
        printf 'Header ausente: %s\n' "$header" >&2
        exit 1
    }
done

printf '%s\n' '3/5 Validando PostgreSQL e Redis...'
compose exec -T postgres sh -lc 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null
compose exec -T redis redis-cli ping | grep -q '^PONG$'

printf '%s\n' '4/5 Validando isolamento e runtime Baileys-only...'
openwa_container="$(compose ps -q openwa)"
published_openwa_port="$(docker port "$openwa_container" 2785/tcp 2>/dev/null || true)"
if [ -n "$published_openwa_port" ]; then
    printf '%s\n' 'A porta do OpenWA não pode estar publicada.' >&2
    exit 1
fi
compose exec -T openwa node -e '
for (const name of ["whatsapp-web.js", "puppeteer", "@nestjs/testing"]) {
  try { require.resolve(name); process.exit(1); } catch {}
}
require("sqlite3");
'
openwa_master_key="$(compose exec -T openwa printenv API_MASTER_KEY)"
[ -n "$openwa_master_key" ] || { printf '%s\n' 'API_MASTER_KEY ausente no OpenWA.' >&2; exit 1; }
if compose logs --no-color openwa | grep -Fq "$openwa_master_key"; then
    printf '%s\n' 'A chave mestre do OpenWA foi exposta nos logs.' >&2
    exit 1
fi

printf '%s\n' '5/5 Verificando containers esperados...'
for service in gateway laravel worker scheduler openwa postgres redis; do
    [ "$(compose ps --status running -q "$service" | wc -l | tr -d ' ')" = "1" ] || {
        printf 'Serviço não está em execução: %s\n' "$service" >&2
        exit 1
    }
done

printf '%s\n' 'Smoke integrado aprovado; removendo stack e volumes efêmeros.'
