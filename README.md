# Licensebox php SDK

MIT · 2026 Vijai Kumar · https://meshing.engineer

Native application licensing for Linux. Requires the public Licensebox C v0.5.0 shared library. Set `LICENSEBOX_LIB_DIR` (or `LICENSEBOX_LIBRARY` for runtime FFI bindings), and add that directory to `LD_LIBRARY_PATH` when linking native extensions. The host library retains installation keys, verifies leases, enforces native identity and stores usage locally. Other platforms and browser runtimes are unvalidated.

The client supports lease, require/module check, local status, sign-in, logout, modules, access requests, access links, and usage recording. Stateless derive/native binding and lease verification helpers are included. Close each client explicitly in a finally/ensure block. Trusted clock, machine binding, sign-in and cancellation hooks are callable platform integration points; never source identity from end users. Callbacks must not reenter or close their client. Callback exceptions become `callback_error` after the native call returns. Native-owned JSON is always freed.

`ci/setup.sh` installs dependencies on disposable Ubuntu 24.04 CI runners only. `ci/test.sh` compiles/tests the actual binding and package consumer. `ci/adapter.sh` runs the shared protocol adapter after testing/building. Run `python3 ../protocol/conformance/run.py --adapter "$PWD/ci/adapter.sh"` with the native environment configured. All test credentials and identities are synthetic.

Build the native core using `scripts/install-native.sh`, then set `LICENSEBOX_LIB_DIR="$PWD/.native/lib"` and `LD_LIBRARY_PATH="$LICENSEBOX_LIB_DIR${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"`. Supply only administrator-provided trusted public keys (`ed25519-mldsa65:...`).

```php
require 'vendor/autoload.php';
$client = new \Licensebox\Client('my-app', ['product-key' => getenv('LICENSEBOX_PUBLIC_KEY')],
    hooks: new \Licensebox\Hooks(onSignIn: fn($url, $code) => printf("%s %s\n", $url, $code)));
try { $client->requireModule('core'); /* Protected work. */ }
finally { $client->close(); }
```

PHP 8.2+ with `ext-ffi` is required. Enable `ffi.enable=true` in the application's trusted PHP configuration; do not enable native FFI for untrusted scripts. Composer's package name is `licensebox/licensebox`. `LicenseboxException::$errorCode` preserves protocol errors. Methods: `lease`, `requireModule`, `status`, `signIn`, `logout`, `modules`, `requests`, `requestAccess`, `accessLink`, `record`, `close`; static helpers: `deriveBinding`, `nativeBinding`, `verifyLease`.

## Optional heartbeat checkpoints

Call `$client->heartbeat("core")` with a module name to force a silent server check. It returns
`{"lease": ..., "online": true}` after contact, or `online: false` only while
an already verified offline lease remains valid. Heartbeat never activates,
transfers a seat or creates an installation key; sign in explicitly first.
Explicit refusals and removed module grants stop access immediately.

Background work remains opt-in. Schedule checkpoints in your application's own
runtime/event loop and stop protected work at the last verified lease's `expires`
time even if a renewal hangs. Use the signed `heartbeat.interval_seconds` when
present (otherwise a 300-second interval is a reasonable default); a failed
contact never extends the signed deadline. This SDK starts no heartbeat threads.

## Seats per module, floating copies and service accounts

Calls: `enrol($token)`, `checkout($module)`, `checkin(...$modules)`, and the `$enrolToken` constructor argument.

A module can have seats of its own: on a machine without its seat the lease
names it in `elsewhere`, requiring it fails with `module_denied`, and an
explicit sign-in moves the seat here. A customer can hold a pool of floating
copies of a module, named in the lease's `floating`: requiring such a module
checks a copy out (`no_free_copy` when every copy is in use), heartbeats keep
it, and checking in returns it. A crashed application's copy is freed when
the heartbeat grace passes. A server or build runner licensed as a service
account sets the one-time token an admin issued as `LICENSEBOX_ENROL_TOKEN`
or the `enrol_token` option: its first lease enrols the machine without a
browser (`invalid_token` when the token is used or expired).
