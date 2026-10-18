# Licensebox php SDK

MIT · 2026 Vijai Kumar · https://meshing.engineer

Native application licensing for Linux. Requires the public Licensebox C v0.3.0 shared library. Set `LICENSEBOX_LIB_DIR` (or `LICENSEBOX_LIBRARY` for runtime FFI bindings), and add that directory to `LD_LIBRARY_PATH` when linking native extensions. The host library retains installation keys, verifies leases, enforces native identity and stores usage locally. Other platforms and browser runtimes are unvalidated.

The client supports lease, require/module check, local status, sign-in, logout, modules, access requests, access links, and usage recording. Stateless derive/native binding and lease verification helpers are included. Close each client explicitly in a finally/ensure block. Trusted clock, machine binding, sign-in and cancellation hooks are callable platform integration points; never source identity from end users. Callbacks must not reenter or close their client. Callback exceptions become `callback_error` after the native call returns. Native-owned JSON is always freed.

`ci/setup.sh` installs dependencies on disposable Ubuntu 24.04 CI runners only. `ci/test.sh` compiles/tests the actual binding and package consumer. `ci/adapter.sh` runs the shared protocol adapter after testing/building. Run `python3 ../protocol/conformance/run.py --adapter "$PWD/ci/adapter.sh"` with the native environment configured. All test credentials and identities are synthetic.

Build the native core using `scripts/install-native.sh`, then set `LICENSEBOX_LIB_DIR="$PWD/.native/lib"` and `LD_LIBRARY_PATH="$LICENSEBOX_LIB_DIR${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"`. Supply only administrator-provided trusted Ed25519 public keys.

```php
require 'vendor/autoload.php';
$client = new \Licensebox\Client('my-app', ['product-key' => getenv('LICENSEBOX_PUBLIC_KEY')],
    hooks: new \Licensebox\Hooks(onSignIn: fn($url, $code) => printf("%s %s\n", $url, $code)));
try { $client->requireModule('core'); /* Protected work. */ }
finally { $client->close(); }
```

PHP 8.2+ with `ext-ffi` is required. Enable `ffi.enable=true` in the application's trusted PHP configuration; do not enable native FFI for untrusted scripts. Composer's package name is `licensebox/licensebox`. `LicenseboxException::$errorCode` preserves protocol errors. Methods: `lease`, `requireModule`, `status`, `signIn`, `logout`, `modules`, `requests`, `requestAccess`, `accessLink`, `record`, `close`; static helpers: `deriveBinding`, `nativeBinding`, `verifyLease`.
