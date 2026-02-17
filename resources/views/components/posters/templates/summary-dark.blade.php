{{-- Dark Summary Poster Template --}}
<div class="poster-dark" style="
    width: 1080px;
    height: 1350px;
    background: #000000;
    position: relative;
    overflow: hidden;
    font-family: 'Roboto', sans-serif;
    color: white;
">
    {{-- Subtle Grid Pattern --}}
    <div style="position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 50px 50px;"></div>

    {{-- Glow Effects --}}
    <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 600px; height: 300px; background: radial-gradient(ellipse, {{ $secondaryColor }}15, transparent 70%);"></div>
    <div style="position: absolute; bottom: 300px; left: 50%; transform: translateX(-50%); width: 1000px; height: 400px; background: radial-gradient(ellipse, {{ $secondaryColor }}08, transparent 70%);"></div>

    {{-- Top Border Accent --}}
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, transparent, {{ $secondaryColor }}, transparent);"></div>

    {{-- Content Container --}}
    <div style="position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; padding: 60px;">

        {{-- Header --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 20px;">
                @if($settings?->logo)
                    <img src="{{ Storage::url($settings->logo) }}" style="width: 70px; height: 70px; border-radius: 14px; object-fit: cover; border: 2px solid #333;">
                @endif
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; margin: 0; color: #fff;">{{ $tournament->name }}</h1>
                    <span style="font-size: 14px; color: {{ $secondaryColor }}; font-weight: 600; letter-spacing: 2px;">{{ strtoupper($match->stage_display) }}</span>
                </div>
            </div>
            <div style="text-align: right;">
                @if($match->match_date)
                    <div style="font-size: 14px; color: #666;">{{ $match->match_date->format('D, M d') }}</div>
                    <div style="font-size: 24px; font-weight: 700; color: #fff;">{{ $match->match_date->format('Y') }}</div>
                @endif
            </div>
        </div>

        {{-- Result Title --}}
        <div style="text-align: center; margin: 60px 0 50px;">
            <div style="display: inline-flex; align-items: center; gap: 20px;">
                <div style="width: 100px; height: 1px; background: linear-gradient(90deg, transparent, #333);"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666; letter-spacing: 6px;">FINAL RESULT</span>
                <div style="width: 100px; height: 1px; background: linear-gradient(90deg, #333, transparent);"></div>
            </div>
        </div>

        {{-- Score Display --}}
        <div style="display: flex; align-items: center; justify-content: center; gap: 60px; flex: 0;">

            {{-- Team A --}}
            <div style="text-align: center; min-width: 350px;">
                <div style="
                    width: 180px;
                    height: 180px;
                    margin: 0 auto 25px;
                    background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 3px solid {{ $teamAWinner ? '#22c55e' : '#222' }};
                    {{ $teamAWinner ? 'box-shadow: 0 0 50px rgba(34, 197, 94, 0.3);' : '' }}
                ">
                    @if($teamA?->team_logo)
                        <img src="{{ Storage::url($teamA->team_logo) }}" style="width: 120px; height: 120px; object-fit: contain;">
                    @else
                        <span style="font-size: 56px; font-weight: 700; color: #333;">{{ substr($teamA?->name ?? 'A', 0, 2) }}</span>
                    @endif
                </div>
                <h3 style="font-size: 20px; font-weight: 600; margin: 0 0 25px; color: #888;">{{ strtoupper($teamA?->name ?? 'TEAM A') }}</h3>
                <div style="font-size: 80px; font-weight: 800; color: {{ $teamAWinner ? '#22c55e' : '#fff' }}; line-height: 1;">
                    {{ $result->team_a_score ?? 0 }}
                </div>
                <div style="font-size: 24px; color: #444; margin-top: -5px;">/{{ $result->team_a_wickets ?? 0 }}</div>
                <div style="font-size: 16px; color: #555; margin-top: 10px;">({{ number_format($result->team_a_overs ?? 0, 1) }} ov)</div>
                @if($teamAWinner)
                    <div style="margin-top: 20px;">
                        <span style="display: inline-block; border: 2px solid #22c55e; color: #22c55e; padding: 8px 24px; font-size: 12px; font-weight: 700; letter-spacing: 3px;">WINNER</span>
                    </div>
                @endif
            </div>

            {{-- VS --}}
            <div style="text-align: center;">
                <div style="
                    width: 80px;
                    height: 80px;
                    background: {{ $secondaryColor }};
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <span style="font-size: 24px; font-weight: 800; color: #000;">VS</span>
                </div>
            </div>

            {{-- Team B --}}
            <div style="text-align: center; min-width: 350px;">
                <div style="
                    width: 180px;
                    height: 180px;
                    margin: 0 auto 25px;
                    background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 3px solid {{ $teamBWinner ? '#22c55e' : '#222' }};
                    {{ $teamBWinner ? 'box-shadow: 0 0 50px rgba(34, 197, 94, 0.3);' : '' }}
                ">
                    @if($teamB?->team_logo)
                        <img src="{{ Storage::url($teamB->team_logo) }}" style="width: 120px; height: 120px; object-fit: contain;">
                    @else
                        <span style="font-size: 56px; font-weight: 700; color: #333;">{{ substr($teamB?->name ?? 'B', 0, 2) }}</span>
                    @endif
                </div>
                <h3 style="font-size: 20px; font-weight: 600; margin: 0 0 25px; color: #888;">{{ strtoupper($teamB?->name ?? 'TEAM B') }}</h3>
                <div style="font-size: 80px; font-weight: 800; color: {{ $teamBWinner ? '#22c55e' : '#fff' }}; line-height: 1;">
                    {{ $result->team_b_score ?? 0 }}
                </div>
                <div style="font-size: 24px; color: #444; margin-top: -5px;">/{{ $result->team_b_wickets ?? 0 }}</div>
                <div style="font-size: 16px; color: #555; margin-top: 10px;">({{ number_format($result->team_b_overs ?? 0, 1) }} ov)</div>
                @if($teamBWinner)
                    <div style="margin-top: 20px;">
                        <span style="display: inline-block; border: 2px solid #22c55e; color: #22c55e; padding: 8px 24px; font-size: 12px; font-weight: 700; letter-spacing: 3px;">WINNER</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Result Summary --}}
        @if($result?->result_summary)
            <div style="text-align: center; margin: 50px 0;">
                <span style="font-size: 22px; color: {{ $secondaryColor }}; font-weight: 600;">{{ $result->result_summary }}</span>
            </div>
        @endif

        {{-- Man of the Match --}}
        @if($motmAward)
            <div style="margin-top: auto; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 25px; padding: 30px 50px; background: linear-gradient(135deg, #111, #0a0a0a); border-radius: 20px; border: 1px solid #222;">
                @if($motmAward->player?->image_path)
                    <img src="{{ Storage::url($motmAward->player->image_path) }}" style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid {{ $secondaryColor }};">
                @else
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; border: 3px solid {{ $secondaryColor }};">
                        <span style="font-size: 32px; font-weight: 700; color: {{ $secondaryColor }};">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                    </div>
                @endif
                <div>
                    <div style="font-size: 12px; color: {{ $secondaryColor }}; text-transform: uppercase; letter-spacing: 3px; font-weight: 600; margin-bottom: 8px;">
                        {{ $motmAward->tournamentAward->icon ?? '🏆' }} Player of the Match
                    </div>
                    <div style="font-size: 28px; font-weight: 700; color: #fff;">{{ $motmAward->player?->name ?? 'Unknown' }}</div>
                    @if($motmAward->remarks)
                        <div style="font-size: 14px; color: #666; margin-top: 4px;">{{ $motmAward->remarks }}</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 30px; border-top: 1px solid #222;">
            <span style="color: #444; font-size: 14px;">{{ $match->venue }}</span>
            @if($match->match_number)
                <span style="color: #333; font-size: 14px;">Match #{{ $match->match_number }}</span>
            @endif
        </div>
    </div>

    {{-- Bottom Border --}}
    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, transparent, {{ $secondaryColor }}, transparent);"></div>
</div>
