<?php
/**
 * tools/generate_font.php
 *
 * Converts a TTF font using the installed tc-lib-pdf-font converter.
 *
 * Usage:
 *   php tools/generate_font.php /path/to/Bodoni72-Regular.ttf bodoni72
 *   php tools/generate_font.php /path/to/Bodoni72-Bold.ttf bodoni72b
 *   php tools/generate_font.php /path/to/Bodoni72-Italic.ttf bodoni72i
 *   php tools/generate_font.php /path/to/Bodoni72-BoldItalic.ttf bodoni72bi
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

$converter = $projectRoot .
    '/vendor/tecnickcom/tc-lib-pdf-font/util/convert.php';

if ($argc < 3) {
    fwrite(
        STDERR,
        "Usage: php generate_font.php <path-to-ttf> <output-font-key>\n"
    );
    exit(1);
}

$ttfPath = realpath($argv[1]);
$fontKey = preg_replace(
    '/[^a-z0-9]/',
    '',
    strtolower($argv[2])
);

if (!$ttfPath || !is_file($ttfPath)) {
    fwrite(
        STDERR,
        "TTF file not found: {$argv[1]}\n"
    );
    exit(1);
}

if ($fontKey === '') {
    fwrite(
        STDERR,
        "Invalid font key.\n"
    );
    exit(1);
}

if (!is_file($converter)) {
    fwrite(
        STDERR,
        "tc-lib-pdf-font converter not found:\n{$converter}\n"
    );
    exit(1);
}

$outDir = $projectRoot . '/storage/fonts';

if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0755, true) && !is_dir($outDir)) {
        fwrite(
            STDERR,
            "Unable to create font directory:\n{$outDir}\n"
        );
        exit(1);
    }
}

if (!is_writable($outDir)) {
    fwrite(
        STDERR,
        "Font directory is not writable:\n{$outDir}\n"
    );
    exit(1);
}

/*
 * tc-lib-pdf-font generates files based on the font's internal
 * font name, not necessarily our application key.
 *
 * Therefore we:
 *   1. Run the official converter.
 *   2. Detect the newly generated files.
 *   3. Rename them to our application font key.
 */

$before = [];

foreach (glob($outDir . '/*') ?: [] as $file) {
    if (is_file($file)) {
        $before[basename($file)] = true;
    }
}

$command = sprintf(
    '%s %s --outpath=%s --type=TrueTypeUnicode --flags=32 --encoding_id=1 --fonts=%s 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($converter),
    escapeshellarg($outDir),
    escapeshellarg($ttfPath)
);

echo "Converting font...\n";
echo "Input : {$ttfPath}\n";
echo "Output: {$outDir}\n\n";

exec($command, $output, $exitCode);

foreach ($output as $line) {
    echo $line . PHP_EOL;
}

if ($exitCode !== 0) {
    fwrite(
        STDERR,
        "\nFont conversion failed. Exit code: {$exitCode}\n"
    );
    exit($exitCode ?: 1);
}

/*
 * Find newly generated files.
 */
$generated = [];

foreach (glob($outDir . '/*') ?: [] as $file) {
    if (!is_file($file)) {
        continue;
    }

    $base = basename($file);

    if (!isset($before[$base])) {
        $generated[] = $file;
    }
}

if (!$generated) {
    fwrite(
        STDERR,
        "\nConversion completed but no new font files were detected.\n"
    );
    exit(1);
}

echo "\nGenerated files:\n";

foreach ($generated as $file) {
    echo "  " . basename($file) . PHP_EOL;
}

/*
 * Rename generated files.
 *
 * tc-lib-pdf-font normally produces:
 *
 *   something.json
 *   something.z
 *   something.ctg.z
 *
 * We map the generated basename to our application font key.
 */
$extensions = [
    '.json',
    '.z',
    '.ctg.z',
];

$renamed = [];

foreach ($generated as $file) {

    $filename = basename($file);

    foreach ($extensions as $extension) {

        if (!str_ends_with($filename, $extension)) {
            continue;
        }

        $destination = $outDir . '/' . $fontKey . $extension;

        if (realpath($file) === realpath($destination)) {
            $renamed[] = $destination;
            continue;
        }

        if (file_exists($destination)) {
            unlink($destination);
        }

        if (!rename($file, $destination)) {
            fwrite(
                STDERR,
                "Unable to rename:\n" .
                "  {$file}\n" .
                "to\n" .
                "  {$destination}\n"
            );
            exit(1);
        }

        $renamed[] = $destination;
    }
}

echo "\nFont installed successfully:\n";

foreach ($renamed as $file) {
    echo "  storage/fonts/" . basename($file) . PHP_EOL;
}

echo "\nFont key: {$fontKey}\n";