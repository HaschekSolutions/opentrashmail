#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

START_SCRIPT="$ROOT/docker/rootfs/start.sh"

# Extraire les deux fonctions réelles sans démarrer les services.
sed -n \
  -e '/^ini_quote() {/,/^}/p' \
  -e '/^_buildConfig() {/,/^}/p' \
  "$START_SCRIPT" > "$TMP_DIR/functions.sh"

source "$TMP_DIR/functions.sh"

# Valeurs de test fictives, contenant les caractères problématiques.
export NOTICE=""
unset DOMAINS URL ALLOWED_IPS TRUSTED_PROXIES
unset MAILPORT DISCARD_UNKNOWN ATTACHMENTS_MAX_SIZE MAILPORT_TLS
unset TLS_CERTIFICATE TLS_PRIVATE_KEY SMTP_HOSTNAME DATEFORMAT
unset DELETE_OLDER_THAN_DAYS WEBHOOK_URL ADMIN_ENABLED SHOW_ACCOUNT_LIST
unset SHOW_LOGS ADMIN_PASSWORD PASSWORD ADMIN

export PASSWORD='test&;# "quoted" $value \slash'
export ADMIN_PASSWORD='test&;# "quoted" $value \slash'
export ADMIN='admin@example.test'

_buildConfig > "$TMP_DIR/config.ini"

# Même parse_ini_file() que l'application, sans INI_SCANNER_RAW.
CONFIG_FILE="$TMP_DIR/config.ini" php <<'PHP'
<?php
$config = parse_ini_file(getenv('CONFIG_FILE'), true);

if ($config === false) {
    fwrite(STDERR, "ECHEC : impossible de lire config.ini\n");
    exit(1);
}

$checks = [
    'PASSWORD' => [$config['GENERAL']['PASSWORD'] ?? null, getenv('PASSWORD')],
    'ADMIN_PASSWORD' => [$config['ADMIN']['ADMIN_PASSWORD'] ?? null, getenv('ADMIN_PASSWORD')],
    'ADMIN' => [$config['ADMIN']['ADMIN'] ?? null, getenv('ADMIN')],
];

foreach ($checks as $name => [$actual, $expected]) {
    if ($actual !== $expected) {
        fwrite(STDERR, "ECHEC : $name modifié lors de la génération ou lecture INI\n");
        exit(1);
    }

    echo "OK : $name conservé à l’identique\n";
}
PHP
