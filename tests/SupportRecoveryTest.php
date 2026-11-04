<?php

namespace WebBlocks\Support\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;
use WebBlocks\Cms\Support\System\SystemDiagnosticReports;
use WebBlocks\Cms\Tests\TestCase;
use WebBlocks\Support\Database\SupportConnectionSchema;
use WebBlocks\Support\Models\SupportConnection;
use WebBlocks\Support\Policies\DiagnosticSharingPolicy;
use WebBlocks\Support\Services\InstallId;
use WebBlocks\Support\Services\SupportConnectionRepository;
use WebBlocks\Support\Services\SupportSubmissionIntents;
use WebBlocks\Support\SupportServiceProvider;

class SupportRecoveryTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('app.url', 'https://example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $root = getenv('WEBBLOCKS_CMS_TEST_ROOT');
    $this->loadMigrationsFrom($root.'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    Route::middleware('web')->prefix('test-support')->name('webblocks.plugins.webblocks_support.')->group(function (): void {
      require dirname(__DIR__).'/routes/admin.php';
    });
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->app->register(SupportServiceProvider::class);
    app(SupportConnectionSchema::class)->ensure();
  }

  private function actor(bool $system = true, bool $share = true, int $id = 15): User
  {
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => $id, 'name' => 'Synthetic operator', 'is_active' => true]);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $user->shouldReceive('can')->with('access-system')->andReturn($system);
    $user->shouldReceive('can')->with(SupportServiceProvider::PERMISSION_ACCESS)->andReturn(true);
    $user->shouldReceive('can')->with(SupportServiceProvider::PERMISSION_DIAGNOSTICS)->andReturn($share);
    $user->shouldReceive('isSuperAdmin')->andReturn($system);

    return $user;
  }

  private function connection(bool $recovery = true): SupportConnection
  {
    return app(SupportConnectionRepository::class)->replace([
      'provider_url' => 'https://8.8.8.8', 'api_base_url' => 'https://8.8.8.8/api', 'status' => 'active',
      'credential' => 'synthetic-server-only-secret', 'capabilities' => $recovery ? ['ticket.recovery', 'ticket.idempotency'] : ['ticket.create'],
    ]);
  }

  private function data(User $user, $connection): array
  {
    return ['type' => 'Issue', 'title' => 'Update blocked', 'body' => 'The update stopped.', 'impact' => 'update_blocked',
      'submission_intent' => app(SupportSubmissionIntents::class)->issue($user, $connection->id)];
  }

  public function test_ticket_access_does_not_grant_installation_diagnostic_permission(): void
  {
    $this->assertFalse(app(DiagnosticSharingPolicy::class)->share($this->actor(false, true)));
    $this->assertFalse(app(DiagnosticSharingPolicy::class)->share($this->actor(true, false)));
    $this->assertTrue(app(DiagnosticSharingPolicy::class)->share($this->actor()));
    $user = $this->actor(false, true);
    $connection = $this->connection();
    Http::preventStrayRequests();
    $this->actingAs($user)->post('/test-support/support/prepare', $this->data($user, $connection) + ['categories' => ['system_summary'], 'prepare_consent' => 1])->assertForbidden();
    Http::assertNothingSent();
  }

  public function test_report_preview_requires_consent_and_does_not_contact_provider(): void
  {
    $user = $this->actor();
    $connection = $this->connection();
    Http::preventStrayRequests();
    $data = $this->data($user, $connection) + ['categories' => ['system_summary']];
    $this->actingAs($user)->post('/test-support/support/prepare', $data)->assertSessionHasErrors('prepare_consent');
    $this->post('/test-support/support/prepare', $data + ['prepare_consent' => 1])->assertOk()->assertSee('webblocks-diagnostics/1')->assertDontSee('synthetic-server-only-secret');
    Http::assertNothingSent();
  }

  public function test_submission_uses_exact_reviewed_report_and_retry_key_and_stores_only_encrypted_tracking_link(): void
  {
    $user = $this->actor();
    $connection = $this->connection();
    $reports = app(SystemDiagnosticReports::class);
    $envelope = $reports->prepare($user, ['system_summary']);
    $expected = $reports->open($user, $envelope);
    $this->mock(SystemDiagnosticReportQuery::class, fn ($mock) => $mock->shouldNotReceive('collect'));
    Http::fake(['https://8.8.8.8/api/tickets/recovery' => Http::response(['ticket' => ['id' => 'ticket-a', 'number' => 1], 'tracking_url' => 'https://8.8.8.8/t/synthetic-private-link'], 201)]);
    $data = $this->data($user, $connection) + ['report' => $envelope, 'diagnostic_consent' => 1];
    $this->actingAs($user)->post('/test-support/support', $data)->assertRedirect();
    $this->post('/test-support/support', $data)->assertRedirect();
    $requests = Http::recorded();
    $this->assertSame($expected, $requests[0][0]['diagnostic_report']);
    $this->assertSame($requests[0][0]['submission_key'], $requests[1][0]['submission_key']);
    $this->assertStringNotContainsString('synthetic-private-link', DB::table('webblocks_support_ticket_links')->first()->tracking_url);
    $this->assertDatabaseCount('webblocks_support_ticket_links', 1);
  }

  public function test_submission_failure_retains_text_and_does_not_report_success(): void
  {
    $user = $this->actor();
    $connection = $this->connection();
    Http::fake(['*' => Http::response([], 503)]);
    $this->actingAs($user)->post('/test-support/support', $this->data($user, $connection))->assertSessionHasErrors('title')->assertSessionHasInput('body', 'The update stopped.')->assertSessionMissing('status');
  }

  public function test_legacy_provider_keeps_working_without_new_fields_or_report(): void
  {
    $user = $this->actor();
    $connection = $this->connection(false);
    Http::fake(['https://8.8.8.8/api/tickets' => Http::response(['ticket' => ['id' => 'legacy-ticket']], 201)]);
    $this->actingAs($user)->post('/test-support/support', $this->data($user, $connection))->assertRedirect();
    Http::assertSent(fn ($request) => $request->url() === 'https://8.8.8.8/api/tickets' && ! isset($request['diagnostic_report']) && ! isset($request['submission_key']));
  }

  public function test_requested_diagnostics_send_and_download_the_exact_preview_without_recollection(): void
  {
    $user = $this->actor();
    $this->connection(false);
    $ref = app(InstallId::class)->userRef($user->getAuthIdentifier());
    Http::fake(fn ($request) => $request->method() === 'GET' ? Http::response([
      'ticket' => ['id' => 'ticket-a', 'external_user_ref' => $ref],
      'diagnostic_requests' => [['id' => 'diag-a', 'capabilities' => ['system_summary']]],
    ]) : Http::response(['status' => 'submitted']));
    $this->actingAs($user);
    $url = '/test-support/support/ticket-a/diagnostics/diag-a/approve';
    $preview = $this->post($url)->assertOk();
    $envelope = $preview->viewData('envelope');
    $reports = app(SystemDiagnosticReports::class);
    $snapshot = $reports->open($user, $envelope);
    $this->mock(SystemDiagnosticReportQuery::class, fn ($mock) => $mock->shouldNotReceive('collect'));
    $this->post('/test-support/support/diagnostic-snapshot/download', ['report' => $envelope])->assertOk()->assertContent($reports->encode($snapshot));
    $this->post($url, ['report' => $envelope, 'diagnostic_consent' => 1])->assertRedirect();
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['snapshot'] === $snapshot);
    $this->assertArrayNotHasKey('laravel_version', $snapshot['system_summary']);
  }

  public function test_upgrade_imports_legacy_capabilities_once_without_expanding_json(): void
  {
    $connection = $this->connection(false);
    Schema::table('webblocks_support_connections', fn ($table) => $table->text('capabilities')->nullable());
    DB::table('webblocks_support_connections')->where('id', $connection->id)->update(['capabilities' => '["ticket.create","diagnostics.consent"]']);
    Schema::drop('webblocks_support_capabilities');
    app(SupportConnectionSchema::class)->ensure();
    $this->assertTrue($connection->supports('diagnostics.consent'));
    app(SupportConnectionSchema::class)->ensure();
    $this->assertDatabaseCount('webblocks_support_capabilities', 2);
    $this->assertSame('["ticket.create","diagnostics.consent"]', DB::table('webblocks_support_connections')->first()->capabilities);
  }

  public function test_intents_and_reports_cannot_cross_users_or_connections(): void
  {
    $user = $this->actor();
    $connection = $this->connection();
    Http::preventStrayRequests();
    $data = $this->data($user, $connection);
    $this->actingAs($this->actor(id: 20))->post('/test-support/support', $data)->assertSessionHasErrors('title');
    $this->assertDatabaseCount('webblocks_support_capabilities', 2);
    $this->assertFalse(Schema::hasColumn('webblocks_support_connections', 'capabilities'));
    Http::assertNothingSent();
  }
}
