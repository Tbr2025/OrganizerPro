{{-- Modern Summary Poster Template --}}
<div class="poster-modern" style="
    width: 1080px;
    height: 1350px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    position: relative;
    overflow: hidden;
    font-family: 'Roboto', sans-serif;
    color: white;
">
    {{-- Geometric Background Pattern --}}
    <svg style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0.05;" viewBox="0 0 100 100" preserveAspectRatio="none">
        <defs>
            <pattern id="grid-modern" width="10" height="10" patternUnits="userSpaceOnUse">
                <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
            </pattern>
        </defs>
        <rect width="100" height="100" fill="url(#grid-modern)"/>
    </svg>

    {{-- Accent Circles --}}
    <div style="position: absolute; top: -150px; right: -150px; width: 500px; height: 500px; border: 2px solid {{ $secondaryColor }}20; border-radius: 50%;"></div>
    <div style="position: absolute; top: -100px; right: -100px; width: 400px; height: 400px; border: 2px solid {{ $secondaryColor }}15; border-radius: 50%;"></div>
    <div style="position: absolute; bottom: -200px; left: -200px; width: 600px; height: 600px; border: 2px solid {{ $secondaryColor }}10; border-radius: 50%;"></div>

    {{-- Top Bar --}}
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 40px 60px; background: linear-gradient(180deg, rgba(0,0,0,0.5), transparent);">
        <div style="display: flex; align-items: center; gap: 20px;">
            @if($settings?->logo)
                <img src="{{ Storage::url($settings->logo) }}" style="width: 60px; height: 60px; border-radius: 12px; object-fit: cover;">
            @endif
            <div>
                <div style="font-size: 24px; font-weight: 700; letter-spacing: 1px;">{{ strtoupper($tournament->name) }}</div>
                <div style="font-size: 14px; color: {{ $secondaryColor }}; margin-top: 4px;">{{ strtoupper($match->stage_display) }}</div>
            </div>
        </div>
        <div style="text-align: right;">
            @if($match->match_date)
                <div style="font-size: 16px; color: rgba(255,255,255,0.7);">{{ $match->match_date->format('M d, Y') }}</div>
            @endif
        </div>
    </div>

    {{-- Main Content Card --}}
    <div style="margin: 40px 60px; background: linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02)); border-radius: 30px; border: 1px solid rgba(255,255,255,0.1); padding: 50px; position: relative; z-index: 1;">

        {{-- Result Header --}}
        <div style="text-align: center; margin-bottom: 40px;">
            <div style="display: inline-flex; align-items: center; gap: 15px;">
                <div style="width: 80px; height: 2px; background: linear-gradient(90deg, transparent, {{ $secondaryColor }});"></div>
                <span style="font-size: 18px; font-weight: 600; color: {{ $secondaryColor }}; letter-spacing: 3px;">MATCH RESULT</span>
                <div style="width: 80px; height: 2px; background: linear-gradient(90deg, {{ $secondaryColor }}, transparent);"></div>
            </div>
        </div>

        {{-- Score Cards --}}
        <div style="display: flex; justify-content: center; gap: 30px; margin-bottom: 40px;">
            {{-- Team A Card --}}
            <div style="
                flex: 1;
                max-width: 380px;
                background: {{ $teamAWinner ? 'linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.05))' : 'rgba(255,255,255,0.05)' }};
                border-radius: 24px;
                padding: 40px 30px;
                text-align: center;
                border: 2px solid {{ $teamAWinner ? 'rgba(34, 197, 94, 0.5)' : 'rgba(255,255,255,0.1)' }};
                position: relative;
            ">
                @if($teamAWinner)
                    <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #22c55e; padding: 6px 20px; border-radius: 20px;">
                        <span style="font-size: 12px; font-weight: 700; color: #000; letter-spacing: 1px;">WINNER</span>
                    </div>
                @endif
                <div style="width: 120px; height: 120px; margin: 0 auto 20px; background: rgba(255,255,255,0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; padding: 15px;">
                    @if($teamA?->team_logo)
                        <img src="{{ Storage::url($teamA->team_logo) }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    @else
                        <span style="font-size: 40px; font-weight: 700; color: rgba(255,255,255,0.4);">{{ substr($teamA?->name ?? 'A', 0, 2) }}</span>
                    @endif
                </div>
                <h3 style="font-size: 20px; font-weight: 700; margin: 0 0 20px; color: rgba(255,255,255,0.9);">{{ $teamA?->name ?? 'Team A' }}</h3>
                <div style="font-size: 64px; font-weight: 800; color: {{ $teamAWinner ? '#22c55e' : '#fff' }}; line-height: 1;">
                    {{ $result->team_a_score ?? 0 }}<span style="font-size: 36px; color: rgba(255,255,255,0.5);">/{{ $result->team_a_wickets ?? 0 }}</span>
                </div>
                <div style="font-size: 16px; color: rgba(255,255,255,0.5); margin-top: 10px;">({{ number_format($result->team_a_overs ?? 0, 1) }} overs)</div>
            </div>

            {{-- VS Badge --}}
            <div style="display: flex; align-items: center; justify-content: center;">
                <div style="
                    width: 70px;
                    height: 70px;
                    background: {{ $secondaryColor }};
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 0 40px {{ $secondaryColor }}40;
                ">
                    <span style="font-size: 20px; font-weight: 800; color: #000;">VS</span>
                </div>
            </div>

            {{-- Team B Card --}}
            <div style="
                flex: 1;
                max-width: 380px;
                background: {{ $teamBWinner ? 'linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.05))' : 'rgba(255,255,255,0.05)' }};
                border-radius: 24px;
                padding: 40px 30px;
                text-align: center;
                border: 2px solid {{ $teamBWinner ? 'rgba(34, 197, 94, 0.5)' : 'rgba(255,255,255,0.1)' }};
                position: relative;
            ">
                @if($teamBWinner)
                    <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #22c55e; padding: 6px 20px; border-radius: 20px;">
                        <span style="font-size: 12px; font-weight: 700; color: #000; letter-spacing: 1px;">WINNER</span>
                    </div>
                @endif
                <div style="width: 120px; height: 120px; margin: 0 auto 20px; background: rgba(255,255,255,0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; padding: 15px;">
                    @if($teamB?->team_logo)
                        <img src="{{ Storage::url($teamB->team_logo) }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    @else
                        <span style="font-size: 40px; font-weight: 700; color: rgba(255,255,255,0.4);">{{ substr($teamB?->name ?? 'B', 0, 2) }}</span>
                    @endif
                </div>
                <h3 style="font-size: 20px; font-weight: 700; margin: 0 0 20px; color: rgba(255,255,255,0.9);">{{ $teamB?->name ?? 'Team B' }}</h3>
                <div style="font-size: 64px; font-weight: 800; color: {{ $teamBWinner ? '#22c55e' : '#fff' }}; line-height: 1;">
                    {{ $result->team_b_score ?? 0 }}<span style="font-size: 36px; color: rgba(255,255,255,0.5);">/{{ $result->team_b_wickets ?? 0 }}</span>
                </div>
                <div style="font-size: 16px; color: rgba(255,255,255,0.5); margin-top: 10px;">({{ number_format($result->team_b_overs ?? 0, 1) }} overs)</div>
            </div>
        </div>

        {{-- Result Summary --}}
        @if($result?->result_summary)
            <div style="text-align: center; padding: 20px 40px; background: linear-gradient(90deg, transparent, {{ $secondaryColor }}15, transparent); border-radius: 12px;">
                <span style="font-size: 20px; color: {{ $secondaryColor }}; font-weight: 600;">{{ $result->result_summary }}</span>
            </div>
        @endif
    </div>

    {{-- Man of the Match Section --}}
    @if($motmAward)
        <div style="margin: 0 60px 40px; display: flex; align-items: center; gap: 30px; background: linear-gradient(135deg, #4f46e520, #7c3aed10); border-radius: 20px; padding: 30px 40px; border: 1px solid #7c3aed30;">
            <div style="flex-shrink: 0;">
                @if($motmAward->player?->image_path)
                    <img src="{{ Storage::url($motmAward->player->image_path) }}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #7c3aed;">
                @else
                    <div style="width: 100px; height: 100px; border-radius: 50%; background: #7c3aed30; display: flex; align-items: center; justify-content: center; border: 4px solid #7c3aed;">
                        <span style="font-size: 36px; font-weight: 700; color: #a78bfa;">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                    </div>
                @endif
            </div>
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span style="font-size: 24px;">{{ $motmAward->tournamentAward->icon ?? '🏆' }}</span>
                    <span style="font-size: 14px; color: #a78bfa; text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">Man of the Match</span>
                </div>
                <div style="font-size: 28px; font-weight: 700;">{{ $motmAward->player?->name ?? 'Unknown' }}</div>
                @if($motmAward->remarks)
                    <div style="font-size: 16px; color: rgba(255,255,255,0.6); margin-top: 6px;">{{ $motmAward->remarks }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 30px 60px; background: linear-gradient(180deg, transparent, rgba(0,0,0,0.5));">
        <div style="display: flex; justify-content: space-between; align-items: center; color: rgba(255,255,255,0.5); font-size: 14px;">
            <div>
                @if($match->venue)
                    <span>{{ $match->venue }}</span>
                @endif
            </div>
            @if($match->match_number)
                <div style="background: rgba(255,255,255,0.1); padding: 6px 16px; border-radius: 20px;">
                    Match #{{ $match->match_number }}
                </div>
            @endif
        </div>
    </div>
</div>
