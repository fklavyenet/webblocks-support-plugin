<?php

namespace WebBlocks\Support\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportSubmissionIntents
{
  public function issue(Authenticatable $user, int $connectionId): string
  {
    return Crypt::encryptString(json_encode(['actor' => (string) $user->getAuthIdentifier(), 'connection' => $connectionId, 'key' => (string) Str::uuid(), 'expires' => now()->addDay()->timestamp], JSON_THROW_ON_ERROR));
  }

  public function key(Authenticatable $user, int $connectionId, string $intent): string
  {
    try {
      $data = json_decode(Crypt::decryptString($intent), true, 8, JSON_THROW_ON_ERROR);
      if (($data['actor'] ?? null) !== (string) $user->getAuthIdentifier() || ($data['connection'] ?? null) !== $connectionId
        || ($data['expires'] ?? 0) <= now()->timestamp || ! Str::isUuid($data['key'] ?? '')) {
        throw new \RuntimeException;
      }

      return $data['key'];
    } catch (\Throwable) {
      throw ValidationException::withMessages(['title' => __('webblocks-support::admin.recovery.expired_intent')]);
    }
  }
}
