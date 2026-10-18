{{--
    The LED wall. Standalone (no app layout, no build step): a TV browser or a
    mini PC loads one HTML file and two small push scripts.

    What is on screen is decided by the server (LedQueueService::tick) on every
    poll, so every screen on this link shows the same panel. The page only
    draws what it is told and asks again when the panel ends, when a push
    arrives, or every 3 s while push is down. On a dropped connection it keeps
    the last score and shows a small amber dot.

    Every name is escaped (esc()) before it reaches innerHTML: player and team
    names are typed by the public.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $display->name }}</title>
<style>
  :root { --bg:#070b14; --panel:#0f172a; --line:#1e293b; --ink:#f8fafc; --muted:#94a3b8; --accent:#facc15; --good:#22c55e; --bad:#ef4444; --six:#a855f7; --four:#3b82f6; }
  body.light { --bg:#f1f5f9; --panel:#ffffff; --line:#cbd5e1; --ink:#0f172a; --muted:#475569; }
  * { box-sizing:border-box; margin:0; padding:0; }
  html, body { width:100%; height:100%; background:var(--bg); overflow:hidden; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:var(--ink); }
  #stage { position:absolute; left:0; top:0; width:1920px; height:1080px; transform-origin:0 0; display:flex; flex-direction:column; }
  header { height:110px; display:flex; align-items:center; gap:24px; padding:0 48px; border-bottom:2px solid var(--line); }
  header img { height:72px; width:72px; object-fit:contain; }
  header .t { font-size:34px; font-weight:800; }
  header .m { font-size:24px; color:var(--muted); }
  header .badge { margin-left:auto; font-size:24px; font-weight:800; padding:8px 20px; border-radius:999px; background:var(--bad); color:#fff; letter-spacing:2px; }
  header .badge.break { background:#f59e0b; } header .badge.done { background:var(--good); } header .badge.pre { background:#334155; }
  #main { flex:1; position:relative; padding:36px 48px; overflow:hidden; }
  .panel { position:absolute; inset:36px 48px; display:flex; flex-direction:column; justify-content:center; animation:in .45s ease-out; }
  body.still .panel { animation:none; }
  @keyframes in { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:none; } }
  .h1 { font-size:64px; font-weight:900; } .h2 { font-size:40px; font-weight:800; } .mut { color:var(--muted); }
  .big { font-size:180px; font-weight:900; line-height:1; letter-spacing:-4px; }
  .row { display:flex; gap:32px; align-items:center; }
  .card { background:var(--panel); border:2px solid var(--line); border-radius:28px; padding:32px 40px; }
  table.lb { width:100%; border-collapse:collapse; font-size:40px; }
  table.lb td, table.lb th { padding:16px 20px; border-bottom:2px solid var(--line); text-align:left; }
  table.lb th { font-size:26px; color:var(--muted); text-transform:uppercase; letter-spacing:2px; }
  table.lb td.n { text-align:right; font-weight:800; }
  .ball { display:inline-flex; align-items:center; justify-content:center; min-width:84px; height:84px; padding:0 16px; border-radius:999px; background:var(--line); font-size:34px; font-weight:800; margin-right:14px; }
  .ball.w { background:var(--bad); color:#fff; } .ball.f { background:var(--four); color:#fff; } .ball.s { background:var(--six); color:#fff; } .ball.x { background:#f59e0b; color:#111; }
  .splash { text-align:center; }
  .splash .word { font-size:260px; font-weight:900; line-height:1; letter-spacing:8px; }
  .logo { height:160px; width:160px; object-fit:contain; }
  .sponsors { display:flex; flex-wrap:wrap; gap:48px; justify-content:center; align-items:center; }
  .sponsors img { max-height:220px; max-width:520px; object-fit:contain; }
  footer { height:150px; display:flex; align-items:stretch; border-top:3px solid var(--accent); background:var(--panel); font-size:34px; }
  footer > div { display:flex; flex-direction:column; justify-content:center; padding:0 32px; border-right:2px solid var(--line); }
  footer .score { font-size:64px; font-weight:900; } footer .sm { font-size:24px; color:var(--muted); }
  footer .bat { flex:1; } footer .strike { color:var(--accent); }
  footer .ball { min-width:56px; height:56px; font-size:24px; margin-right:8px; padding:0 10px; }
  #dot { position:absolute; right:18px; top:18px; width:18px; height:18px; border-radius:50%; background:#f59e0b; display:none; }
  #dot.on { display:block; }
  .center { text-align:center; }
  /* ---- IPL-style Opening Pair ---- */
  .op{position:absolute;inset:0;display:flex;overflow:hidden}
  .op-side{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:24px;position:relative}
  /* Accent glow behind each player */
  .op-side::before{content:'';position:absolute;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,var(--accent) 0%,transparent 70%);opacity:.1;top:15%;left:50%;transform:translateX(-50%);z-index:0;pointer-events:none}
  .op-l{animation:opSl .7s cubic-bezier(.16,1,.3,1) both}
  .op-r{animation:opSr .7s cubic-bezier(.16,1,.3,1) both}
  @keyframes opSl{from{opacity:0;transform:translateX(-60px)}to{opacity:1;transform:none}}
  @keyframes opSr{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:none}}
  .op-wm{position:absolute;width:360px;height:360px;object-fit:contain;opacity:.06;top:10%;left:50%;transform:translateX(-50%);pointer-events:none;z-index:0}
  /* Player photo: no border, gradient fade at bottom for cutout feel */
  .op-img{width:340px;height:440px;object-fit:cover;object-position:top center;border:none;border-radius:0;position:relative;z-index:1;animation:opPh .65s cubic-bezier(.16,1,.3,1) .15s both;-webkit-mask-image:linear-gradient(to bottom,black 60%,transparent 100%);mask-image:linear-gradient(to bottom,black 60%,transparent 100%)}
  .op-img.empty{display:flex;align-items:center;justify-content:center;background:transparent;width:260px;height:340px;border:2px solid var(--line);border-radius:16px;-webkit-mask-image:none;mask-image:none}
  @keyframes opPh{from{opacity:0;transform:scale(.9) translateY(20px)}to{opacity:1;transform:none}}
  .op-pname{font-size:48px;font-weight:900;text-transform:uppercase;letter-spacing:3px;margin-top:0;position:relative;z-index:2;animation:opFi .5s ease-out .45s both}
  .op-prole{font-size:22px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:4px;margin-top:4px;position:relative;z-index:2;animation:opFi .5s ease-out .55s both}
  .op-team{font-size:18px;color:var(--muted);margin-top:4px;position:relative;z-index:2;animation:opFi .5s ease-out .6s both}
  @keyframes opFi{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  .op-div{width:3px;align-self:stretch;margin:40px 0;background:linear-gradient(180deg,transparent,var(--accent) 30%,var(--accent) 70%,transparent);z-index:2;animation:opLn .5s ease-out .2s both;transform-origin:center}
  @keyframes opLn{from{transform:scaleY(0);opacity:0}to{transform:scaleY(1);opacity:1}}
  .op-top{position:absolute;top:0;left:50%;transform:translateX(-50%);z-index:3;text-align:center;animation:opDn .6s ease-out .1s both}
  @keyframes opDn{from{opacity:0;transform:translate(-50%,-24px)}to{opacity:1;transform:translate(-50%,0)}}
  .op-top-text{font-size:40px;font-weight:900;color:var(--accent);text-transform:uppercase;letter-spacing:6px}
  .op-top-line{width:180px;height:3px;background:var(--accent);margin:6px auto;border-radius:2px}
  .op-bot{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);z-index:3;text-align:center;animation:opFi .5s ease-out .75s both;white-space:nowrap}
  .op-bot-label{font-size:16px;color:var(--muted);letter-spacing:3px;text-transform:uppercase}
  .op-bot-name{font-size:32px;font-weight:800;margin-top:2px}
  .op-bot-team{font-size:18px;color:var(--muted)}
  body.still .op *{animation-duration:0s!important;animation-delay:0s!important}
</style>
</head>
<body>
<div id="stage">
  <header>
    <img id="tLogo" alt="" style="display:none">
    <div><div class="t" id="tName"></div><div class="m" id="mName"></div></div>
    <div class="badge" id="badge"></div>
  </header>
  <div id="main"></div>
  <footer id="ticker"></footer>
  <div id="dot" title="Reconnecting"></div>
</div>

<script type="application/json" id="boot">@json($boot)</script>
@include('backend.pages.auction.partials.echo-init')
<script>
(function () {
  const STATE_URL = @json($stateUrl);
  let data = JSON.parse(document.getElementById('boot').textContent);
  let shownKey = null, timer = null, pushOk = false, failures = 0;

  // ---- fit the 1920x1080 stage to any screen ----
  function fit() {
    const s = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    const st = document.getElementById('stage');
    st.style.transform = `scale(${s})`;
    st.style.left = ((window.innerWidth - 1920 * s) / 2) + 'px';
    st.style.top = ((window.innerHeight - 1080 * s) / 2) + 'px';
  }
  window.addEventListener('resize', fit); fit();

  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const img = (src, cls) => src ? `<img class="${cls}" src="${esc(src)}" alt="">` : '';
  const ballCls = b => b.startsWith('W') ? 'w' : (b === '6' ? 's' : (b === '4' ? 'f' : (/wd|nb/.test(b) ? 'x' : '')));
  const balls = list => (list || []).map(b => `<span class="ball ${ballCls(b)}">${esc(b)}</span>`).join('');

  // ---- panels ----
  const P = {
    scoreboard(st) {
      if (!st) return `<div class="center h1">No match on this screen yet</div>`;
      if (st.status === 'innings_break') return P.innings_break(st);
      const s = st.score, b = st.batting || {};
      const chase = st.chase ? `<div class="h2" style="margin-top:24px">Need <span style="color:var(--accent)">${esc(st.chase.runs_needed)}</span> off ${esc(st.chase.balls_left)} balls · RRR ${esc(st.chase.required_run_rate ?? '-')}</div>` : '';
      const result = st.result ? `<div class="h1" style="color:var(--good);margin-top:24px">${esc(st.result.text)}</div>` : '';
      const pp = st.in_powerplay ? ' · <span style="color:var(--accent)">POWERPLAY</span>' : '';
      const agg = (st.innings_per_team || 1) > 1 ? `<div class="h2 mut" style="margin-top:16px">${(st.team_scores || []).map(t => esc(t.team?.short || '') + ' ' + esc(t.display || '-')).join(' &nbsp;·&nbsp; ')}</div>` : '';
      return `<div class="row">${img(b.logo, 'logo')}<div><div class="h2 mut">${esc(b.name || '')}${(st.total_innings || 2) > 2 ? ' · innings ' + esc(st.current_innings) : ''}${pp}</div>
        <div class="big">${esc(s.runs)}/${esc(s.wickets)}</div>
        <div class="h2 mut">${esc(s.overs)} overs · Run rate ${esc(s.run_rate)}</div>${agg}${chase}${result}</div></div>
        <div style="margin-top:36px">${balls(s.this_over)}</div>`;
    },
    // Between innings: the innings just played and where the match stands
    // ("need 181 to win" before the last innings, else the lead), not an empty 0/0.
    innings_break(st) {
      const first = (st.innings || {})[st.break?.innings || 1] || {}, team = first.team || {};
      const target = st.chase ? st.chase.target : (first.runs ?? 0) + 1;
      const standing = st.break?.text || `${st.batting?.name || 'Chasing side'} need ${target} to win`;
      return `<div class="center">
        <div class="h2" style="color:var(--accent);letter-spacing:.2em">INNINGS BREAK</div>
        <div class="row" style="justify-content:center;margin-top:24px">${img(team.logo, 'logo')}<div>
          <div class="h2 mut">${esc(team.name || '')}</div>
          <div class="big">${esc(first.runs ?? 0)}/${esc(first.wickets ?? 0)}</div>
          <div class="h2 mut">${esc(first.overs ?? '0.0')} overs</div></div></div>
        <div class="h1" style="margin-top:32px;color:var(--accent)">${esc(standing)}</div>
      </div>`;
    },
    wicket(st, p) {
      const w = p.wicket || {};
      return `<div class="splash"><div class="word" style="color:var(--bad)">WICKET</div>
        <div class="h1" style="margin-top:24px">${esc(w.name)} ${esc(w.runs)} (${esc(w.balls)})</div>
        <div class="h2 mut" style="margin-top:12px">${esc(w.how_out || '')}</div>
        <div class="h2" style="margin-top:24px">${esc(p.team?.short || '')} ${esc(p.score)}</div></div>`;
    },
    boundary(st, p) {
      const six = p.runs === 6;
      return `<div class="splash"><div class="word" style="color:${six ? 'var(--six)' : 'var(--four)'}">${six ? 'SIX' : 'FOUR'}</div>
        <div class="h1" style="margin-top:24px">${esc(p.batter?.name || '')}</div>
        <div class="h2 mut">${esc(p.batter?.runs)} (${esc(p.batter?.balls)}) · ${esc(p.score)}</div></div>`;
    },
    last_over(st, p) {
      const o = p.over || {};
      return `<div class="h2 mut">End of over ${esc(o.over)}${o.bowler ? ' · ' + esc(o.bowler) : ''}</div>
        <div style="margin:32px 0">${balls(o.balls)}</div>
        <div class="h1">${esc(o.runs)} runs · ${esc(o.wickets)} ${o.wickets === 1 ? 'wicket' : 'wickets'}</div>
        <div class="h2 mut" style="margin-top:16px">${esc(p.score || '')}</div>`;
    },
    batter_card(st, p) {
      const b = p.batter || st?.striker || {};
      return `<div class="card"><div class="h2 mut">Batting</div><div class="h1">${esc(b.name)}</div>
        <div class="big" style="margin-top:12px">${esc(b.runs)}<span class="h1 mut"> (${esc(b.balls)})</span></div>
        <div class="h2 mut">4s ${esc(b.fours)} · 6s ${esc(b.sixes)} · SR ${esc(b.strike_rate)}</div></div>`;
    },
    bowler_card(st, p) {
      const b = p.bowler || st?.bowler || {};
      return `<div class="card"><div class="h2 mut">Bowling</div><div class="h1">${esc(b.name)}</div>
        <div class="big" style="margin-top:12px">${esc(b.wickets)}-${esc(b.runs)}</div>
        <div class="h2 mut">${esc(b.overs)} overs · ${esc(b.maidens)} maidens · Econ ${esc(b.economy)}</div></div>`;
    },
    partnership(st, p) {
      const pt = p.partnership || st?.partnership || {};
      const a = p.striker || st?.striker, b = p.non_striker || st?.non_striker;
      return `<div class="center"><div class="h2 mut">Partnership</div>
        <div class="big">${esc(pt.runs)}<span class="h1 mut"> (${esc(pt.balls)})</span></div>
        <div class="h2" style="margin-top:24px">${a ? esc(a.name) + ' ' + esc(a.runs) + ' (' + esc(a.balls) + ')' : ''}${b ? ' &nbsp;·&nbsp; ' + esc(b.name) + ' ' + esc(b.runs) + ' (' + esc(b.balls) + ')' : ''}</div></div>`;
    },
    innings_summary(st, p) {
      const i = p.innings || {};
      const bat = (i.batters || []).filter(b => b.balls > 0 || b.runs > 0).sort((x, y) => y.runs - x.runs).slice(0, 4);
      const bowl = (i.bowlers || []).slice().sort((x, y) => y.wickets - x.wickets || x.runs - y.runs).slice(0, 3);
      return `<div class="row" style="justify-content:space-between"><div class="h1">${esc(i.team?.name || '')}</div>
        <div class="h1">${esc(i.runs)}/${esc(i.wickets)} <span class="mut">(${esc(i.overs)})</span></div></div>
        <div class="row" style="gap:48px;margin-top:32px;align-items:flex-start">
        <table class="lb" style="flex:1"><tr><th>Batting</th><th class="n">R</th><th class="n">B</th></tr>
        ${bat.map(b => `<tr><td>${esc(b.name)}${b.out ? '' : '*'}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.balls)}</td></tr>`).join('')}</table>
        <table class="lb" style="flex:1"><tr><th>Bowling</th><th class="n">W-R</th><th class="n">O</th></tr>
        ${bowl.map(b => `<tr><td>${esc(b.name)}</td><td class="n">${esc(b.wickets)}-${esc(b.runs)}</td><td class="n">${esc(b.overs)}</td></tr>`).join('')}</table></div>
        <div class="h2 mut" style="margin-top:24px">Extras ${esc(i.extras?.total ?? 0)}</div>`;
    },
    top_batting(st, p, title = 'Top batting — this match') {
      return `<div class="h1" style="margin-bottom:24px">${esc(title)}</div><table class="lb">
        <tr><th>Player</th><th class="n">Runs</th><th class="n">Balls</th><th class="n">SR</th></tr>
        ${(p.rows || []).map(r => `<tr><td>${esc(r.name)}${r.team ? ' <span class="mut">' + esc(r.team) + '</span>' : ''}</td><td class="n">${esc(r.runs)}</td><td class="n">${esc(r.balls ?? r.innings ?? '')}</td><td class="n">${esc(r.strike_rate ?? '')}</td></tr>`).join('')}</table>`;
    },
    top_bowling(st, p, title = 'Top bowling — this match') {
      return `<div class="h1" style="margin-bottom:24px">${esc(title)}</div><table class="lb">
        <tr><th>Player</th><th class="n">Wkts</th><th class="n">${p.rows?.[0]?.best !== undefined ? 'Best' : 'Runs'}</th><th class="n">Econ</th></tr>
        ${(p.rows || []).map(r => `<tr><td>${esc(r.name)}${r.team ? ' <span class="mut">' + esc(r.team) + '</span>' : ''}</td><td class="n">${esc(r.wickets)}</td><td class="n">${esc(r.best ?? r.runs ?? '')}</td><td class="n">${esc(r.economy ?? '')}</td></tr>`).join('')}</table>`;
    },
    tournament_batting(st, p) { return P.top_batting(st, p, 'Top run scorers — tournament'); },
    tournament_bowling(st, p) { return P.top_bowling(st, p, 'Top wicket takers — tournament'); },
    result(st, p) {
      const r = p.result || st?.result || {};
      return `<div class="splash">${img(r.winner?.logo, 'logo')}<div class="h1" style="font-size:96px;color:var(--good);margin-top:24px">${esc(r.text)}</div></div>`;
    },
    sponsor(st, p) {
      return `<div class="center h2 mut" style="margin-bottom:40px">Our sponsors</div><div class="sponsors">${(p.sponsors || []).map(s => img(s.image, '')).join('')}</div>`;
    },
    prematch(st) {
      if (!st) return P.scoreboard(st);
      const [a, b] = st.teams || [];
      return `<div class="row" style="justify-content:center;gap:80px">
        <div class="center">${img(a?.logo, 'logo')}<div class="h1">${esc(a?.name)}</div></div><div class="h1 mut">vs</div>
        <div class="center">${img(b?.logo, 'logo')}<div class="h1">${esc(b?.name)}</div></div></div>
        <div class="center h2 mut" style="margin-top:40px">${esc(st.match?.toss || '')}</div>`;
    },
    playing_xi(st, p) {
      const lineups = p.lineups || st?.lineups || {};
      const sides = Object.values(lineups);
      if (!sides.length) return `<div class="center h1">No lineups set</div>`;
      const col = side => {
        const team = side.team || {};
        const players = (side.players || []).slice(0, 11);
        return `<div style="flex:1">
          <div class="row" style="gap:16px;margin-bottom:24px">${img(team.logo, '')} <span class="h2">${esc(team.name)}</span></div>
          <table class="lb"><tr><th>#</th><th>Player</th><th>Role</th></tr>
          ${players.map((p, i) => `<tr><td>${esc(p.batting_order ?? i + 1)}</td><td>${esc(p.name)}</td><td class="n">${esc(p.role || '')}</td></tr>`).join('')}</table></div>`;
      };
      return `<div class="center h2" style="color:var(--accent);letter-spacing:.2em;margin-bottom:24px">PLAYING XI</div>
        <div class="row" style="gap:48px;align-items:flex-start">${sides.map(col).join('')}</div>`;
    },
    full_scorecard(st, p) {
      const i = p.innings || {};
      const bat = (i.batters || []);
      const bowl = (i.bowlers || []);
      return `<div class="row" style="justify-content:space-between;margin-bottom:24px">
        <div class="h1">${esc(i.team?.name || 'Innings')}</div>
        <div class="h1">${esc(i.runs)}/${esc(i.wickets)} <span class="mut">(${esc(i.overs)})</span></div></div>
        <div class="row" style="gap:48px;align-items:flex-start">
        <table class="lb" style="flex:1.3"><tr><th>Batter</th><th>How out</th><th class="n">R</th><th class="n">B</th><th class="n">4s</th><th class="n">6s</th><th class="n">SR</th></tr>
        ${bat.map(b => `<tr><td>${esc(b.name)}${b.out ? '' : '*'}</td><td class="mut">${esc(b.how_out || 'not out')}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.balls)}</td><td class="n">${esc(b.fours)}</td><td class="n">${esc(b.sixes)}</td><td class="n">${esc(b.strike_rate)}</td></tr>`).join('')}
        <tr><td colspan="2" class="mut">Extras</td><td class="n" colspan="5">${esc(i.extras?.total ?? 0)}</td></tr></table>
        <table class="lb" style="flex:1"><tr><th>Bowler</th><th class="n">O</th><th class="n">M</th><th class="n">R</th><th class="n">W</th><th class="n">Econ</th></tr>
        ${bowl.map(b => `<tr><td>${esc(b.name)}</td><td class="n">${esc(b.overs)}</td><td class="n">${esc(b.maidens)}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.wickets)}</td><td class="n">${esc(b.economy)}</td></tr>`).join('')}</table></div>`;
    },
    opening(st, p) {
      const s = p.striker || {}, n = p.non_striker || {}, bw = p.bowler;
      const bLogo = p.batting?.logo, batName = p.batting?.short || p.batting?.name || '';
      const pcard = (pl, role, side) => {
        const ph = pl.image
          ? `<img class="op-img" src="${esc(pl.image)}" alt="">`
          : `<div class="op-img empty">${bLogo ? `<img src="${esc(bLogo)}" style="width:45%;height:45%;object-fit:contain;opacity:.4" alt="">` : ''}</div>`;
        return `<div class="op-side op-${side}">
          ${bLogo ? `<img class="op-wm" src="${esc(bLogo)}" alt="">` : ''}
          ${ph}
          <div class="op-pname">${esc(pl.name)}</div>
          <div class="op-prole">${esc(role)}</div>
          <div class="op-team">${esc(batName)}</div>
        </div>`;
      };
      return `<div class="op">
        <div class="op-top"><div class="op-top-text">Opening Pair</div><div class="op-top-line"></div></div>
        ${pcard(s, 'Striker', 'l')}
        <div class="op-div"></div>
        ${pcard(n, 'Non-Striker', 'r')}
        ${bw ? `<div class="op-bot">
          <div class="op-bot-label">Opening Bowler</div>
          <div class="op-bot-name">${esc(bw.name)}</div>
          ${p.bowling ? `<div class="op-bot-team">${esc(p.bowling.short || p.bowling.name)}</div>` : ''}
        </div>` : ''}
      </div>`;
    },
  };

  // ---- ticker strip (always on) ----
  function ticker(st) {
    if (!st) return '';
    if (st.status === 'innings_break') {
      const first = (st.innings || {})[st.break?.innings || 1] || {};
      const target = st.chase ? st.chase.target : (first.runs ?? 0) + 1;
      const standing = st.break?.text || `${st.batting?.short || st.batting?.name || ''} need ${target}`;
      return `<div><div class="sm">Innings break</div><div class="score">${esc(first.team?.short || '')} ${esc(first.runs ?? 0)}/${esc(first.wickets ?? 0)}</div></div>
        <div><div class="sm">Overs</div><div>${esc(first.overs ?? '0.0')}</div></div>
        <div style="border-right:none"><div class="sm">${st.break && st.break.next_innings < st.total_innings ? 'Next innings' : 'Target'}</div><div>${esc(standing)}</div></div>`;
    }
    const s = st.score;
    const bat = p => p ? `<span class="${p.on_strike ? 'strike' : ''}">${esc(p.name)}${p.on_strike ? '*' : ''} ${esc(p.runs)} (${esc(p.balls)})</span>` : '';
    const target = st.chase ? `<div><div class="sm">Need</div><div>${esc(st.chase.runs_needed)} off ${esc(st.chase.balls_left)}</div></div>` : '';
    return `<div><div class="sm">${esc(st.batting?.short || '')} v ${esc(st.bowling?.short || '')}</div><div class="score">${esc(s.runs)}/${esc(s.wickets)}</div></div>
      <div><div class="sm">Overs</div><div>${esc(s.overs)}</div><div class="sm">RR ${esc(s.run_rate)}</div></div>${target}
      <div class="bat"><div>${bat(st.striker)}</div><div>${bat(st.non_striker)}</div></div>
      <div><div class="sm">Bowling</div><div>${st.bowler ? esc(st.bowler.name) + ' ' + esc(st.bowler.wickets) + '-' + esc(st.bowler.runs) + ' (' + esc(st.bowler.overs) + ')' : ''}</div></div>
      <div style="border-right:none;flex-direction:row;align-items:center">${balls(s.this_over)}</div>`;
  }

  // ---- draw ----
  function draw() {
    const st = data.state, now = data.now || { panel: 'scoreboard' };
    document.body.classList.toggle('light', data.display?.theme === 'light');
    // A design theme sets the colours and the wide background; plain dark/light clear them.
    const tv = data.display?.theme_vars;
    const root = document.documentElement.style;
    ['bg', 'panel', 'line', 'ink', 'muted', 'accent'].forEach(k => tv ? root.setProperty('--' + k, tv[k]) : root.removeProperty('--' + k));
    const stage = document.getElementById('stage');
    stage.style.backgroundImage = tv && tv.image ? `url("${tv.image.replace(/"/g, '')}")` : '';
    stage.style.backgroundSize = 'cover';
    document.body.classList.toggle('still', !!data.display?.reduced_motion);

    const t = data.tournament || {};
    const logo = document.getElementById('tLogo');
    if (t.logo) { logo.src = t.logo; logo.style.display = ''; } else { logo.style.display = 'none'; }
    document.getElementById('tName').textContent = t.name || '';
    document.getElementById('mName').textContent = st?.match?.name || '';

    const badge = document.getElementById('badge');
    const status = st?.status || 'prematch';
    badge.textContent = { live: 'LIVE', innings_break: 'INNINGS BREAK', completed: 'RESULT', prematch: 'UPCOMING', cancelled: 'CANCELLED' }[status] || '';
    badge.className = 'badge ' + ({ innings_break: 'break', completed: 'done', prematch: 'pre' }[status] || '');

    // Redraw the main panel only when the panel or the score changed.
    const key = (now.item_id ?? 'sb') + ':' + (st?.version ?? 0);
    if (key !== shownKey) {
      const panel = (P[now.panel] ? now.panel : 'scoreboard');
      let html;
      try { html = P[panel](st, now.payload || {}); } catch (e) { html = P.scoreboard(st); }
      document.getElementById('main').innerHTML = `<div class="panel">${html}</div>`;
      shownKey = key;
    }
    document.getElementById('ticker').innerHTML = ticker(st);
  }

  // ---- fetch: chained, never stacked ----
  function schedule(ms) {
    clearTimeout(timer);
    timer = setTimeout(refresh, pushOk ? ms : Math.min(ms, 3000));
  }

  async function refresh() {
    try {
      const r = await fetch(STATE_URL, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (r.status === 404) { document.getElementById('main').innerHTML = '<div class="panel center h1">This wall link is no longer active.</div>'; document.getElementById('ticker').innerHTML = ''; return; }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      data = await r.json();
      failures = 0;
      document.getElementById('dot').classList.remove('on');
      draw();
      schedule(data.next_check_ms || 3000);
    } catch (e) {
      // Keep the last score on screen; a small amber dot says we are reconnecting.
      failures++;
      document.getElementById('dot').classList.add('on');
      schedule(Math.min(15000, 2000 * failures));
    }
  }

  draw();
  schedule(data.next_check_ms || 3000);

  // ---- push: nudges only ----
  try {
    const matchCh = data.state ? window.pushChannel?.('match.' + data.state.match_id) : null;
    matchCh?.listen('.score.updated', e => { if (!data.state || e.version > data.state.version) refresh(); });
    window.pushChannel?.('led.' + data.display.id)?.listen('.led.changed', () => refresh());
    const conn = window.Echo?.connector?.pusher?.connection;
    conn?.bind('connected', () => { pushOk = true; refresh(); });
    conn?.bind('unavailable', () => { pushOk = false; schedule(3000); });
    conn?.bind('disconnected', () => { pushOk = false; schedule(3000); });
  } catch (e) {
    // Push is a speed-up only; the poll keeps the wall right without it.
    console.warn('[led] live updates unavailable, polling only:', e);
  }
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refresh(); });
  window.addEventListener('online', refresh);
})();
</script>
</body>
</html>
