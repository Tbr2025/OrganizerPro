{{-- Live show/hide for custom fields that carry conditions.

     This mirrors TournamentCustomField::isVisibleGiven() so the form reacts as it is filled in.
     It is NOT the guarantee: the server re-evaluates the same rules on submit and skips
     validation for anything it decides is hidden, because a browser can be bypassed and a
     required question nobody was asked must never block a submission. --}}
<script>
(function () {
    var wraps = Array.prototype.slice.call(document.querySelectorAll('.cf-wrap[data-cf-conditions]'));
    if (!wraps.length) return;

    var form = wraps[0].closest('form');
    if (!form) return;

    /* The answer to one field, in the same shape the server sees: a string, or an array for the
       multi-choice types. */
    function answerFor(key) {
        // Custom fields are addressed by id; core fields by their input name.
        var nodes = form.querySelectorAll(
            '[name="custom_fields[' + key + ']"], [name="custom_fields[' + key + '][]"], [name="' + key + '"]'
        );
        if (!nodes.length) return '';

        var first = nodes[0];
        var multiple = nodes.length > 1 || first.multiple;

        if (first.type === 'checkbox' && nodes.length > 1) {
            return Array.prototype.filter.call(nodes, function (n) { return n.checked; })
                .map(function (n) { return n.value; });
        }
        if (first.multiple) {
            return Array.prototype.filter.call(first.options, function (o) { return o.selected; })
                .map(function (o) { return o.value; });
        }
        if (first.type === 'radio') {
            var picked = Array.prototype.find.call(nodes, function (n) { return n.checked; });
            return picked ? picked.value : '';
        }
        if (first.type === 'checkbox') {
            return first.checked ? '1' : '0';
        }
        // A hidden 0 companion means the real control is the last node.
        return (multiple ? nodes[nodes.length - 1] : first).value || '';
    }

    function holds(cond) {
        var actual = answerFor(cond.field);
        var expected = String(cond.value == null ? '' : cond.value).toLowerCase();
        var op = cond.operator || 'equals';

        if (Array.isArray(actual)) {
            var hay = actual.map(function (v) { return String(v).toLowerCase(); });
            switch (op) {
                case 'not_equals':
                case 'not_contains': return hay.indexOf(expected) === -1;
                case 'filled': return hay.length > 0;
                case 'empty': return hay.length === 0;
                default: return hay.indexOf(expected) !== -1;
            }
        }

        var a = String(actual).trim().toLowerCase();
        switch (op) {
            case 'not_equals': return a !== expected;
            case 'contains': return expected !== '' && a.indexOf(expected) !== -1;
            case 'not_contains': return expected === '' || a.indexOf(expected) === -1;
            case 'gt': return a !== '' && expected !== '' && parseFloat(a) > parseFloat(expected);
            case 'lt': return a !== '' && expected !== '' && parseFloat(a) < parseFloat(expected);
            case 'filled': return a !== '';
            case 'empty': return a === '';
            default: return a === expected;
        }
    }

    function apply() {
        wraps.forEach(function (wrap) {
            var conds;
            try {
                conds = JSON.parse(wrap.getAttribute('data-cf-conditions') || '[]');
            } catch (e) {
                return;   // a malformed rule must not hide a field the organizer meant to show
            }
            conds = conds.filter(function (c) { return c && c.field; });
            if (!conds.length) return;

            var results = conds.map(holds);
            var mode = wrap.getAttribute('data-cf-match') || 'all';
            var show = mode === 'any' ? results.indexOf(true) !== -1
                     : mode === 'none' ? results.indexOf(true) === -1
                     : results.indexOf(false) === -1;

            wrap.style.display = show ? '' : 'none';

            /*
             * A hidden field must also stop blocking the browser's own validation: `required` on
             * a display:none input makes Chrome refuse to submit while showing nothing the user
             * can act on. The attribute is stashed so it comes back when the field reappears.
             */
            wrap.querySelectorAll('[required], [data-cf-was-required]').forEach(function (input) {
                if (!show) {
                    if (input.hasAttribute('required')) {
                        input.setAttribute('data-cf-was-required', '1');
                        input.removeAttribute('required');
                    }
                } else if (input.getAttribute('data-cf-was-required') === '1') {
                    input.setAttribute('required', 'required');
                    input.removeAttribute('data-cf-was-required');
                }
            });
        });
    }

    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
    apply();
})();
</script>
