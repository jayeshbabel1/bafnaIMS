<?php
/**
 * Resolves the running application version.
 *
 * Production is deployed via FTP/zip, so there is no .git directory on the
 * server and version can't be derived by shelling out to git at request
 * time. Instead, tools/bake_version.php runs `git describe --tags` locally
 * (where .git exists) right before packaging a release, and writes the
 * result into a plain VERSION file at the repo root. This function just
 * reads that file.
 *
 * Falls back to the APP_VERSION constant (config/config.php) if VERSION is
 * missing or empty, so a deploy that skips the baking step still shows a
 * sane value instead of an error.
 */
function appVersion(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $versionFile = __DIR__ . '/../VERSION';
    if (is_readable($versionFile)) {
        $v = trim((string) file_get_contents($versionFile));
        if ($v !== '') {
            return $cached = $v;
        }
    }

    return $cached = (defined('APP_VERSION') ? APP_VERSION : '0.0.0');
}
