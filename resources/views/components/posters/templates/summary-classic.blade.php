{{-- Classic Summary Poster Template --}}
<div class="poster-classic" style="
    width: 1080px;
    height: 1350px;
    background: linear-gradient(180deg, {{ $primaryColor }} 0%, #0a0a1a 100%);
    position: relative;
    overflow: hidden;
    font-family: 'Oswald', sans-serif;
    color: white;
">
    {{-- Decorative Background Elements --}}
    <div style="position: absolute; top: -100px; left: -100px; width: 400px; height: 400px; background: radial-gradient(circle, {{ $secondaryColor }}20, transparent 70%); border-radius: 50%;"></div>
    <div style="position: absolute; top: -100px; right: -100px; width: 400px; height: 400px; background: radial-gradient(circle, {{ $secondaryColor }}20, transparent 70%); border-radius: 50%;"></div>
    <div style="position: absolute; bottom: -100px; left: 50%; transform: translateX(-50%); width: 600px; height: 300px; background: radial-gradient(ellipse, {{ $secondaryColor }}10, transparent 70%);"></div>

    {{-- Tournament Header --}}
    <div style="text-align: center; padding-top: 50px; position: relative; z-index: 1;">
        @if($settings?->logo)
            <img src="{{ Storage::url($settings->logo) }}"
                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid {{ $secondaryColor }}; margin-bottom: 15px;">
        @endif
        <h1 style="font-size: 32px; font-weight: 700; margin: 0; letter-spacing: 2px;">{{ strtoupper($tournament->name) }}</h1>

        {{-- Stage Badge --}}
        <div style="display: inline-block; margin-top: 15px; padding: 8px 30px; background: {{ $secondaryColor }}; border-radius: 20px;">
            <span style="color: #000; font-weight: 700; font-size: 16px; letter-spacing: 1px;">{{ strtoupper($match->stage_display) }}</span>
        </div>
    </div>

    {{-- Match Result Header --}}
    <div style="text-align: center; margin-top: 40px; position: relative; z-index: 1;">
        <h2 style="font-size: 48px; font-weight: 700; color: {{ $secondaryColor }}; margin: 0; letter-spacing: 4px;">MATCH RESULT</h2>
        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 10px;">
            <div style="width: 150px; height: 3px; background: linear-gradient(90deg, transparent, {{ $secondaryColor }});"></div>
            <div style="width: 150px; height: 3px; background: linear-gradient(90deg, {{ $secondaryColor }}, transparent);"></div>
        </div>
    </div>

    {{-- Teams Section --}}
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 50px 80px; position: relative; z-index: 1;">
        {{-- Team A --}}
        <div style="text-align: center; flex: 1;">
            <div style="
                width: 160px;
                height: 160px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                border: 4px solid {{ $teamAWinner ? '#22c55e' : 'rgba(255,255,255,0.3)' }};
                {{ $teamAWinner ? 'box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);' : '' }}
            ">
                @if($teamA?->team_logo)
                    <img src="{{ Storage::url($teamA->team_logo) }}" style="width: 120px; height: 120px; object-fit: contain;">
                @else
                    <span style="font-size: 48px; font-weight: 700; color: rgba(255,255,255,0.5);">{{ substr($teamA?->name ?? 'A', 0, 2) }}</span>
                @endif
            </div>
            <h3 style="font-size: 24px; margin: 0 0 10px; font-weight: 700;">{{ strtoupper($teamA?->short_name ?? $teamA?->name ?? 'TEAM A') }}</h3>
            <div style="font-size: 56px; font-weight: 700; color: {{ $teamAWinner ? '#22c55e' : '#fff' }};">
                {{ $result->team_a_score ?? 0 }}/{{ $result->team_a_wickets ?? 0 }}
            </div>
            <div style="font-size: 18px; color: rgba(255,255,255,0.6);">({{ number_format($result->team_a_overs ?? 0, 1) }} overs)</div>
            @if($teamAWinner)
                <div style="margin-top: 15px; display: inline-block; padding: 8px 20px; background: #22c55e; border-radius: 20px;">
                    <span style="color: #000; font-weight: 700; font-size: 14px;">WINNER</span>
                </div>
            @endif
        </div>

        {{-- VS Badge --}}
        <div style="flex-shrink: 0; margin: 0 30px;">
            <div style="
                width: 90px;
                height: 90px;
                border-radius: 50%;
                background: linear-gradient(135deg, {{ $secondaryColor }}, #f97316);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            ">
                <span style="font-size: 28px; font-weight: 700; color: #fff;">VS</span>
            </div>
        </div>

        {{-- Team B --}}
        <div style="text-align: center; flex: 1;">
            <div style="
                width: 160px;
                height: 160px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                border: 4px solid {{ $teamBWinner ? '#22c55e' : 'rgba(255,255,255,0.3)' }};
                {{ $teamBWinner ? 'box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);' : '' }}
            ">
                @if($teamB?->team_logo)
                    <img src="{{ Storage::url($teamB->team_logo) }}" style="width: 120px; height: 120px; object-fit: contain;">
                @else
                    <span style="font-size: 48px; font-weight: 700; color: rgba(255,255,255,0.5);">{{ substr($teamB?->name ?? 'B', 0, 2) }}</span>
                @endif
            </div>
            <h3 style="font-size: 24px; margin: 0 0 10px; font-weight: 700;">{{ strtoupper($teamB?->short_name ?? $teamB?->name ?? 'TEAM B') }}</h3>
            <div style="font-size: 56px; font-weight: 700; color: {{ $teamBWinner ? '#22c55e' : '#fff' }};">
                {{ $result->team_b_score ?? 0 }}/{{ $result->team_b_wickets ?? 0 }}
            </div>
            <div style="font-size: 18px; color: rgba(255,255,255,0.6);">({{ number_format($result->team_b_overs ?? 0, 1) }} overs)</div>
            @if($teamBWinner)
                <div style="margin-top: 15px; display: inline-block; padding: 8px 20px; background: #22c55e; border-radius: 20px;">
                    <span style="color: #000; font-weight: 700; font-size: 14px;">WINNER</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Result Summary --}}
    @if($result?->result_summary)
        <div style="text-align: center; margin: 20px 80px; position: relative; z-index: 1;">
            <div style="display: inline-block; padding: 15px 40px; background: rgba(251, 191, 36, 0.15); border: 1px solid {{ $secondaryColor }}50; border-radius: 30px;">
                <span style="font-size: 22px; color: {{ $secondaryColor }}; font-weight: 600;">{{ $result->result_summary }}</span>
            </div>
        </div>
    @endif

    {{-- Man of the Match --}}
    @if($motmAward)
        <div style="text-align: center; margin: 40px 80px; padding: 30px; background: linear-gradient(135deg, #8b5cf620, #7c3aed20); border-radius: 20px; border: 1px solid #8b5cf640; position: relative; z-index: 1;">
            <div style="font-size: 14px; color: #a78bfa; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">Man of the Match</div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                @if($motmAward->player?->image_path)
                    <img src="{{ Storage::url($motmAward->player->image_path) }}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #a78bfa;">
                @else
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #8b5cf630; display: flex; align-items: center; justify-content: center; border: 3px solid #a78bfa;">
                        <span style="font-size: 32px; color: #a78bfa;">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                    </div>
                @endif
                <div style="text-align: left;">
                    <div style="font-size: 28px; font-weight: 700;">{{ $motmAward->player?->name ?? 'Unknown' }}</div>
                    @if($motmAward->remarks)
                        <div style="font-size: 16px; color: rgba(255,255,255,0.6);">{{ $motmAward->remarks }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Match Details Footer --}}
    <div style="position: absolute; bottom: 50px; left: 0; right: 0; text-align: center; z-index: 1;">
        <div style="display: flex; justify-content: center; gap: 40px; color: rgba(255,255,255,0.5); font-size: 16px;">
            @if($match->match_date)
                <span>{{ $match->match_date->format('D, M d, Y') }}</span>
            @endif
            @if($match->venue)
                <span>{{ $match->venue }}</span>
            @endif
        </div>
        @if($match->match_number)
            <div style="margin-top: 10px; font-size: 14px; color: rgba(255,255,255,0.3);">Match #{{ $match->match_number }}</div>
        @endif
    </div>

    {{-- Bottom Border --}}
    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, {{ $primaryColor }}, {{ $secondaryColor }}, {{ $primaryColor }});"></div>
</div>
