#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
env_file="$script_dir/../.env"
example_file="$script_dir/../.env.example"

command -v openssl >/dev/null 2>&1 || {
    printf '%s\n' "OpenSSL is required to generate Waflow secrets." >&2
    exit 1
}

app_key="base64:$(openssl rand -base64 32)"
postgres_password="$(openssl rand -hex 24)"
openwa_master_key="$(openssl rand -hex 32)"
baileys_auth_encryption_key="$(openssl rand -hex 32)"
openwa_webhook_secret="$(openssl rand -hex 32)"
temp_file="$(mktemp "$script_dir/../.env.tmp.XXXXXX")"

trap 'rm -f "$temp_file"' EXIT

if [ -e "$env_file" ]; then
    cp "$env_file" "$temp_file"

    ensure_secret() {
        key="$1"
        value="$2"
        current="$(sed -n "s|^${key}=||p" "$temp_file" | head -n 1)"
        [ -n "$current" ] && return 0

        next_file="$(mktemp "$script_dir/../.env.next.XXXXXX")"
        if grep -q "^${key}=" "$temp_file"; then
            sed "s|^${key}=.*|${key}=${value}|" "$temp_file" > "$next_file"
        else
            cp "$temp_file" "$next_file"
            printf '\n%s=%s\n' "$key" "$value" >> "$next_file"
        fi
        mv "$next_file" "$temp_file"
    }

    ensure_secret APP_KEY "$app_key"
    ensure_secret POSTGRES_PASSWORD "$postgres_password"
    ensure_secret OPENWA_MASTER_KEY "$openwa_master_key"
    ensure_secret BAILEYS_AUTH_ENCRYPTION_KEY "$baileys_auth_encryption_key"
    ensure_secret OPENWA_WEBHOOK_SECRET "$openwa_webhook_secret"

    chmod 600 "$temp_file"
    mv "$temp_file" "$env_file"
    trap - EXIT
    printf '%s\n' "Updated missing Waflow secrets in $env_file"
    exit 0
fi

sed \
    -e "s|^APP_KEY=.*|APP_KEY=$app_key|" \
    -e "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=$postgres_password|" \
    -e "s|^OPENWA_MASTER_KEY=.*|OPENWA_MASTER_KEY=$openwa_master_key|" \
    -e "s|^BAILEYS_AUTH_ENCRYPTION_KEY=.*|BAILEYS_AUTH_ENCRYPTION_KEY=$baileys_auth_encryption_key|" \
    -e "s|^OPENWA_WEBHOOK_SECRET=.*|OPENWA_WEBHOOK_SECRET=$openwa_webhook_secret|" \
    "$example_file" > "$temp_file"

chmod 600 "$temp_file"
mv "$temp_file" "$env_file"
trap - EXIT

printf '%s\n' "Created $env_file with generated local secrets."
