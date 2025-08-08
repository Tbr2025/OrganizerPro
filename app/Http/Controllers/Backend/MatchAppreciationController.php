<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MatchAppreciation;
use App\Models\Matches;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MatchAppreciationController extends Controller
{


    public function index(Request $request)
    {
        $query = MatchAppreciation::with(['match', 'player']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('player', fn($p) => $p->where('name', 'like', "%{$search}%"))
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $appreciations = $query->latest()->paginate(15);

        $breadcrumbs = [
            ['label' => 'Matches', 'url' => route('admin.matches.index')],
            ['label' => 'All Appreciations'],
        ];

        return view('backend.pages.match_appreciations.index', compact('appreciations', 'breadcrumbs'));
    }

    public function create(Matches $match)
    {

        return view('backend.pages.match_appreciations.create', [
            'match' => $match,
            'players' => Player::all(),
            'titles' => [
                'Man of the Match',
                'Best Batsman',
                'Best Bowler',
                'Best Fielder',
                'Fighter of the Match',
                'Super Sixer',
            ],
        ]);
    }

    public function store(Request $request, Matches $match)
    {
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:6144',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('appreciations', 'public');
        }

        MatchAppreciation::create([
            'match_id' => $match->id,
            'player_id' => $validated['player_id'],
            'title' => $validated['title'],
            'image_path' => $imagePath,
        ]);

        return redirect()->route('admin.matches.show', $match)->with('success', 'Appreciation added successfully.');
    }

    public function destroy(MatchAppreciation $appreciation)
    {
        if ($appreciation->image_path) {
            Storage::disk('public')->delete($appreciation->image_path);
        }

        $appreciation->delete();

        return back()->with('success', 'Appreciation deleted.');
    }
}
