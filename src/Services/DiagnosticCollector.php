<?php

namespace WebBlocks\Support\Services;

use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;

final class DiagnosticCollector
{
  public const CAPABILITIES = ['system_summary', 'recent_application_errors', 'plugin_health'];

  public function collect(array $requested): array
  {
    return $this->snapshot(app(SystemDiagnosticReportQuery::class)->collect(array_values(array_intersect(self::CAPABILITIES, array_filter($requested, 'is_string')))));
  }

  public function snapshot(array $report): array
  {
    $snapshot = ['collected_at' => $report['collected_at']];
    foreach ($report['categories'] as $category => $data) {
      if ($category === 'system_summary') {
        $snapshot['system_summary'] = array_intersect_key($data, array_flip(['cms_version', 'php_version', 'debug', 'database_driver']));
      } elseif ($category === 'plugin_health') {
        $snapshot['plugin_health'] = $data['items'] ?? [];
      } elseif ($category === 'recent_application_errors') {
        $snapshot['recent_application_errors'] = array_map(fn ($item) => $item['at'].' '.$item['level'].' '.$item['fingerprint'], $data['items'] ?? []);
      }
    }

    return $snapshot;
  }
}
