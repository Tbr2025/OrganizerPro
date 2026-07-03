<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Http\Controllers\Controller;
use App\Mail\ApplicationUnderReviewMail;
use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationCorrectionMail;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Notification\TournamentNotificationService;
use App\Services\Tournament\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {}

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $type = $request->get('type');
        $status = $request->get('status', 'pending');

        $query = $tournament->registrations()
            ->with(['player', 'processedBy', 'actualTeam'])
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $registrations = $query->paginate(20);

        return view('backend.pages.tournaments.registrations.index', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'pendingCount' => $tournament->registrations()->pending()->count(),
            'approvedCount' => $tournament->registrations()->approved()->count(),
            'rejectedCount' => $tournament->registrations()->rejected()->count(),
            'cancelledCount' => $tournament->registrations()->cancelled()->count(),
            'breadcrumbs' => [
                'title' => __('Registrations'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function show(Tournament $tournament, TournamentRegistration $registration): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        // Get approved registered players with linked users for captain selection
        $approvedPlayerUsers = collect();
        if ($registration->isTeamRegistration() && $registration->isPending()) {
            $approvedPlayerUsers = TournamentRegistration::where('tournament_id', $tournament->id)
                ->approved()
                ->players()
                ->whereHas('player.user')
                ->with('player.user')
                ->get()
                ->map(fn ($reg) => $reg->player->user)
                ->unique('id')
                ->values();
        }

        return view('backend.pages.tournaments.registrations.show', [
            'tournament' => $tournament,
            'registration' => $registration->load([
                'player.battingProfile', 'player.bowlingProfile', 'player.playerType',
                'player.kitSize', 'player.location', 'processedBy', 'actualTeam',
            ]),
            'approvedPlayerUsers' => $approvedPlayerUsers,
            'breadcrumbs' => [
                'title' => __('Registration Details'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                    ['label' => __('Registrations'), 'url' => route('admin.tournaments.registrations.index', $tournament)],
                ],
            ],
        ]);
    }

    public function approve(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if (!$registration->isPending()) {
            return redirect()->back()->with('error', __('This registration has already been processed.'));
        }

        if ($registration->isPlayerRegistration()) {
            $result = $this->registrationService->approvePlayerRegistration($registration, Auth::id());
        } else {
            $captainUserId = $request->input('captain_user_id') ? (int) $request->input('captain_user_id') : null;
            $result = $this->registrationService->approveTeamRegistration($registration, Auth::id(), $captainUserId);
        }

        if ($result) {
            return redirect()->back()->with('success', __('Registration approved successfully.'));
        }

        return redirect()->back()->with('error', __('Failed to approve registration.'));
    }

    public function reject(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if (!$registration->isPending()) {
            return redirect()->back()->with('error', __('This registration has already been processed.'));
        }

        $remarks = $request->input('remarks');

        $result = $this->registrationService->rejectRegistration($registration, Auth::id(), $remarks);

        if ($result) {
            return redirect()->back()->with('success', __('Registration rejected.'));
        }

        return redirect()->back()->with('error', __('Failed to reject registration.'));
    }

    public function cancel(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if (!$registration->isPending()) {
            return redirect()->back()->with('error', __('Only pending registrations can be cancelled.'));
        }

        $registration->update([
            'status' => 'cancelled',
            'processed_at' => now(),
            'processed_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', __('Registration cancelled.'));
    }

    public function forceDelete(Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // If approved team registration, delete the linked ActualTeam first (cascade handles pivot)
        if ($registration->isTeamRegistration() && $registration->isApproved() && $registration->actualTeam) {
            $registration->actualTeam->delete();
        }

        $registration->delete();

        return redirect()->route('admin.tournaments.registrations.index', $tournament)
            ->with('success', __('Registration deleted successfully.'));
    }

    public function bulkApprove(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $ids = $request->input('registration_ids', []);

        $approved = 0;
        foreach ($ids as $id) {
            $registration = TournamentRegistration::find($id);
            if ($registration && $registration->isPending()) {
                if ($registration->isPlayerRegistration()) {
                    $this->registrationService->approvePlayerRegistration($registration, Auth::id());
                } else {
                    $this->registrationService->approveTeamRegistration($registration, Auth::id());
                }
                $approved++;
            }
        }

        return redirect()->back()->with('success', __(':count registrations approved.', ['count' => $approved]));
    }

    /**
     * Save per-field verification state and optionally email the applicant a
     * correction request listing the fields that were NOT verified.
     */
    public function updateVerification(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($registration->tournament_id !== $tournament->id, 404);

        $allFields = array_values((array) $request->input('all_fields', []));
        $checked = array_values((array) $request->input('verified', []));
        // Only keep verified keys that are actually part of the displayed set.
        $verified = array_values(array_intersect($allFields, $checked));
        $registration->update(['verified_fields' => $verified]);

        if ($request->input('action') === 'send') {
            $email = $registration->player?->email ?? $registration->captain_email;
            if (! $email) {
                return back()->with('error', __('No email address on file for this registration.'));
            }

            $unverifiedKeys = array_values(array_diff($allFields, $verified));
            $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
            $labels = array_map(fn ($k) => $fieldConfig[$k]['label'] ?? ucwords(str_replace('_', ' ', $k)), $unverifiedKeys);

            Mail::to($email)->send(new RegistrationCorrectionMail($tournament, $registration, $labels, $request->input('note')));

            return back()->with('success', __('Verification saved and correction request emailed to :email.', ['email' => $email]));
        }

        return back()->with('success', __('Field verification saved.'));
    }

    /**
     * Stream the signed consent as a PDF (YouSelects logo, T&C snapshot, signer
     * name + timestamp). Generated on demand via Browsershot.
     */
    public function downloadConsent(Tournament $tournament, TournamentRegistration $registration): Response
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);
        abort_if($registration->tournament_id !== $tournament->id, 404);
        abort_if(! $registration->consent_signed_at, 404, 'No signed consent on file for this registration.');

        // Embed logos as base64 data URIs — headless Chrome can't reliably fetch
        // remote/relative image URLs, which is why the logo was blank.
        $appLogo = $this->logoDataUri(config('settings.site_logo_lite') ?: config('settings.site_logo_dark'))
            ?? $this->logoDataUri('images/logo/lara-dashboard.png');
        $tournamentLogo = $this->logoDataUri($tournament->settings?->logo);

        $html = view('pdf.consent', [
            'tournament' => $tournament,
            'registration' => $registration,
            'appLogo' => $appLogo,
            'tournamentLogo' => $tournamentLogo,
            'appName' => config('app.name'),
            'signerName' => $registration->consent_name,
            'signedAt' => $registration->consent_signed_at,
            'ip' => $registration->consent_ip,
            'content' => $registration->consent_snapshot ?: ($tournament->settings?->terms_and_conditions_content ?? ''),
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->margins(12, 12, 12, 12)
            ->pdf();

        $filename = 'consent-' . $registration->id . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Resend the welcome card email to the player (bypasses the already-sent guard).
     */
    public function resendWelcome(Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($registration->tournament_id !== $tournament->id, 404);

        if (! $registration->isPlayerRegistration()) {
            return back()->with('error', __('Welcome cards are only available for player registrations.'));
        }

        $sent = app(TournamentNotificationService::class)->sendWelcomeCard($registration, true, true);

        return $sent
            ? back()->with('success', __('Welcome card resent to the player.'))
            : back()->with('error', __('Could not send the welcome card. Ensure a welcome-card template exists and the player has an email address.'));
    }

    /**
     * Resend the confirmation email — approval email when approved, otherwise the
     * application-received (under review) email.
     */
    public function resendConfirmation(Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        abort_if($registration->tournament_id !== $tournament->id, 404);

        $email = $registration->player?->email ?? $registration->captain_email;
        if (! $email) {
            return back()->with('error', __('No email address on file for this registration.'));
        }

        $name = $registration->player?->name ?? $registration->captain_name ?? 'Applicant';

        if ($registration->status === 'approved') {
            Mail::to($email)->send(new RegistrationApprovedMail($tournament, $registration));
            $message = __('Approval confirmation resent to :email.', ['email' => $email]);
        } else {
            Mail::to($email)->send(new ApplicationUnderReviewMail($tournament, $registration, $name));
            $message = __('Confirmation email resent to :email.', ['email' => $email]);
        }

        return back()->with('success', $message);
    }

    /**
     * Resolve a logo (stored path, /storage URL, full URL, or public asset) to a
     * base64 data URI so it embeds reliably in a Browsershot-rendered PDF.
     */
    protected function logoDataUri(?string $src): ?string
    {
        if (! $src) {
            return null;
        }

        $path = null;
        if (str_starts_with($src, 'http')) {
            $urlPath = parse_url($src, PHP_URL_PATH) ?: '';
            if (str_contains($urlPath, '/storage/')) {
                $rel = ltrim(substr($urlPath, strpos($urlPath, '/storage/') + strlen('/storage/')), '/');
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($rel)) {
                    $path = \Illuminate\Support\Facades\Storage::disk('public')->path($rel);
                }
            } else {
                $cand = public_path(ltrim($urlPath, '/'));
                $path = is_file($cand) ? $cand : null;
            }
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($src)) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($src);
        } elseif (is_file(public_path($src))) {
            $path = public_path($src);
        }

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
