<?php

namespace WebBlocks\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Support\Policies\DiagnosticSharingPolicy;

class DiagnosticSnapshotRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(DiagnosticSharingPolicy::class)->share($this->user());
  }

  public function rules(): array
  {
    return [
      'report' => [$this->routeIs('*.download') ? 'required' : 'nullable', 'string', 'max:140000'],
      'diagnostic_consent' => ! $this->routeIs('*.download') && $this->filled('report') ? ['accepted'] : ['sometimes', 'accepted'],
    ];
  }
}
