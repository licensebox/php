#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
exec php -d ffi.enable=true tests/adapter.php
