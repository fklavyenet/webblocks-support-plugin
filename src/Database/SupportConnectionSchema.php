<?php

namespace WebBlocks\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SupportConnectionSchema
{
  public function ensure(): void
  {
    if (! Schema::hasTable('webblocks_support_connections')) {
    Schema::create('webblocks_support_connections', function (Blueprint $table): void {
      $table->id();
      $table->string('provider_url', 2048);
      $table->string('provider_name')->nullable();
      $table->string('api_base_url', 2048);
      $table->string('protocol_version', 16)->default('1.0');
      $table->string('support_portal_url', 2048)->nullable();
      $table->string('status', 24)->default('pending');
      $table->string('activation_id')->nullable();
      $table->longText('activation_secret')->nullable();
      $table->string('activation_user_code')->nullable();
      $table->string('activation_url', 2048)->nullable();
      $table->timestamp('activation_expires_at')->nullable();
      $table->longText('credential')->nullable();
      $table->string('plan_name')->nullable();
      $table->timestamp('entitlement_expires_at')->nullable();
      $table->timestamp('activated_at')->nullable();
      $table->timestamp('last_verified_at')->nullable();
      $table->text('last_error')->nullable();
      $table->timestamps();
    });
    }
    if (! Schema::hasColumn('webblocks_support_connections', 'support_portal_url')) {
      Schema::table('webblocks_support_connections', fn (Blueprint $table) => $table->string('support_portal_url', 2048)->nullable());
    }
    if (! Schema::hasTable('webblocks_support_capabilities')) {
      Schema::create('webblocks_support_capabilities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('connection_id');
        $table->string('capability', 100);
        $table->unique(['connection_id', 'capability']);
      });
      // Import the existing capability list once; new reads/writes use SQL rows.
      if (Schema::hasColumn('webblocks_support_connections', 'capabilities')) {
        foreach (DB::table('webblocks_support_connections')->get(['id', 'capabilities']) as $connection) {
          foreach (json_decode($connection->capabilities ?? '[]', true) ?: [] as $capability) {
            if (is_string($capability) && strlen($capability) <= 100) {
              DB::table('webblocks_support_capabilities')->insertOrIgnore(['connection_id' => $connection->id, 'capability' => $capability]);
            }
          }
        }
      }
    }
    if (! Schema::hasTable('webblocks_support_ticket_links')) {
      Schema::create('webblocks_support_ticket_links', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('connection_id');
        $table->string('user_ref');
        $table->string('ticket_ref');
        $table->text('tracking_url');
        $table->timestamps();
        $table->unique(['connection_id', 'user_ref', 'ticket_ref'], 'wbs_ticket_link_owner_unique');
      });
    }
  }
}
