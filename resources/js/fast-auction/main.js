import { createApp } from 'vue';

import TeamBidding from './screens/TeamBidding.vue';
import Wall from './screens/Wall.vue';
import Panel from './screens/Panel.vue';

/**
 * Fast Auction's entry point.
 *
 * There is no client-side router. The server already serves a separate URL per screen, each
 * behind its own middleware, so the screen is chosen by an attribute Blade wrote — and the role
 * that decides what a viewer may see is settled server-side, before any of this runs. Editing
 * `data-screen` by hand gets you a component with no data in it and endpoints that refuse you.
 *
 * Each screen is a dynamic import so Rollup splits it into its own chunk: a wall viewer never
 * downloads the bidding code, and there is still only one build.
 */
const SCREENS = {
    'team-bidding': () => Promise.resolve({ default: TeamBidding }),
    'wall': () => Promise.resolve({ default: Wall }),
    'panel': () => Promise.resolve({ default: Panel }),
};

function boot() {
    const el = document.getElementById('fast-auction');

    if (!el) {
        return;
    }

    const load = SCREENS[el.dataset.screen];

    if (!load) {
        // Say so rather than leaving an empty div: a blank screen mid-auction is the one thing
        // nobody can debug from the floor.
        el.textContent = `Fast Auction: no screen registered for "${el.dataset.screen}".`;

        return;
    }

    let bootData = {};

    try {
        bootData = JSON.parse(document.getElementById('fast-auction-boot')?.textContent || '{}');
    } catch (e) {
        el.textContent = 'Fast Auction: could not read the bootstrap data for this screen.';

        return;
    }

    load().then((module) => {
        createApp(module.default, { boot: bootData }).mount(el);
    });
}

boot();
