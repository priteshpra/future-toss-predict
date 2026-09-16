const API = 'api.php';
const state = {
  date: null,
  league: 'all',
  view: 'matches',
  meta: null,
  matches: [],
  notified: new Set(),
};

const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

async function api(action, params = {}, method = 'GET') {
  const qs = new URLSearchParams({ action, ... (method === 'GET' ? params : {}) });
  const res = await fetch(`${API}?${qs}`, {
    method,
    headers: method === 'GET' ? {} : { 'Content-Type': 'application/json' },
    body: method === 'GET' ? undefined : JSON.stringify(params),
  });
  return res.json();
}

function fmtMins(m) {
  if (m <= -60) return 'Toss window passed';
  if (m <= 0) return 'Toss happening now';
  if (m < 60) return `${m} min to toss`;
  const h = Math.floor(m / 60);
  const mm = m % 60;
  return `${h}h ${mm}m to toss`;
}

function leagueName(id) {
  return (state.meta?.leagues || []).find((l) => l.id === id)?.name || id;
}

function renderDates() {
  const wrap = $('dates');
  wrap.innerHTML = (state.meta?.calendar || []).map((d) => `
    <button class="date-card ${d.date === state.date ? 'active' : ''} ${d.label === 'Today' ? 'today' : ''}" data-date="${d.date}">
      <small>${d.label} · ${d.weekday}</small>
      <b>${d.pretty}</b>
      <em>${d.count} matches</em>
    </button>
  `).join('');
  wrap.querySelectorAll('.date-card').forEach((el) => {
    el.onclick = () => {
      state.date = el.dataset.date;
      loadMatches();
      renderDates();
    };
  });
}

function renderLeagues() {
  const wrap = $('leagues');
  wrap.innerHTML = (state.meta?.leagues || []).map((l) => `
    <button class="chip ${state.league === l.id ? 'active' : ''}" data-league="${l.id}">${l.icon} ${l.name}</button>
  `).join('');
  wrap.querySelectorAll('.chip').forEach((el) => {
    el.onclick = () => {
      state.league = el.dataset.league;
      loadMatches();
      renderLeagues();
    };
  });
}

function fillTeams(selectId) {
  const sel = $(selectId);
  if (!sel || !state.meta) return;
  sel.innerHTML = '<option value="">Select team</option>' + state.meta.teams.map((t) =>
    `<option value="${t.name}">${t.badge || ''} ${t.name}</option>`
  ).join('');
}

function cardHTML(m) {
  const pred = m.prediction || {};
  const form = m.form || {};
  const venue = m.venueStats || {};
  const h2h = form.h2h || {};
  const aPct = pred.teamAPct ?? 50;
  const bPct = pred.teamBPct ?? 50;
  const loadA = pred.tossLoadA ?? 50;
  const loadB = pred.tossLoadB ?? 50;
  const phaseBadge = m.tossWinner
    ? '<span class="badge live">TOSS DONE</span>'
    : m.phase === 'alert_30' || m.phase === 'toss_now'
      ? '<span class="badge soon">30-MIN LOCK</span>'
      : m.status === 'LIVE'
        ? '<span class="badge live">LIVE</span>'
        : m.status === 'COMPLETED'
          ? '<span class="badge done">DONE</span>'
          : '<span class="badge">UPCOMING</span>';
  const pickClass = pred.locked ? 'pick locked' : 'pick';
  const actual = m.tossWinner
    ? `<div class="insight">Ground toss: <b>${esc(m.tossWinner)}</b> chose ${esc(m.tossDecision || '—')} ${pred.winner && namesClose(m.tossWinner, pred.winner) ? '· AI hit' : '· AI miss / pending compare'}</div>`
    : '';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const dateBit = (m.date && m.date !== state.date)
    ? ` · ${Number(m.date.slice(8, 10))} ${months[Number(m.date.slice(5, 7)) - 1] || m.date}`
    : '';
  return `
    <article class="card ${m.phase === 'alert_30' || m.phase === 'toss_now' ? 'alert30' : ''} ${m.status === 'LIVE' ? 'live' : ''}">
      <div class="meta">
        <div class="meta-left">${esc(m.format)} · ${esc(leagueName(m.league))}</div>
        ${phaseBadge}
      </div>
      <div class="meta" style="margin-top:6px">
        <span class="meta-left">${esc(m.time)}${dateBit} · toss ${esc(m.tossTime)}</span>
        <span class="meta-right">${fmtMins(m.minutesToToss)}</span>
      </div>
      <p class="tourney">${esc(m.tournament)}</p>
      <div class="vs">
        <div class="team">
          <div class="ico">${m.teamABadge || '🏏'}</div>
          <h3>${esc(m.teamA)}</h3>
          <small>${m.teamAHome ? 'Home ground' : esc(m.teamACaptain || 'Away / Neutral')}</small>
        </div>
        <div class="vs-mid">VS</div>
        <div class="team">
          <div class="ico">${m.teamBBadge || '🏏'}</div>
          <h3>${esc(m.teamB)}</h3>
          <small>${m.teamBHome ? 'Home ground' : esc(m.teamBCaptain || 'Away / Neutral')}</small>
        </div>
      </div>
      <div class="bars">
        <div class="bar-row"><span>AI toss win %</span><span>${aPct}% — ${bPct}%</span></div>
        <div class="track"><i style="width:${aPct}%"></i><i style="width:${bPct}%"></i></div>
        <div class="bar-row"><span>Market toss load</span><span>${loadA} — ${loadB}</span></div>
        <div class="track"><i style="width:${loadA}%"></i><i style="width:${loadB}%"></i></div>
      </div>
      <div class="${pickClass}">
        ${pred.locked ? '🔒 Locked 30-min pick' : '🤖 AI toss winner'}:
        <b>${esc(pred.winner)}</b> (${pred.probability || 50}%) · likely ${esc(pred.decision)}
      </div>
      ${m.liveScore ? `<p class="live-line">${esc(m.liveScore)}</p>` : ''}
      ${actual}
      ${m.punterLoad && (m.punterLoad.amountA + m.punterLoad.amountB) > 0 ? `<div class="punter-line">Punter money: <b>${esc(m.punterLoad.leader || 'Even')}</b> ${m.punterLoad.leader ? m.punterLoad.leaderPct + '%' : ''} · ${esc(m.teamA)} ${esc(m.punterLoad.amountALabel)} vs ${esc(m.teamB)} ${esc(m.punterLoad.amountBLabel)}</div>` : ''}
      <div class="facts">
        <span>📍 ${esc(m.venue)}</span>
        <span>Last 5: ${form.aLast5 || '—'} vs ${form.bLast5 || '—'}</span>
        <span>H2H toss ${h2h.teamAWins ?? 0}-${h2h.teamBWins ?? 0}</span>
        <span>${venue.dewFactor || '—'} dew</span>
      </div>
      <div class="actions">
        <button class="btn mint" data-act="analysis" data-id="${m.id}">Full analysis</button>
        <button class="btn" data-act="edit" data-id="${m.id}">Update</button>
        <button class="btn" data-act="toss" data-id="${m.id}">Set ground toss</button>
        <button class="btn danger" data-act="del" data-id="${m.id}">Remove</button>
      </div>
    </article>
  `;
}

function namesClose(a, b) {
  const n = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
  const x = n(a), y = n(b);
  return x && y && (x === y || x.includes(y) || y.includes(x));
}

function renderMatches(payload) {
  state.matches = payload.matches || [];
  const grid = $('grid');
  $('dayTitle').textContent = headingFor(payload.date);
  $('dayCount').textContent = `${payload.total} matches · updated ${payload.now}`;
  const calRow = (state.meta?.calendar || []).find((d) => d.date === payload.date);
  if (calRow && typeof payload.total === 'number') {
    calRow.count = payload.total;
  }
  if (!state.matches.length) {
    grid.innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
  } else {
    grid.innerHTML = state.matches.map(cardHTML).join('');
    grid.querySelectorAll('[data-act]').forEach((btn) => {
      const m = state.matches.find((x) => x.id === btn.dataset.id);
      if (!m) return;
      btn.onclick = () => {
        if (btn.dataset.act === 'analysis') openAnalysis(m.id);
        if (btn.dataset.act === 'edit') openMatchModal(m);
        if (btn.dataset.act === 'toss') promptToss(m);
        if (btn.dataset.act === 'del') removeMatch(m);
      };
    });
  }
  const alerts = payload.alerts || [];
  const box = $('heroAlert');
  if (alerts.length) {
    box.classList.add('show');
    box.innerHTML = `<strong>30-minute toss lock:</strong> ${alerts.map((a) => `${a.teamA} vs ${a.teamB} → <b>${a.prediction.winner}</b> (${a.prediction.probability}%)`).join(' · ')}`;
    alerts.forEach(maybeNotify);
  } else {
    box.classList.remove('show');
  }
}

function headingFor(date) {
  const row = (state.meta?.calendar || []).find((d) => d.date === date);
  if (!row) return date;
  if (row.label === 'Today') return 'Aaj ke matches';
  if (row.label === 'Tomorrow') return 'Kal / tomorrow ke matches';
  if (row.label === 'Yesterday') return 'Kal (yesterday) ke matches';
  return `${row.weekday}, ${row.pretty} ke matches`;
}

async function loadMatches() {
  $('grid').innerHTML = `<div class="empty">Live feeds + historical toss model load ho raha hai…</div>`;
  try {
    const data = await api('matches', { date: state.date, league: state.league });
    if (!data || data.error) {
      $('grid').innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
      return;
    }
    renderMatches(data);
  } catch (e) {
    $('grid').innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
  }
  renderDates();
}

function recordLines(team, rec) {
  rec = rec || {};
  const recent = (rec.recent || []).map((r) => `<div style="margin-top:4px;color:#94a3b8">${esc(r.text)}</div>`).join('');
  return `
    <div class="insight">
      <b>${esc(team.name)}</b>${team.captain ? ` · captain ${esc(team.captain)}` : ''}${team.home ? ' · Home' : ''}<br>
      Career toss wins <b>${rec.won || 0}/${rec.played || 0}</b> (${rec.pct || 50}%) · lost ${rec.lost || 0}<br>
      Last 10: ${team.last10Wins || 0}/${team.last10Total || 0} (${team.last10Pct || 50}%) · Last 5: ${team.last5Wins || 0}/${team.last5Total || 0} (${team.last5Pct || 50}%)<br>
      After winning toss: bowl ${rec.choseBowl || 0} / bat ${rec.choseBat || 0} · usual call <b>${esc(rec.call || '—')}</b><br>
      ${esc(team.streak?.text || '')}
      ${recent}
    </div>`;
}

function analysisDetailHTML(a, teamAName, teamBName, extra = {}) {
  const v = a.venue || extra.venueStats || {};
  const h2h = a.headToHead || extra.h2h || {};
  const pick = extra.prediction || a.prediction || {};
  return `
    <div class="pick locked">Best AI toss winner: <b>${esc(pick.winner || pick.favoredWinner || '')}</b> · ${pick.probability || pick.favoredProbability || ''}% · ${esc(pick.confidence || '')}</div>
    <div class="insight">If they win the toss, ground call: <b>${esc(pick.decision || pick.likelyDecision || v.preferredDecision || '—')}</b> at ${esc(v.venueName || extra.venue || '')} (bowl ${v.bowlFirstPct ?? '—'}% / bat ${v.batFirstPct ?? '—'}%, dew ${esc(v.dewFactor || '—')})</div>
    <h3>Why this pick</h3>
    ${(Array.isArray(a.prediction?.insights) ? a.prediction.insights : (Array.isArray(pick.insights) ? pick.insights : [])).map((i) => `<div class="insight">${esc(i)}</div>`).join('')}
    <h3>Team toss wins</h3>
    ${recordLines(a.teamA || {}, a.teamA?.record)}
    ${recordLines(a.teamB || {}, a.teamB?.record)}
    <h3>H2H toss</h3>
    <div class="insight">${esc(teamAName)} ${h2h.teamAWins ?? 0} – ${h2h.teamBWins ?? 0} ${esc(teamBName)} from ${h2h.total ?? 0} meetings</div>
    <h3>Calling / home / venue</h3>
    <div class="insight">Home: ${esc(teamAName)} ${a.teamA?.home ? 'YES' : 'no'} · ${esc(teamBName)} ${a.teamB?.home ? 'YES' : 'no'}</div>
    <div class="insight">Toss winner also won match historically ${v.tossWinMatchWinPct ?? '—'}% at this ground</div>
    <p style="font-size:12px;color:#64748b">Pick is from recorded toss wins (career + last 10 + H2H + home). A toss is still a coin; this is the statistical lean, not a guarantee.</p>
  `;
}

function openAnalysis(id) {
  const m = state.matches.find((x) => x.id === id);
  const d = $('drawer');
  if (!m || !d) return;
  document.body.classList.add('drawer-open');
  $('drawerBg')?.classList.add('show');
  d.innerHTML = `
    <button class="btn ghost" onclick="closeDrawer()">Close</button>
    <h2 style="margin:12px 0 4px">${esc(m.teamA)} vs ${esc(m.teamB)}</h2>
    <p style="color:#94a3b8">${esc(m.tournament)}<br>${esc(m.venue)} · toss ${esc(m.tossTime)}</p>
    ${analysisDetailHTML(m.analysis || {}, m.teamA, m.teamB, { prediction: m.prediction, venueStats: m.venueStats, venue: m.venue })}
  `;
  d.classList.add('show');
  d.scrollTop = 0;
  d.style.setProperty('transform', 'translate3d(0,0,0)', 'important');
}

function closeDrawer() {
  document.body.classList.remove('drawer-open');
  const d = $('drawer');
  if (d) {
    d.classList.remove('show');
    d.style.removeProperty('transform');
  }
  $('drawerBg')?.classList.remove('show');
}

async function promptToss(m) {
  const winner = prompt('Ground toss winner team name', m.prediction.winner);
  if (winner === null) return;
  const decision = prompt('Decision: bat or bowl', 'bowl') || 'bowl';
  await api('set_toss', {
    date: m.date,
    teamA: m.teamA,
    teamB: m.teamB,
    tossWinner: winner,
    tossDecision: decision,
    league: m.league,
    time: m.time,
    venue: m.venue,
    tournament: m.tournament,
    format: m.format,
    id: m.id,
  }, 'POST');
  loadMatches();
}

async function removeMatch(m) {
  if (!confirm(`Remove ${m.teamA} vs ${m.teamB}?`)) return;
  await api('delete_match', { date: m.date, teamA: m.teamA, teamB: m.teamB, id: m.id }, 'POST');
  loadMatches();
}

let editingMatch = null;

function setTeamField(selectId, customId, name) {
  const sel = $(selectId);
  const custom = $(customId);
  if (!sel) return;
  const hit = [...sel.options].some((o) => o.value === name);
  if (hit) {
    sel.value = name;
    if (custom) custom.value = '';
  } else {
    sel.value = '';
    if (custom) custom.value = name || '';
  }
}

function ensureLeagueOption(id) {
  const sel = $('cLeague');
  if (!sel || !id) return;
  if (![...sel.options].some((o) => o.value === id)) {
    const opt = document.createElement('option');
    opt.value = id;
    opt.textContent = leagueName(id);
    sel.appendChild(opt);
  }
  sel.value = id;
}

function openMatchModal(m = null) {
  editingMatch = m || null;
  $('addModal').classList.add('show');
  fillTeams('cTeamA');
  fillTeams('cTeamB');
  if ($('modalTitle')) $('modalTitle').textContent = m ? 'Update match' : 'Add custom match';
  if ($('cSaveBtn')) $('cSaveBtn').textContent = m ? 'Save updates' : 'Save & predict';
  if (m) {
    $('cDate').value = m.date || state.date;
    setTeamField('cTeamA', 'cTeamACustom', m.teamA);
    setTeamField('cTeamB', 'cTeamBCustom', m.teamB);
    $('cTime').value = String(m.time || '').replace(/\s*IST\s*$/i, '').trim() || '07:00 PM';
    $('cVenue').value = m.venue || 'International Cricket Ground';
    $('cFormat').value = m.format || 'T20';
    $('cTourn').value = m.tournament || '';
    ensureLeagueOption(m.league || 'custom');
  } else {
    $('cDate').value = state.date;
    $('cTeamA').value = '';
    $('cTeamB').value = '';
    if ($('cTeamACustom')) $('cTeamACustom').value = '';
    if ($('cTeamBCustom')) $('cTeamBCustom').value = '';
    $('cTime').value = '07:00 PM';
    $('cVenue').value = 'International Cricket Ground';
    $('cFormat').value = 'T20';
    $('cTourn').value = '';
    if ($('cLeague')) $('cLeague').value = 'custom';
  }
}

function openModal() {
  openMatchModal(null);
}

function closeModal() {
  editingMatch = null;
  $('addModal').classList.remove('show');
  if ($('modalTitle')) $('modalTitle').textContent = 'Add custom match';
  if ($('cSaveBtn')) $('cSaveBtn').textContent = 'Save & predict';
}

async function submitCustom(e) {
  e.preventDefault();
  const payload = {
    date: $('cDate').value,
    teamA: $('cTeamA').value || $('cTeamACustom').value,
    teamB: $('cTeamB').value || $('cTeamBCustom').value,
    time: $('cTime').value,
    venue: $('cVenue').value,
    league: $('cLeague').value,
    format: $('cFormat').value,
    tournament: $('cTourn').value,
  };
  if (editingMatch) {
    payload.id = editingMatch.id;
    payload.origDate = editingMatch.date;
    payload.origTeamA = editingMatch.teamA;
    payload.origTeamB = editingMatch.teamB;
  }
  const res = await api(editingMatch ? 'update_match' : 'add_custom', payload, 'POST');
  if (res.error) {
    alert(res.error);
    return;
  }
  closeModal();
  state.date = payload.date;
  await bootMeta();
  loadMatches();
}

async function runSim(e) {
  e.preventDefault();
  const teamA = $('sTeamA').value || $('sTeamACustom').value;
  const teamB = $('sTeamB').value || $('sTeamBCustom').value;
  const venue = $('sVenue').value;
  const data = await api('predict', { teamA, teamB, venue });
  if (!data || data.error) {
    $('simOut').innerHTML = `<div class="empty">${esc(data?.error || 'Prediction failed')}</div>`;
    return;
  }
  $('simOut').innerHTML = analysisDetailHTML(data, teamA, teamB, { venue });
}

async function loadBoard() {
  const data = await api('leaderboard', { league: $('lbLeague').value });
  $('lbBody').innerHTML = (data.teams || []).map((t, i) => `
    <tr>
      <td>${i + 1}</td>
      <td>${t.badge} ${t.name}</td>
      <td>${t.played}</td>
      <td>${t.tossWon}</td>
      <td><b>${t.tossWinPct}%</b></td>
      <td>${t.choseBowl}/${t.choseBat}</td>
    </tr>
  `).join('');
}

function setView(view) {
  state.view = view;
  document.querySelectorAll('.nav button[data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === view));
  $('view-matches').classList.toggle('hidden', view !== 'matches');
  $('view-sim').classList.toggle('hidden', view !== 'sim');
  $('view-board').classList.toggle('hidden', view !== 'board');
  $('view-tg')?.classList.toggle('hidden', view !== 'tg');
  if (view === 'sim') {
    fillTeams('sTeamA');
    fillTeams('sTeamB');
  }
  if (view === 'board') loadBoard();
  if (view === 'tg') {
    const badge = $('tgNavBadge');
    if (badge) {
      badge.classList.add('hidden');
      badge.textContent = '0';
    }
    loadTelegram(true);
  }
}

function maybeNotify(m) {
  if (state.notified.has(m.id) || !('Notification' in window)) return;
  if (Notification.permission !== 'granted') return;
  state.notified.add(m.id);
  new Notification('Toss lock · 30 min', {
    body: `${m.teamA} vs ${m.teamB} → AI: ${m.prediction.winner} (${m.prediction.probability}%)`,
  });
}

async function bootMeta() {
  state.meta = await api('meta');
  if (!state.date) state.date = state.meta.today;
  $('istNow').textContent = state.meta.now;
  $('istDay').textContent = state.meta.today;
  renderDates();
  renderLeagues();
  $('cLeague').innerHTML = state.meta.leagues.map((l) => `<option value="${l.id}">${l.name}</option>`).join('');
  $('lbLeague').innerHTML = state.meta.leagues.map((l) => `<option value="${l.id}">${l.name}</option>`).join('');
}

async function boot() {
  await bootMeta();
  await loadMatches();
  if ('Notification' in window && Notification.permission === 'default') {
    $('notifyBtn').classList.remove('hidden');
  }
  loadTelegram(false);
  setInterval(loadMatches, 45000);
  setInterval(() => loadTelegram(false), 12000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadTelegram(false);
  });
  setInterval(() => {
    const now = new Date().toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit' });
    $('istNow').textContent = now + ' IST';
  }, 15000);
}

document.addEventListener('DOMContentLoaded', () => {
  $('addBtn').onclick = openModal;
  $('closeModal').onclick = closeModal;
  $('drawerBg')?.addEventListener('click', closeDrawer);
  $('addModal')?.addEventListener('click', (e) => {
    if (e.target === $('addModal')) closeModal();
  });
  $('customForm').onsubmit = submitCustom;
  $('simForm').onsubmit = runSim;
  $('notifyBtn').onclick = async () => {
    await Notification.requestPermission();
    $('notifyBtn').classList.add('hidden');
  };
  document.querySelectorAll('.nav button[data-view]').forEach((b) => {
    b.onclick = () => setView(b.dataset.view);
  });
  $('tgRefresh')?.addEventListener('click', () => loadTelegram(true));
  $('tgSaveUsers')?.addEventListener('click', saveTgWatch);
  $('tgNotifyBtn')?.addEventListener('click', enableTgAlerts);
  syncTgNotifyBtn();
  boot();
});

const tgSeen = new Set(JSON.parse(localStorage.getItem('fta_tg_seen') || '[]'));
let tgPrimed = false;

function playTgBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.value = 880;
    o.connect(g);
    g.connect(ctx.destination);
    g.gain.setValueAtTime(0.0001, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
    o.start();
    o.stop(ctx.currentTime + 0.5);
  } catch (e) {}
}

function rememberSeen() {
  localStorage.setItem('fta_tg_seen', JSON.stringify([...tgSeen].slice(-200)));
}

function notifyRahul(bet) {
  if (!bet?.postId || tgSeen.has(bet.postId)) return;
  tgSeen.add(bet.postId);
  rememberSeen();
  playTgBeep();
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification(`${bet.userName || 'Rahul Dada'} ka naya bet`, {
      body: `Team: ${bet.teamName || '—'}  |  Amount: ${bet.amount || '—'}`,
      tag: bet.postId,
      requireInteraction: true,
    });
  }
  const badge = $('tgNavBadge');
  if (badge) {
    badge.classList.remove('hidden');
    badge.textContent = String((parseInt(badge.textContent, 10) || 0) + 1);
  }
}

function syncTgNotifyBtn() {
  const btn = $('tgNotifyBtn');
  if (!btn || !('Notification' in window)) return;
  btn.textContent = Notification.permission === 'granted' ? 'Alerts on' : 'Enable alerts';
}

async function enableTgAlerts() {
  if (!('Notification' in window)) return;
  await Notification.requestPermission();
  syncTgNotifyBtn();
}

async function saveTgWatch() {
  const users = ($('tgUsers').value || 'Rahul Dada').split(',').map((s) => s.trim()).filter(Boolean);
  await api('telegram_config', { targetUsers: users.length ? users : ['Rahul Dada'], hideOthers: true }, 'POST');
  loadTelegram(true);
}

function renderTeamMoney(teams) {
  const box = $('tgTeams');
  if (!box) return;
  if (!teams?.length) {
    box.innerHTML = '';
    return;
  }
  box.innerHTML = `<p class="tg-hint">Unmapped channel teams</p>` +
    teams.slice(0, 8).map((t) => `<div class="bet-row">${esc(t.team)} · <b>${esc(t.totalLabel)}</b> · ${t.bets} bets</div>`).join('');
}

function isTgMatchDone(r) {
  const st = String(r?.status || '').toUpperCase();
  return !!(r?.tossWinner || r?.matchWinner || r?.phase === 'done' || st === 'COMPLETED' || st === 'LIVE');
}

function renderPunterLoad(rows) {
  const box = $('tgLoad');
  if (!box) return;
  if (!rows?.length) {
    box.innerHTML = '<div class="empty">Aaj ke matches load hone ke baad Team A vs Team B amount yahan dikhega.</div>';
    return;
  }
  const sorted = [...rows].sort((a, b) => {
    const dA = isTgMatchDone(a) ? 1 : 0;
    const dB = isTgMatchDone(b) ? 1 : 0;
    if (dA !== dB) return dA - dB;
    return ((b.amountA || 0) + (b.amountB || 0)) - ((a.amountA || 0) + (a.amountB || 0));
  });
  box.innerHTML = sorted.map((r) => {
    const pctA = r.pctA ?? (r.amountA + r.amountB > 0 ? Math.round(r.amountA / (r.amountA + r.amountB) * 100) : 50);
    const pctB = r.pctB ?? (100 - pctA);
    const done = isTgMatchDone(r);
    return `
    <article class="vs-amount ${done ? 'done' : ''}">
      <div class="vs-amount-meta">${esc(r.time || '')} · ${esc(r.tournament || '')}${done ? ' · TOSS DONE' : ''}</div>
      <div class="vs-amount-grid">
        <div class="vs-side ${r.leader === r.teamA ? 'win' : ''}">
          <small>Team A</small>
          <h4>${esc(r.teamA)}</h4>
          <b>${esc(r.amountALabel)}</b>
          <em>${r.betsA || 0} bets · ${pctA}%</em>
        </div>
        <div class="vs-mid-amt">VS</div>
        <div class="vs-side ${r.leader === r.teamB ? 'win' : ''}">
          <small>Team B</small>
          <h4>${esc(r.teamB)}</h4>
          <b>${esc(r.amountBLabel)}</b>
          <em>${r.betsB || 0} bets · ${pctB}%</em>
        </div>
      </div>
      <div class="track vs-track"><i style="width:${pctA}%"></i><i style="width:${pctB}%"></i></div>
      <div class="vs-total">Total amount: <strong>${esc(r.totalLabel)}</strong>${r.leader ? ` · lean <strong>${esc(r.leader)}</strong>` : ''}</div>
    </article>`;
  }).join('');
}

function renderTgFeed(bets) {
  const box = $('tgFeed');
  if (!box) return;
  if (!bets.length) {
    box.innerHTML = '<div class="empty">Rahul Dada ka koi bet abhi nahi aaya. Jaise hi channel pe unka bet drop hoga, yahan dikhega aur notification aa jayegi.</div>';
    return;
  }
  box.innerHTML = bets.map((b) => `
    <article class="bet-row ${b.watched ? 'watched' : ''}">
      <div class="who">${esc(b.userName)} · ${esc(b.displayTime)}</div>
      <h4>${esc(b.teamName || 'Update')} · ${esc(b.amount || '')}</h4>
      <div style="color:#94a3b8;font-size:12px;white-space:pre-wrap">${esc(b.rawText)}</div>
      <a class="btn" href="${esc(b.messageUrl)}" target="_blank" rel="noopener">View post</a>
    </article>
  `).join('');
}

async function loadTelegram(force) {
  const data = await api('telegram_bets', { type: 'bets_only', hideOthers: '0', force: force ? '1' : '' });
  if (data.config?.targetUsers && $('tgUsers') && document.activeElement !== $('tgUsers')) {
    $('tgUsers').value = data.config.targetUsers.join(', ');
  }
  if ($('tgStatus')) {
    $('tgStatus').textContent = data.status === 'connected' ? 'Live' : (data.status || 'Wait');
  }
  if ($('tgPoll')) $('tgPoll').textContent = `Last poll: ${data.fetchedAt || '—'}`;
  const watched = (data.watched || []).filter((b) => b.type === 'BET_PLACED');
  if ($('tgCount')) $('tgCount').textContent = String(watched.length);
  renderTeamMoney(data.punterLoad?.teams || []);
  renderPunterLoad(data.punterLoad?.matches || []);
  renderTgFeed(watched);
  if (!tgPrimed) {
    watched.forEach((b) => tgSeen.add(b.postId));
    rememberSeen();
    tgPrimed = true;
    return;
  }
  watched.forEach(notifyRahul);
}
