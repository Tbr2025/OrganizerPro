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

    public function destroy(Tournament $tournament, TournamentCustomField $customField): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($customField->tournament_id !== $tournament->id, 404);

        $customField->delete();

        return back()->with('success', __('Custom field removed.'));
    }

    /**
     * Validate + normalise the request into a fillable array. Dropdown option
     * values arrive as a comma/newline list and are stored as an array.
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => 'required|string|max:150',
            'type' => 'required|in:' . implode(',', array_keys(TournamentCustomField::TYPES)),
            'section' => 'required|string|max:100',
            'options' => 'nullable|string|max:2000',
            'required' => 'nullable',
            'visible' => 'nullable',
        ]);

        $options = null;
        if ($validated['type'] === 'dropdown' && ! empty($validated['options'])) {
            $options = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $validated['options'])), fn ($v) => $v !== ''));
        }

        return [
            'label' => $validated['label'],
            'type' => $validated['type'],
            'section' => $validated['section'],
            'options' => $options,
            'required' => $request->boolean('required'),
            'visible' => $request->boolean('visible'),
        ];
    }
}
