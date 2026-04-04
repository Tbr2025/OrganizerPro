<?php

namespace App\Services\Share;

use App\Models\Tournament;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\PointTableEntry;

class WhatsAppShareService
{
    /**
     * Generate wa.me share link with pre-filled message
     */
    public function generateShareLink(string $text, ?string $phone = null): string
    {
        $encodedText = urlencode($text);

        if ($phone) {
            // Direct message to specific number
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            return "https://wa.me/{$cleanPhone}?text={$encodedText}";
        }

        // Generic share link (opens WhatsApp chat picker)
        return "https://wa.me/?text={$encodedText}";
    }

    /**
     * Generate tournament announcement share message
     */
    public function getTournamentShareMessage(Tournament $tournament): string
    {
        $settings = $tournament->settings;

        $message = "*{$tournament->name}*\n\n";

        if ($settings && $settings->description) {
            $message .= "{$settings->description}\n\n";
        }

        // Dates
        if ($tournament->start_date && $tournament->end_date) {
            $message .= "📅 *Dates:* {$tournament->start_date->format('M d')} - {$tournament->end_date->format('M d, Y')}\n";
        }

        // Location
        if ($tournament->location) {
            $message .= "📍 *Location:* {$tournament->location}\n";
        }

        $message .= "\n🏏 *Register Now:*\n";
        $message .= $tournament->public_url . "\n";

        // Registration links
        if ($settings && $settings->player_registration_open) {
            $message .= "\n👤 Player Registration:\n{$tournament->player_registration_url}";
        }

        if ($settings && $settings->team_registration_open) {
            $message .= "\n👥 Team Registration:\n{$tournament->team_registration_url}";
        }

        // Contact info
        if ($settings && ($settings->contact_phone || $settings->contact_email)) {
            $message .= "\n\n📞 *Contact:*\n";
            if ($settings->contact_phone) {
                $message .= "Phone: {$settings->contact_phone}\n";
            }
            if ($settings->contact_email) {
                $message .= "Email: {$settings->contact_email}";
            }
        }

        return $message;
    }

    /**
     * Generate tournament share link
     */
    public function getTournamentShareLink(Tournament $tournament, ?string $phone = null): string
    {
        return $this->generateShareLink($this->getTournamentShareMessage($tournament), $phone);
    }

    /**
     * Generate match announcement share message
     */
    public function getMatchShareMessage(Matches $match): string
    {
        $tournament = $match->tournament;
        $teamA = $match->teamA?->name ?? 'TBD';
        $teamB = $match->teamB?->name ?? 'TBD';

        $message = "🏏 *{$tournament->name}*\n\n";
        $message .= "*{$match->stage_display}*\n";
        $message .= "Match #{$match->match_number}\n\n";

        $message .= "*{$teamA}*\n";
        $message .= "⚔️ VS ⚔️\n";
        $message .= "*{$teamB}*\n\n";

        if ($match->match_date) {
            $message .= "📅 *Date:* {$match->match_date->format('D, M d, Y')}\n";
        }

        if ($match->start_time) {
            $message .= "🕐 *Time:* {$match->start_time}\n";
        }

        $venue = $match->ground?->name ?? $match->venue;
        if ($venue) {
            $message .= "📍 *Venue:* {$venue}\n";
        }

        $message .= "\n🔗 *View Details:*\n{$match->public_url}";

        return $message;
    }

    /**
     * Generate match share link
     */
    public function getMatchShareLink(Matches $match, ?string $phone = null): string
    {
        return $this->generateShareLink($this->getMatchShareMessage($match), $phone);
    }

    /**
     * Generate match result share message
     */
    public function getResultShareMessage(Matches $match): string
    {
        $tournament = $match->tournament;
        $result = $match->result;
        $teamA = $match->teamA;
        $teamB = $match->teamB;

        $message = "🏏 *Match Result*\n\n";
        $message .= "*{$tournament->name}*\n";
        $message .= "{$match->stage_display}\n\n";

        // Determine batting order: first batting team shows first
        $teamABatsFirst = $result?->team_a_batting_first ?? true;
        $firstTeam = $teamABatsFirst ? $teamA : $teamB;
        $secondTeam = $teamABatsFirst ? $teamB : $teamA;
        $firstScore = $result ? ($teamABatsFirst
            ? $this->formatScore($result->team_a_score, $result->team_a_wickets, $result->team_a_overs)
            : $this->formatScore($result->team_b_score, $result->team_b_wickets, $result->team_b_overs)) : '-';
        $secondScore = $result ? ($teamABatsFirst
            ? $this->formatScore($result->team_b_score, $result->team_b_wickets, $result->team_b_overs)
            : $this->formatScore($result->team_a_score, $result->team_a_wickets, $result->team_a_overs)) : '-';

        // Scores (first batting team first)
        if ($firstTeam) {
            $winner = $match->winner_team_id === $firstTeam->id ? ' 🏆' : '';
            $message .= "*{$firstTeam->name}*: {$firstScore}{$winner}\n";
        }

        if ($secondTeam) {
            $winner = $match->winner_team_id === $secondTeam->id ? ' 🏆' : '';
            $message .= "*{$secondTeam->name}*: {$secondScore}{$winner}\n";
        }

        // Result summary
        if ($result && $result->result_summary) {
            $message .= "\n🎯 *{$result->result_summary}*\n";
        }

        // Awards
        $awards = $match->matchAwards()->with('player', 'tournamentAward')->get();
        if ($awards->count() > 0) {
            $message .= "\n🏅 *Awards:*\n";
            foreach ($awards as $award) {
                $awardName = $award->tournamentAward?->name ?? 'Award';
                $playerName = $award->player?->name ?? 'Unknown';
                $message .= "• {$awardName}: {$playerName}\n";
            }
        }

        $message .= "\n🔗 *Full Summary:*\n" . route('public.match.summary', $match->slug);

        return $message;
    }

    /**
     * Generate result share link
     */
    public function getResultShareLink(Matches $match, ?string $phone = null): string
    {
        return $this->generateShareLink($this->getResultShareMessage($match), $phone);
    }

    /**
     * Generate point table share message
     */
    public function getPointTableShareMessage(Tournament $tournament, ?int $groupId = null): string
    {
        $message = "🏏 *{$tournament->name}*\n";
        $message .= "📊 *Point Table*\n\n";

        $query = PointTableEntry::where('tournament_id', $tournament->id)
            ->with('team')
            ->orderByDesc('points')
            ->orderByDesc('net_run_rate');

        if ($groupId) {
            $query->where('tournament_group_id', $groupId);
        }

        $entries = $query->get();

        $message .= "```\n";
        $message .= str_pad('#', 3) . str_pad('Team', 15) . str_pad('P', 4) . str_pad('W', 4) . str_pad('L', 4) . str_pad('Pts', 5) . "NRR\n";
        $message .= str_repeat('-', 45) . "\n";

        foreach ($entries as $index => $entry) {
            $position = $index + 1;
            $teamName = substr($entry->team?->short_name ?? $entry->team?->name ?? 'TBD', 0, 12);
            $nrr = $entry->net_run_rate >= 0 ? '+' . number_format($entry->net_run_rate, 2) : number_format($entry->net_run_rate, 2);

            $message .= str_pad($position, 3);
            $message .= str_pad($teamName, 15);
            $message .= str_pad($entry->matches_played, 4);
            $message .= str_pad($entry->won, 4);
            $message .= str_pad($entry->lost, 4);
            $message .= str_pad($entry->points, 5);
            $message .= $nrr . "\n";
        }

        $message .= "```\n";
        $message .= "\n🔗 *View Full Table:*\n" . route('public.tournament.point-table', $tournament->slug);

        return $message;
    }

    /**
     * Generate point table share link
     */
    public function getPointTableShareLink(Tournament $tournament, ?string $phone = null, ?int $groupId = null): string
    {
        return $this->generateShareLink($this->getPointTableShareMessage($tournament, $groupId), $phone);
    }

    /**
     * Generate registration link share message
     */
    public function getRegistrationShareMessage(Tournament $tournament, string $type = 'player'): string
    {
        $message = "🏏 *{$tournament->name}*\n\n";
        $message .= "📝 *Registration Open!*\n\n";

        if ($type === 'player') {
            $message .= "👤 Register as a player:\n{$tournament->player_registration_url}";
        } else {
            $message .= "👥 Register your team:\n{$tournament->team_registration_url}";
        }

        $settings = $tournament->settings;
        if ($settings && $settings->registration_deadline) {
            $message .= "\n\n⏰ *Deadline:* {$settings->registration_deadline->format('M d, Y')}";
        }

        return $message;
    }

    /**
     * Generate registration share link
     */
    public function getRegistrationShareLink(Tournament $tournament, string $type = 'player', ?string $phone = null): string
    {
        return $this->generateShareLink($this->getRegistrationShareMessage($tournament, $type), $phone);
    }

    /**
     * Generate champions announcement share message
     */
    public function getChampionsShareMessage(Tournament $tournament): string
    {
        $champion = $tournament->champion;
        $runnerUp = $tournament->runnerUp;

        $message = "🏏 *{$tournament->name}*\n\n";
        $message .= "🏆 *TOURNAMENT COMPLETED!*\n\n";

        if ($champion) {
            $message .= "🥇 *CHAMPIONS:*\n";
            $message .= "*{$champion->name}*\n\n";
        }

        if ($runnerUp) {
            $message .= "🥈 *Runners Up:*\n";
            $message .= "{$runnerUp->name}\n\n";
        }

        $message .= "Congratulations to all participants! 🎉\n\n";
        $message .= "🔗 *View Full Results:*\n{$tournament->public_url}";

        return $message;
    }

    /**
     * Generate champions share link
     */
    public function getChampionsShareLink(Tournament $tournament, ?string $phone = null): string
    {
        return $this->generateShareLink($this->getChampionsShareMessage($tournament), $phone);
    }

    /**
     * Format score for display
     */
    protected function formatScore(?int $runs, ?int $wickets, $overs): string
    {
        if ($runs === null) {
            return '-';
        }

        $score = (string) $runs;

        if ($wickets !== null) {
            $score .= '/' . $wickets;
        }

        if ($overs !== null) {
            $score .= ' (' . number_format((float) $overs, 1) . ')';
        }

        return $score;
    }
}
