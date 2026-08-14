/*
 * Short alert tones for the auction screens.
 *
 * SYNTHESISED, not loaded. A two-tone chime is a handful of oscillator settings, so there is no
 * file to fetch — nothing on the venue's uplink, nothing to cache, nothing to 404 on a wall that
 * has been open for six hours. A .wav of the same sound is tens of kilobytes fetched by every
 * screen in the hall for something a browser can generate exactly.
 *
 * ── The autoplay rule, which decides the whole shape of this ──
 *
 * Browsers refuse audio until the user has interacted with the page. A wall on a projector and a
 * team dashboard left open on a table have had no interaction, so the first play is silently
 * ignored — and a silent alarm nobody knows is silent is worse than no alarm at all. So this
 * never pretends: `armed()` reports the truth, and the screens show an "Enable sound" control
 * until a tap has armed it. After that the context stays alive for the session.
 */

let ctx = null;

/** Has the browser actually let us make sound yet? */
export function soundArmed() {
    return !! ctx && ctx.state === 'running';
}

/**
 * Called from a real click. Creates the context and resumes it, which is the only moment a
 * browser will allow.
 */
export function armSound() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;

        if (! Ctx) {
            return false;
        }

        ctx = ctx || new Ctx();

        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        return soundArmed();
    } catch (e) {
        return false;
    }
}

/**
 * One note.
 *
 * Gain is ramped rather than switched: an oscillator started and stopped at full volume clicks,
 * and on a hall PA a click is louder than the note it wraps.
 */
function tone(startAt, freq, duration, volume) {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    // Sine: a bell-ish tone that carries over a room without being shrill on a laptop speaker.
    osc.type = 'sine';
    osc.frequency.setValueAtTime(freq, startAt);

    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start(startAt);
    osc.stop(startAt + duration + 0.02);
}

/**
 * "Ting-tong" — two descending notes, the sound of an announcement about to be made.
 *
 * Deliberately short and only twice: this plays when a player comes up, which on a busy evening
 * is every ninety seconds, and anything longer becomes something the room learns to ignore.
 */
export function playChime() {
    if (! soundArmed()) {
        return false;
    }

    const now = ctx.currentTime;

    tone(now, 988, 0.28, 0.22);          // B5
    tone(now + 0.16, 740, 0.42, 0.22);   // F#5

    return true;
}

/**
 * A more insistent pattern, for something that needs answering rather than noticing.
 *
 * Three rising notes, used where a team has to act — an invitation they have not responded to.
 * It repeats on a timer decided by the caller, not here: how often to nag is a decision about
 * the situation, and burying it in a sound file is how it becomes impossible to change.
 */
export function playAlert() {
    if (! soundArmed()) {
        return false;
    }

    const now = ctx.currentTime;

    tone(now, 660, 0.18, 0.25);
    tone(now + 0.2, 880, 0.18, 0.25);
    tone(now + 0.4, 1175, 0.36, 0.25);

    return true;
}

// Exposed on window as well as exported: the wall and the ticker are Blade pages with inline
// script rather than bundled modules, and they need the same implementation.
window.auctionSound = { armSound, soundArmed, playChime, playAlert };
