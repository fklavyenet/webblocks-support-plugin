<?php

declare(strict_types=1);

namespace WebBlocks\Support;

use RuntimeException;

final class PluginManifest
{
  /** @var array<string, mixed>|null */
  private static ?array $cache = null;

  /** @return array<string, mixed> */
  public static function all(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }

    $path = dirname(__DIR__).'/webblocks-plugin.json';
    $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    if (! is_array($decoded)) {
      throw new RuntimeException('webblocks-plugin.json is missing or unreadable at '.$path);
    }

    return self::$cache = $decoded;
  }

  public static function version(): string
  {
    return self::string('version');
  }

  public static function label(): string
  {
    return self::string('label');
  }

  public static function description(): string
  {
    return self::string('description');
  }

  public static function requiredCmsVersion(): string
  {
    return self::string('required_cms_version');
  }

  private static function string(string $key): string
  {
    $value = self::all()[$key] ?? null;

    if (! is_string($value) || $value === '') {
      throw new RuntimeException('webblocks-plugin.json is missing a usable ['.$key.']');
    }

    return $value;
  }
}
