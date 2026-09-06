/**
 * Turns a template's stored element position into a CSS style object.
 *
 * This is the Javascript twin of `elementStyle()` in `public/auction/live.blade.php`, and it has
 * to keep agreeing with it: both read the same `element_positions` JSON that the Fabric.js editor
 * writes, so a template laid out for the classic wall must place the same element in the same spot
 * here. The keys are the classic wall's keys for exactly that reason.
 *
 * Two rules carried over from the PHP, because both are easy to get wrong:
 *
 *  - **A blank field means "not set", not zero.** The editor stores an untouched width as an empty
 *    string, and a naive check emits `width: px`, which the browser drops — so the element silently
 *    keeps its intrinsic size and nobody can see why.
 *  - **`bottom` only applies when there is no `top`.** The editor writes both; honouring both
 *    stretches the element between them, which is not what was drawn.
 */

/** Present and meaningful — not '', not null, not undefined. */
const has = (p, k) => p[k] !== undefined && p[k] !== '' && p[k] !== null;

const px = (v) => `${v}px`;

/**
 * @param {object} positions the template's element_positions
 * @param {string} key       e.g. 'player_name'
 * @param {object} fallback  used only when the template has nothing for this key
 */
export function elementStyle(positions, key, fallback = {}) {
    const p = { ...fallback, ...(positions?.[key] ?? {}) };
    const style = { position: 'absolute' };

    if (has(p, 'top')) style.top = px(p.top);
    else if (has(p, 'bottom')) style.bottom = px(p.bottom);

    if (has(p, 'left')) style.left = px(p.left);
    if (has(p, 'width')) style.width = px(p.width);
    if (has(p, 'height')) style.height = px(p.height);

    if (has(p, 'fontSize')) style.fontSize = px(p.fontSize);
    if (has(p, 'color')) style.color = p.color;
    if (has(p, 'fontWeight')) style.fontWeight = p.fontWeight;
    // Same rule for the default alignment: the PHP skips 'left'.
    if (has(p, 'textAlign') && p.textAlign !== 'left') style.textAlign = p.textAlign;
    if (has(p, 'lineHeight')) style.lineHeight = p.lineHeight;

    /*
     * 'none' and 'left' are SKIPPED, exactly as the PHP does — and this is not cosmetic.
     *
     * The wall writes `text-transform: uppercase` as a default and then lets the template
     * override it, so the default "only fills the gap". The editor stores an untouched field as
     * the literal string 'none', and emitting `text-transform: none` from that beats the default
     * and quietly un-capitalises every player name on the wall. Which is what happened here
     * before this line existed: the name read in title case next to BASE VALUE in capitals.
     */
    if (has(p, 'textTransform') && p.textTransform !== 'none') style.textTransform = p.textTransform;
    if (has(p, 'letterSpacing') && Number(p.letterSpacing)) style.letterSpacing = px(p.letterSpacing);

    if (has(p, 'bgColor')) {
        const o = p.bgOpacity === undefined || p.bgOpacity === null ? 1 : Number(p.bgOpacity);
        style.backgroundColor = o >= 1 ? p.bgColor : withAlpha(p.bgColor, o);
    }

    if (has(p, 'opacity') && Number(p.opacity) !== 1) style.opacity = String(p.opacity);
    if (has(p, 'zIndex')) style.zIndex = String(p.zIndex);
    if (has(p, 'padding') && Number(p.padding)) style.padding = px(p.padding);
    if (has(p, 'borderRadius') && Number(p.borderRadius)) style.borderRadius = px(p.borderRadius);

    if (has(p, 'borderWidth') && Number(p.borderWidth)) {
        style.border = `${px(p.borderWidth)} ${p.borderStyle || 'solid'} ${p.borderColor || 'currentColor'}`;
    }

    if (has(p, 'boxShadow') && p.boxShadow !== 'none') style.boxShadow = p.boxShadow;
    if (has(p, 'textShadow') && p.textShadow !== 'none') style.textShadow = p.textShadow;
    if (has(p, 'rotation') && Number(p.rotation)) style.transform = `rotate(${p.rotation}deg)`;

    return style;
}

/** Whether the organizer switched this element off in the editor. */
export function isVisible(positions, key) {
    const p = positions?.[key];

    // Absent means "the template does not mention it", which the classic wall treats as visible.
    return p === undefined || p.visible !== false;
}

/**
 * The custom elements an organizer dropped on the canvas — text, shapes and artwork.
 *
 * ONE pass over the keys, in the order the template stores them, because that is the order the
 * classic wall paints them in and elements that overlap without an explicit z-index rely on it.
 * This screen used to render `custom_image_*` only, so a template carrying a caption or a
 * coloured panel arrived on the projector missing pieces of itself.
 *
 * The `visible` flag is honoured for the same reason the classic wall honours it: an element the
 * organizer switched off in the editor has no other way of leaving the wall.
 */
const SHAPE_GEOMETRY = {
    circle: { borderRadius: '50%' },
    pill: { borderRadius: '9999px' },
    'rounded-rect': { borderRadius: '12px' },
    diamond: { transform: 'rotate(45deg)' },
    triangle: { clipPath: 'polygon(50% 0%, 0% 100%, 100% 100%)' },
};

export function customElements(positions) {
    const out = [];

    for (const key of Object.keys(positions ?? {})) {
        const p = positions[key];

        if (!p || p.visible === false) continue;

        if (key.startsWith('custom_text_')) {
            out.push({
                key,
                kind: 'text',
                content: p.content ?? '',
                // An element with no width set is a label, and a label that wraps mid-word is
                // worse than one that runs past its box.
                extra: p.width ? { wordWrap: 'break-word' } : { whiteSpace: 'nowrap' },
            });
        } else if (key.startsWith('custom_shape_')) {
            const shape = p.shapeType ?? 'rectangle';
            const extra = { ...(SHAPE_GEOMETRY[shape] ?? {}) };

            // A triangle is cut out of its box, so its colour has to come from a background
            // rather than a border — the same exception the classic wall makes.
            if (shape === 'triangle') extra.background = p.bgColor ?? 'rgba(255,255,255,0.1)';

            out.push({ key, kind: 'shape', extra });
        } else if (key.startsWith('custom_image_') && p.imagePath) {
            out.push({ key, kind: 'image', path: p.imagePath, extra: { objectFit: 'contain' } });
        }
    }

    return out;
}

export function tableColumns(positions) {
    const raw = positions?.stats_table?.tableColumns;

    if (!raw) return [];

    try {
        return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch (e) {
        return [];
    }
}

/** #rrggbb + opacity → rgba(). Left alone if it is already a function or a name. */
function withAlpha(color, opacity) {
    const m = /^#([0-9a-f]{6})$/i.exec(String(color).trim());

    if (!m) return color;

    const n = parseInt(m[1], 16);

    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${opacity})`;
}
