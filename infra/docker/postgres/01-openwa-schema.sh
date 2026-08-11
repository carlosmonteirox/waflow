#!/bin/sh
set -eu

psql \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --set=app_user="$POSTGRES_USER" \
    --set=ON_ERROR_STOP=1 <<-'SQL'
CREATE SCHEMA IF NOT EXISTS openwa AUTHORIZATION :"app_user";
SQL

