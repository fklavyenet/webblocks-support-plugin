<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (! is_file($root.'/catalog/media.json')) {
    throw new RuntimeException('The source catalog media manifest is missing.');
}

$artifact = tempnam(sys_get_temp_dir(), 'webblocks-package-');
if ($artifact === false) {
    throw new RuntimeException('Unable to allocate a temporary artifact path.');
}

try {
    $builder = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tools/plugin.php');
    passthru($builder.' build '.escapeshellarg($artifact), $status);
    if ($status !== 0) {
        throw new RuntimeException('Unable to build the plugin package.');
    }

    $zip = new ZipArchive;
    if ($zip->open($artifact) !== true) {
        throw new RuntimeException('Unable to open the plugin package.');
    }

    try {
        if ($zip->locateName('webblocks-plugin.json') === false) {
            throw new RuntimeException('The plugin package is missing its installable manifest.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if ($entry === 'catalog' || str_starts_with($entry, 'catalog/')) {
                throw new RuntimeException('Catalog media in the plugin package: '.$entry);
            }
        }
    } finally {
        $zip->close();
    }
} finally {
    foreach ([$artifact, $artifact.'.sha256'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

echo "Plugin package excludes catalog media.\n";
