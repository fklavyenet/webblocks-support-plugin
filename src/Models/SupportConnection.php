<?php

namespace WebBlocks\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportConnection extends Model
{
  protected $table = 'webblocks_support_connections';

  protected $fillable = [
    'provider_url',
    'provider_name',
    'api_base_url',
    'protocol_version',
    'support_portal_url',
    'status',
    'activation_id',
    'activation_secret',
    'activation_user_code',
    'activation_url',
    'activation_expires_at',
    'credential',
    'plan_name',
    'entitlement_expires_at',
    'activated_at',
    'last_verified_at',
    'last_error',
  ];

  protected $hidden = ['activation_secret', 'credential'];

  protected function casts(): array
  {
    return [
      'activation_secret' => 'encrypted',
      'activation_expires_at' => 'datetime',
      'credential' => 'encrypted',
      'entitlement_expires_at' => 'datetime',
      'activated_at' => 'datetime',
      'last_verified_at' => 'datetime',
    ];
  }

  public function isActive(): bool
  {
    return $this->status === 'active' && is_string($this->credential) && $this->credential !== '';
  }

  public function supports(string $capability): bool
  {
    return Schema::hasTable('webblocks_support_capabilities')
      && DB::table('webblocks_support_capabilities')->where('connection_id', $this->id)->where('capability', $capability)->exists();
  }
}
