<?php

namespace WebBlocks\Support\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Support\System\SystemDiagnosticReports;
use WebBlocks\Support\Http\Requests\SupportProviderConnectRequest;
use WebBlocks\Support\Http\Requests\SupportTicketRequest;
use WebBlocks\Support\Policies\DiagnosticSharingPolicy;
use WebBlocks\Support\Services\DiagnosticCollector;
use WebBlocks\Support\Services\SupportActivationService;
use WebBlocks\Support\Services\SupportSubmissionIntents;
use WebBlocks\Support\Services\SupportTicketService;
use WebBlocks\Support\SupportServiceProvider;
use WebBlocks\Support\SupportTranslator;

/**
 * Lets a signed-in CMS admin report a problem or request a change, and follow
 * the thread, without leaving the admin or holding a second account. The
 * ticket itself lives at the installation's connected support provider.
 *
 * It is a top-level admin item rather than a System one: `access-system` is
 * held by the operator who maintains the installation, and the editor who
 * cannot get a page to publish is exactly the person with something to report.
 */
class SupportController extends Controller
{
  public function __construct(
    private readonly SupportTicketService $support,
    private readonly SupportActivationService $activation,
    private readonly SupportTranslator $translator,
    private readonly DiagnosticCollector $diagnostics,
  ) {}

  public function index(Request $request): View
  {
    $this->authorizeAccess($request);
    $tickets = [];
    $error = null;

    if ($this->support->isConfigured()) {
      try {
        $tickets = $this->support->forUser($request->user());
      } catch (Throwable) {
        $error = $this->translator->admin('support.unavailable');
      }
    }

    return view('webblocks-support::support.index', [
      'tickets' => $tickets,
      'configured' => $this->support->isConfigured(),
      'error' => $error,
      'connection' => $this->support->connection(),
      'canManageConnection' => $request->user()?->can(SupportServiceProvider::PERMISSION_MANAGE) === true,
    ]);
  }

  public function create(Request $request): Response
  {
    $this->authorizeAccess($request);

    return $this->draft($request);
  }

  public function prepare(SupportTicketRequest $request): Response
  {
    abort_unless(app(DiagnosticSharingPolicy::class)->share($request->user()), 403);
    $connection = $this->support->connection();
    abort_unless($connection?->supports('ticket.recovery') && $connection->supports('ticket.idempotency'), 409);
    $request->validate(['categories' => ['required', 'array', 'min:1']]);
    $envelope = app(SystemDiagnosticReports::class)->prepare($request->user(), $request->validated('categories'), $request->filled('run_id') ? $request->integer('run_id') : null);

    return $this->draft($request, $request->validated(), $envelope);
  }

  private function draft(Request $request, array $draft = [], ?string $envelope = null): Response
  {
    $connection = $this->support->connection();
    $canShare = app(DiagnosticSharingPolicy::class)->share($request->user());
    $envelope ??= old('report');
    $reportText = null;
    if ($canShare && is_string($envelope) && $envelope !== '') {
      try {
        $reports = app(SystemDiagnosticReports::class);
        $reportText = $reports->encode($reports->open($request->user(), $envelope));
      } catch (ValidationException) {
        $envelope = null;
      }
    }

    return response()->view('webblocks-support::support.create', [
      'types' => SupportTicketService::TYPES, 'configured' => $this->support->isConfigured(),
      'connection' => $connection, 'draft' => $draft, 'reportEnvelope' => $envelope, 'reportText' => $reportText,
      'canShare' => $canShare && $connection?->supports('ticket.recovery') && $connection?->supports('ticket.idempotency'),
      'submissionIntent' => old('submission_intent', $draft['submission_intent'] ?? ($connection ? app(SupportSubmissionIntents::class)->issue($request->user(), $connection->id) : '')),
    ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
  }

  public function store(SupportTicketRequest $request): RedirectResponse
  {
    abort_unless($this->support->isConfigured(), 404);
    $data = $request->validated();
    $key = app(SupportSubmissionIntents::class)->key($request->user(), $this->support->connection()->id, $data['submission_intent']);
    $report = null;
    if ($request->filled('report') && ! $request->boolean('omit_report')) {
      abort_unless(app(DiagnosticSharingPolicy::class)->share($request->user()), 403);
      $report = app(SystemDiagnosticReports::class)->open($request->user(), $data['report']);
    }
    try {
      $ticket = $this->support->fileRecovery($request->user(), $data, $report, $key);
    } catch (Throwable) {
      return back()->withInput()->withErrors(['title' => $this->translator->admin('support.recovery.delivery_uncertain')]);
    }

    return redirect()->route('webblocks.plugins.webblocks_support.support.show', ['ticket' => $ticket['id']])
      ->with('status', $this->translator->admin('support.created'));
  }

  public function connect(SupportProviderConnectRequest $request): RedirectResponse
  {
    try {
      $connection = $this->activation->start(
        $request->validated('provider_url'),
        $request->validated('invitation_code'),
      );
    } catch (Throwable) {
      return back()->withInput()->withErrors(['invitation_code' => $this->translator->admin('support.invitation_invalid')]);
    }

    return redirect()->route('webblocks.plugins.webblocks_support.support.index')->with('status', $this->translator->admin('support.activation_started', null, [
      'provider' => $connection->provider_name,
    ]));
  }

  public function refreshActivation(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->can(SupportServiceProvider::PERMISSION_MANAGE), 403);

    try {
      $connection = $this->activation->refresh();
    } catch (Throwable) {
      return back()->withErrors(['provider_url' => $this->translator->admin('support.unavailable')]);
    }

    $key = $connection->isActive() ? 'support.connected' : 'support.activation_pending';

    return redirect()->route('webblocks.plugins.webblocks_support.support.index')->with('status', $this->translator->admin($key));
  }

  public function disconnect(Request $request): RedirectResponse
  {
    abort_unless($request->user()?->can(SupportServiceProvider::PERMISSION_MANAGE), 403);

    try {
      $this->activation->disconnect();
    } catch (Throwable) {
      return back()->withErrors(['provider_url' => $this->translator->admin('support.unavailable')]);
    }

    return redirect()->route('webblocks.plugins.webblocks_support.support.index')->with('status', $this->translator->admin('support.disconnected'));
  }

  public function show(Request $request, string $ticket): View
  {
    $this->authorizeAccess($request);
    $found = $this->find($request, $ticket);

    return view('webblocks-support::support.show', [
      'ticket' => $found['ticket'],
      'comments' => $found['comments'],
      'diagnosticRequests' => $found['diagnostic_requests'] ?? [],
      'trackingUrl' => $this->support->trackingUrl($request->user(), $ticket),
      'canShareDiagnostics' => app(DiagnosticSharingPolicy::class)->share($request->user()),
    ]);
  }

  public function approveDiagnostics(Request $request, string $ticket, string $diagnostic): RedirectResponse|Response
  {
    $this->authorizeAccess($request);
    abort_unless(app(DiagnosticSharingPolicy::class)->share($request->user()), 403);
    $found = $this->find($request, $ticket);
    $diagnosticRequest = collect($found['diagnostic_requests'] ?? [])->first(fn (array $item): bool => ($item['id'] ?? null) === $diagnostic);
    abort_unless(is_array($diagnosticRequest), 404);
    $categories = array_values(array_intersect(DiagnosticCollector::CAPABILITIES, (array) ($diagnosticRequest['capabilities'] ?? [])));
    abort_if($categories === [], 422);
    $reports = app(SystemDiagnosticReports::class);
    if (! $request->filled('report')) {
      $snapshot = $this->diagnostics->collect($categories);
      $envelope = $reports->seal($request->user(), $snapshot);

      return response()->view('webblocks-support::support.diagnostics-preview', ['ticket' => $ticket, 'diagnostic' => $diagnostic, 'envelope' => $envelope, 'reportText' => json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)])
        ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
    $request->validate(['report' => ['required', 'string', 'max:140000'], 'diagnostic_consent' => ['accepted']]);
    $snapshot = $reports->open($request->user(), $request->string('report')->toString());
    $actual = array_values(array_diff(array_keys($snapshot), ['collected_at']));
    sort($actual);
    sort($categories);
    abort_unless($actual === $categories, 422);
    try {
      abort_unless($this->support->respondToDiagnostic($request->user(), $ticket, $diagnostic, 'submit', $snapshot), 404);
    } catch (Throwable) {
      return back()->withInput()->withErrors(['diagnostics' => $this->translator->admin('support.unavailable')]);
    }

    return redirect()->route('webblocks.plugins.webblocks_support.support.show', ['ticket' => $ticket])
      ->with('status', $this->translator->admin('support.diagnostics_shared'));
  }

  public function downloadDiagnosticSnapshot(Request $request): Response
  {
    abort_unless(app(DiagnosticSharingPolicy::class)->share($request->user()), 403);
    $data = $request->validate(['report' => ['required', 'string', 'max:140000']]);
    $reports = app(SystemDiagnosticReports::class);
    $snapshot = $reports->open($request->user(), $data['report']);
    abort_if(array_diff(array_keys($snapshot), ['collected_at', ...DiagnosticCollector::CAPABILITIES]) !== [], 422);

    return response($reports->encode($snapshot))->header('Content-Type', 'text/plain; charset=UTF-8')
      ->header('Content-Disposition', 'attachment; filename="webblocks-diagnostic-snapshot.txt"')->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'private, no-store');
  }

  public function declineDiagnostics(Request $request, string $ticket, string $diagnostic): RedirectResponse
  {
    $this->authorizeAccess($request);
    try {
      abort_unless($this->support->respondToDiagnostic($request->user(), $ticket, $diagnostic, 'decline'), 404);
    } catch (Throwable) {
      return back()->withErrors(['diagnostics' => $this->translator->admin('support.unavailable')]);
    }

    return redirect()->route('webblocks.plugins.webblocks_support.support.show', ['ticket' => $ticket])
      ->with('status', $this->translator->admin('support.diagnostics_declined'));
  }

  public function comment(Request $request, string $ticket): RedirectResponse
  {
    $this->authorizeAccess($request);
    abort_unless($this->support->isConfigured(), 404);

    $data = $request->validate([
      'body' => ['required', 'string', 'max:20000'],
    ]);

    try {
      $replied = $this->support->reply($request->user(), $ticket, $data['body']);
    } catch (Throwable) {
      return back()->withInput()->withErrors(['body' => $this->translator->admin('support.unavailable')]);
    }

    abort_unless($replied, 404);

    return redirect()
      ->route('webblocks.plugins.webblocks_support.support.show', ['ticket' => $ticket])
      ->with('status', $this->translator->admin('support.reply_sent'));
  }

  /**
   * @return array{ticket: array<string, mixed>, comments: array<int, array<string, mixed>>}
   */
  private function find(Request $request, string $ticket): array
  {
    abort_unless($this->support->isConfigured(), 404);

    try {
      $found = $this->support->findForUser($request->user(), $ticket);
    } catch (Throwable) {
      abort(503, $this->translator->admin('support.unavailable'));
    }

    // Another admin's ticket is a 404 here, not a 403: the reader learns
    // nothing about whether it exists.
    abort_if($found === null, 404);

    return $found;
  }

  private function authorizeAccess(Request $request): void
  {
    abort_unless($request->user()?->can(SupportServiceProvider::PERMISSION_ACCESS), 403);
  }
}
