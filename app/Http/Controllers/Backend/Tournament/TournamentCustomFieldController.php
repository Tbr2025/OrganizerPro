<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentCustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentCustomFieldController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $data = $this->validated($request);
        $data['tournament_id'] = $tournament->id;
        $data['sort_order'] = ($tournament->customFields()->max('sort_order') ?? 0) + 1;

        TournamentCustomField::create($data);

        return back()->with('success', __('Custom field added.'));
    }

    public function update(Request $request, Tournament $tournament, TournamentCustomField $customField): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($customField->tournament_id !== $tournament->id, 404);

        $customField->update($this->validated($request));

        return back()->with('success', __('Custom field updated.'));
    }

    /**
     * Enable or disable one field in a single click.
     *
     * Separate from update() because that validates the whole definition — label, type, section
     * — so a toggle posted on its own would fail validation on fields it was not trying to
     * change. `visible` is what both the registration form and the admin data area filter on, so
     * flipping it here is what makes a field appear or disappear in both places.
     */
    public function toggle(Tournament $tournament, TournamentCustomField $customField): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($customField->tournament_id !== $tournament->id, 404);

        $customField->update(['visible' => ! $customField->visible]);

        return back()->with('success', $customField->visible
            ? __('":label" is now shown on the registration form.', ['label' => $customField->label])
            : __('":label" is now hidden. Answers already collected are kept.', ['label' => $customField->label]));
    }

    public function destroy(Tournament $tournament, TournamentCustomField $customField): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($customField->tournament_id !== $tournament->id, 404);

        $customField->delete();

        return back()->with('success', __('Custom field removed.'));
    }

    /**
     * Validate + normalise the request into a fillable array.
     *
     * Everything added in 2026-08 is optional. A form that posts only the original seven inputs
     * (the shape the builder used before validation and conditions existed) still saves, and the
     * new columns stay null — which the model reads as "no extra rules, always visible".
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'form' => 'required|in:player,team',
            'label' => 'required|string|max:150',
            'type' => 'required|in:' . implode(',', array_keys(TournamentCustomField::TYPES)),
            'section' => 'required|string|max:100',
            'options' => 'nullable|string|max:2000',
            'help_text' => 'nullable|string|max:500',
            'required' => 'nullable',
            'visible' => 'nullable',

            // Curated validation. Each key is read by name in
            // TournamentCustomField::validationRules() — an unknown key is simply ignored, so a
            // stray input can never become a validation rule.
            'validation' => 'nullable|array',
            'validation.min' => 'nullable|numeric',
            'validation.max' => 'nullable|numeric',
            'validation.minlength' => 'nullable|integer|min:0|max:5000',
            'validation.maxlength' => 'nullable|integer|min:1|max:5000',
            'validation.pattern' => 'nullable|string|max:200',
            'validation.after' => 'nullable|date',
            'validation.before' => 'nullable|date',
            'validation.min_choices' => 'nullable|integer|min:0|max:100',
            'validation.max_choices' => 'nullable|integer|min:1|max:100',
            'validation.file_types' => 'nullable|string|max:200',
            'validation.file_max_kb' => 'nullable|integer|min:1|max:20480',

            // Conditional visibility.
            'condition_match' => 'nullable|in:' . implode(',', [
                TournamentCustomField::MATCH_ALL,
                TournamentCustomField::MATCH_ANY,
                TournamentCustomField::MATCH_NONE,
            ]),
            'conditions' => 'nullable|array|max:20',
            'conditions.*.field' => 'nullable|string|max:100',
            'conditions.*.operator' => 'nullable|in:' . implode(',', array_keys(TournamentCustomField::OPERATORS)),
            'conditions.*.value' => 'nullable|string|max:200',
        ]);

        $type = $validated['type'];

        /*
         * Options belong to every choice type now, not only `dropdown`. A radio group whose
         * options were dropped on save would render as an empty fieldset.
         */
        $options = null;
        if (in_array($type, TournamentCustomField::OPTION_TYPES, true) && ! empty($validated['options'])) {
            $options = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $validated['options'])),
                fn ($v) => $v !== ''
            ));
        }

        // Drop empty rule boxes rather than storing "" — validationRules() checks for presence,
        // and an empty string would otherwise become `min:` and throw at validation time.
        $rules = array_filter(
            $validated['validation'] ?? [],
            fn ($v) => $v !== null && $v !== ''
        );

        // A condition row with no field chosen is an unfinished row in the builder, not a rule.
        $conditions = array_values(array_filter(
            $validated['conditions'] ?? [],
            fn ($c) => is_array($c) && ! empty($c['field'])
        ));

        $conditions = array_map(fn ($c) => [
            'field' => (string) $c['field'],
            'operator' => $c['operator'] ?? 'equals',
            'value' => (string) ($c['value'] ?? ''),
        ], $conditions);

        return [
            'form' => $validated['form'],
            'label' => $validated['label'],
            'type' => $type,
            'section' => $validated['section'],
            'options' => $options,
            'help_text' => $validated['help_text'] ?? null,
            'validation' => $rules === [] ? null : $rules,
            'conditions' => $conditions === [] ? null : $conditions,
            'condition_match' => $conditions === [] ? null : ($validated['condition_match'] ?? TournamentCustomField::MATCH_ALL),
            'required' => $request->boolean('required'),
            'visible' => $request->boolean('visible'),
        ];
    }
}
