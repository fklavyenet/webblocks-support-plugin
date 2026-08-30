@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Support\SupportTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(SupportTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);

    $statusBadges = [
        'new' => 'wb-badge-primary',
        'triaged' => 'wb-badge-warning',
        'converted' => 'wb-badge-success',
        'rejected' => 'wb-badge-danger',
        'closed' => 'wb-badge',
    ];
    $statusLabel = static fn (string $status): string => $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode) === 'support.statuses.'.$status
        ? $status
        : $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode);
    $typeLabel = static fn (string $type): string => $adminTranslator->admin('support.types.'.$type, $adminLocaleCode) === 'support.types.'.$type
        ? $type
        : $adminTranslator->admin('support.types.'.$type, $adminLocaleCode);
    $waitingForReporter = $ticket['status'] === 'waiting_on_reporter';
    $displayStatus = match ($ticket['status']) {
        'new', 'triaged' => $adminText('support.waiting_support'),
        'waiting_on_reporter' => $adminText('support.waiting_reporter'),
        default => $statusLabel($ticket['status']),
    };
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $ticket['title'], 'heading' => $ticket['title']])

@section('content')
    <div class="wb-stack wb-gap-4">
        @if (session('status'))
            <div class="wb-alert wb-alert-success" role="status"><div>{{ session('status') }}</div></div>
        @endif
        <div class="wb-cluster wb-cluster-between">
            <div class="wb-stack wb-gap-1">
                <div class="wb-cluster wb-cluster-2">
                    <h1 class="wb-page-title">#{{ $ticket['number'] }} · {{ $ticket['title'] }}</h1>
                    <span class="wb-badge {{ $statusBadges[$ticket['status']] ?? 'wb-badge' }}">{{ $displayStatus }}</span>
                </div>
                <p class="wb-text-sm wb-text-muted">
                    {{ $typeLabel($ticket['type']) }} · {{ $adminText('support.opened', ['date' => \Illuminate\Support\Carbon::parse($ticket['created_at'])->isoFormat('LLL')]) }}
                </p>
            </div>
            <a class="wb-btn wb-btn-secondary wb-ms-auto" href="{{ route('webblocks.plugins.webblocks_support.support.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i>{{ $adminText('support.back') }}
            </a>
        </div>

        @foreach ($diagnosticRequests as $diagnosticRequest)
            <section class="wb-card wbs-diagnostics">
                <div class="wb-card-header">
                    <h2 class="wb-card-title">{{ $adminText('support.diagnostics_title') }}</h2>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    @error('diagnostics')
                        <div class="wb-alert wb-alert-danger"><div>{{ $message }}</div></div>
                    @enderror
                    <p>{{ $adminText('support.diagnostics_intro') }}</p>
                    <ul>
                        @foreach ($diagnosticRequest['capabilities'] ?? [] as $capability)
                            <li>{{ $adminText('support.diagnostics_'.$capability) }}</li>
                        @endforeach
                    </ul>
                    <div class="wb-alert wb-alert-info"><div>{{ $adminText('support.diagnostics_privacy') }}</div></div>
                </div>
                <div class="wb-card-footer wb-cluster wb-cluster-2">
                    <form method="POST" action="{{ route('webblocks.plugins.webblocks_support.support.diagnostics.approve', ['ticket' => $ticket['id'], 'diagnostic' => $diagnosticRequest['id']]) }}">
                        @csrf
                        <button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.diagnostics_approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('webblocks.plugins.webblocks_support.support.diagnostics.decline', ['ticket' => $ticket['id'], 'diagnostic' => $diagnosticRequest['id']]) }}">
                        @csrf
                        <button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('support.diagnostics_decline') }}</button>
                    </form>
                </div>
            </section>
        @endforeach

        <section class="wb-card">
            <div class="wb-card-header">
                <h2 class="wb-card-title">{{ $adminText('support.conversation') }}</h2>
            </div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <article class="wb-callout wb-stack wb-gap-2">
                        <header class="wb-cluster wb-cluster-between">
                            <span class="wb-cluster wb-cluster-2"><span class="wb-badge">{{ mb_strtoupper(mb_substr($adminText('support.you'), 0, 1)) }}</span><strong>{{ $adminText('support.you') }}</strong><span class="wb-badge">{{ $adminText('support.author_reporter') }}</span></span>
                            <time class="wb-text-sm wb-text-muted" datetime="{{ \Illuminate\Support\Carbon::parse($ticket['created_at'])->toAtomString() }}">{{ \Illuminate\Support\Carbon::parse($ticket['created_at'])->isoFormat('LLL') }}</time>
                        </header>
                        <div class="wb-prose">{!! nl2br(e($ticket['body'])) !!}</div>
                </article>
                @foreach ($comments as $comment)
                    <article @class(['wb-callout', 'wb-alert', 'wb-alert-info' => $comment['author_type'] === 'admin', 'wb-stack', 'wb-gap-2'])>
                            <header class="wb-cluster wb-cluster-between">
                                <span class="wb-cluster wb-cluster-2">
                                    <span class="wb-badge">{{ mb_strtoupper(mb_substr($comment['author_name'], 0, 1)) }}</span>
                                    <strong>{{ $comment['author_name'] }}</strong>
                                    @if ($comment['author_type'] === 'admin')
                                        <span class="wb-badge wb-badge-primary">{{ $adminText('support.author_team') }}</span>
                                    @endif
                                </span>
                                <time class="wb-text-sm wb-text-muted" datetime="{{ \Illuminate\Support\Carbon::parse($comment['created_at'])->toAtomString() }}">{{ \Illuminate\Support\Carbon::parse($comment['created_at'])->isoFormat('LLL') }}</time>
                            </header>
                            <div class="wb-prose">{!! nl2br(e($comment['body'])) !!}</div>
                    </article>
                @endforeach

                @if (in_array($ticket['status'], ['new', 'triaged', 'waiting_on_reporter'], true))
                    <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
                        <i class="wb-icon wb-icon-clock" aria-hidden="true"></i>
                        <span>{{ $waitingForReporter ? $adminText('support.your_reply_pending') : $adminText('support.support_reply_pending') }}</span>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('webblocks.plugins.webblocks_support.support.comment', ['ticket' => $ticket['id']]) }}">
                @csrf
                <div class="wb-card-body">
                    <div class="wb-field">
                        <label class="wb-label" for="supportReply">{{ $adminText('support.reply_label') }}</label>
                        <textarea id="supportReply" class="wb-textarea" name="body" rows="4" maxlength="20000" placeholder="{{ $adminText('support.reply_placeholder') }}" required>{{ old('body') }}</textarea>
                        @error('body')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="wb-card-footer wb-cluster wb-cluster-between">
                    <button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.reply_submit') }}</button>
                </div>
            </form>
        </section>
    </div>
@endsection
