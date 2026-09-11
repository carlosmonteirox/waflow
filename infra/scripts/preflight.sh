#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_dir="$script_dir/../.."
env_file="${WAFLOW_ENV_FILE:-$project_dir/infra/.env}"
secrets_dir="${WAFLOW_SECRETS_DIR:-$project_dir/infra/secrets}"
failures=0

fail() {
    printf 'FALHA: %s\n' "$1" >&2
    failures=$((failures + 1))
}

value() {
    sed -n "s|^$1=||p" "$env_file" | tail -n 1
}

required() {
    required_value="$(value "$1")"
    [ -n "$required_value" ] || fail "$1 não está definido."
}

minimum_length() {
    secret_value="$(value "$1")"
    [ -z "$secret_value" ] && return 0
    secret_length="$(printf '%s' "$secret_value" | wc -c | tr -d ' ')"
    [ "$secret_length" -ge "$2" ] || fail "$1 deve ter ao menos $2 caracteres."
}

[ -f "$env_file" ] || {
    printf 'FALHA: arquivo de ambiente não encontrado: %s\n' "$env_file" >&2
    exit 1
}

docker_available=true
openssl_available=true
command -v docker >/dev/null 2>&1 || { fail 'Docker não está instalado.'; docker_available=false; }
command -v openssl >/dev/null 2>&1 || { fail 'OpenSSL não está instalado.'; openssl_available=false; }

for key in APP_KEY POSTGRES_PASSWORD OPENWA_MASTER_KEY BAILEYS_AUTH_ENCRYPTION_KEY OPENWA_WEBHOOK_SECRET INTER_WEBHOOK_SECRET; do
    required "$key"
    minimum_length "$key" 32
done

case "$(value APP_URL)" in
    https://*) ;;
    *) fail 'APP_URL deve usar HTTPS em produção.' ;;
esac

[ "$(value SESSION_SECURE_COOKIE)" = "true" ] || fail 'SESSION_SECURE_COOKIE deve ser true.'

case "$(value WAFLOW_TRUSTED_PROXIES)" in
    \*|\*\*) fail 'WAFLOW_TRUSTED_PROXIES nunca pode ser * ou **.' ;;
esac

mailer="$(value MAIL_MAILER)"
case "$mailer" in
    ''|log|array) fail 'Configure um transportador real em MAIL_MAILER.' ;;
esac
required MAIL_FROM_ADDRESS

if [ "$(value BILLING_DRIVER)" = "inter" ]; then
    [ "$(value INTER_ENVIRONMENT)" = "production" ] || fail 'INTER_ENVIRONMENT deve ser production.'
    for key in INTER_CLIENT_ID INTER_CLIENT_SECRET INTER_RECEIVER_NAME INTER_RECEIVER_CNPJ INTER_RECEIVER_AGENCY INTER_RECEIVER_ACCOUNT; do
        required "$key"
    done

    callback_url="$(value INTER_WEBHOOK_URL)"
    if [ -n "$callback_url" ]; then
        case "$callback_url" in
            https://*) ;;
            *) fail 'INTER_WEBHOOK_URL deve usar HTTPS.' ;;
        esac
    fi

    cert_path="$(value INTER_CERTIFICATE_PATH)"
    key_path="$(value INTER_PRIVATE_KEY_PATH)"
    case "$cert_path" in
        /run/secrets/*) cert_file="$secrets_dir/${cert_path##*/}" ;;
        *) cert_file=''; fail 'INTER_CERTIFICATE_PATH deve apontar para /run/secrets/.' ;;
    esac
    case "$key_path" in
        /run/secrets/*) key_file="$secrets_dir/${key_path##*/}" ;;
        *) key_file=''; fail 'INTER_PRIVATE_KEY_PATH deve apontar para /run/secrets/.' ;;
    esac

    cert_valid=false
    key_valid=false
    if [ "$openssl_available" = true ] && [ -n "$cert_file" ] && [ -r "$cert_file" ]; then
        openssl x509 -in "$cert_file" -checkend 2592000 -noout >/dev/null 2>&1 || fail 'Certificado Inter inválido ou vence em menos de 30 dias.'
        openssl x509 -in "$cert_file" -checkend 2592000 -noout >/dev/null 2>&1 && cert_valid=true
    else
        fail 'Certificado Inter não está legível em infra/secrets/.'
    fi

    if [ "$openssl_available" = true ] && [ -n "$key_file" ] && [ -r "$key_file" ]; then
        passphrase="$(value INTER_PRIVATE_KEY_PASSPHRASE)"
        if [ -n "$passphrase" ]; then
            openssl pkey -in "$key_file" -passin "pass:$passphrase" -check -noout >/dev/null 2>&1 || fail 'Chave privada Inter inválida ou passphrase incorreta.'
            openssl pkey -in "$key_file" -passin "pass:$passphrase" -check -noout >/dev/null 2>&1 && key_valid=true
        else
            openssl pkey -in "$key_file" -check -noout >/dev/null 2>&1 || fail 'Chave privada Inter inválida.'
            openssl pkey -in "$key_file" -check -noout >/dev/null 2>&1 && key_valid=true
        fi
    else
        fail 'Chave privada Inter não está legível em infra/secrets/.'
    fi

    if [ "$cert_valid" = true ] && [ "$key_valid" = true ]; then
        cert_public="$(openssl x509 -in "$cert_file" -pubkey -noout | openssl pkey -pubin -outform DER 2>/dev/null | openssl dgst -sha256)"
        if [ -n "$passphrase" ]; then
            key_public="$(openssl pkey -in "$key_file" -passin "pass:$passphrase" -pubout -outform DER 2>/dev/null | openssl dgst -sha256)"
        else
            key_public="$(openssl pkey -in "$key_file" -pubout -outform DER 2>/dev/null | openssl dgst -sha256)"
        fi
        [ "$cert_public" = "$key_public" ] || fail 'A chave privada Inter não corresponde ao certificado.'
    fi
else
    fail 'BILLING_DRIVER deve ser inter para esta implantação.'
fi

if [ "$docker_available" = true ]; then
    docker compose --env-file "$env_file" -f "$project_dir/infra/compose.yml" config --quiet || fail 'Compose de produção inválido.'
fi

if [ "$failures" -ne 0 ]; then
    printf '%s\n' "Preflight reprovado com $failures pendência(s)." >&2
    exit 1
fi

printf '%s\n' 'Preflight de produção aprovado.'
