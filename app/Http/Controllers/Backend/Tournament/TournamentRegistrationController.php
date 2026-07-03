<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Http\Controllers\Controller;
use App\Mail\RegistrationCorrectionMail;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
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

        $logo = config('settings.site_logo_lite') ?: ($tournament->settings?->logo_url ?? null);

        $html = view('pdf.consent', [
            'tournament' => $tournament,
            'registration' => $registration,
            'logo' => $logo,
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
}
