<?php

namespace WebBlocks\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use WebBlocks\Cms\Support\Plugins\PluginHealthResult;

final class SupportPluginHealth
{
  public function health(): PluginHealthResult
  {
    SupportServiceProvider::registerViewNamespace();

    $checks = [
      [
        'name' => 'Support database',
        'status' => Schema::hasTable('webblocks_support_connections') ? PluginHealthResult::HEALTHY : PluginHealthResult::WARNING,
        'message' => Schema::hasTable('webblocks_support_connections')
          ? 'The support connection table is ready.'
          : 'Setup required. Run plugin migrations to create the support connection table.',
      ],
      [
        'name' => 'Support interface',
        'status' => View::exists('webblocks-support::support.index') ? PluginHealthResult::HEALTHY : PluginHealthResult::WARNING,
        'message' => View::exists('webblocks-support::support.index')
          ? 'The Support admin interface is registered.'
          : 'The Support admin interface could not be registered.',
      ],
    ];

    $warning = array_filter($checks, static fn (array $check): bool => $check['status'] !== PluginHealthResult::HEALTHY);

    return PluginHealthResult::withChecks(
      $warning === [] ? PluginHealthResult::HEALTHY : PluginHealthResult::WARNING,
      $warning === [] ? 'Support storage and the admin interface are ready.' : 'Setup required. WebBlocks Support needs attention.',
      $checks,
    );
  }
}
