<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::latest()->paginate(10);

        return view('backend.pages.tournaments.index', [
            'tournaments' => $tournaments,
            'breadcrumbs' => [
                'title' => __('Tournaments'),
            ],
        ]);
    }

    public function create()
    {
        return view('backend.pages.tournaments.create', [
            'breadcrumbs' => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Create Tournament'],
            ],
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
        ]);


        Tournament::create($validated);

        return redirect()->route('admin.tournaments.index')->with('success', 'Tournament created successfully.');
    }
    public function edit(Tournament $tournament)
    {
        return view('backend.pages.tournaments.edit', [
            'tournament' => $tournament,
            'breadcrumbs' => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Edit Tournament'],
            ],
        ]);
    }

    public function destroy(Tournament $tournament)
    {
        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament deleted successfully.');
    }


    // store, update, and destroy remain unchanged
}
