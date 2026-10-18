#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php -d ffi.enable=true -l src/Licensebox.php
composer validate --no-check-publish
composer install --no-interaction
php -d ffi.enable=true tests/binding_test.php
mkdir -p build
composer archive --format=tar --dir=build --file=licensebox
mkdir -p build/consumer
tar -xf build/licensebox.tar -C build/consumer
php -d ffi.enable=true -r 'require "build/consumer/src/Licensebox.php"; if (\Licensebox\Client::VERSION !== "0.2.0" || !str_starts_with(\Licensebox\Client::nativeBinding("demo"),"m1:")) exit(1);'
