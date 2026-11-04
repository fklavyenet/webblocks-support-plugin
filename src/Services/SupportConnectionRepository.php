<?php

namespace WebBlocks\Support\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Support\Database\SupportConnectionSchema;
use WebBlocks\Support\Models\SupportConnection;

final class SupportConnectionRepository
{
  public function __construct(
    private readonly SupportConnectionSchema $schema,
  ) {}

  public function current(): ?SupportConnection
  {
    if (! Schema::hasTable('webblocks_support_connections')) {
      return null;
    }

    return SupportConnection::query()->latest('id')->first();
  }

  public function replace(array $attributes): SupportConnection
  {
    // The support connection is an optional feature first used after an
    // update. Repair its package-owned table at the write boundary so a host
    // whose update-migration step was skipped is not sent to a terminal.
    $this->schema->ensure();

    $capabilities = $attributes['capabilities'] ?? [];
    unset($attributes['capabilities']);

    return DB::transaction(function () use ($attributes, $capabilities): SupportConnection {
      $this->disconnect();
      $connection = SupportConnection::query()->create($attributes);
      $this->saveCapabilities($connection, $capabilities);

      return $connection;
    });
  }

  public function saveCapabilities(SupportConnection $connection, array $capabilities): void
  {
    $this->schema->ensure();
    DB::transaction(function () use ($connection, $capabilities): void {
      DB::table('webblocks_support_capabilities')->where('connection_id', $connection->id)->delete();
      foreach (array_unique($capabilities) as $capability) {
        if (is_string($capability) && preg_match('/^[a-z][a-z0-9.]{1,99}$/D', $capability)) {
          DB::table('webblocks_support_capabilities')->insert(['connection_id' => $connection->id, 'capability' => $capability]);
        }
      }
    });
  }

  public function disconnect(): void
  {
    foreach (['webblocks_support_capabilities', 'webblocks_support_ticket_links'] as $table) {
      if (Schema::hasTable($table)) {
        DB::table($table)->delete();
      }
    }
    SupportConnection::query()->delete();
  }
}
