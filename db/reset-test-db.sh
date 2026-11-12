#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
DC="docker compose -f docker/docker-compose.yml"

$DC exec -T db mariadb -uroot -proot \
  -e "DROP DATABASE IF EXISTS vacation_test; CREATE DATABASE vacation_test"
$DC exec -T db mariadb -uroot -proot vacation_test < db/schema.sql
