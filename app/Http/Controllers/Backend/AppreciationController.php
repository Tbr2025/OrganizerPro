<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerAppreciation;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Expr\Match_;

class AppreciationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Tournament $tournament, Match_ $match, Player $player)
    {
        $imageData = $request->input('image_data');
        $canvasData = $request->input('canvas_data');

        // Decode and save image
        $image = str_replace('data:image/jpeg;base64,', '', $imageData);
        $image = base64_decode($image);
        $filename = 'best_player_' . $player->id . '_' . time() . '.jpg';
        $path = 'appreciations/' . $filename;

        Storage::disk('public')->put($path, $image);

        // Save in DB
        PlayerAppreciation::updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'match_id' => $match->id,
                'player_id' => $player->id,
            ],
            [
                'image_path' => $path,
                'positions' => $canvasData,
            ]
        );

        return response()->json(['status' => 'success']);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function saveAppreciations(Request $request, Matches $match)
{
    $types = ['Star of the Match', 'Best Batsman', 'Best Bowler', 'Best Fielder', 'Best Catch', 'Best Six', 'Emerging Player'];

    foreach ($types as $type) {
        if ($request->has("appreciations.$type")) {
            $playerId = $request->input("appreciations.$type");
            if ($playerId) {
                PlayerAppreciation::updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'appreciation_type' => $type,
                    ],
                    [
                        'player_id' => $playerId,
                        'tournament_id' => $match->tournament_id,
                        'title_line1' => $request->input("title_line1.$type"),
                        'title_line2' => $request->input("title_line2.$type"),
                        'font_family' => $request->input("font_family.$type"),
                        'angle' => $request->input("angle.$type", 0),
                        'overlay_name' => $request->input("overlay_name.$type"),
                        // image_path can be auto-generated later
                    ]
                );
            }
        }
    }

    return back()->with('success', 'Appreciations saved.');
}

}
