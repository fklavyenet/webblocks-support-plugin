<?php

namespace WebBlocks\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Support\SupportServiceProvider;

class SupportProviderConnectRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can(SupportServiceProvider::PERMISSION_MANAGE) === true;
  }

  public function rules(): array
  {
    return [
      'provider_url' => ['required', 'url:https', 'max:2048'],
      'invitation_code' => ['required', 'string', 'max:64'],
    ];
  }
}
