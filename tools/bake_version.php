<?php
/**
 * Run this locally (where .git exists) right before zipping a release for
 * deploy. It resolves the current git tag and writes it to VERSION at the
 * repo root, which includes/version.php reads at runtime in production.
 *
 * Usage: php tools/bake_version.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$out  = [];
$code = 0;
exec('git -C ' . escapeshellarg($root) . ' describe --tags --always --dirty 2>&1', $out, $code);

if ($code !== 0 || empty($out)) {
    fwrite(STDERR, "Could not resolve git version:\n" . implode("\n", $out) . "\n");
    exit(1);
}

$version = trim($out[0]);
file_put_contents($root . '/VERSION', $version . "\n");
echo "Baked VERSION file: {$version}\n";
echo "Don't forget to include VERSION in the zip you upload.\n";
