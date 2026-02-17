{{-- Minimal Summary Poster Template --}}
<div class="poster-minimal" style="
    width: 1080px;
    height: 1350px;
    background: #ffffff;
    position: relative;
    overflow: hidden;
    font-family: 'Roboto', sans-serif;
    color: #1a1a1a;
">
    {{-- Accent Bar Top --}}
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 8px; background: {{ $secondaryColor }};"></div>

    {{-- Tournament Header --}}
    <div style="padding: 60px 80px 40px; border-bottom: 1px solid #eee;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 20px;">
                @if($settings?->logo)
                    <img src="{{ Storage::url($settings->logo) }}" style="width: 70px; height: 70px; border-radius: 12px; object-fit: cover;">
                @endif
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; margin: 0; color: #1a1a1a;">{{ $tournament->name }}</h1>
                    <div style="font-size: 14px; color: #666; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px;">{{ $match->stage_display }}</div>
                </div>
            </div>
            @if($match->match_date)
                <div style="text-align: right; color: #666;">
                    <div style="font-size: 20px; font-weight: 600;">{{ $match->match_date->format('d') }}</div>
                    <div style="font-size: 14px;">{{ $match->match_date->format('M Y') }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Match Result Title --}}
    <div style="text-align: center; padding: 50px 0 30px;">
        <h2 style="font-size: 16px; font-weight: 600; color: #999; letter-spacing: 4px; margin: 0;">MATCH RESULT</h2>
    </div>

    {{-- Teams Section --}}
    <div style="display: flex; justify-content: center; align-items: stretch; padding: 0 80px; gap: 40px;">
        {{-- Team A --}}
        <div style="flex: 1; max-width: 350px; text-align: center; padding: 40px 30px; {{ $teamAWinner ? 'background: #f0fdf4; border-radius: 16px;' : '' }}">
            <div style="width: 140px; height: 140px; margin: 0 auto 25px; background: #f5f5f5; border-radius: 70px; display: flex; align-items: center; justify-content: center; {{ $teamAWinner ? 'border: 4px solid #22c55e;' : '' }}">
                @if($teamA?->team_logo)
                    <img src="{{ Storage::url($teamA->team_logo) }}" style="width: 100px; height: 100px; object-fit: contain;">
                @else
                    <span style="font-size: 44px; font-weight: 700; color: #ccc;">{{ substr($teamA?->name ?? 'A', 0, 2) }}</span>
                @endif
            </div>
            <h3 style="font-size: 22px; font-weight: 600; margin: 0 0 20px; color: #333;">{{ $teamA?->name ?? 'Team A' }}</h3>
            <div style="font-size: 72px; font-weight: 800; color: {{ $teamAWinner ? '#16a34a' : '#1a1a1a' }}; line-height: 1;">
                {{ $result->team_a_score ?? 0 }}<span style="font-size: 28px; color: #999;">/{{ $result->team_a_wickets ?? 0 }}</span>
            </div>
            <div style="font-size: 16px; color: #888; margin-top: 10px;">{{ number_format($result->team_a_overs ?? 0, 1) }} overs</div>
            @if($teamAWinner)
                <div style="margin-top: 20px;">
                    <span style="display: inline-block; background: #22c55e; color: white; padding: 8px 24px; border-radius: 24px; font-size: 13px; font-weight: 700; letter-spacing: 1px;">WINNER</span>
                </div>
            @endif
        </div>

        {{-- Divider --}}
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;">
            <div style="width: 1px; height: 80px; background: #eee;"></div>
            <div style="width: 60px; height: 60px; background: {{ $secondaryColor }}; border-radius: 30px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 18px; font-weight: 700; color: {{ $primaryColor }};">VS</span>
            </div>
            <div style="width: 1px; height: 80px; background: #eee;"></div>
        </div>

        {{-- Team B --}}
        <div style="flex: 1; max-width: 350px; text-align: center; padding: 40px 30px; {{ $teamBWinner ? 'background: #f0fdf4; border-radius: 16px;' : '' }}">
            <div style="width: 140px; height: 140px; margin: 0 auto 25px; background: #f5f5f5; border-radius: 70px; display: flex; align-items: center; justify-content: center; {{ $teamBWinner ? 'border: 4px solid #22c55e;' : '' }}">
                @if($teamB?->team_logo)
                    <img src="{{ Storage::url($teamB->team_logo) }}" style="width: 100px; height: 100px; object-fit: contain;">
                @else
                    <span style="font-size: 44px; font-weight: 700; color: #ccc;">{{ substr($teamB?->name ?? 'B', 0, 2) }}</span>
                @endif
            </div>
            <h3 style="font-size: 22px; font-weight: 600; margin: 0 0 20px; color: #333;">{{ $teamB?->name ?? 'Team B' }}</h3>
            <div style="font-size: 72px; font-weight: 800; color: {{ $teamBWinner ? '#16a34a' : '#1a1a1a' }}; line-height: 1;">
                {{ $result->team_b_score ?? 0 }}<span style="font-size: 28px; color: #999;">/{{ $result->team_b_wickets ?? 0 }}</span>
            </div>
            <div style="font-size: 16px; color: #888; margin-top: 10px;">{{ number_format($result->team_b_overs ?? 0, 1) }} overs</div>
            @if($teamBWinner)
                <div style="margin-top: 20px;">
                    <span style="display: inline-block; background: #22c55e; color: white; padding: 8px 24px; border-radius: 24px; font-size: 13px; font-weight: 700; letter-spacing: 1px;">WINNER</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Result Summary --}}
    @if($result?->result_summary)
        <div style="text-align: center; margin: 50px 80px 40px; padding: 25px 40px; background: #fef3c7; border-radius: 12px; border-left: 4px solid {{ $secondaryColor }};">
            <span style="font-size: 20px; color: #92400e; font-weight: 600;">{{ $result->result_summary }}</span>
        </div>
    @endif

    {{-- Man of the Match --}}
    @if($motmAward)
        <div style="margin: 40px 80px; display: flex; align-items: center; gap: 25px; padding: 30px; background: #faf5ff; border-radius: 16px; border: 1px solid #e9d5ff;">
            @if($motmAward->player?->image_path)
                <img src="{{ Storage::url($motmAward->player->image_path) }}" style="width: 90px; height: 90px; border-radius: 45px; object-fit: cover; border: 3px solid #9333ea;">
            @else
                <div style="width: 90px; height: 90px; border-radius: 45px; background: #e9d5ff; display: flex; align-items: center; justify-content: center; border: 3px solid #9333ea;">
                    <span style="font-size: 32px; font-weight: 700; color: #9333ea;">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                </div>
            @endif
            <div>
                <div style="font-size: 12px; color: #9333ea; text-transform: uppercase; letter-spacing: 2px; font-weight: 600; margin-bottom: 6px;">
                    {{ $motmAward->tournamentAward->icon ?? '🏆' }} Man of the Match
                </div>
                <div style="font-size: 26px; font-weight: 700; color: #1a1a1a;">{{ $motmAward->player?->name ?? 'Unknown' }}</div>
                @if($motmAward->remarks)
                    <div style="font-size: 15px; color: #666; margin-top: 4px;">{{ $motmAward->remarks }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 30px 80px; border-top: 1px solid #eee; background: #fafafa;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #888; font-size: 14px;">
                @if($match->venue)
                    <span>{{ $match->venue }}</span>
                @endif
            </div>
            @if($match->match_number)
                <span style="color: #aaa; font-size: 14px;">Match #{{ $match->match_number }}</span>
            @endif
        </div>
    </div>
</div>
