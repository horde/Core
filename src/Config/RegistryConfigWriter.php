<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config;

use RuntimeException;

/**
 * Serialize a compiled registry to disk.
 *
 * Companion to {@see RegistryConfigCompiler}. The compiler produces
 * the array. The writer serializes it as a PHP file that
 * {@see RegistryConfigLoader} can `require` back at load time.
 *
 * ## On-disk shape
 *
 * The written file is a minimal `<?php return [...];`. No header
 * markers, no admin-facing content. `RegistryConfigLoader::load()`
 * evaluates it via `require` and expects the top-level `default` key
 * plus one key per compiled vhost. Human-friendly formatting is
 * intentionally not a goal: this is a machine artifact regenerated
 * from source files, and admins who want to inspect the registry
 * should read the source files, not the compiled cache.
 *
 * ## Atomicity
 *
 * The write is atomic against concurrent readers. The writer first
 * emits to a temp file in the same directory as the destination,
 * then renames it into place. A `require` running while the writer
 * is mid-write sees either the previous file (if any) or the new
 * one. Never a partial file. `rename` is atomic on POSIX
 * filesystems when source and destination share a mount point,
 * which is why the temp file lives next to the destination rather
 * than in `/tmp`.
 *
 * ## Opcache
 *
 * If opcache is loaded, the writer invalidates the destination path
 * so subsequent `require` calls see the new file rather than the
 * previously cached bytecode. Without this, deployments running
 * `opcache.validate_timestamps=0` (or a coarse `revalidate_freq`)
 * would keep serving stale compiled registries until the process
 * cycled.
 *
 * ## Permissions
 *
 * The writer creates missing parent directories at 0o755. It does
 * not chown, chgrp or run privilege escalation. That is the
 * caller's responsibility. The intended caller (installer,
 * upgrader, `hordectl` command) already runs with whatever user
 * needs to own the compiled file at rest.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryConfigWriter
{
    /**
     * Write a compiled registry to disk atomically.
     *
     * @param array<string, array<string, array<string, mixed>>> $compiled
     *        The two-level structure produced by
     *        {@see RegistryConfigCompiler::compile()}.
     * @param string $path Destination path (usually
     *                     `{HORDE_VAR}/cache/compiled/registry.php`).
     * @throws RuntimeException If the destination directory cannot
     *                          be created, if serialization fails,
     *                          or if the atomic install fails.
     */
    public function write(array $compiled, string $path): void
    {
        $dir = dirname($path);
        $this->ensureDirectory($dir);

        $code = "<?php\n\nreturn " . var_export($compiled, true) . ";\n";

        // Temp file in the destination directory so `rename` stays
        // atomic. tempnam() picks a unique name. We then take
        // ownership of the fd via file_put_contents (tempnam creates
        // the file with 0o600, which is fine for a private artifact).
        //
        // tempnam has a silent fallback: if $dir isn't writable it
        // uses the system temp dir instead. Detect that by checking
        // the returned path's directory. Falling back would break
        // atomicity (rename across mount points isn't atomic) and
        // hide the real "destination isn't writable" failure.
        $tmp = @tempnam($dir, '.compiled-registry-');
        if ($tmp === false || dirname($tmp) !== $dir) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            throw new RuntimeException(
                "Cannot create temp file in destination directory: {$dir}"
            );
        }

        if (file_put_contents($tmp, $code) === false) {
            @unlink($tmp);
            throw new RuntimeException(
                "Cannot write compiled registry payload to {$tmp}"
            );
        }

        // Match the parent-directory permissions (minus write for
        // group/other, since the file is machine-owned). Explicit
        // chmod because tempnam's 0o600 default is too restrictive
        // for a file the web-server user needs to read.
        @chmod($tmp, 0o644);

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(
                "Cannot atomically install compiled registry to {$path}"
            );
        }

        $this->invalidateOpcache($path);
    }

    /**
     * Create the destination directory if it doesn't exist.
     *
     * The compiled-registry path canonically sits under
     * `var/cache/compiled/`, which may not exist on a fresh install
     * that hasn't yet run the compiler. Create it eagerly rather
     * than error out. The caller almost never wants "directory
     * missing" to be a hard failure mode.
     */
    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        // The race between the is_dir check and the mkdir call is
        // benign: mkdir returns false with the EEXIST errno, and the
        // second is_dir catches it. Only genuine failure to create
        // the tree is reported.
        if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(
                "Cannot create compiled-registry directory: {$dir}"
            );
        }
    }

    /**
     * Invalidate the opcache entry for the newly-written file.
     *
     * No-op when opcache isn't loaded (CLI without opcache, testing,
     * older PHP builds). Errors from opcache itself are not fatal:
     * the file is already on disk and will be re-cached on next
     * request in the worst case.
     */
    private function invalidateOpcache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
