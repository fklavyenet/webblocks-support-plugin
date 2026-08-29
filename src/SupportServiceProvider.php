<?php

namespace WebBlocks\Support;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;

class SupportServiceProvider extends ServiceProvider
{
  public const HANDLE = 'webblocks-support';
  public const PERMISSION_ACCESS = self::HANDLE.'.access';
  public const PERMISSION_MANAGE = self::HANDLE.'.manage';

  public function boot(): void
  {
    self::registerViewNamespace();
    $this->loadTranslationsFrom(__DIR__.'/../resources/lang', self::HANDLE);
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
  }

  public static function definition(): PluginDefinition
  {
    self::registerViewNamespace();

    return PluginDefinition::make(self::HANDLE)
      ->label(PluginManifest::label())
      ->version(PluginManifest::version())
      ->provider(self::class)
      ->description(PluginManifest::description())
      ->requiresCms(PluginManifest::requiredCmsVersion())
      ->databasePrefix('webblocks_support_')
      ->permissions([
        PluginPermission::make(self::PERMISSION_ACCESS)->label('Use support tickets'),
        PluginPermission::make(self::PERMISSION_MANAGE)->label('Manage the support provider connection'),
      ])
      ->menu([
        PluginMenuItem::make('support')
          ->label('Support')
          ->route('webblocks.plugins.webblocks_support.support.index')
          ->icon('wb-icon-help-circle')
          ->permission(self::PERMISSION_ACCESS)
          ->group('Help')
          ->sort(20),
      ])
      ->source('catalog')
      ->adminRoutes(__DIR__.'/../routes/admin.php')
      ->migrations(['database/migrations'])
      ->health(SupportPluginHealth::class);
  }

  public static function registerViewNamespace(): void
  {
    if (app()->bound('view')) {
      View::addNamespace(self::HANDLE, dirname(__DIR__).'/resources/views');
    }
  }
}
