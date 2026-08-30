<?php

namespace WebBlocks\Support\Services;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\WebBlocks;

final class DiagnosticCollector
{
  public const CAPABILITIES = ['system_summary', 'recent_application_errors', 'plugin_health'];

  public function collect(array $requested): array
  {
    $requested = array_values(array_intersect(self::CAPABILITIES, array_filter($requested, 'is_string')));
    $snapshot = ['collected_at' => now()->toIso8601String()];

    if (in_array('system_summary', $requested, true)) {
      $snapshot['system_summary'] = [
        'cms_version' => WebBlocks::VERSION,
        'php_version' => PHP_VERSION,
        'environment' => app()->environment(),
        'debug' => (bool) config('app.debug'),
        'database_driver' => (string) config('database.default'),
      ];
    }

    if (in_array('plugin_health', $requested, true)) {
      $snapshot['plugin_health'] = collect(app(PluginRegistry::class)->summaries())
        ->map(fn (array $plugin): array => [
          'handle' => (string) ($plugin['handle'] ?? ''),
          'version' => $plugin['version'] ?? null,
          'enabled' => (bool) ($plugin['enabled'] ?? false),
          'compatible' => (bool) ($plugin['compatible'] ?? false),
        ])->values()->all();
    }

    if (in_array('recent_application_errors', $requested, true)) {
      $snapshot['recent_application_errors'] = $this->recentErrors();
    }

    return $snapshot;
  }

  private function recentErrors(): array
  {
    $path = storage_path('logs/laravel.log');

    if (! File::isFile($path)) {
      return [];
    }

    $size = (int) File::size($path);
    $handle = fopen($path, 'rb');

    if ($handle === false) {
      return [];
    }

    fseek($handle, max(0, $size - 65536));
    $tail = stream_get_contents($handle, 65536) ?: '';
    fclose($handle);

    return collect(preg_split('/\R/', $tail) ?: [])
      ->filter(fn (string $line): bool => preg_match('/(?:ERROR|CRITICAL|ALERT|EMERGENCY|Exception)/i', $line) === 1)
      ->map(fn (string $line): string => $this->redact($line))
      ->filter()
      ->take(-40)
      ->values()
      ->all();
  }

  private function redact(string $line): string
  {
    $line = preg_replace('/(authorization|cookie|token|secret|password|api[_-]?key)(["\'\s:=]+)[^,\s}\]]+/i', '$1$2[REDACTED]', $line) ?? '';
    $line = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+/i', 'Bearer [REDACTED]', $line) ?? '';
    $line = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $line) ?? '';

    return mb_substr($line, 0, 2000);
  }
}
