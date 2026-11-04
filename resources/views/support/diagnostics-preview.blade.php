@php($text = fn ($key) => app(\WebBlocks\Support\SupportTranslator::class)->admin('support.recovery.'.$key))
@extends('webblocks-cms::layouts.admin', ['title' => $text('report'), 'heading' => $text('report')])
@section('content')
    @include('webblocks-cms::admin.partials.flash')
    <section class="wb-card"><div class="wb-card-body wb-stack wb-stack-3">
        <h2 class="wb-card-title">{{ $text('report') }}</h2><p>{{ $text('optional') }}</p>
        <pre><code>{{ $reportText }}</code></pre>
        <form method="post" action="{{ route('webblocks.plugins.webblocks_support.support.diagnostics.approve', ['ticket' => $ticket, 'diagnostic' => $diagnostic]) }}" class="wb-stack wb-stack-3">@csrf
            <input type="hidden" name="report" value="{{ $envelope }}"><label class="wb-check"><input type="checkbox" name="diagnostic_consent" value="1" required><span>{{ $text('share_consent') }}</span></label><button class="wb-btn wb-btn-primary" type="submit">{{ $text('share') }}</button>
        </form>
        <form method="post" action="{{ route('webblocks.plugins.webblocks_support.support.diagnostics.download') }}">@csrf<input type="hidden" name="report" value="{{ $envelope }}"><button class="wb-btn wb-btn-secondary" type="submit">{{ $text('download') }}</button></form>
        <form method="post" action="{{ route('webblocks.plugins.webblocks_support.support.diagnostics.decline', ['ticket' => $ticket, 'diagnostic' => $diagnostic]) }}">@csrf<button class="wb-btn wb-btn-secondary" type="submit">{{ app(\WebBlocks\Support\SupportTranslator::class)->admin('support.diagnostics_decline') }}</button></form>
    </div></section>
@endsection
