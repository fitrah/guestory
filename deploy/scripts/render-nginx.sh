#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" = "" ]; then
  cat >&2 <<'USAGE'
Usage:
  deploy/scripts/render-nginx.sh <domain> [app_root] [php_fpm_sock]

Example:
  deploy/scripts/render-nginx.sh guestory.example.com /var/www/guestory /run/php/php8.3-fpm.sock
USAGE
  exit 64
fi

domain="$1"
app_root="${2:-/var/www/guestory}"
php_fpm_sock="${3:-/run/php/php8.3-fpm.sock}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
template="${script_dir}/../nginx/guestory.conf.example"

sed \
  -e "s#guestory.example.com#${domain}#g" \
  -e "s#/var/www/guestory#${app_root}#g" \
  -e "s#/run/php/php8.3-fpm.sock#${php_fpm_sock}#g" \
  "${template}"
