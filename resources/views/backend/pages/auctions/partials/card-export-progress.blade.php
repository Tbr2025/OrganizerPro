{{-- The card export's progress dialog, plus the Alpine that drives it.

     Included once per page and shared by every control that starts an export. `cardExport`
     is registered as an Alpine store rather than as component data so a page with a dozen
     per-pool Download buttons has ONE dialog and one poll, not one per pool.

     Usage from any component on the page:

         $store.cardExport.start({{ $auction->id }}, playerIds, withResult, templateId, status)

     Pass `null` for playerIds to export every player in the auction, `null` for templateId to
     render the LED wall's card rather than an auction poster design, and 'all' | 'sold' |
     'unsold' for status. --}}

<div x-data x-show="$store.cardExport.open" x-cloak
     class="fixed inset-0 z-[70] flex items-center justify-center p-4"
     style="background: rgba(0,0,0,.55)"
     x-transition.opacity>
    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl overflow-hidden"
         @click.outside="$store.cardExport.dismiss()">

        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="font-semibold text-gray-900 dark:text-white"
                x-text="$store.cardExport.title()"></h3>
            <p class="text-xs text-gray-500 mt-0.5" x-text="$store.cardExport.subtitle()"></p>
        </div>

        <div class="px-5 py-5 space-y-4">
            {{-- The bar. Indeterminate until the first count arrives, because a bar sitting
                 at 0% is indistinguishable from one that is not moving. --}}
            <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500"
                     :class="$store.cardExport.failed() ? 'bg-red-500' : 'bg-emerald-500'"
                     :style="`width: ${Math.max($store.cardExport.percent, 4)}%`"></div>
            </div>

            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold tabular-nums"
                      :class="$store.cardExport.failed() ? 'text-red-600' : 'text-gray-700 dark:text-gray-300'"
                      x-text="$store.cardExport.countLabel()"></span>
                <span class="tabular-nums text-gray-400"
                      x-show="$store.cardExport.total > 0"
                      x-text="`${$store.cardExport.percent}%`"></span>
            </div>

            {{-- Each card is a browser screenshot, so a big export is genuinely minutes.
                 Saying so up front is the difference between waiting and giving up. --}}
            <p class="text-[11px] text-gray-400 leading-relaxed"
               x-show="! $store.cardExport.finished">
                Each card is rendered one at a time, so this takes a few seconds per player.
                You can leave this open — closing it will not stop the export.
            </p>

            <p x-show="$store.cardExport.message" x-cloak
               class="text-[11px] leading-relaxed rounded-lg px-3 py-2"
               :class="$store.cardExport.failed()
                    ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'"
               x-text="$store.cardExport.message"></p>
        </div>

        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-800 flex justify-end gap-2">
            <button type="button" @click="$store.cardExport.dismiss()"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    x-text="$store.cardExport.finished ? 'Close' : 'Hide'"></button>

            {{-- Shown rather than triggered automatically: a download that starts by itself
                 out of a dialog the operator may have walked away from is how you end up with
                 a browser asking about a file nobody is looking at. --}}
            <a x-show="$store.cardExport.downloadUrl" x-cloak
               :href="$store.cardExport.downloadUrl"
               @click="$store.cardExport.open = false"
               class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                Download zip
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('cardExport', {
            open: false,
            finished: false,
            status: 'queued',
            total: 0,
            completed: 0,
            failedCount: 0,
            percent: 0,
            message: '',
            downloadUrl: null,
            _timer: null,
            _auctionId: null,
            _token: null,

            failed() {
                return this.status === 'failed';
            },

            title() {
                if (this.status === 'failed') return 'Export failed';
                if (this.status === 'done') return 'Cards ready';
                return 'Rendering cards…';
            },

            subtitle() {
                if (this.status === 'done') {
                    return this.failedCount
                        ? `${this.completed} rendered, ${this.failedCount} could not be`
                        : 'Every card rendered.';
                }
                if (this.status === 'failed') return 'Nothing was produced.';
                return 'This runs on the server — the zip appears here when it is done.';
            },

            countLabel() {
                if (! this.total) return 'Preparing…';
                return `${this.completed + this.failedCount} of ${this.total} card(s)`;
            },

            /**
             * Begin an export.
             *
             * `players` may be null, meaning every player in the auction. `templateId` may be
             * null, meaning the LED wall's own card rather than a poster design. `status`
             * narrows to an outcome and is applied on the server, so it stays true even if this
             * page's list is stale. A second call while one is running replaces the one on
             * screen — the first keeps rendering on the server, it simply stops being the one
             * being watched.
             */
            async start(auctionId, players, withResult, templateId = null, status = 'all') {
                this._stopPolling();

                this.open = true;
                this.finished = false;
                this.status = 'queued';
                this.total = 0;
                this.completed = 0;
                this.failedCount = 0;
                this.percent = 0;
                this.message = '';
                this.downloadUrl = null;
                this._auctionId = auctionId;
                this._token = null;

                try {
                    const res = await fetch(`/admin/auctions/${auctionId}/cards/export`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            players: players || [],
                            result: withResult ? 1 : 0,
                            template_id: templateId || 0,
                            status: status || 'all',
                        }),
                    });

                    const data = await res.json();

                    if (! res.ok) {
                        this._fail(data.message || 'The export could not be started.');
                        return;
                    }

                    this._apply(data);

                    /* Under QUEUE_CONNECTION=sync the whole render already happened inside that
                       request, so there is nothing left to poll for. */
                    if (! this.finished) {
                        this._token = data.token;
                        this._poll();
                    }
                } catch (e) {
                    this._fail('The export could not be started. Check your connection and try again.');
                }
            },

            dismiss() {
                this.open = false;
                /* Polling continues while the dialog is hidden, so re-opening it — or simply
                   finishing — still lands on the right state. Only a finished export lets go. */
                if (this.finished) this._stopPolling();
            },

            _poll() {
                this._timer = setInterval(async () => {
                    try {
                        const res = await fetch(
                            `/admin/auctions/${this._auctionId}/cards/export/${this._token}`,
                            { headers: { Accept: 'application/json' } }
                        );
                        if (! res.ok) return; // a dropped poll is not worth surfacing
                        this._apply(await res.json());
                        if (this.finished) this._stopPolling();
                    } catch (e) { /* keep polling — the next one usually lands */ }
                }, 1500);
            },

            _stopPolling() {
                if (this._timer) clearInterval(this._timer);
                this._timer = null;
            },

            _apply(data) {
                this.status = data.status;
                this.total = data.total ?? 0;
                this.completed = data.completed ?? 0;
                this.failedCount = data.failed ?? 0;
                this.percent = data.percent ?? 0;
                this.message = data.message || '';
                this.finished = !! data.finished;
                this.downloadUrl = data.download_url || null;
            },

            _fail(message) {
                this._stopPolling();
                this.status = 'failed';
                this.finished = true;
                this.message = message;
            },
        });
    });
</script>
