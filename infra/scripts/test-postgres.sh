#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_dir="$script_dir/../.."
compose_file="$project_dir/infra/compose.test.yml"

cleanup() {
    docker compose -f "$compose_file" down --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

docker compose -f "$compose_file" up --attach tests --abort-on-container-exit --exit-code-from tests
