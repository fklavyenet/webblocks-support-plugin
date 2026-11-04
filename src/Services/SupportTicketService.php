<?php

namespace WebBlocks\Support\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Support\Models\SupportConnection;
use WebBlocks\Support\Models\SupportTicketLink;

final class SupportTicketService
{
  public const TYPES = ['Issue', 'Suggestion', 'Question'];

  public function __construct(
    private readonly SupportConnectionRepository $connections,
    private readonly CompatibleSupportProvider $provider,
    private readonly InstallId $install,
  ) {}

  public function connection(): ?SupportConnection
  {
    return $this->connections->current();
  }

  public function isConfigured(): bool
  {
    return $this->connection()?->isActive() === true;
  }

  public function file(Authenticatable $user, string $type, string $title, string $body): array
  {
    return $this->provider->createTicket($this->activeConnection(), [
      'title' => $title,
      'body' => $body,
      'type' => $type,
      'external_user_ref' => $this->userRef($user),
      'external_user_name' => (string) $user->name,
      'install_ref' => $this->install->value(),
      'product' => 'webblocks-cms',
      'product_version' => WebBlocks::VERSION,
      'site_url' => (string) config('app.url'),
      'environment' => app()->environment(),
    ]);
  }

  public function fileRecovery(Authenticatable $user, array $data, ?array $report, string $key): array
  {
    $connection = $this->activeConnection();
    $context = [
      'external_user_ref' => $this->userRef($user), 'external_user_name' => (string) $user->name,
      'install_ref' => $this->install->value(), 'product_version' => WebBlocks::VERSION,
      'site_url' => (string) config('app.url'), 'environment' => app()->environment(),
    ];
    $payload = array_intersect_key($data, array_flip(['type', 'title', 'body', 'impact', 'onset', 'last_action'])) + $context + ['submission_key' => $key];
    if ($connection->supports('ticket.recovery') && $connection->supports('ticket.idempotency')) {
      if ($report !== null) {
        $payload['diagnostic_report'] = $report;
        $payload['diagnostic_consent'] = true;
      }
      $result = $this->provider->createRecoveryTicket($connection, $payload);
      $ticket = $result['ticket'] ?? [];
      if (! is_string($ticket['id'] ?? null) || $ticket['id'] === '') {
        throw new SupportProviderException('The provider returned an invalid receipt. Check existing requests before retrying.');
      }
      if (is_string($result['tracking_url'] ?? null)) {
        $url = $this->provider->checkedTrackingUrl($connection, $result['tracking_url']);
        SupportTicketLink::updateOrCreate([
          'connection_id' => $connection->id, 'user_ref' => $this->userRef($user), 'ticket_ref' => $ticket['id'],
        ], ['tracking_url' => $url]);
      }

      return $ticket;
    }
    if ($report !== null) {
      throw new SupportProviderException('The provider does not support reviewed reports.');
    }
    $body = $data['body']."\n\n".trans('webblocks-support::admin.recovery.impact').': '.$data['impact'];
    foreach (['onset', 'last_action'] as $field) {
      if (! empty($data[$field])) {
        $body .= "\n".trans('webblocks-support::admin.recovery.'.$field).': '.$data[$field];
      }
    }
    $ticket = $this->file($user, $data['type'], $data['title'], $body);
    if (! is_string($ticket['id'] ?? null) || $ticket['id'] === '') {
      throw new SupportProviderException('The provider returned an invalid receipt.');
    }

    return $ticket;
  }

  public function trackingUrl(Authenticatable $user, string $ticketId): ?string
  {
    if (! Schema::hasTable('webblocks_support_ticket_links')) {
      return null;
    }

    return SupportTicketLink::query()->where('connection_id', $this->activeConnection()->id)->where('user_ref', $this->userRef($user))->where('ticket_ref', $ticketId)->first()?->tracking_url;
  }

  public function forUser(Authenticatable $user): array
  {
    return $this->provider->tickets($this->activeConnection(), $this->userRef($user));
  }

  public function findForUser(Authenticatable $user, string $ticketId): ?array
  {
    $payload = $this->provider->ticket($this->activeConnection(), $ticketId);

    if ($payload === null || ($payload['ticket']['external_user_ref'] ?? null) !== $this->userRef($user)) {
      return null;
    }

    return [
      'ticket' => $payload['ticket'],
      'comments' => $payload['comments'] ?? [],
      'diagnostic_requests' => $payload['diagnostic_requests'] ?? [],
    ];
  }

  public function reply(Authenticatable $user, string $ticketId, string $body): bool
  {
    if ($this->findForUser($user, $ticketId) === null) {
      return false;
    }

    $this->provider->reply($this->activeConnection(), $ticketId, [
      'body' => $body,
      'external_user_name' => (string) $user->name,
      'install_ref' => $this->install->value(),
    ]);

    return true;
  }

  public function respondToDiagnostic(Authenticatable $user, string $ticketId, string $diagnosticId, string $action, ?array $snapshot = null): bool
  {
    $found = $this->findForUser($user, $ticketId);
    $exists = collect($found['diagnostic_requests'] ?? [])->contains(fn (array $request): bool => ($request['id'] ?? null) === $diagnosticId);

    if (! $exists) {
      return false;
    }

    $this->provider->respondToDiagnostic($this->activeConnection(), $ticketId, $diagnosticId, [
      'action' => $action,
      'snapshot' => $snapshot,
      'install_ref' => $this->install->value(),
    ]);

    return true;
  }

  private function activeConnection(): SupportConnection
  {
    $connection = $this->connection();

    if (! $connection?->isActive()) {
      throw new SupportProviderException('Support is not connected.');
    }

    return $connection;
  }

  private function userRef(Authenticatable $user): string
  {
    return $this->install->userRef($user->getAuthIdentifier());
  }
}
