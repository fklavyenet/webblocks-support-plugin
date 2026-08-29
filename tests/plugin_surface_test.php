<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root.'/webblocks-plugin.json'), true);

$assert = static function (bool $condition, string $message): void {
  if (! $condition) {
    throw new RuntimeException($message);
  }
};

$assert(($manifest['handle'] ?? null) === 'webblocks-support', 'Plugin handle is invalid.');
$assert(($manifest['provider'] ?? null) === 'WebBlocks\\Support\\SupportServiceProvider', 'Provider is invalid.');
$assert(($manifest['migrations'] ?? null) === 'database/migrations', 'Migration path is missing.');
$assert(is_file($root.'/src/SupportServiceProvider.php'), 'Service provider is missing.');
$assert(is_file($root.'/routes/admin.php'), 'Admin routes are missing.');
$assert(is_file($root.'/database/migrations/2026_08_29_080000_create_webblocks_support_connections_table.php'), 'Migration is missing.');

foreach (['index', 'create', 'show'] as $view) {
  $assert(is_file($root.'/resources/views/support/'.$view.'.blade.php'), 'Support view is missing: '.$view);
}

$sources = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src')) as $file) {
  if ($file->isFile() && $file->getExtension() === 'php') {
    $sources .= file_get_contents($file->getPathname());
  }
}

$assert(! str_contains($sources, 'wbcms_support_connections'), 'Plugin still uses the removed core table.');
$assert(str_contains($sources, 'webblocks_support_connections'), 'Plugin table is not declared.');

$indexView = (string) file_get_contents($root.'/resources/views/support/index.blade.php');
$assert(! str_contains($indexView, 'workbench.webblocksui.com'), 'Plugin UI must not default to one provider.');

echo "WebBlocks Support plugin surface passed.\n";
