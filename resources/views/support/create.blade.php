@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Support\SupportTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(SupportTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);

    $selectedType = old('type', $draft['type'] ?? 'Issue');
    $typeLabel = static fn (string $type): string => $adminTranslator->admin('support.types.'.$type, $adminLocaleCode) === 'support.types.'.$type
        ? $type
        : $adminTranslator->admin('support.types.'.$type, $adminLocaleCode);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('support.new_title'), 'heading' => $adminText('support.new_title')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('support.new_title'),
            'description' => $adminText('support.new_intro'),
        ])

        @include('webblocks-cms::admin.partials.flash')
        @if($connection?->support_portal_url)<p><a class="wb-btn wb-btn-secondary" href="{{ $connection->support_portal_url }}" rel="noreferrer">{{ $adminText('support.recovery.external') }}</a></p>@endif

        <div>
            <a class="wb-btn wb-btn-secondary" href="{{ route('webblocks.plugins.webblocks_support.support.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i>{{ $adminText('support.back') }}
            </a>
        </div>

        @if (! $configured)
            <div class="wb-alert wb-alert-warning">
                <div>{{ $adminText('support.not_configured') }}</div>
            </div>
        @else
            <section class="wb-card">
                <form method="POST" action="{{ route('webblocks.plugins.webblocks_support.support.store') }}">
                    @csrf
                    <input type="hidden" name="submission_intent" value="{{ $submissionIntent }}">

                    <div class="wb-card-body wb-stack wb-gap-4">
                        <div class="wb-field"><label class="wb-label" for="impact">{{ $adminText('support.recovery.impact') }}</label><select class="wb-select" id="impact" name="impact">@foreach(['feature', 'update_blocked', 'site_down'] as $impact)<option value="{{ $impact }}" @selected(old('impact', $draft['impact'] ?? 'feature') === $impact)>{{ $adminText('support.recovery.impacts.'.$impact) }}</option>@endforeach</select></div>
                        <div class="wb-field"><label class="wb-label" for="onset">{{ $adminText('support.recovery.onset') }}</label><input class="wb-input" id="onset" name="onset" maxlength="100" value="{{ old('onset', $draft['onset'] ?? '') }}"></div>
                        <div class="wb-field"><label class="wb-label" for="last-action">{{ $adminText('support.recovery.last_action') }}</label><textarea class="wb-textarea" id="last-action" name="last_action" maxlength="2000">{{ old('last_action', $draft['last_action'] ?? '') }}</textarea></div>

                        <div class="wb-field">
                            <label class="wb-label" for="supportType">{{ $adminText('support.col_type') }}</label>
                            <select id="supportType" name="type" class="wb-select">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ $typeLabel($type) }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="supportTitle">{{ $adminText('support.col_subject') }}</label>
                            <input id="supportTitle" class="wb-input" name="title" maxlength="255" value="{{ old('title', $draft['title'] ?? '') }}" required>
                            @error('title')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="supportBody">{{ $adminText('support.body_label') }}</label>
                            <textarea id="supportBody" class="wb-textarea" name="body" rows="8" maxlength="20000" required>{{ old('body', $draft['body'] ?? '') }}</textarea>
                            <p class="wb-text-muted wb-text-sm">{{ $adminText('support.body_help') }} {{ $adminText('support.recovery.no_credentials') }}</p>
                            @error('body')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>


                    @if ($canShare)
                        <div class="wb-card-body wb-stack wb-stack-3" role="group" aria-labelledby="support-report-heading">
                            <h2 class="wb-card-title" id="support-report-heading">{{ $adminText('support.recovery.report') }}</h2>
                            <p>{{ $adminText('support.recovery.optional') }}</p>
                            @foreach(\WebBlocks\Cms\Queries\SystemDiagnosticReportQuery::CATEGORIES as $category)
                                <label class="wb-check"><input type="checkbox" name="categories[]" value="{{ $category }}" @checked(in_array($category, (array) old('categories', $draft['categories'] ?? ['system_summary', 'update_run']), true))><span>{{ app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('diagnostics.category.'.$category, $adminLocaleCode) }}</span></label>
                            @endforeach
                            <div class="wb-field"><label class="wb-label" for="run-id">{{ $adminText('support.recovery.run_id') }}</label><input class="wb-input" id="run-id" name="run_id" type="number" min="1" value="{{ old('run_id', $draft['run_id'] ?? '') }}"></div>
                            <label class="wb-check"><input type="checkbox" name="prepare_consent" value="1"><span>{{ $adminText('support.recovery.prepare_consent') }}</span></label>
                            <button class="wb-btn wb-btn-secondary" type="submit" formaction="{{ route('webblocks.plugins.webblocks_support.support.prepare') }}">{{ $adminText('support.recovery.prepare') }}</button>
                            @if($reportEnvelope && $reportText)
                                <pre><code>{{ $reportText }}</code></pre>
                                <input type="hidden" name="report" value="{{ $reportEnvelope }}">
                                <label class="wb-check"><input type="checkbox" name="diagnostic_consent" value="1"><span>{{ $adminText('support.recovery.share_consent') }}</span></label>
                                <label class="wb-check"><input type="checkbox" name="omit_report" value="1"><span>{{ $adminText('support.recovery.omit') }}</span></label>
                            @endif
                        </div>
                    @endif
                    <div class="wb-card-footer">
                        <button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.submit') }}</button>
                    </div>
                </form>
            </section>
            @if($reportEnvelope && $reportText)
                <form method="post" action="{{ route('admin.system.diagnostics.download') }}">@csrf<input type="hidden" name="report" value="{{ $reportEnvelope }}"><button class="wb-btn wb-btn-secondary" type="submit">{{ $adminText('support.recovery.download') }}</button></form>
            @endif
        @endif
    </div>
@endsection
