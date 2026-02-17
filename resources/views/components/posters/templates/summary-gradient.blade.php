{{-- Gradient Summary Poster Template --}}
<div class="poster-gradient" style="
    width: 1080px;
    height: 1350px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    position: relative;
    overflow: hidden;
    font-family: 'Oswald', sans-serif;
    color: white;
">
    {{-- Animated Background Shapes --}}
    <div style="position: absolute; top: 100px; left: -100px; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(60px);"></div>
    <div style="position: absolute; bottom: 100px; right: -100px; width: 500px; height: 500px; background: rgba(255,255,255,0.08); border-radius: 50%; filter: blur(80px);"></div>
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 800px; height: 800px; background: rgba(0,0,0,0.1); border-radius: 50%; filter: blur(100px);"></div>

    {{-- Glass Card Container --}}
    <div style="position: absolute; inset: 40px; background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); border-radius: 40px; border: 1px solid rgba(255,255,255,0.2); padding: 50px; display: flex; flex-direction: column;">

        {{-- Tournament Header --}}
        <div style="text-align: center; margin-bottom: 30px;">
            @if($settings?->logo)
                <div style="width: 100px; height: 100px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid rgba(255,255,255,0.4);">
                    <img src="{{ Storage::url($settings->logo) }}" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">
                </div>
            @endif
            <h1 style="font-size: 36px; font-weight: 700; margin: 0; letter-spacing: 3px; text-shadow: 0 2px 10px rgba(0,0,0,0.2);">{{ strtoupper($tournament->name) }}</h1>
            <div style="margin-top: 15px; display: inline-block; padding: 10px 30px; background: rgba(255,255,255,0.2); border-radius: 25px; border: 1px solid rgba(255,255,255,0.3);">
                <span style="font-size: 14px; font-weight: 600; letter-spacing: 2px;">{{ strtoupper($match->stage_display) }}</span>
            </div>
        </div>

        {{-- Match Result Badge --}}
        <div style="text-align: center; margin-bottom: 40px;">
            <span style="font-size: 18px; font-weight: 600; letter-spacing: 6px; opacity: 0.8;">MATCH RESULT</span>
        </div>

        {{-- Teams Display --}}
        <div style="display: flex; align-items: center; justify-content: center; gap: 30px; flex: 1;">

            {{-- Team A --}}
            <div style="
                flex: 1;
                max-width: 360px;
                text-align: center;
                padding: 40px 30px;
                background: {{ $teamAWinner ? 'rgba(34, 197, 94, 0.25)' : 'rgba(255,255,255,0.1)' }};
                border-radius: 30px;
                border: 2px solid {{ $teamAWinner ? 'rgba(34, 197, 94, 0.6)' : 'rgba(255,255,255,0.2)' }};
                position: relative;
            ">
                @if($teamAWinner)
                    <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #22c55e, #16a34a); padding: 8px 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);">
                        <span style="font-size: 12px; font-weight: 700; letter-spacing: 2px;">WINNER</span>
                    </div>
                @endif

                <div style="width: 140px; height: 140px; margin: 15px auto 25px; background: rgba(255,255,255,0.15); border-radius: 70px; display: flex; align-items: center; justify-content: center; border: 3px solid rgba(255,255,255,0.3);">
                    @if($teamA?->team_logo)
                        <img src="{{ Storage::url($teamA->team_logo) }}" style="width: 100px; height: 100px; object-fit: contain;">
                    @else
                        <span style="font-size: 48px; font-weight: 700; opacity: 0.5;">{{ substr($teamA?->name ?? 'A', 0, 2) }}</span>
                    @endif
                </div>

                <h3 style="font-size: 24px; font-weight: 600; margin: 0 0 20px;">{{ strtoupper($teamA?->short_name ?? $teamA?->name ?? 'TEAM A') }}</h3>

                <div style="font-size: 72px; font-weight: 800; line-height: 1; text-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                    {{ $result->team_a_score ?? 0 }}<span style="font-size: 32px; opacity: 0.6;">/{{ $result->team_a_wickets ?? 0 }}</span>
                </div>
                <div style="font-size: 16px; opacity: 0.7; margin-top: 10px;">({{ number_format($result->team_a_overs ?? 0, 1) }} overs)</div>
            </div>

            {{-- VS Badge --}}
            <div style="
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.25);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 3px solid rgba(255,255,255,0.4);
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            ">
                <span style="font-size: 24px; font-weight: 700;">VS</span>
            </div>

            {{-- Team B --}}
            <div style="
                flex: 1;
                max-width: 360px;
                text-align: center;
                padding: 40px 30px;
                background: {{ $teamBWinner ? 'rgba(34, 197, 94, 0.25)' : 'rgba(255,255,255,0.1)' }};
                border-radius: 30px;
                border: 2px solid {{ $teamBWinner ? 'rgba(34, 197, 94, 0.6)' : 'rgba(255,255,255,0.2)' }};
                position: relative;
            ">
                @if($teamBWinner)
                    <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #22c55e, #16a34a); padding: 8px 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);">
                        <span style="font-size: 12px; font-weight: 700; letter-spacing: 2px;">WINNER</span>
                    </div>
                @endif

                <div style="width: 140px; height: 140px; margin: 15px auto 25px; background: rgba(255,255,255,0.15); border-radius: 70px; display: flex; align-items: center; justify-content: center; border: 3px solid rgba(255,255,255,0.3);">
                    @if($teamB?->team_logo)
                        <img src="{{ Storage::url($teamB->team_logo) }}" style="width: 100px; height: 100px; object-fit: contain;">
                    @else
                        <span style="font-size: 48px; font-weight: 700; opacity: 0.5;">{{ substr($teamB?->name ?? 'B', 0, 2) }}</span>
                    @endif
                </div>

                <h3 style="font-size: 24px; font-weight: 600; margin: 0 0 20px;">{{ strtoupper($teamB?->short_name ?? $teamB?->name ?? 'TEAM B') }}</h3>

                <div style="font-size: 72px; font-weight: 800; line-height: 1; text-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                    {{ $result->team_b_score ?? 0 }}<span style="font-size: 32px; opacity: 0.6;">/{{ $result->team_b_wickets ?? 0 }}</span>
                </div>
                <div style="font-size: 16px; opacity: 0.7; margin-top: 10px;">({{ number_format($result->team_b_overs ?? 0, 1) }} overs)</div>
            </div>
        </div>

        {{-- Result Summary --}}
        @if($result?->result_summary)
            <div style="text-align: center; margin-top: 30px; padding: 20px 40px; background: rgba(255,255,255,0.15); border-radius: 20px;">
                <span style="font-size: 22px; font-weight: 600;">{{ $result->result_summary }}</span>
            </div>
        @endif

        {{-- Man of the Match --}}
        @if($motmAward)
            <div style="margin-top: 30px; display: flex; align-items: center; gap: 25px; padding: 25px 35px; background: rgba(0,0,0,0.2); border-radius: 20px;">
                @if($motmAward->player?->image_path)
                    <img src="{{ Storage::url($motmAward->player->image_path) }}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.5);">
                @else
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; border: 3px solid rgba(255,255,255,0.5);">
                        <span style="font-size: 28px; font-weight: 700;">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                    </div>
                @endif
                <div>
                    <div style="font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px;">
                        {{ $motmAward->tournamentAward->icon ?? '🏆' }} Man of the Match
                    </div>
                    <div style="font-size: 26px; font-weight: 700;">{{ $motmAward->player?->name ?? 'Unknown' }}</div>
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div style="margin-top: auto; padding-top: 30px; display: flex; justify-content: space-between; align-items: center; opacity: 0.7; font-size: 14px;">
            <span>{{ $match->match_date?->format('D, M d, Y') }}</span>
            <span>{{ $match->venue }}</span>
            @if($match->match_number)
                <span>Match #{{ $match->match_number }}</span>
            @endif
        </div>
    </div>
</div>
