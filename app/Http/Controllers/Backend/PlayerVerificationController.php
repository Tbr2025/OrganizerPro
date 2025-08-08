<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\PlayerWelcomeMail;
use App\Models\ImageTemplate;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PlayerVerificationController extends Controller
{
    /**
     * Mark the given player's email as verified.
     *
     * @param  \Illuminate\Foundation\Auth\EmailVerificationRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(EmailVerificationRequest $request)
    {
        // The EmailVerificationRequest automatically validates the ID and hash.
        // It also resolves the correct model based on the request.
        // For custom models, ensure the 'id' in the route matches the model's key,
        // and the model implements MustVerifyEmail.

        $player = Player::findOrFail($request->route('id'));

        if ($player->hasVerifiedEmail()) {
            // Email is already verified, redirect to admin panel or success page
            return redirect()->route('backend.pages.profileplayers.show', $player->id)->with('info', 'Your email has already been verified.');
        }

        if ($player->markEmailAsVerified()) {
            event(new Verified($player)); // Dispatch the Verified event
            $player->status = 'verified'; // Update player status
            $player->save();
        }

        // Redirect to the admin panel with the player's profile.
        // Make sure your admin panel route can receive a player ID or slug.
        return redirect()->route('backend.pages.profileplayers.show', $player->id)->with('success', 'Your email has been successfully verified! You can now view your profile.');
    }

    /**
     * Resend the email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resend(Request $request)
    {
        // This method assumes the player trying to resend is authenticated.
        // If your players don't log in, you might need a different approach
        // (e.g., asking for their email again).
        // For now, let's assume this is for an authenticated player.
        $player = $request->user('player'); // Or however you retrieve the player

        if (!$player) {
            return back()->with('error', 'Unable to find player to resend verification.');
        }

        if ($player->hasVerifiedEmail()) {
            return back()->with('info', 'Your email has already been verified.');
        }

        $player->sendEmailVerificationNotification();

        return back()->with('success', 'Verification link sent!');
    }

    protected function generateAppreciationImageGD(Player $player)
    {
        $basePath = public_path('images/themes/');
        $fontPath = public_path('fonts/Oswald-Bold.ttf');
        $outputPath = public_path('appreciations/star_of_the_match_' . $player->id . '.jpg');

        // Load background (Layer 0) and player image (Layer 1)
        $layer0 = imagecreatefrompng($basePath . 'Layer 0.png');
        $actualBasePath = public_path('storage/');
        $absolutePath = $actualBasePath . $player->image_path;

        if (!file_exists($absolutePath)) {
            throw new \Exception('Player image not found: ' . $absolutePath);
        }

        $layer1 = imagecreatefrompng($absolutePath);

        // Dimensions
        $imgWidth = imagesx($layer0);
        $imgHeight = imagesy($layer0);
        $layer1Width = imagesx($layer1);
        $layer1Height = imagesy($layer1);

        // Merge player image (centered)
        $x1 = ($imgWidth - $layer1Width) / 2;
        $y1 = ($imgHeight - $layer1Height) / 2 - 0;
        imagecopy($layer0, $layer1, $x1, $y1, 0, 0, $layer1Width, $layer1Height);

        // Define Colors
        $white = imagecolorallocate($layer0, 255, 255, 255);
        $gold = imagecolorallocate($layer0, 255, 215, 0);
        $purple = imagecolorallocate($layer0, 102, 0, 204);
        $blue = imagecolorallocate($layer0, 0, 51, 102);

        // Font Sizes
        $fontSizeTitle = 56;
        $fontSizeName = 42;
        $fontSizeTeam = 30;
        $fontSizeScore = 28;

        // 1. "STAR OF THE MATCH" angled at bottom
        $title1 = "STAR OF THE MATCH";
        $bboxTitle = imagettfbbox($fontSizeTitle, 0, $fontPath, $title1);
        $xTitle = ($imgWidth - ($bboxTitle[2] - $bboxTitle[0])) / 2;
        $yTitle = 0.9 * $imgHeight - 20; // Adjusted for bottom placement
        imagettftext($layer0, $fontSizeTitle, 0, $xTitle, $yTitle, $gold, $fontPath, $title1);

        // 2. Player Name in white
        $playerName = strtoupper($player->name);
        $bboxName = imagettfbbox($fontSizeName, 0, $fontPath, $playerName);
        $xName = ($imgWidth - ($bboxName[2] - $bboxName[0])) / 2;
        $yName = $y1 + $layer1Height + 40;
        imagettftext($layer0, $fontSizeName, 0, $xName, $yName, $white, $fontPath, $playerName);

        // 3. Team Name in purple box
        $teamName = strtoupper(optional($player->team)->name ?? 'NO TEAM');
        $bboxTeam = imagettfbbox($fontSizeTeam, 0, $fontPath, $teamName);
        $textWidth = $bboxTeam[2] - $bboxTeam[0];
        $xBox = ($imgWidth - $textWidth - 60) / 2;
        $yBox = $yName + 20;
        imagefilledrectangle($layer0, $xBox, $yBox, $xBox + $textWidth + 60, $yBox + 50, $purple);
        imagettftext($layer0, $fontSizeTeam, 0, $xBox + 30, $yBox + 38, $white, $fontPath, $teamName);

        // 4. Score Line in dark blue box
        $score = $player->score_line ?? "120(43) 4s-12 6s-9";
        $bboxScore = imagettfbbox($fontSizeScore, 0, $fontPath, $score);
        $scoreWidth = $bboxScore[2] - $bboxScore[0];
        $xScore = ($imgWidth - $scoreWidth - 40) / 2;
        $yScore = $yBox + 80;
        imagefilledrectangle($layer0, $xScore, $yScore, $xScore + $scoreWidth + 40, $yScore + 40, $blue);
        imagettftext($layer0, $fontSizeScore, 0, $xScore + 20, $yScore + 30, $white, $fontPath, $score);

        // Save image
        imagejpeg($layer0, $outputPath, 90);

        // Cleanup
        imagedestroy($layer0);
        imagedestroy($layer1);
    }





    public function approve(Request $request, Player $player)
    {


        if ($player->welcome_email_sent_at) {
            return back()->with('info', 'Welcome image has already been sent.');
        }

        $template = ImageTemplate::where('category_id', 1)->first();

        if (!$template) {
            return back()->with('error', 'There is no welcome template associated! Please create and try again!');
        }
        if (!$player->allFieldsVerified()) {
            return back()->with('error', 'Cannot approve now. All fields must be verified. Please check the player details');
        }
        if (!$player->image_path) {
            return back()->with('error', 'There is no profile pic found for the player, please check');
        }
        $imagePath = PlayerController::generateWelcomePlayerImageGD($player, $template);

        if (is_array($imagePath)) {
            $imagePath = $imagePath[0] ?? '';
        }

        Mail::to($player->email)->send(new PlayerWelcomeMail($player, $imagePath));

        // Mark email as sent
        $player->update(['welcome_email_sent_at' => now()]);

        if (!$this->checkAuthorization(Auth::user(), ['player.edit'])) {

            abort(403, 'Unauthorized action.');
        }

        if ($player->isApproved()) {
            return back()->with('error', 'Player is already approved.');
        }

        if ($player->isRejected()) {
            return back()->with('error', 'Player has been rejected and cannot be approved.');
        }

        // Ensure the player has a linked user
        $user = $player->user;
        if (!$user) {
            return back()->with('error', 'This player does not have a linked user account.');
        }

        // Assign 'player' role if not already assigned
        if (!$user->hasRole('player')) {
            $playerRole = Role::firstOrCreate(['name' => 'player']);
            $user->assignRole($playerRole);
        }

        // Update player status
        $player->status = 'approved';
        $player->approved_by = auth()->id();
        $player->save();
        $this->generateAppreciationImageGD($player);

        return back()->with('success', 'Player approved and role assigned.');
    }


    /**
     * Reject a player.
     */
    public function reject(Request $request, Player $player)
    {
        if (!$this->checkAuthorization(Auth::user(), ['player.edit'])) {

            abort(403, 'Unauthorized action.');
        }

        if ($player->isApproved()) {
            return back()->with('error', 'Approved players cannot be rejected directly. Unapprove first if needed.');
        }
        if ($player->isRejected()) {
            return back()->with('error', 'Player is already rejected.');
        }

        DB::transaction(function () use ($player) {
            $player->status = 'rejected';
            $player->approved_by = auth()->id(); // Set who rejected it
            $player->save();

            // Optional: If you created a user for this player prematurely, you might delete it here.
            // However, with this flow, a user is only created on approval.

            // Optional: Send a notification to the player about their rejection.
        });

        return back()->with('success', 'Player rejected successfully.');
    }
}
