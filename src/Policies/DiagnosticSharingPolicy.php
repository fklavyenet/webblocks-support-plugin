<?php

namespace WebBlocks\Support\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Policies\SystemDiagnosticReportPolicy;
use WebBlocks\Support\SupportServiceProvider;

class DiagnosticSharingPolicy
{
  public function share(?Authenticatable $user): bool
  {
    return app(SystemDiagnosticReportPolicy::class)->view($user)
      && $user->can(SupportServiceProvider::PERMISSION_ACCESS)
      && $user->can(SupportServiceProvider::PERMISSION_DIAGNOSTICS);
  }
}
