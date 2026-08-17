# Deploying to Docker / Kubernetes

Build the compiled scripts in a build stage, `COPY` the application and the compiled scripts into
the runtime image, and run as a non-root user with a read-only root filesystem.
[`examples/docker/Dockerfile`](../examples/docker/Dockerfile) is the full, buildable version — its
comments cover the two non-obvious parts (installing `composer`/`unzip`, since `php:8.3-cli` ships
neither; and why the compiled scripts need a *second* `COPY` into the runtime stage). It's built and
run (`--read-only`, `tmpDir` mounted as tmpfs, non-root) in CI against this repository's own working
tree, not the published package; run `bash tests/docker-check.sh` from the repo root to reproduce it
yourself.

The app's own runtime bootstrap must resolve `compileDir` to the same literal path the Dockerfile
compiles to:

```php
$meta = AppMeta::fromAppDir(dirname(__DIR__), 'prod', '/app/var/di/prod');
```

`APP_TMP_DIR` doesn't fail this loudly at compile time — a `tmpDir` that doesn't exist yet only
surfaces once something tries to write to it, silently, in `sys_get_temp_dir()` (see the README) —
but resolve it to the runtime path for the same reason.

`APP_COMPILE_DIR`/`APP_TMP_DIR` override the whole directory, not just the `appDir` they're derived
from, so they ignore `context` entirely. One override can only ever serve one context: baking
`prod-cli` and `prod-html` into the same image means compiling both to their conventional,
context-suffixed paths (`{appDir}/var/di/prod-cli`, `{appDir}/var/di/prod-html`) and `COPY`-ing the
whole `appDir`, rather than pointing `APP_COMPILE_DIR` at one shared path.

In Kubernetes, mount `tmpDir` as an `emptyDir` (`medium: Memory` for tmpfs) and leave everything else
read-only:

```yaml
securityContext:
  readOnlyRootFilesystem: true
  runAsNonRoot: true
volumeMounts:
  - name: tmp
    mountPath: /app/var/tmp
volumes:
  - name: tmp
    emptyDir: {}
```

Ray.Compiler writes two housekeeping files into `compileDir` alongside the compiled scripts:
`compile.lock` (held only during the compile) and `_bindings.log` (a human-readable dump of the
binding graph, including the compile-time `compileDir` path). Both get baked into the image along
with the rest of `compileDir` — neither is meant to hold secrets, but they're not scripts
`BakedPathGuard` scans, either.
