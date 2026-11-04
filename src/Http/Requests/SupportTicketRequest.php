<?php

namespace WebBlocks\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;
use WebBlocks\Support\Services\SupportTicketService;
use WebBlocks\Support\SupportServiceProvider;

class SupportTicketRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can(SupportServiceProvider::PERMISSION_ACCESS) === true;
  }

  public function rules(): array
  {
    return [
      'type' => ['required', Rule::in(SupportTicketService::TYPES)], 'title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:20000'],
      'impact' => ['required', Rule::in(['feature', 'update_blocked', 'site_down'])], 'onset' => ['nullable', 'string', 'max:100'], 'last_action' => ['nullable', 'string', 'max:2000'],
      'submission_intent' => ['required', 'string', 'max:2048'], 'report' => ['nullable', 'string', 'max:140000'],
      'diagnostic_consent' => ! $this->routeIs('*.prepare') && $this->filled('report') && ! $this->boolean('omit_report') ? ['accepted'] : ['sometimes', 'accepted'],
      'omit_report' => ['sometimes', 'boolean'],
      'categories' => ['sometimes', 'array', 'min:1', 'max:5'], 'categories.*' => ['required', 'string', 'distinct', Rule::in(SystemDiagnosticReportQuery::CATEGORIES)],
      'prepare_consent' => [$this->routeIs('*.prepare') ? 'accepted' : 'sometimes'],
      'run_id' => ['nullable', 'integer', 'min:1'],
    ];
  }
}
