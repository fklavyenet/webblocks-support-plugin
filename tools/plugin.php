<?php

declare(strict_types=1);

/*
 * Build, validate and publish the plugin artifact.
 *
 * The publish contract — multipart POST, bearer token, `metadata`/`artifact`/`sha256`
 * fields — matches `cms-redirect-manager-plugin`, which is the working reference for
 * what plugins.webblocksui.com accepts. Publishing is operator-owned: it sends a
 * real artifact to a public catalog and cannot be taken back, so it never runs as a
 * side effect of building.
 *
 * Nothing here ever prints the token, and the response is redacted before display
 * in case the API echoes an Authorization header back in an error.
 */

$root = dirname(__DIR__);
$command = $argv[1] ?? 'build';
$dryRun = in_array('--dry-run', $argv, true);

$manifestPath = $root . '/webblocks-plugin.json';

if (! is_file($manifestPath)) {
    fwrite(STDERR, "Missing webblocks-plugin.json\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest)) {
    fwrite(STDERR, "webblocks-plugin.json is not valid JSON\n");
    exit(1);
}

$errors = [];

foreach (['handle', 'label', 'version', 'provider', 'required_cms_version'] as $required) {
    if (! isset($manifest[$required]) || trim((string) $manifest[$required]) === '') {
        $errors[] = "Manifest is missing {$required}";
    }
}

$handle = (string) ($manifest['handle'] ?? '');

if ($handle !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $handle) !== 1) {
    $errors[] = "Handle [{$handle}] is not kebab-case";
}

$version = (string) ($manifest['version'] ?? '');

if ($version !== '' && preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
    $errors[] = "Version [{$version}] is not semver";
}

foreach (($manifest['routes'] ?? []) as $kind => $relative) {
    if (is_string($relative) && ! is_file($root . '/' . $relative)) {
        $errors[] = "Declared {$kind} route file [{$relative}] does not exist";
    }
}

// The provider is declared, not loaded: the CMS host owns the classes it
// extends, and this tool runs without that host in scope.
$providerRelative = 'src/' . str_replace(
    '\\',
    '/',
    preg_replace('/^WebBlocks\\\\Support\\\\/', '', (string) ($manifest['provider'] ?? ''))
) . '.php';

if (! is_file($root . '/' . $providerRelative)) {
    $errors[] = "Declared provider file [{$providerRelative}] does not exist";
}

/*
 * Declared block views must resolve to a file.
 *
 * The CMS treats a declared view that does not exist as an absence and falls back
 * to its directory convention, which is the right runtime behaviour — a bad
 * declaration degrades instead of throwing mid-render — but it also means a typo
 * here is invisible at runtime. Catching it at build time is the only place it
 * shows up.
 */
foreach (($manifest['block_types'] ?? []) as $blockType) {
    if (! is_array($blockType)) {
        continue;
    }

    foreach (['admin_view', 'public_view'] as $key) {
        $view = $blockType[$key] ?? null;

        if (! is_string($view) || $view === '') {
            continue;
        }

        [$namespace, $name] = array_pad(explode('::', $view, 2), 2, '');

        if ($namespace !== $handle) {
            $errors[] = "Block view [{$view}] must use the plugin's own view namespace";

            continue;
        }

        $path = 'resources/views/' . str_replace('.', '/', $name) . '.blade.php';

        if (! is_file($root . '/' . $path)) {
            $errors[] = "Declared block view [{$view}] has no file at [{$path}]";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

if ($command === 'validate') {
    fwrite(STDOUT, "Manifest is valid: {$handle} {$version}\n");
    exit(0);
}

if (! in_array($command, ['build', 'publish'], true)) {
    fwrite(STDERR, "Unknown command [{$command}]. Use build, validate or publish.\n");
    exit(1);
}

$output = $root . '/build/' . $handle . '-' . $version . '.zip';

if ($command === 'build' && isset($argv[2]) && ! str_starts_with($argv[2], '--')) {
    $output = $argv[2];
}

if (! is_dir(dirname($output)) && ! mkdir(dirname($output), 0755, true) && ! is_dir(dirname($output))) {
    fwrite(STDERR, 'Unable to create ' . dirname($output) . "\n");
    exit(1);
}

if (is_file($output)) {
    unlink($output);
}

$zip = new ZipArchive;

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create {$output}\n");
    exit(1);
}

$excludedRoots = ['build', 'tools', 'tests', 'docs', 'vendor'];
$excludedFiles = ['composer.json', 'composer.lock', 'AGENTS.md'];
$added = 0;

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $relative = str_replace('\\', '/', str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname()));

    if (str_starts_with($relative, '.') || str_contains($relative, '/.')) {
        continue;
    }

    if (in_array($relative, $excludedFiles, true)) {
        continue;
    }

    if (in_array(explode('/', $relative)[0], $excludedRoots, true)) {
        continue;
    }

    $zip->addFile($file->getPathname(), $relative);
    $added++;
}

$zip->close();

$checksum = hash_file('sha256', $output);
file_put_contents($output . '.sha256', $checksum . PHP_EOL);

fwrite(STDOUT, "Artifact: {$output}\n");
fwrite(STDOUT, "Files: {$added}\n");
fwrite(STDOUT, "SHA-256: {$checksum}\n");

if ($command !== 'publish') {
    exit(0);
}

/*
 * Publish.
 *
 * Reached only through the explicit `publish` command — building never publishes as
 * a side effect, because an artifact sent to a public catalog cannot be recalled.
 */

/**
 * Read the publish environment.
 *
 * The `.env` file supplies it and is gitignored; a real environment variable wins,
 * so CI can supply the token without a file. Only the six known keys are read —
 * anything else in the file is ignored rather than absorbed.
 *
 * @return array<string, string>
 */
$readEnv = static function (string $root, string $handle, string $version): array {
    $values = [
        'WEBBLOCKS_PLUGINS_URL' => 'https://plugins.webblocksui.com/api/plugins/publish',
        'WEBBLOCKS_PLUGINS_TOKEN' => '',
        'WEBBLOCKS_PLUGINS_HANDLE' => $handle,
        'WEBBLOCKS_PLUGINS_VERSION' => $version,
        'WEBBLOCKS_PLUGINS_CHANNEL' => 'stable',
        'WEBBLOCKS_PLUGINS_PRODUCT' => 'webblocks-cms',
    ];

    $envPath = $root . '/.env';

    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if (array_key_exists($key, $values)) {
                $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }

    foreach (array_keys($values) as $key) {
        $fromEnvironment = getenv($key);

        if (is_string($fromEnvironment) && $fromEnvironment !== '') {
            $values[$key] = $fromEnvironment;
        }
    }

    return $values;
};

$env = $readEnv($root, $handle, $version);
$token = $env['WEBBLOCKS_PLUGINS_TOKEN'];

if ($token === '') {
    fwrite(STDERR, "Missing WEBBLOCKS_PLUGINS_TOKEN. Set it in .env or the environment.\n");
    exit(1);
}

/*
 * The manifest is the single source of truth for handle and version, so neither has
 * to appear in .env at all — they default from it above. A stale copy that disagrees
 * would publish one version's metadata against another version's ZIP, which the
 * catalog has no way to detect, so a copy that is present and wrong stops the
 * release rather than being quietly ignored.
 */
foreach ([
    'WEBBLOCKS_PLUGINS_HANDLE' => $handle,
    'WEBBLOCKS_PLUGINS_VERSION' => $version,
] as $key => $fromManifest) {
    if ($env[$key] !== $fromManifest) {
        fwrite(STDERR, sprintf(
            "%s in .env is [%s] but the manifest says [%s].\nRemove the line — it is optional now and read from the manifest.\n",
            $key,
            $env[$key],
            $fromManifest,
        ));
        exit(1);
    }
}

$releaseNotesPath = $root . '/CHANGELOG.md';
$releaseNotes = '';

if (is_file($releaseNotesPath)) {
    // The notes for this version are the CHANGELOG section headed by it, so the
    // catalog listing and the repository cannot drift apart.
    if (preg_match('/^## ' . preg_quote($version, '/') . '(?:\s+-[^\n]*)?\s*\n(.*?)(?=\n## |\z)/ms', (string) file_get_contents($releaseNotesPath), $section) === 1) {
        $releaseNotes = trim($section[1]);
    }
}

if ($command === 'publish' && $releaseNotes === '') {
    fwrite(STDERR, "CHANGELOG.md has no release notes section for {$version}. Publishing stopped.\n");
    exit(1);
}

/*
 * The one-line summary is derived from the notes rather than written here. Held
 * as a literal it described whichever release it was last edited for, and stayed
 * that way through every release after — which is exactly the drift the notes
 * themselves are read from the CHANGELOG to avoid.
 */
$releaseSummary = (static function (string $notes) use ($handle, $version): string {
    if (preg_match('/^-\s+(.*)$/m', $notes, $firstBullet) !== 1) {
        return $handle . ' ' . $version;
    }

    // Markdown emphasis and inline code read as noise in a plain-text listing.
    $summary = trim(preg_replace('/[*`]/', '', $firstBullet[1]) ?? '');

    // The bullets lead with the symptom in a bold sentence of its own, which is
    // the summary; without one, the whole bullet is cut to a readable length.
    if (preg_match('/^(.{20,200}?[.!?])\s/u', $summary, $sentence) === 1) {
        return $sentence[1];
    }

    return mb_strlen($summary) > 200 ? mb_substr($summary, 0, 197) . '...' : $summary;
})($releaseNotes);

$metadata = [
    'handle' => $handle,
    'name' => (string) $manifest['label'],
    'summary' => 'Connect WebBlocks CMS to a compatible support provider and manage tickets from the admin panel.',
    'description' => (string) $manifest['description'],
    'vendor_name' => 'WebBlocks',
    'license_name' => 'MIT',
    'status' => 'listed',
    'is_first_party' => true,
    'version' => $version,
    'channel' => $env['WEBBLOCKS_PLUGINS_CHANNEL'],
    'release' => [
        'status' => 'published',
        'summary' => $releaseSummary,
        'release_notes' => $releaseNotes,
        'details_url' => "https://github.com/fklavyenet/webblocks-support-plugin/blob/v{$version}/CHANGELOG.md",
    ],
    'compatibility' => [
        'product' => $env['WEBBLOCKS_PLUGINS_PRODUCT'],
        'version_constraint' => (string) $manifest['required_cms_version'],
        'php_constraint' => '^8.4',
        'laravel_constraint' => '^13.0',
    ],
];

$endpoint = rtrim($env['WEBBLOCKS_PLUGINS_URL'], '/');
$requestUrl = $dryRun
    ? $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . 'dry_run=1'
    : $endpoint;

fwrite(STDOUT, "\nPublishing plugin artifact...\n");
fwrite(STDOUT, "Endpoint: {$endpoint}\n");
fwrite(STDOUT, "Handle: {$handle}\n");
fwrite(STDOUT, "Version: {$version}\n");
fwrite(STDOUT, "Channel: {$metadata['channel']}\n");
fwrite(STDOUT, "Product: {$metadata['compatibility']['product']}\n");
fwrite(STDOUT, "Requires CMS: {$metadata['compatibility']['version_constraint']}\n");
fwrite(STDOUT, 'Dry run: ' . ($dryRun ? 'yes' : 'no') . "\n");
fwrite(STDOUT, "Bearer token: ***\n");

$curl = curl_init($requestUrl);

if ($curl === false) {
    fwrite(STDERR, "Unable to initialize cURL.\n");
    exit(1);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    CURLOPT_POSTFIELDS => [
        'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        'artifact' => new CURLFile($output, 'application/zip', basename($output)),
        'sha256' => $checksum,
    ],
]);

$response = curl_exec($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);

/**
 * Never echo the token, even when the API includes the request in an error body.
 */
$redact = static function (string $text) use ($token): string {
    $text = str_replace($token, '***', $text);

    return preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer ***', $text) ?? $text;
};

if ($response === false) {
    fwrite(STDERR, 'Publish request failed: ' . $redact($curlError) . "\n");
    exit(1);
}

$body = $redact((string) $response);

if ($httpCode < 200 || $httpCode >= 300) {
    fwrite(STDERR, "Publish failed with HTTP {$httpCode}.\n{$body}\n");
    exit(1);
}

fwrite(STDOUT, "\nPublish accepted with HTTP {$httpCode}.\n");

$decoded = json_decode($body, true);

if (is_array($decoded)) {
    $plugin = $decoded['plugin'] ?? $decoded['data']['plugin'] ?? $decoded['data'] ?? $decoded;

    foreach ([
        'Handle' => $plugin['handle'] ?? null,
        'Version' => $plugin['version'] ?? null,
        'Channel' => $plugin['channel'] ?? null,
        'Checksum' => $plugin['checksum_sha256'] ?? $plugin['checksum'] ?? $plugin['sha256'] ?? null,
        'Download URL' => $plugin['download_url'] ?? $plugin['artifact_url'] ?? null,
    ] as $label => $value) {
        if (is_scalar($value) && (string) $value !== '') {
            fwrite(STDOUT, $label . ': ' . $redact((string) $value) . "\n");
        }
    }

    exit(0);
}

fwrite(STDOUT, $body . "\n");
