<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Future Toss AI · 30-min Toss Portal</title>
  <link rel="stylesheet" href="assets/app.css?v=6">
</head>
<body>
  <div class="orb a"></div>
  <div class="orb b"></div>
  <div class="app">
    <header class="topbar">
      <div class="brand">
        <div class="logo">🪙</div>
        <div>
          <h1>Future<span>Toss</span> AI</h1>
          <p class="tagline">Date-wise live schedule · home/calling · venue · 30-min toss lock</p>
        </div>
      </div>
      <div class="clock">
        <b id="istNow">--:-- IST</b>
        <small id="istDay">Loading IST calendar…</small>
      </div>
    </header>

    <nav class="nav">
      <button data-view="matches" class="active">📅 <span class="full">Date matches</span><span class="short">Matches</span></button>
      <button data-view="sim">⚡ <span class="full">Custom predictor</span><span class="short">Predict</span></button>
      <button data-view="board">🏆 <span class="full">Toss leaderboard</span><span class="short">Board</span></button>
      <button data-view="tg" class="nav-tg">✈️ <span class="full">TeleGramBet</span><span class="short">TG Bet</span><span id="tgNavBadge" class="live-dot hidden">0</span></button>
      <button class="btn gold" id="addBtn">＋ <span class="full">Add custom match</span><span class="short">Add</span></button>
      <button class="btn ghost hidden" id="notifyBtn">🔔 <span class="full">Enable 30-min alert</span><span class="short">Alert</span></button>
    </nav>

    <section id="view-matches">
      <div id="dates" class="dates"></div>
      <div id="leagues" class="leagues"></div>
      <div id="heroAlert" class="hero-alert"></div>
      <div class="day-head">
        <h2 id="dayTitle">Matches</h2>
        <small id="dayCount"></small>
      </div>
      <div id="grid" class="grid"></div>
    </section>

    <section id="view-sim" class="hidden panel" style="text-align:left">
      <h2>Custom AI toss laboratory</h2>
      <p style="color:#94a3b8">Koi bhi do teams + venue daalo. Model historical toss, home ground, captain streak aur venue bowl/bat lean use karta hai.</p>
      <form id="simForm" class="form">
        <div class="two">
          <label>Team A
            <select id="sTeamA"></select>
            <input id="sTeamACustom" placeholder="Or type custom team A">
          </label>
          <label>Team B
            <select id="sTeamB"></select>
            <input id="sTeamBCustom" placeholder="Or type custom team B">
          </label>
        </div>
        <label>Venue
          <input id="sVenue" placeholder="e.g. PCA IS Bindra Stadium, Mohali" value="PCA IS Bindra Stadium, Mohali">
        </label>
        <button class="btn mint" type="submit">Generate future toss pick</button>
      </form>
      <div id="simOut" style="margin-top:16px"></div>
    </section>

    <section id="view-tg" class="hidden panel tg-panel">
      <div class="tg-head">
        <div>
          <h2>TeleGramBet live</h2>
          <p>Channel <a href="https://web.telegram.org/k/#@BetfairTossbookOrignal" target="_blank" rel="noopener">@BetfairTossbookOrignal</a> · Rahul Dada drop = instant team + amount alert</p>
        </div>
        <div class="tg-actions">
          <span id="tgStatus" class="badge live">Connecting</span>
          <button class="btn" type="button" id="tgRefresh">Refresh</button>
          <button class="btn mint" type="button" id="tgNotifyBtn">Enable alerts</button>
          <a class="btn" href="https://web.telegram.org/k/#@BetfairTossbookOrignal" target="_blank" rel="noopener">Open Telegram</a>
        </div>
      </div>

      <div class="tg-track card">
        <label>Watch user (default Rahul Dada)
          <input id="tgUsers" value="Rahul Dada" placeholder="Rahul Dada, BTB0353">
        </label>
        <p class="tg-hint" style="margin:0">Watched drops mein sirf <b>Rahul Dada</b> (ya jo users save kiye) dikhenge. Naya bet aate hi notification aayegi.</p>
        <button class="btn mint" type="button" id="tgSaveUsers">Save watch</button>
        <small id="tgPoll">Last poll: —</small>
      </div>

      <h3 class="tg-sub">Team A vs Team B — total amount</h3>
      <p class="tg-hint">Har match par dono teams ka combined punter amount. Jis side zyada ₹ hai wahi lean hai.</p>
      <div id="tgLoad" class="tg-load"></div>
      <div id="tgTeams" class="tg-load" style="margin-top:10px"></div>

      <h3 class="tg-sub">Rahul Dada drops <span id="tgCount" class="badge">0</span></h3>
      <div id="tgFeed" class="tg-feed"></div>
    </section>

    <section id="view-board" class="hidden panel" style="text-align:left">
      <h2>Who wins more tosses</h2>
      <label>League filter
        <select id="lbLeague" onchange="loadBoard()"></select>
      </label>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>#</th><th>Team</th><th>Played</th><th>Toss won</th><th>%</th><th>Bowl / Bat</th></tr>
          </thead>
          <tbody id="lbBody"></tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="modal-bg" id="addModal">
    <div class="modal">
      <h2>Add custom match</h2>
      <form id="customForm" class="form">
        <label>Date <input type="date" id="cDate" required></label>
        <div class="two">
          <label>Team A
            <select id="cTeamA"></select>
            <input id="cTeamACustom" placeholder="Or type name">
          </label>
          <label>Team B
            <select id="cTeamB"></select>
            <input id="cTeamBCustom" placeholder="Or type name">
          </label>
        </div>
        <div class="two">
          <label>Match time IST <input id="cTime" placeholder="07:00 PM" value="07:00 PM" required></label>
          <label>Format
            <select id="cFormat">
              <option>T20</option><option>ODI</option><option>Test</option><option>T10</option>
            </select>
          </label>
        </div>
        <label>League <select id="cLeague"></select></label>
        <label>Venue <input id="cVenue" placeholder="Stadium, City" value="International Cricket Ground"></label>
        <label>Tournament label <input id="cTourn" placeholder="Optional series name"></label>
        <div class="actions">
          <button class="btn mint" type="submit">Save & predict</button>
          <button class="btn ghost" type="button" id="closeModal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="drawer-bg" id="drawerBg"></div>
  <aside class="drawer" id="drawer"></aside>
  <script src="assets/app.js?v=8"></script>
</body>
</html>
