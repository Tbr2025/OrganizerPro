{{--
    Which template each broadcast screen renders with.

    Shared by Create and Edit deliberately. The LED picker used to live only on Edit — and
    buried in "Financial Rules", where a screen choice has no business being — so an auction
    made through Create always fell back to the default wall no matter how many templates
    existed, and `store()` validated `auction_template_id` that the form never sent.

    Expects: $auctionId (nullable on create), $selectedDisplay, $selectedTicker,
             $displayTemplates, $tickerTemplates
--}}
@php
    $auctionId = $auctionId ?? null;
    $displayTemplates = $displayTemplates ?? collect();
    $tickerTemplates = $tickerTemplates ?? collect();
@endphp

<div class="mb-6 p-5 rounded-2xl border border-indigo-200 dark:border-indigo-800/60 bg-indigo-50 dark:bg-indigo-900/10">
    <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-200">Broadcast screens</h3>
    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 mb-4">
        The hall wall and the stream ticker are two separate screens, so each picks its own
        template. Leave either on the default and it keeps its built-in look. Switching a
        template never changes the URL — whatever is already open keeps working.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="auction_template_id" class="form-label text-xs">LED Wall Template</label>
            <select name="auction_template_id" id="auction_template_id" class="form-control">
                <option value="">— Use the default —</option>
                @foreach($displayTemplates as $tpl)
                    <option value="{{ $tpl->id }}"
                        {{ (string) $selectedDisplay === (string) $tpl->id ? 'selected' : '' }}>
                        {{ $tpl->name }} ({{ $tpl->render_mode === 'html' ? 'HTML' : 'positioned' }})
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                @if($auctionId)
                    Shown at <code>/auction/{{ $auctionId }}/live</code>.
                @else
                    The full-screen wall for the hall projector.
                @endif
            </p>
            @if($displayTemplates->isEmpty())
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    No wall templates yet — build one under Auctions &rarr; LED Templates.
                </p>
            @endif
        </div>

        <div>
            <label for="ticker_template_id" class="form-label text-xs">Broadcast Ticker Template</label>
            <select name="ticker_template_id" id="ticker_template_id" class="form-control">
                <option value="">— Use the default —</option>
                @foreach($tickerTemplates as $tpl)
                    <option value="{{ $tpl->id }}"
                        {{ (string) $selectedTicker === (string) $tpl->id ? 'selected' : '' }}>
                        {{ $tpl->name }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                @if($auctionId)
                    Shown at <code>/auction/{{ $auctionId }}/ticker</code>.
                @else
                    The lower-third strip an OBS scene points at.
                @endif
            </p>
            @if($tickerTemplates->isEmpty())
                {{-- Ticker templates are HTML-only by definition, so say so here rather than
                     letting someone build a positioned one and find out at save time. --}}
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    No ticker templates yet — build one under Auctions &rarr; LED Templates,
                    choosing type <strong>Broadcast Ticker</strong>.
                </p>
            @endif
        </div>
    </div>
</div>
