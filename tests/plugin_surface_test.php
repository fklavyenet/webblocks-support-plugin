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
$assert(($manifest['health'] ?? null) === 'WebBlocks\\Support\\SupportPluginHealth', 'Health reporter is missing.');
$assert(is_file($root.'/src/SupportServiceProvider.php'), 'Service provider is missing.');
$assert(is_file($root.'/src/SupportPluginHealth.php'), 'Health reporter file is missing.');
$assert(is_file($root.'/src/PluginManifest.php'), 'Manifest reader is missing.');
$assert(is_file($root.'/routes/admin.php'), 'Admin routes are missing.');
$assert(is_file($root.'/database/migrations/2026_08_29_080000_create_webblocks_support_connections_table.php'), 'Migration is missing.');

foreach (['index', 'create', 'show'] as $view) {
  $assert(is_file($root.'/resources/views/support/'.$view.'.blade.php'), 'Support view is missing: '.$view);
}

$showView = (string) file_get_contents($root.'/resources/views/support/show.blade.php');
$assert(! str_contains($showView, '@empty@endforelse'), 'Support ticket detail contains an invalid empty forelse branch.');
$assert(str_contains($showView, "asset('cms/plugins/webblocks-support/css/admin.css')"), 'Support ticket detail does not load its scoped admin stylesheet.');
$assert(is_file($root.'/resources/public/css/admin.css'), 'Support ticket admin stylesheet is missing.');
$assert(str_contains($showView, 'support.diagnostics.approve'), 'Support ticket detail does not expose diagnostic approval.');
$assert(str_contains($showView, 'support.diagnostics.decline'), 'Support ticket detail does not expose diagnostic decline.');
$assert(is_file($root.'/src/Services/DiagnosticCollector.php'), 'The consent-based diagnostic collector is missing.');

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

$provider = (string) file_get_contents($root.'/src/SupportServiceProvider.php');
$assert(
  preg_match('/public static function definition\(\): PluginDefinition\s*\{\s*self::registerViewNamespace\(\);/', $provider) === 1,
  'definition() must register the view namespace because installed plugin providers are not booted.',
);
$assert(str_contains($provider, '->health(SupportPluginHealth::class)'), 'Provider does not register the health reporter.');
$assert(str_contains($provider, 'PluginManifest::version()'), 'Provider must read its version from the manifest.');
$assert(! str_contains($provider, "->version('0.1.1')"), 'Provider duplicates the manifest version.');

$health = (string) file_get_contents($root.'/src/SupportPluginHealth.php');
$assert(str_contains($health, "Schema::hasTable('webblocks_support_connections')"), 'Health does not verify plugin storage.');
$assert(str_contains($health, "View::exists('webblocks-support::support.index')"), 'Health does not verify the Support interface.');

$routes = (string) file_get_contents($root.'/routes/admin.php');
$assert(! str_contains($routes, 'SupportServiceProvider::registerViewNamespace('), 'A new route file must remain callable with the previous provider class already loaded.');
$assert(str_contains($routes, "View::addNamespace('webblocks-support'"), 'The transition-safe route view fallback is missing.');
$assert(str_contains($routes, 'support/{ticket}/diagnostics/{diagnostic}/approve'), 'Diagnostic approval route is missing.');

$publisher = (string) file_get_contents($root.'/tools/plugin.php');
$assert(str_contains($publisher, "(?:\\s+-[^\\n]*)?"), 'Publisher must accept dated CHANGELOG headings.');
$assert(str_contains($publisher, 'Publishing stopped.'), 'Publisher must refuse a release without matching changelog notes.');
$assert(! str_contains($publisher, "'See CHANGELOG.md.'"), 'Publisher must not send an unresolvable CHANGELOG fallback.');
$assert(str_contains($publisher, "'details_url'"), 'Publisher must include a release-notes URL.');

echo "WebBlocks Support plugin surface passed.\n";
