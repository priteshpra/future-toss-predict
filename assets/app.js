const API = 'api.php';
const state = {
  date: null,
  league: 'all',
  view: 'matches',
  meta: null,
  matches: [],
  notified: new Set(),
  deskFilter: ['live', 'done'].includes(localStorage.getItem('ftp_deskFilter') || '')
    ? 'all'
    : (localStorage.getItem('ftp_deskFilter') || 'all'),
  prevAct: {},
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
      <small>${d.label}  ·  ${d.weekday}</small>
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

function matchAction(m) {
  return (m?.prediction?.tipperReport?.action || 'WAIT').toUpperCase();
}

function matchPick(m) {
  const r = m?.prediction?.tipperReport || {};
  return r.pick || m?.prediction?.winner || '';
}

function rupeeLine(m) {
  const pl = m?.punterLoad || {};
  const total = (pl.amountA || 0) + (pl.amountB || 0);
  if (total <= 0) return 'No mapped ₹';
  return `${pl.amountALabel || '₹0'} vs ${pl.amountBLabel || '₹0'}`;
}

function watchedIds() {
  try {
    return JSON.parse(localStorage.getItem('ftp_watch') || '[]');
  } catch (e) {
    return [];
  }
}

function isWatched(id) {
  return watchedIds().includes(id);
}

function toggleWatch(id) {
  const set = new Set(watchedIds());
  if (set.has(id)) set.delete(id);
  else set.add(id);
  localStorage.setItem('ftp_watch', JSON.stringify([...set]));
}

function actionStamp(m) {
  if (m?.tossWinner) return { cls: 'done', label: 'TOSS DONE', sub: m.tossWinner };
  const act = matchAction(m);
  const pick = matchPick(m);
  if (act === 'PLAY') return { cls: 'play', label: 'PLAY', sub: pick };
  if (act === 'LEAN') {
    const early = !m?.prediction?.hasLoad && !m?.prediction?.sources?.loadOnly;
    return { cls: 'lean', label: early ? 'EARLY AI' : 'LEAN', sub: pick || 'last 5 lean' };
  }
  if (act === 'SKIP') return { cls: 'skip', label: 'SKIP', sub: 'split — no bet' };
  return { cls: 'wait', label: 'WAIT', sub: pick || 'load pending' };
}

function isOpenToss(m) {
  if (m?.tossWinner) return false;
  const mins = Number(m?.minutesToToss);
  return !Number.isFinite(mins) || mins >= -10;
}

function punterSort(a, b) {
  const doneA = a?.tossWinner ? 1 : 0;
  const doneB = b?.tossWinner ? 1 : 0;
  if (doneA !== doneB) return doneA - doneB;
  const rank = (m) => {
    const act = matchAction(m);
    const pri = act === 'PLAY' ? 0 : act === 'LEAN' ? 1 : act === 'SKIP' ? 2 : 3;
    return (isWatched(m.id) ? -1 : 0) + pri;
  };
  const r = rank(a) - rank(b);
  if (r !== 0) return r;
  return (Number(a.minutesToToss) || 99999) - (Number(b.minutesToToss) || 99999);
}

function filteredMatches(matches) {
  const list = [...(matches || [])];
  const f = state.deskFilter || 'all';
  const out = list.filter((m) => {
    if (f === 'all') return true;
    if (f === 'done') return !!m.tossWinner;
    if (f === 'play') return isOpenToss(m) && (matchAction(m) === 'PLAY' || matchAction(m) === 'LEAN');
    if (f === 'wait') return isOpenToss(m) && matchAction(m) === 'WAIT';
    if (f === 'watch') return isWatched(m.id);
    return isOpenToss(m);
  });
  out.sort(punterSort);
  return out;
}

function deskCandidates(matches) {
  const open = (matches || []).filter(isOpenToss).sort(punterSort);
  const plays = open.filter((m) => matchAction(m) === 'PLAY' || matchAction(m) === 'LEAN');
  const soon = open.filter((m) => Number(m.minutesToToss) <= 40);
  const watched = open.filter((m) => isWatched(m.id));
  const seen = new Set();
  const rows = [];
  for (const m of [...plays, ...watched, ...soon, ...open]) {
    if (seen.has(m.id)) continue;
    seen.add(m.id);
    rows.push(m);
    if (rows.length >= 4) break;
  }
  return rows;
}

function renderDeskFilters(matches) {
  const box = $('deskFilters');
  if (!box) return;
  const all = matches || [];
  const nLive = all.filter(isOpenToss).length;
  const nPlay = all.filter((m) => isOpenToss(m) && (matchAction(m) === 'PLAY' || matchAction(m) === 'LEAN')).length;
  const nWait = all.filter((m) => isOpenToss(m) && matchAction(m) === 'WAIT').length;
  const nDone = all.filter((m) => m.tossWinner).length;
  const nWatch = all.filter((m) => isWatched(m.id)).length;
  box.hidden = !all.length;
  const chips = [
    ['live', `Live desk ${nLive}`],
    ['play', `Play / lean ${nPlay}`],
    ['wait', `Wait load ${nWait}`],
    ['watch', `Watch ${nWatch}`],
    ['done', `Toss done ${nDone}`],
    ['all', `All ${all.length}`],
  ];
  box.innerHTML = chips.map(([id, label]) =>
    `<button class="chip ${state.deskFilter === id ? 'active' : ''}" data-desk="${id}">${label}</button>`
  ).join('');
  box.querySelectorAll('[data-desk]').forEach((el) => {
    el.onclick = () => {
      state.deskFilter = el.dataset.desk;
      localStorage.setItem('ftp_deskFilter', state.deskFilter);
      renderDeskFilters(state.matches);
      renderPunterDesk(state.matches);
      paintMatchGrid();
    };
  });
}

function renderPunterDesk(matches) {
  const box = $('punterDesk');
  if (!box) return;
  const rows = deskCandidates(matches);
  if (!rows.length) {
    box.hidden = true;
    box.innerHTML = '';
    return;
  }
  box.hidden = false;
  const playN = rows.filter((m) => matchAction(m) === 'PLAY').length;
  box.innerHTML = `
    <div class="desk-kicker">Punter desk · pehle yeh dekho <small>${playN ? playN + ' PLAY ready' : 'koi PLAY lock nahi — load wait'}</small></div>
    ${rows.map((m) => {
      const st = actionStamp(m);
      const pick = matchPick(m);
      return `<button class="desk-row ${st.cls}" type="button" data-jump="${esc(m.id)}">
        <span class="desk-act">${esc(st.label)}</span>
        <span class="desk-vs"><b>${esc(pick || m.teamA)}</b> <small>${esc(m.teamA)} vs ${esc(m.teamB)}</small></span>
        <span class="desk-meta">${esc(fmtMins(m.minutesToToss))}<br>${esc(rupeeLine(m))}</span>
      </button>`;
    }).join('')}
  `;
  box.querySelectorAll('[data-jump]').forEach((el) => {
    el.onclick = () => {
      const card = document.querySelector(`article[data-mid="${el.dataset.jump}"]`);
      card?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      card?.classList.add('flash');
      setTimeout(() => card?.classList.remove('flash'), 1200);
    };
  });
}

function maybePlayNotify(m) {
  const act = matchAction(m);
  const prev = state.prevAct[m.id];
  state.prevAct[m.id] = act;
  if (!prev || prev === act || m.tossWinner) return;
  if (act !== 'PLAY' && act !== 'LEAN') return;
  if (!('Notification' in window) || Notification.permission !== 'granted') return;
  const pick = matchPick(m) || 'load side';
  new Notification(act === 'PLAY' ? 'PLAY lock · load aa gaya' : 'Load lean ready', {
    body: `${m.teamA} vs ${m.teamB} → ${pick} · ${rupeeLine(m)} · ${fmtMins(m.minutesToToss)}`,
  });
}

function copyPick(m) {
  const st = actionStamp(m);
  const extra = m.tossWinner
    ? `chose ${m.tossDecision || '—'}`
    : `${rupeeLine(m)} · toss ${m.tossTime || m.time || ''} IST`;
  const text = `${st.label} ${st.sub || ''} · ${m.teamA} vs ${m.teamB} · ${extra}`;
  if (navigator.clipboard?.writeText) navigator.clipboard.writeText(text.trim());
}

function tossDots(team, rows) {
  const rec = (rows && rows.length)
    ? rows
    : ((team?.last5Rows && team.last5Rows.length)
      ? team.last5Rows
      : (team?.record?.recent || []));
  if (!rec.length) return '';
  return `<div class="toss-dots">${rec.slice(0, 5).map((r) => `<em class="${r.won ? 'w' : 'l'}">${r.won ? 'W' : 'L'}</em>`).join('')}</div>`;
}

function cardHTML(m) {
  const pred = m.prediction || {};
  const form = m.form || {};
  const venue = m.venueStats || {};
  const h2h = form.h2h || {};
  const src = pred.sources || {};
  const report = pred.tipperReport || {};
  const tossed = !!m.tossWinner;
  const aPct = pred.teamAPct ?? 50;
  const bPct = pred.teamBPct ?? 50;
  const loadA = pred.tossLoadA ?? 50;
  const loadB = pred.tossLoadB ?? 50;
  const result = tossResult(m);
  const phaseBadge = m.tossWinner
    ? (result === 'pass'
      ? '<span class="badge live">AI PASS</span>'
      : result === 'fail'
        ? '<span class="badge soon">AI FAIL</span>'
        : '<span class="badge live">TOSS DONE</span>')
    : m.phase === 'alert_30' || m.phase === 'toss_now'
      ? '<span class="badge soon">30-MIN LOCK</span>'
      : m.status === 'LIVE'
        ? '<span class="badge live">LIVE</span>'
        : m.status === 'COMPLETED'
          ? '<span class="badge done">DONE</span>'
          : '<span class="badge">UPCOMING</span>';
  const st = actionStamp(m);
  const stampMeta = tossed
    ? (m.tossDecision ? `chose ${m.tossDecision}` : 'ground toss')
    : `${rupeeLine(m)} · ${fmtMins(m.minutesToToss)}`;
  const stamp = `<div class="action-stamp ${st.cls}"><b>${esc(st.label)}</b><span>${esc(st.sub || '')}</span><em>${esc(stampMeta)}</em></div>`;
  const pickClass = report.action === 'PLAY'
    ? 'pick strong'
    : report.action === 'SKIP'
      ? 'pick skip'
      : report.action === 'WAIT'
        ? 'pick wait'
        : (pred.locked ? 'pick locked' : 'pick');
  const pickLabel = report.action === 'PLAY'
    ? (src.triple || pred.pickStrength === 'best' ? 'BEST PICK' : (src.loadOnly ? 'LOAD PICK' : 'STRONG PICK'))
    : report.action === 'SKIP'
      ? 'NO PICK'
      : report.action === 'WAIT'
        ? 'WAIT'
        : report.action === 'LEAN'
          ? 'SOFT LEAN'
          : (pred.locked ? 'Locked lean' : 'Toss lean');
  const last5Note = src.lastToss?.winner || report.lastToss || '';
  const pickBody = report.action === 'SKIP'
    ? `${pickLabel}: last 5 vs load split — skip`
    : report.action === 'WAIT'
      ? (last5Note
        ? `${pickLabel}: last-5 lean <b>${esc(last5Note)}</b> · load pending · not a lock`
        : `${pickLabel}: last 5 even · load pending`)
      : report.action === 'LEAN'
        ? `${pickLabel}: <b>${esc(report.pick || pred.winner || 'even')}</b> (${pred.probability || 50}%) · load lean`
        : (report.pick || pred.winner
          ? `${pickLabel}: <b>${esc(report.pick || pred.winner)}</b> (${pred.probability || 50}%)  ·  ${esc(pred.confidence || '')}`
          : `${pickLabel}: no side locked`);
  const resultMark = result === 'pass'
    ? '<b class="hit">PASS</b>'
    : result === 'fail'
      ? '<b class="miss">FAIL</b>'
      : result === 'nopick'
        ? 'no pick'
        : 'pending';
  const actual = m.tossWinner
    ? `<div class="insight">Ground toss: <b>${esc(m.tossWinner)}</b> chose ${esc(m.tossDecision || '—')}  ·  AI ${resultMark}</div>`
    : '';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const dateBit = (m.date && m.date !== state.date)
    ? `  ·  ${Number(m.date.slice(8, 10))} ${months[Number(m.date.slice(5, 7)) - 1] || m.date}`
    : '';
  const last5A = form.aLast5 && form.aLast5 !== '0/0' ? form.aLast5 : '—';
  const last5B = form.bLast5 && form.bLast5 !== '0/0' ? form.bLast5 : '—';
  const hasTg = !!(pred.hasTgLoad || (m.punterLoad && (m.punterLoad.amountA + m.punterLoad.amountB) > 0));
  const web = m.websiteLoad || src.website || {};
  const onBook = !!(pred.onBook || web.onBook);
  const hasWeb = !!(pred.hasWebLoad || web.hasLean);
  const tgLabel = hasTg
    ? `${loadA}% — ${loadB}%`
    : 'No TG money yet';
  const webA = pred.webLoadA ?? web.pctA ?? 50;
  const webB = pred.webLoadB ?? web.pctB ?? 50;
  const webLabel = onBook
    ? (hasWeb ? `${webA}% — ${webB}%` : 'Listed · no load yet')
    : 'Not on toss-book';
  const signalNote = src.split
    ? `<div class="split-note">SPLIT: last 5 <b>${esc(src.lastToss?.winner)}</b>  ·  load <b>${esc(src.load?.winner)}</b> — skip</div>`
    : (src.triple
      ? `<div class="agree-note">BEST: last 5 + Telegram + website teeno <b>${esc(pred.winner)}</b> pe agree</div>`
      : (src.agree ? `<div class="agree-note">STRONG: last 5 aur load dono <b>${esc(pred.winner)}</b> pe agree</div>` : ''));
  const money = hasTg
    ? `<div class="punter-line">Telegram ₹: <b>${esc(m.punterLoad?.leader || 'Even')}</b> ${m.punterLoad?.leader ? m.punterLoad.leaderPct + '%' : ''}  ·  ${esc(m.teamA)} ${esc(m.punterLoad?.amountALabel || '')} vs ${esc(m.teamB)} ${esc(m.punterLoad?.amountBLabel || '')}</div>`
    : `<div class="punter-line">Telegram ₹: is match pe mapped toss money nahi mila</div>`;
  const webLine = `<div class="web-line">${esc(web.label || (onBook ? (hasWeb ? ('Website load favouring ' + (web.leanTeam || '')) : 'Toss-book pe listed · no load yet') : 'Website load: toss-book pe listed nahi'))}</div>`;
  const bookBadge = onBook && !tossed ? '<span class="badge book">ON BOOK</span>' : '';
  const reportBox = (!tossed && report.headline)
    ? `<div class="tipper ${esc(report.grade || 'wait')}">
          <div class="tipper-kicker">Tipper report · ${esc(report.action || 'WAIT')}</div>
          <p>${esc(report.headline)}</p>
          <small>${esc(report.when || '')}</small>
        </div>`
    : '';
  const bars = tossed ? '' : `
      <div class="bars">
        <div class="bar-row"><span>Last-match toss %</span><span>${aPct}% — ${bPct}%</span></div>
        <div class="track"><i style="width:${aPct}%"></i><i style="width:${bPct}%"></i></div>
        <div class="bar-row"><span>Telegram ₹</span><span>${esc(tgLabel)}</span></div>
        <div class="track"><i style="width:${hasTg ? loadA : 50}%"></i><i style="width:${hasTg ? loadB : 50}%"></i></div>
        <div class="bar-row"><span>Website load</span><span>${esc(webLabel)}</span></div>
        <div class="track web"><i style="width:${onBook && hasWeb ? webA : 50}%"></i><i style="width:${onBook && hasWeb ? webB : 50}%"></i></div>
      </div>`;
  const pickBlock = tossed ? '' : `<div class="${pickClass}">${pickBody}</div>`;
  const moneyBlock = tossed ? '' : money;
  const webBlock = tossed ? '' : webLine;
  const signalBlock = tossed ? '' : signalNote;
  return `
    <article class="card ${m.phase === 'alert_30' || m.phase === 'toss_now' ? 'alert30' : ''} ${m.status === 'LIVE' ? 'live' : ''} ${st.cls === 'play' ? 'play-now' : ''} ${isWatched(m.id) ? 'watched' : ''}" data-mid="${esc(m.id)}">
      ${stamp}
      <div class="meta">
        <div class="meta-left">${esc(m.format)}  ·  ${esc(leagueName(m.league))}</div>
        <div class="meta-right">${bookBadge}${phaseBadge}</div>
      </div>
      <div class="meta" style="margin-top:6px">
        <span class="meta-left">${esc(m.time)}${dateBit}  ·  toss ${esc(m.tossTime)}</span>
        <span class="meta-right">${fmtMins(m.minutesToToss)}</span>
      </div>
      <p class="tourney">${esc(m.tournament)}</p>
      <div class="vs">
        <div class="team">
          <div class="ico">${m.teamABadge || '🏏'}</div>
          <h3>${esc(m.teamA)}</h3>
          <small>Last 5 toss ${last5A}${m.teamAHome ? '  ·  Home' : (m.teamACaptain ? '  ·  ' + esc(m.teamACaptain) : '')}</small>
          ${tossDots(m.analysis?.teamA, form.aLast5Rows)}
        </div>
        <div class="vs-mid">VS</div>
        <div class="team">
          <div class="ico">${m.teamBBadge || '🏏'}</div>
          <h3>${esc(m.teamB)}</h3>
          <small>Last 5 toss ${last5B}${m.teamBHome ? '  ·  Home' : (m.teamBCaptain ? '  ·  ' + esc(m.teamBCaptain) : '')}</small>
          ${tossDots(m.analysis?.teamB, form.bLast5Rows)}
        </div>
      </div>
      ${bars}
      ${reportBox}
      ${pickBlock}
      ${actual}
      ${moneyBlock}
      ${webBlock}
      ${signalBlock}
      <div class="facts">
        <span>📍 ${esc(m.venue)}</span>
        <span>Last 10: ${form.aLast10 || '—'} vs ${form.bLast10 || '—'}</span>
        <span>H2H toss ${h2h.teamAWins ?? 0}-${h2h.teamBWins ?? 0}</span>
        <span>${venue.dewFactor || '—'} dew</span>
      </div>
      <div class="actions">
        <button class="btn mint" data-act="analysis" data-id="${m.id}">Full analysis</button>
        <button class="btn" data-act="copy" data-id="${m.id}">${m.tossWinner ? 'Copy toss' : 'Copy pick'}</button>
        <button class="btn ${isWatched(m.id) ? 'gold' : ''}" data-act="watch" data-id="${m.id}">${isWatched(m.id) ? 'Watching' : 'Watch'}</button>
        <button class="btn" data-act="edit" data-id="${m.id}">Update</button>
        <button class="btn" data-act="toss" data-id="${m.id}">${m.tossWinner ? 'Edit toss' : 'Set ground toss'}</button>
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

function last5ScorePick(m) {
  const pred = m?.prediction || {};
  if (pred.last5Winner) return pred.last5Winner;
  const parse = (s) => {
    const hit = String(s || '').match(/^(\d+)\s*\/\s*(\d+)$/);
    return hit ? { w: Number(hit[1]), n: Number(hit[2]) } : null;
  };
  const a = parse(m?.form?.aLast5);
  const b = parse(m?.form?.bLast5);
  if (!a || !b || a.n < 2 || b.n < 2 || a.w === b.w) return '';
  return a.w > b.w ? m.teamA : m.teamB;
}

function gradedPick(m) {
  if (m?.scorePick) return m.scorePick;
  const pred = m?.prediction || {};
  const report = pred.tipperReport || {};
  const action = (report.action || '').toUpperCase();
  if (action === 'PLAY' || action === 'LEAN') return report.pick || pred.winner || '';
  if (m?.tossWinner) return last5ScorePick(m);
  return '';
}

function tossResult(m) {
  if (!m?.tossWinner) return 'pending';
  const pick = gradedPick(m);
  if (!pick) return 'nopick';
  return namesClose(m.tossWinner, pick) ? 'pass' : 'fail';
}

function renderScoreboard(matches) {
  const box = $('scoreboard');
  if (!box) return;
  let pass = 0, fail = 0, pending = 0, nopick = 0;
  (matches || []).forEach((m) => {
    const r = tossResult(m);
    if (r === 'pass') pass += 1;
    else if (r === 'fail') fail += 1;
    else if (r === 'nopick') nopick += 1;
    else pending += 1;
  });
  const graded = pass + fail;
  const rate = graded ? Math.round((pass / graded) * 100) : 0;
  box.hidden = false;
  box.innerHTML = `
    <span class="score-chip pass"><b>${pass}</b> Pass</span>
    <span class="score-chip fail"><b>${fail}</b> Fail</span>
    <span class="score-chip nopick"><b>${nopick}</b> No pick</span>
    <span class="score-chip pending"><b>${pending}</b> Pending</span>
    <span class="score-chip rate"><b>${graded ? rate + '%' : '—'}</b> Hit rate</span>
  `;
}


function paintMatchGrid() {
  const grid = $('grid');
  if (!grid) return;
  if (!state.matches.length) {
    grid.innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
    return;
  }
  const shown = filteredMatches(state.matches);
  if (!shown.length) {
    grid.innerHTML = `<div class="empty">Is filter pe match nahi. Upar se <b>All</b> ya <b>Toss done</b> try karo.</div>`;
    return;
  }
  grid.innerHTML = shown.map(cardHTML).join('');
  grid.querySelectorAll('[data-act]').forEach((btn) => {
    const m = state.matches.find((x) => x.id === btn.dataset.id);
    if (!m) return;
    btn.onclick = () => {
      if (btn.dataset.act === 'analysis') openAnalysis(m.id);
      if (btn.dataset.act === 'edit') openMatchModal(m);
      if (btn.dataset.act === 'toss') promptToss(m);
      if (btn.dataset.act === 'del') removeMatch(m);
      if (btn.dataset.act === 'copy') copyPick(m);
      if (btn.dataset.act === 'watch') {
        toggleWatch(m.id);
        renderDeskFilters(state.matches);
        renderPunterDesk(state.matches);
        paintMatchGrid();
      }
    };
  });
}

function renderMatches(payload) {
  state.matches = payload.matches || [];
  $('dayTitle').textContent = headingFor(payload.date);
  $('dayCount').textContent = `${payload.total} matches  ·  updated ${payload.now}`;
  renderScoreboard(state.matches);
  renderPunterDesk(state.matches);
  renderDeskFilters(state.matches);
  const calRow = (state.meta?.calendar || []).find((d) => d.date === payload.date);
  if (calRow && typeof payload.total === 'number') {
    calRow.count = payload.total;
  }
  paintMatchGrid();
  state.matches.forEach(maybePlayNotify);
  const alerts = payload.alerts || [];
  const box = $('heroAlert');
  if (alerts.length) {
    box.classList.add('show');
    box.innerHTML = `<strong>30-minute toss lock:</strong> ${alerts.map((a) => {
      const r = a.prediction?.tipperReport || {};
      const pick = r.action === 'PLAY' ? (r.pick || a.prediction?.winner || '') : '';
      return pick
        ? `${a.teamA} vs ${a.teamB} → <b>${esc(pick)}</b> (${a.prediction?.probability || 50}%)`
        : `${a.teamA} vs ${a.teamB} → WAIT (no lock)`;
    }).join('  ·  ')}`;
    alerts.forEach(maybeNotify);
  } else {
    box.classList.remove('show');
  }
  scheduleMatchPoll();
}

function renderBookBoard(board) {
  const box = $('bookBoard');
  if (!box) return;
  const rows = board?.matches || [];
  if (!rows.length) {
    box.hidden = true;
    box.innerHTML = '';
    return;
  }
  const inferred = board?.inferred ? ' · live sides (schedule post nahi mila)' : '';
  box.hidden = false;
  box.innerHTML = `<div class="book-head">Toss-book website load <small>${esc(board.fetchedAt || '')} · ${rows.length} listed${inferred}</small></div>
    <p class="tg-hint">Yahi board tipper log website pe check karte hain — listed match + favouring side.</p>
    ${rows.map((r) => {
    const hot = !!r.hasLean;
    const pctA = r.pctA ?? 50;
    const pctB = r.pctB ?? 50;
    return `<article class="book-row ${hot ? 'hot' : ''}">
        <div class="book-meta">${esc(r.time || '')}${r.league ? ' · ' + esc(r.league) : ''} · ${esc(r.status || 'LISTED')}</div>
        <div class="book-teams"><b>${esc(r.teamA)}</b> <span>vs</span> <b>${esc(r.teamB)}</b></div>
        <div class="book-label">${esc(r.label || 'No load yet')}</div>
        <div class="track web"><i style="width:${hot ? pctA : 50}%"></i><i style="width:${hot ? pctB : 50}%"></i></div>
        <div class="book-money">${esc(r.amountALabel || '₹0')} vs ${esc(r.amountBLabel || '₹0')}</div>
      </article>`;
  }).join('')}`;
}

function headingFor(date) {
  const row = (state.meta?.calendar || []).find((d) => d.date === date);
  if (!row) return date;
  if (row.label === 'Today') return 'Aaj ke matches';
  if (row.label === 'Tomorrow') return 'Kal / tomorrow ke matches';
  if (row.label === 'Yesterday') return 'Kal (yesterday) ke matches';
  return `${row.weekday}, ${row.pretty} ke matches`;
}

const matchCache = {};
function matchCacheKey(date, league) {
  return `${date || ''}|${league || 'all'}`;
}

function bustMatchCache() {
  Object.keys(matchCache).forEach((k) => { delete matchCache[k]; });
}

function nearTossWindow(matches) {
  return (matches || []).some((m) => {
    if (m.tossWinner) return false;
    const mins = Number(m.minutesToToss);
    return Number.isFinite(mins) && mins <= 15 && mins >= -8;
  });
}

let matchPollTimer = null;
function scheduleMatchPoll() {
  if (matchPollTimer) clearInterval(matchPollTimer);
  const ms = nearTossWindow(state.matches) ? 10000 : 45000;
  matchPollTimer = setInterval(() => loadMatches(true), ms);
}

async function loadMatches(silent = false) {
  if (silent && ($('addModal')?.classList.contains('show') || $('tossModal')?.classList.contains('show'))) {
    return;
  }
  const key = matchCacheKey(state.date, state.league);
  const cached = matchCache[key];
  if (cached && !silent) {
    renderMatches(cached);
  } else if (!silent) {
    $('grid').innerHTML = `<div class="empty">Live feeds + historical toss model load ho raha hai…</div>`;
  }
  try {
    const data = await api('matches', { date: state.date, league: state.league });
    if (!data || data.error) {
      if (!cached) {
        $('grid').innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
      }
      return;
    }
    matchCache[key] = data;
    if (matchCacheKey(state.date, state.league) === key) {
      renderMatches(data);
    }
  } catch (e) {
    if (!silent && !cached) {
      $('grid').innerHTML = `<div class="empty">Is date par scheduled match nahi mila. Niche se custom match add karo.</div>`;
    }
  }
  renderDates();
}

async function prefetchMatchDay(date) {
  if (!date || !state.league) return;
  const key = matchCacheKey(date, state.league);
  if (matchCache[key]) return;
  try {
    const data = await api('matches', { date, league: state.league });
    if (data && !data.error) {
      matchCache[key] = data;
    }
  } catch (e) { }
}

function lastTossTable(team) {
  const rows = (team.last5Rows && team.last5Rows.length)
    ? team.last5Rows
    : (team.record?.recent || []).slice(0, 5);
  if (!rows.length) {
    return `<div class="insight"><b>${esc(team.name || '')}</b> last 5 toss: no completed toss sample yet</div>`;
  }
  const body = rows.map((r) => {
    const won = !!r.won;
    return `<tr>
      <td>${esc(r.date || '—')}</td>
      <td>vs ${esc(r.vs || '—')}</td>
      <td class="${won ? 'w' : 'l'}">${won ? 'WON' : 'LOST'}</td>
      <td>${esc(won ? (r.decision || '—') : '—')}</td>
    </tr>`;
  }).join('');
  const rec = team.record || {};
  return `
    <div class="toss-block">
      <div class="insight">
        <b>${esc(team.name)}</b>${team.captain ? `  ·  captain ${esc(team.captain)}` : ''}${team.home ? '  ·  Home' : ''}<br>
        Career ${rec.won || 0}/${rec.played || 0} (${rec.pct || 50}%)  ·  Last 10: ${team.last10Wins || 0}/${team.last10Total || 0} (${team.last10Pct || 50}%)  ·  Last 5: ${team.last5Wins || 0}/${team.last5Total || 0} (${team.last5Pct || 50}%)<br>
        After winning toss: bowl ${rec.choseBowl || 0} / bat ${rec.choseBat || 0}  ·  usual call <b>${esc(rec.call || '—')}</b>
        ${team.streak?.text ? `<br>${esc(team.streak.text)}` : ''}
      </div>
      ${tossDots(team)}
      <table class="toss-table">
        <caption>${esc(team.name)} — last toss results</caption>
        <thead><tr><th>Date</th><th>Opponent</th><th>Toss</th><th>Call</th></tr></thead>
        <tbody>${body}</tbody>
      </table>
    </div>`;
}

function recordLines(team, rec) {
  return lastTossTable(Object.assign({}, team, { record: rec || team.record || {} }));
}

function whyPickHTML(a, teamAName, teamBName, extra = {}) {
  const pick = extra.prediction || a.prediction || {};
  const src = pick.sources || {};
  const last = src.lastToss || {};
  const load = src.load || {};
  const tA = a.teamA || {};
  const tB = a.teamB || {};
  const h2h = extra.h2h || a.headToHead || {};
  const winner = pick.winner || pick.favoredWinner || '';
  const pct = pick.probability || pick.favoredProbability || '';
  const why = pick.whyPick || {};
  const factors = Array.isArray(why.factors) && why.factors.length ? why.factors : [
    { name: 'Last 5 toss', a: `${tA.last5Wins || 0}/${tA.last5Total || 0} (${tA.last5Pct || 50}%)`, b: `${tB.last5Wins || 0}/${tB.last5Total || 0} (${tB.last5Pct || 50}%)`, edge: (tA.last5Pct || 50) === (tB.last5Pct || 50) ? 'Even' : ((tA.last5Pct || 50) > (tB.last5Pct || 50) ? teamAName : teamBName) },
    { name: 'Last 10 toss', a: `${tA.last10Wins || 0}/${tA.last10Total || 0} (${tA.last10Pct || 50}%)`, b: `${tB.last10Wins || 0}/${tB.last10Total || 0} (${tB.last10Pct || 50}%)`, edge: (tA.last10Pct || 50) === (tB.last10Pct || 50) ? 'Even' : ((tA.last10Pct || 50) > (tB.last10Pct || 50) ? teamAName : teamBName) },
    { name: 'Career toss', a: `${tA.record?.won || 0}/${tA.record?.played || 0} (${tA.record?.pct || 50}%)`, b: `${tB.record?.won || 0}/${tB.record?.played || 0} (${tB.record?.pct || 50}%)`, edge: (tA.record?.pct || 50) === (tB.record?.pct || 50) ? 'Even' : ((tA.record?.pct || 50) > (tB.record?.pct || 50) ? teamAName : teamBName) },
    { name: 'H2H toss', a: String(h2h.teamAWins ?? 0), b: String(h2h.teamBWins ?? 0), edge: (h2h.teamAWins ?? 0) === (h2h.teamBWins ?? 0) ? 'Even' : ((h2h.teamAWins ?? 0) > (h2h.teamBWins ?? 0) ? teamAName : teamBName) },
    { name: 'Telegram ₹', a: src.telegram?.hasMoney ? `${src.telegram.pctA}%` : '—', b: src.telegram?.hasMoney ? `${src.telegram.pctB}%` : '—', edge: src.telegram?.hasMoney ? (src.load?.winner || 'Even') : 'No TG money' },
    { name: 'Website load', a: src.website?.onBook ? `${src.website.pctA}%` : '—', b: src.website?.onBook ? `${src.website.pctB}%` : '—', edge: !src.website?.onBook ? 'Not listed' : (src.website?.hasLean ? (src.website.winner || 'Even') : 'No load yet') },
  ];
  const rows = factors.map((f) => `<tr><td>${esc(f.name)}</td><td>${esc(f.a)}</td><td>${esc(f.b)}</td><td class="${f.edge === winner ? 'w' : ''}">${esc(f.edge)}</td></tr>`).join('');
  const bullets = [];
  if (load.hasMoney && load.winner) {
    bullets.push(`Punter toss money <b>${esc(load.winner)}</b> pe zyada hai (${load.pctA}% vs ${load.pctB}%).`);
  } else {
    bullets.push('Mapped punter toss money nahi mila. Last 5 even/thin ho to PLAY nahi — wait.');
  }
  bullets.push(`Last 5 toss: <b>${esc(teamAName)}</b> ${tA.last5Wins || 0}/${tA.last5Total || 0} vs <b>${esc(teamBName)}</b> ${tB.last5Wins || 0}/${tB.last5Total || 0}.`);
  bullets.push(`Last 10 toss: <b>${esc(teamAName)}</b> ${tA.last10Wins || 0}/${tA.last10Total || 0} vs <b>${esc(teamBName)}</b> ${tB.last10Wins || 0}/${tB.last10Total || 0}.`);
  if ((h2h.total || 0) > 0) {
    bullets.push(`H2H toss: ${esc(teamAName)} ${h2h.teamAWins ?? 0} – ${h2h.teamBWins ?? 0} ${esc(teamBName)}.`);
  }
  if (src.split) {
    bullets.push(`SPLIT: last 5 <b>${esc(last.winner)}</b>, load <b>${esc(load.winner)}</b>. Best tipper skip karta hai — NO PICK.`);
  } else if (src.triple) {
    bullets.push(`BEST: last 5 + Telegram + website teeno <b>${esc(winner)}</b> pe agree.`);
  } else if (src.agree) {
    bullets.push(`STRONG: last 5 toss aur load dono <b>${esc(winner)}</b> pe agree.`);
  } else if (src.loadOnly) {
    bullets.push(`Last 5 even hai, isliye AI pick mapped ₹ se <b>${esc(load.winner || winner)}</b> hai.`);
  }
  const report = pick.tipperReport || extra.prediction?.tipperReport || {};
  if (report.action === 'PLAY') {
    bullets.push(`Isliye STRONG PICK <b>${esc(winner)}</b> (${pct}%). Toss phir bhi coin hai — best available call, guarantee nahi.`);
  } else if (report.action === 'SKIP') {
    bullets.push('Split signals — AI yahan winner lock nahi karta.');
  } else if (report.action === 'LEAN') {
    bullets.push(`LOAD LEAN <b>${esc(report.pick || winner || 'even')}</b> — mapped ₹ ki side, last 5 even/thin.`);
  } else {
    bullets.push(last.winner
      ? `Last 5 lean <b>${esc(last.winner)}</b> hai, lekin load nahi — PLAY lock nahi.`
      : 'Abhi PLAY lock nahi. Last 5 even/thin, mapped load nahi — tipper wait karta hai.');
  }
  const headline = why.headline || (report.action === 'PLAY'
    ? `${winner} (${pct}%)`
    : report.action === 'SKIP' ? 'NO PICK — split skip' : `WAIT: ${winner || 'even'} (${pct}%)`);
  const reportBox = report.headline
    ? `<div class="tipper ${esc(report.grade || 'wait')}" style="margin-bottom:12px">
        <div class="tipper-kicker">Tipper report · ${esc(report.action || 'WAIT')}</div>
        <p>${esc(report.headline)}</p>
        <small>${esc(report.when || '')}</small>
      </div>`
    : '';
  return `
    <div class="verdict">
      ${reportBox}
      <div class="verdict-kicker">Kyun ye team</div>
      <div class="pick ${report.action === 'PLAY' ? 'strong' : (report.action === 'SKIP' ? 'skip' : (report.action === 'WAIT' ? 'wait' : 'locked'))}">${esc(headline)}</div>
      <ul class="why-list">${bullets.map((b) => `<li>${b}</li>`).join('')}</ul>
      <table class="toss-table">
        <caption>Factor comparison</caption>
        <thead><tr><th>Factor</th><th>${esc(teamAName)}</th><th>${esc(teamBName)}</th><th>Edge</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

function analysisDetailHTML(a, teamAName, teamBName, extra = {}) {
  const v = a.venue || extra.venueStats || {};
  const h2h = extra.h2h || a.headToHead || {};
  const pick = extra.prediction || a.prediction || {};
  const src = pick.sources || {};
  const last = src.lastToss || {};
  const load = src.load || {};
  const insights = Array.isArray(pick.insights) ? pick.insights : (Array.isArray(a.prediction?.insights) ? a.prediction.insights : []);
  const gradeSide = extra.tossWinner ? gradedPick({ prediction: pick }) : '';
  const actual = extra.tossWinner
    ? `<div class="agree-note">Ground toss already saved: <b>${esc(extra.tossWinner)}</b> chose ${esc(extra.tossDecision || '—')}${
        !gradeSide ? ' · no pick' : (namesClose(extra.tossWinner, gradeSide) ? ' · AI PASS' : ' · AI FAIL')
      }</div>`
    : '';
  if (extra.tossWinner) {
    return `
    ${actual}
    <div class="insight">Toss done — naya lean / tipper report nahi nikalte. Saved toss freeze hai.</div>
    <h3>Last toss results</h3>
    ${lastTossTable(a.teamA || {})}
    ${lastTossTable(a.teamB || {})}
    <h3>H2H toss</h3>
    <div class="insight">${esc(teamAName)} ${h2h.teamAWins ?? 0} – ${h2h.teamBWins ?? 0} ${esc(teamBName)} from ${h2h.total ?? 0} meetings</div>
  `;
  }
  return `
    ${whyPickHTML(a, teamAName, teamBName, extra)}
    ${actual}
    <div class="insight"><b>Last-match toss lean</b>: ${esc(last.winner || pick.favoredWinner || 'Even')}  ·  ${esc(teamAName)} ${last.pctA ?? pick.teamAPct ?? '—'}% vs ${esc(teamBName)} ${last.pctB ?? pick.teamBPct ?? '—'}%</div>
    <div class="insight"><b>Punter load</b>: ${load.hasMoney ? `${esc(load.winner || 'Even')}  ·  ${load.pctA}% vs ${load.pctB}%` : 'Is match pe mapped toss money nahi mila — last toss hi lean hai'}</div>
    <div class="insight">If they win toss, usual call: <b>${esc(pick.decision || pick.likelyDecision || v.preferredDecision || '—')}</b> at ${esc(v.venueName || extra.venue || '')}</div>
    <h3>Model notes</h3>
    ${insights.map((i) => `<div class="insight">${esc(i)}</div>`).join('')}
    <h3>Last toss results</h3>
    ${lastTossTable(a.teamA || {})}
    ${lastTossTable(a.teamB || {})}
    <h3>H2H toss</h3>
    <div class="insight">${esc(teamAName)} ${h2h.teamAWins ?? 0} – ${h2h.teamBWins ?? 0} ${esc(teamBName)} from ${h2h.total ?? 0} meetings</div>
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
    <p style="color:#94a3b8">${esc(m.tournament)}<br>${esc(m.venue)}  ·  toss ${esc(m.tossTime)}</p>
    ${analysisDetailHTML(m.analysis || {}, m.teamA, m.teamB, { prediction: m.prediction, venueStats: m.venueStats, venue: m.venue, tossWinner: m.tossWinner, tossDecision: m.tossDecision, h2h: m.form?.h2h })}
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

let tossMatch = null;

function closeTossModal() {
  tossMatch = null;
  $('tossModal')?.classList.remove('show');
}

function openTossModal(m) {
  tossMatch = m;
  const box = $('tossModal');
  if (!box) {
    promptToss(m);
    return;
  }
  $('tossTitle').textContent = `${m.teamA} vs ${m.teamB}`;
  $('tossSub').textContent = m.tossWinner
    ? `Saved toss: ${m.tossWinner} chose ${m.tossDecision || '—'}. Change karo to naya result lock ho jayega.`
    : 'Ground toss winner select karo. Save ke baad yeh lock rehta hai — refresh pe erase nahi hoga.';
  $('tossWinnerA').textContent = m.teamA;
  $('tossWinnerB').textContent = m.teamB;
  $('tossWinnerA').classList.toggle('mint', namesClose(m.tossWinner, m.teamA));
  $('tossWinnerB').classList.toggle('mint', namesClose(m.tossWinner, m.teamB));
  $('tossWinnerA').dataset.winner = m.teamA;
  $('tossWinnerB').dataset.winner = m.teamB;
  const dec = (m.tossDecision || 'bowl').toLowerCase();
  if ($('tossDecBowl')) $('tossDecBowl').checked = dec !== 'bat';
  if ($('tossDecBat')) $('tossDecBat').checked = dec === 'bat';
  box.classList.add('show');
}

async function saveToss(winner) {
  if (!tossMatch || !winner) return;
  const decision = $('tossDecBat')?.checked ? 'bat' : 'bowl';
  const m = tossMatch;
  closeTossModal();
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
  bustMatchCache();
  loadMatches();
}

async function promptToss(m) {
  openTossModal(m);
}

async function removeMatch(m) {
  if (!confirm(`Remove ${m.teamA} vs ${m.teamB}?`)) return;
  await api('delete_match', { date: m.date, teamA: m.teamA, teamB: m.teamB, id: m.id }, 'POST');
  bustMatchCache();
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
  bustMatchCache();
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
  renderBookBoard(data.websiteBoard);
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
  const report = m.prediction?.tipperReport || {};
  const pick = report.action === 'PLAY' ? (report.pick || m.prediction?.winner || '') : '';
  if (!pick) return;
  state.notified.add(m.id);
  new Notification('Toss lock  ·  30 min', {
    body: `${m.teamA} vs ${m.teamB} → PLAY: ${pick} (${m.prediction?.probability || 50}%)`,
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
  const cal = state.meta?.calendar || [];
  (async () => {
    for (const d of cal) {
      if (d.date && d.date !== state.date) await prefetchMatchDay(d.date);
    }
  })();
  if ('Notification' in window && Notification.permission === 'default') {
    $('notifyBtn').classList.remove('hidden');
  }
  loadTelegram(false);
  scheduleMatchPoll();
  setInterval(() => loadTelegram(false), 10000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadTelegram(false);
  });
  document.addEventListener('click', unlockTgAudio, { once: true });
  document.addEventListener('keydown', unlockTgAudio, { once: true });
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
  $('tossModal')?.addEventListener('click', (e) => {
    if (e.target === $('tossModal')) closeTossModal();
  });
  $('tossClose')?.addEventListener('click', closeTossModal);
  $('tossWinnerA')?.addEventListener('click', () => saveToss($('tossWinnerA').dataset.winner));
  $('tossWinnerB')?.addEventListener('click', () => saveToss($('tossWinnerB').dataset.winner));
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
let tgAudio = null;

function unlockTgAudio() {
  try {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!tgAudio) tgAudio = new AC();
    if (tgAudio.state === 'suspended') tgAudio.resume();
    return tgAudio;
  } catch (e) {
    return null;
  }
}

function playTgBeep() {
  const ctx = unlockTgAudio();
  if (!ctx) return;
  const now = ctx.currentTime;
  [0, 0.22, 0.44].forEach((delay, i) => {
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.value = i === 1 ? 1174 : 880;
    o.connect(g);
    g.connect(ctx.destination);
    const t0 = now + delay;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(0.28, t0 + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.18);
    o.start(t0);
    o.stop(t0 + 0.2);
  });
}

function rememberSeen() {
  localStorage.setItem('fta_tg_seen', JSON.stringify([...tgSeen].slice(-400)));
}

function notifyWatched(bet) {
  if (!bet?.postId || tgSeen.has(bet.postId)) return;
  tgSeen.add(bet.postId);
  rememberSeen();
  playTgBeep();
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification(`${bet.userName || 'Watched user'} ka naya bet`, {
      body: `Team: ${bet.teamName || '—'}  |  Amount: ${bet.amount || '—'}`,
      tag: String(bet.postId),
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
  const on = Notification.permission === 'granted';
  btn.textContent = on ? 'Alerts on · test sound' : 'Enable alerts + sound';
  btn.classList.toggle('mint', !on);
}

async function enableTgAlerts() {
  unlockTgAudio();
  if ('Notification' in window && Notification.permission !== 'granted') {
    await Notification.requestPermission();
  }
  syncTgNotifyBtn();
  playTgBeep();
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('Telegram alerts ON', {
      body: 'Rahul Dada, BAT9362, VIP7579 — naya bet aate hi sound + popup aayega.',
      tag: 'tg-alerts-test',
    });
  }
}

async function saveTgWatch() {
  const users = ($('tgUsers').value || 'Rahul Dada, BAT9362, VIP7579').split(',').map((s) => s.trim()).filter(Boolean);
  await api('telegram_config', { targetUsers: users.length ? users : ['Rahul Dada', 'BAT9362', 'VIP7579'], hideOthers: true }, 'POST');
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
    teams.slice(0, 8).map((t) => `<div class="bet-row">${esc(t.team)}  ·  <b>${esc(t.totalLabel)}</b>  ·  ${t.bets} bets</div>`).join('');
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
      <div class="vs-amount-meta">${esc(r.time || '')}  ·  ${esc(r.tournament || '')}${done ? '  ·  TOSS DONE' : ''}</div>
      <div class="vs-amount-grid">
        <div class="vs-side ${r.leader === r.teamA ? 'win' : ''}">
          <small>Team A</small>
          <h4>${esc(r.teamA)}</h4>
          <b>${esc(r.amountALabel)}</b>
          <em>${r.betsA || 0} bets  ·  ${pctA}%</em>
        </div>
        <div class="vs-mid-amt">VS</div>
        <div class="vs-side ${r.leader === r.teamB ? 'win' : ''}">
          <small>Team B</small>
          <h4>${esc(r.teamB)}</h4>
          <b>${esc(r.amountBLabel)}</b>
          <em>${r.betsB || 0} bets  ·  ${pctB}%</em>
        </div>
      </div>
      <div class="track vs-track"><i style="width:${pctA}%"></i><i style="width:${pctB}%"></i></div>
      <div class="vs-total">Total amount: <strong>${esc(r.totalLabel)}</strong>${r.leader ? `  ·  lean <strong>${esc(r.leader)}</strong>` : ''}</div>
    </article>`;
  }).join('');
}

function renderTgFeed(bets) {
  const box = $('tgFeed');
  if (!box) return;
  if (!bets.length) {
    box.innerHTML = '<div class="empty">Rahul Dada / BAT9362 / VIP7579 ka koi bet abhi nahi aaya. Jaise hi channel pe unka bet drop hoga, yahan dikhega aur notification aa jayegi.</div>';
    return;
  }
  box.innerHTML = bets.map((b) => `
    <article class="bet-row ${b.watched ? 'watched' : ''}">
      <div class="who">${esc(b.userName)}  ·  ${esc(b.displayTime)}</div>
      <h4>${esc(b.teamName || 'Update')}  ·  ${esc(b.amount || '')}</h4>
      <div style="color:#94a3b8;font-size:12px;white-space:pre-wrap">${esc(b.rawText)}</div>
      <a class="btn" href="${esc(b.messageUrl)}" target="_blank" rel="noopener">View post</a>
    </article>
  `).join('');
}

function paintTelegram(data, { notify = false, prime = false } = {}) {
  if (!data) return;
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
  if (prime && !tgPrimed) {
    const cutoff = Date.now() - 120000;
    watched.forEach((b) => {
      const ts = Date.parse(b.isoTime || '') || 0;
      if (!ts || ts < cutoff) tgSeen.add(b.postId);
    });
    rememberSeen();
    tgPrimed = true;
  }
  if (notify) watched.forEach(notifyWatched);
}

function rememberTelegram(data) {
  try {
    const slim = {
      status: data.status,
      fetchedAt: data.fetchedAt,
      config: data.config,
      watched: (data.watched || []).slice(0, 120),
      punterLoad: data.punterLoad || { teams: [], matches: [] },
    };
    localStorage.setItem('ftp_tg_last', JSON.stringify(slim));
  } catch (e) {}
}

async function loadTelegram(force) {
  if (!force) {
    try {
      const cached = JSON.parse(localStorage.getItem('ftp_tg_last') || 'null');
      if (cached?.watched?.length) paintTelegram(cached);
    } catch (e) {}
  }
  const data = await api('telegram_bets', { type: 'bets_only', hideOthers: '0', force: force ? '1' : '' });
  if (data && !data.error && (data.watched?.length || data.status === 'connected' || data.status === 'cached')) {
    rememberTelegram(data);
    paintTelegram(data, { notify: true, prime: true });
    return;
  }
  try {
    const cached = JSON.parse(localStorage.getItem('ftp_tg_last') || 'null');
    if (cached?.watched?.length) paintTelegram(cached);
  } catch (e) {}
}
