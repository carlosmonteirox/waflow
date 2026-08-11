#!/bin/sh
set -eu

base_url="${WAFLOW_E2E_BASE_URL:-http://localhost:8080}"
api_key="${WAFLOW_E2E_API_KEY:-}"
instance_uuid="${WAFLOW_E2E_INSTANCE_UUID:-}"
recipient="${WAFLOW_E2E_TO:-}"
compose_file="${WAFLOW_COMPOSE_FILE:-infra/compose.yml}"
env_file="${WAFLOW_ENV_FILE:-infra/.env}"

for command_name in curl jq docker; do
    command -v "$command_name" >/dev/null 2>&1 || {
        printf '%s\n' "Dependência ausente: $command_name" >&2
        exit 1
    }
done

if [ -z "$api_key" ] || [ -z "$instance_uuid" ] || [ -z "$recipient" ]; then
    printf '%s\n' 'Defina WAFLOW_E2E_API_KEY, WAFLOW_E2E_INSTANCE_UUID e WAFLOW_E2E_TO.' >&2
    printf '%s\n' 'O teste envia uma mensagem real ao destino informado e não cria contas automaticamente.' >&2
    exit 2
fi

run_dir="$(mktemp -d "${TMPDIR:-/tmp}/waflow-e2e.XXXXXX")"
trap 'rm -rf "$run_dir"' EXIT INT TERM

compose() {
    docker compose --env-file "$env_file" -f "$compose_file" "$@"
}

api() {
    curl -fsS -H "Authorization: Bearer $api_key" -H 'Accept: application/json' "$@"
}

printf '%s\n' '1/6 Verificando Laravel, PostgreSQL e OpenWA...'
curl -fsS "$base_url/api/v1/health" | jq -e '.status == "ok" and .checks.database.ok == true and .checks.openwa.ok == true' >/dev/null

printf '%s\n' '2/6 Verificando Redis e PostgreSQL dentro da stack...'
compose exec -T redis redis-cli ping | grep -q '^PONG$'
compose exec -T postgres pg_isready -U "${POSTGRES_USER:-waflow}" -d "${POSTGRES_DB:-waflow}" >/dev/null

printf '%s\n' '3/6 Verificando workspace e instância conectada...'
api "$base_url/api/v1/workspace" | jq -e '.data.id != null' >/dev/null
api "$base_url/api/v1/instances/$instance_uuid/status" | jq -e '.data.status == "connected"' >/dev/null

idempotency_key="e2e-live-$(date +%s)-$$"
message_text="Waflow E2E real $idempotency_key"
jq -n \
    --arg instance_uuid "$instance_uuid" \
    --arg recipient "$recipient" \
    --arg text "$message_text" \
    '{instance_uuid:$instance_uuid,to:$recipient,type:"text",text:$text}' > "$run_dir/payload.json"

printf '%s\n' '4/6 Disparando oito requisições concorrentes com a mesma chave idempotente...'
index=1
while [ "$index" -le 8 ]; do
    (
        curl -sS -o "$run_dir/response-$index.json" -w '%{http_code}' \
            -H "Authorization: Bearer $api_key" \
            -H 'Accept: application/json' \
            -H 'Content-Type: application/json' \
            -H "Idempotency-Key: $idempotency_key" \
            --data-binary "@$run_dir/payload.json" \
            "$base_url/api/v1/messages" > "$run_dir/status-$index"
    ) &
    index=$((index + 1))
done
wait

created_count="$(awk '$0 == "202" { count++ } END { print count + 0 }' "$run_dir"/status-*)"
replay_count="$(awk '$0 == "200" { count++ } END { print count + 0 }' "$run_dir"/status-*)"
if [ "$created_count" -ne 1 ] || [ "$replay_count" -ne 7 ]; then
    printf '%s\n' "Concorrência inesperada: criadas=$created_count replays=$replay_count" >&2
    exit 1
fi

message_uuid=""
index=1
while [ "$index" -le 8 ] && [ -z "$message_uuid" ]; do
    message_uuid="$(jq -r '.data.uuid // empty' "$run_dir/response-$index.json")"
    index=$((index + 1))
done
[ -n "$message_uuid" ] || { printf '%s\n' 'A API não retornou o UUID da mensagem.' >&2; exit 1; }

printf '%s\n' '5/6 Aguardando o worker Redis entregar a mensagem pelo OpenWA...'
attempt=1
message_status="queued"
while [ "$attempt" -le 30 ]; do
    message_status="$(api "$base_url/api/v1/messages/$message_uuid" | jq -r '.data.status')"
    case "$message_status" in
        sent|delivered|read) break ;;
        failed) printf '%s\n' 'A entrega real falhou.' >&2; exit 1 ;;
    esac
    sleep 2
    attempt=$((attempt + 1))
done

case "$message_status" in
    sent|delivered|read) ;;
    *) printf '%s\n' "Timeout aguardando entrega; status=$message_status" >&2; exit 1 ;;
esac

printf '%s\n' '6/6 Confirmando uma única gravação no PostgreSQL...'
api --get --data-urlencode 'direction=outbound' --data-urlencode "search=$recipient" --data-urlencode 'per_page=100' "$base_url/api/v1/messages" \
    | jq -e --arg key "$idempotency_key" '[.data[] | select(.idempotency_key == $key)] | length == 1' >/dev/null

printf '%s\n' "E2E concluído: Laravel -> PostgreSQL/Redis -> worker -> OpenWA -> WhatsApp ($message_status)."
