{{--
    "This costs N credits · your balance M" for a feature on a tournament.
    Renders nothing when the organization does not pay (package billing,
    a free feature, or a Superadmin acting).
--}}
@php
    $creditSvc = app(\App\Services\Credits\CreditService::class);
    $creditOrg = $tournament->organization;
    $creditQuote = $creditOrg ? $creditSvc->quote($creditOrg, $feature) : ['cost' => 0];
@endphp
@if($creditQuote['cost'] > 0)
    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-2 text-sm text-indigo-900 dark:text-indigo-100">
        <span>{{ $label ?? __('Each poster costs :price.', ['price' => $creditSvc->format($creditQuote['cost'])]) }}</span>
        <span class="text-indigo-700 dark:text-indigo-300">{{ __('Balance: :b', ['b' => $creditSvc->format($creditSvc->balance($creditOrg))]) }}</span>
        @can('credits.view')<a href="{{ route('admin.credits.index') }}" class="ml-auto underline">{{ __('Top up') }}</a>@endcan
    </div>
@endif
