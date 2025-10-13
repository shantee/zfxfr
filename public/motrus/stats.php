<?php
/**
 * stats.php — tracker + dashboard (fichier unique)
 * - Aucune base de données. Stockage en fichiers (JSON Lines) dans ./stats_data
 * - 3 modes :
 *   ?tracker=1  → Sert un JS de tracking (hit, ping, nav, pause, close)
 *   ?ev=...     → Enregistre un événement (via sendBeacon/fetch)
 *   (défaut)    → Affiche le tableau de bord (HTML)
 *
 * A installer :
 *  - Placer ce fichier à côté de index.html (ex: public_html/jeu/stats.php)
 *  - Créer le dossier writable ./stats_data (ex: public_html/jeu/stats_data)
 *  - Dans <head> de tes pages : <script src="./stats.php?tracker=1" defer></script>
 */

declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);

// =====================
// CONFIG
// =====================
const DATA_DIR       = __DIR__ . '/stats_data';
const LOG_FILE       = DATA_DIR . '/visits.log';   // JSON Lines
const ONLINE_WINDOW  = 120;       // secondes pour "en ligne"
const DAY_SEC        = 86400;     // 24h
const SITE_NAME      = 'Motrus';

// Bootstrap data dir
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0777, true); }

// =====================
// ROUTING
// =====================
$tracker = isset($_GET['tracker']) ? (string)$_GET['tracker'] : '';
$eventEv = isset($_GET['ev']) ? (string)$_GET['ev'] : '';

if ($tracker === '1') { serve_tracker_js(); exit; }
if ($eventEv !== '')  { handle_event($eventEv); exit; }

// Sinon : dashboard
serve_dashboard(); exit;

// =====================
// HELPERS (communs)
// =====================
function client_ip(): string {
  $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
  foreach ($keys as $k){
    if (!empty($_SERVER[$k])) {
      $v = $_SERVER[$k];
      if ($k === 'HTTP_X_FORWARDED_FOR') {
        $parts = explode(',', $v);
        return trim($parts[0]);
      }
      return $v;
    }
  }
  return '0.0.0.0';
}
function now(): int { return time(); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function read_jsonl(string $path): array {
  if (!file_exists($path)) return [];
  $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return [];
  $out = [];
  foreach ($lines as $ln){
    $j = json_decode($ln, true);
    if (is_array($j)) $out[] = $j;
  }
  return $out;
}
function write_jsonl(string $path, array $row): void {
  $dir = dirname($path);
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  $fp = fopen($path, 'ab');
  if ($fp) {
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}
function get_sid(): string {
  $cookieName = 'motrus_sid';
  if (!empty($_COOKIE[$cookieName])) return (string)$_COOKIE[$cookieName];
  $sid = bin2hex(random_bytes(16));
  // Cookie valable 1 an, accessible au path courant
  setcookie($cookieName, $sid, [
    'expires'  => time()+31536000,
    'path'     => '/',
    'samesite' => 'Lax'
  ]);
  return $sid;
}

// =====================
// MODE 1 — TRACKER JS
// =====================
function serve_tracker_js(): void {
  header('Content-Type: application/javascript; charset=utf-8');

  // Le JS :
  // - crée un sid en cookie (si absent)
  // - envoie 'hit' au chargement
  // - ping toutes les 15s (visible)
  // - nav: sur pushState/replaceState/popstate
  // - pause/resume: visibilitychange
  // - close: pagehide
  $endpoint = basename(__FILE__); // stats.php
  $js = <<<JS
(() => {
  const EP = '{$endpoint}';
  const COOKIE = 'motrus_sid';

  function getSid(){
    const m = document.cookie.match(/(?:^|;\\s*)\\Q\${COOKIE}\\E=([^;]+)/);
    if (m) return m[1];
    const b = new Uint8Array(16);
    crypto.getRandomValues(b);
    const sid = Array.from(b).map(x => ('0'+x.toString(16)).slice(-2)).join('');
    document.cookie = COOKIE + '=' + sid + '; path=/; max-age=31536000; samesite=lax';
    return sid;
  }
  const sid = getSid();

  function send(ev, extra){
    try{
      const payload = Object.assign({
        ev, ts: Date.now(),
        url: location.href,
        ref: document.referrer || '',
        ua: navigator.userAgent || ''
      }, extra||{});
      const blob = new Blob([JSON.stringify(payload)], {type:'application/json'});
      navigator.sendBeacon(EP+'?ev='+encodeURIComponent(ev), blob);
    }catch(e){ /* ignore */ }
  }

  // SPA nav hook
  let lastUrl = location.href;
  function onNav(){ 
    if (lastUrl !== location.href){
      lastUrl = location.href;
      send('nav');
    }
  }
  try {
    const _ps = history.pushState; history.pushState = function(){ _ps.apply(this, arguments); onNav(); };
  } catch(e){}
  try {
    const _rs = history.replaceState; history.replaceState = function(){ _rs.apply(this, arguments); onNav(); };
  } catch(e){}
  window.addEventListener('popstate', onNav);

  // Events
  send('hit');
  document.addEventListener('visibilitychange', ()=> send(document.visibilityState==='visible'?'resume':'pause'));
  window.addEventListener('pagehide', ()=> send('close'));

  // Pings périodiques si visible
  setInterval(()=>{ if (document.visibilityState==='visible') send('ping'); }, 15000);
})();
JS;

  echo $js;
}

// =====================
// MODE 2 — EVENT INGEST
// =====================
function handle_event(string $ev): void {
  // Lit JSON envoyé par sendBeacon/fetch
  $raw = file_get_contents('php://input');
  $data = $raw ? json_decode($raw, true) : [];
  if (!is_array($data)) $data = [];

  $sid = get_sid();
  $ip  = client_ip();
  $ua  = (string)($data['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
  $url = (string)($data['url'] ?? '');
  $ref = (string)($data['ref'] ?? '');
  $tsms = (int)($data['ts'] ?? (int)(microtime(true)*1000));
  $t = (int)floor($tsms/1000);

  // Enregistre 1 ligne JSON
  $row = [
    't'   => $t,
    'sid' => $sid,
    'ip'  => $ip,
    'ua'  => $ua,
    'ev'  => $ev,
    'u'   => $url,
    'r'   => $ref,
  ];
  write_jsonl(LOG_FILE, $row);

  http_response_code(204);
  header('Content-Type: text/plain; charset=utf-8');
  echo "OK";
}

// =====================
// MODE 3 — DASHBOARD
// =====================
function serve_dashboard(): void {
  $now = now();
  $rows = read_jsonl(LOG_FILE);

  // Agrégations
  $sessions = [];     // sid => info
  $urls24   = [];     // url => hits (24h)
  $hitsByHour = [];   // timestamp heure => hits (24h)
  $roomPlayers24 = []; // roomId => set(sid)
  $totalHits = 0;

  foreach ($rows as $e){
    $totalHits++;
    $sid = $e['sid'] ?? null; if (!$sid) continue;
    $t   = (int)($e['t'] ?? 0);
    $ip  = (string)($e['ip'] ?? '');
    $ua  = (string)($e['ua'] ?? '');
    $ev  = (string)($e['ev'] ?? '');
    $u   = (string)($e['u'] ?? '');

    // Sessions (all-time)
    if (!isset($sessions[$sid])) {
      $sessions[$sid] = [
        'sid'=>$sid, 'ip'=>$ip, 'ua'=>$ua,
        'first'=>$t, 'last'=>$t, 'hits'=>1,
        'urls'=>[$u=>1],
      ];
    } else {
      $s = &$sessions[$sid];
      $s['last'] = max($s['last'], $t);
      $s['hits']++;
      if ($u !== '') $s['urls'][$u] = ($s['urls'][$u] ?? 0)+1;
      unset($s);
    }

    // 24h aggregations
    if ($t >= $now - DAY_SEC){
      // per-hour chart
      $h = (int)floor($t / 3600) * 3600;
      $hitsByHour[$h] = ($hitsByHour[$h] ?? 0) + 1;

      // top URLs (compte hit/nav uniquement pour éviter sur-comptage des pings)
      if ($ev === 'hit' || $ev === 'nav') {
        $urls24[$u] = ($urls24[$u] ?? 0) + 1;
      }

      // rooms (approx) : récupère ?room=...
      $q = parse_url($u, PHP_URL_QUERY);
      if ($q) {
        parse_str($q, $qp);
        if (!empty($qp['room'])) {
          $rid = (string)$qp['room'];
          if (!isset($roomPlayers24[$rid])) $roomPlayers24[$rid] = [];
          $roomPlayers24[$rid][$sid] = true;
        }
      }
    }
  }

  // Métriques
  $totalSessions = count($sessions);
  $online = 0; $onlineList = [];
  $sessions24 = [];
  foreach ($sessions as $sid=>$s){
    $dur = max(0, $s['last'] - $s['first']);
    $sessions[$sid]['dur'] = $dur;
    if ($s['last'] >= $now - ONLINE_WINDOW) { $online++; $onlineList[] = $s; }
    if ($s['last'] >= $now - DAY_SEC) { $sessions24[$sid] = $sessions[$sid]; }
  }
  $unique24 = count($sessions24);

  // Tri pour affichage
  usort($onlineList, fn($a,$b)=> $b['last'] <=> $a['last']);
  $topUrls24 = $urls24; arsort($topUrls24);
  $rooms24 = [];
  foreach ($roomPlayers24 as $rid=>$set){
    $rooms24[$rid] = count($set);
  }
  arsort($rooms24);

  // Prépare séries chart 24h
  $labels = [];
  $data = [];
  for ($i=23; $i>=0; $i--){
    $ts = (int)floor(($now - $i*3600) / 3600) * 3600;
    $labels[] = date('H:i', $ts);
    $data[]   = (int)($hitsByHour[$ts] ?? 0);
  }

  // Rendu HTML
  header('Content-Type: text/html; charset=utf-8');
  ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?=h(SITE_NAME)?> — Statistiques</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@2.0.6/css/pico.min.css">
  <style>
    body { background: #f6f8fb; }
    header.site { background:#0ea5e9; color:#fff; box-shadow: 0 10px 28px rgba(0,0,0,.15); position:sticky; top:0; z-index:10; }
    header.site nav { max-width: 1100px; margin: 0 auto; padding: 10px 16px; display:flex; align-items:center; justify-content:space-between; }
    main.container { max-width: 1100px; margin: 0 auto; padding: 1rem; }
    .grid-3 { display:grid; grid-template-columns: repeat(3,1fr); gap: .9rem; }
    .grid-2 { display:grid; grid-template-columns: repeat(2,1fr); gap: .9rem; }
    @media (max-width: 960px){ .grid-3,.grid-2{ grid-template-columns: 1fr; } }
    .card { background:#fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.06); padding: 1rem; }
    .muted { opacity: .75; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
    .stat { display:flex; align-items:baseline; justify-content:space-between; }
    .stat .num { font-size: clamp(28px,4vw,40px); font-weight: 800; }
    .pill { display:inline-block; padding:.15rem .5rem; border-radius:999px; background:#eef6ff; color:#0369a1; font-size: .8rem; }
    table td, table th { white-space: nowrap; }
    table td.url { white-space: normal; max-width: 420px; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
  <header class="site">
    <nav>
      <strong><?=h(SITE_NAME)?> — Statistiques</strong>
      <span class="pill"><?=date('Y-m-d H:i:s')?> (server)</span>
    </nav>
  </header>
  <main class="container">

    <section class="grid-3">
      <article class="card stat">
        <div>
          <div class="muted">En ligne maintenant</div>
          <div class="num"><?=$online?></div>
        </div>
        <div class="muted">fenêtre <?=ONLINE_WINDOW?>s</div>
      </article>

      <article class="card stat">
        <div>
          <div class="muted">Visiteurs uniques (24h)</div>
          <div class="num"><?=$unique24?></div>
        </div>
        <div class="muted"><?=count($sessions24)?> sessions</div>
      </article>

      <article class="card stat">
        <div>
          <div class="muted">Total depuis le début</div>
          <div class="num"><?=$totalHits?></div>
        </div>
        <div class="muted"><?=count($sessions)?> sessions</div>
      </article>
    </section>

    <section class="grid-2" style="margin-top:1rem">
      <article class="card">
        <h4>Activité (dernières 24h)</h4>
        <canvas id="chart24" height="100"></canvas>
        <p class="muted">Somme des événements enregistrés par heure (hits/nav/pings/etc.)</p>
      </article>

      <article class="card">
        <h4>En ligne</h4>
        <?php if (!$onlineList): ?>
          <p class="muted">Personne dans la fenêtre de <?=ONLINE_WINDOW?> secondes.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>IP</th><th>Dernière activité</th><th>Hits</th><th>Durée</th><th>UA (trunc.)</th></tr></thead>
            <tbody>
            <?php foreach ($onlineList as $s):
              $durMin = max(1, (int)round(($s['last']-$s['first'])/60));
              $uaShort = mb_substr($s['ua'],0,40);
            ?>
              <tr>
                <td class="mono"><?=h($s['ip'])?></td>
                <td><?=date('H:i:s', $s['last'])?></td>
                <td><?=h((string)$s['hits'])?></td>
                <td><?=$durMin?> min</td>
                <td class="mono"><?=h($uaShort)?><?=strlen($s['ua'])>40?'…':''?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </article>
    </section>

    <section class="grid-2" style="margin-top:1rem">
      <article class="card">
        <h4>Visiteurs (24h)</h4>
        <?php if (!$sessions24): ?>
          <p class="muted">Aucune session récente.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr><th>IP</th><th>1er</th><th>Dernier</th><th>Durée</th><th>Hits</th><th>URLs (distinctes)</th></tr>
            </thead>
            <tbody>
            <?php
              // tri par dernière activité desc
              usort($sessions24, fn($a,$b)=> $b['last'] <=> $a['last']);
              foreach ($sessions24 as $s):
                $dur = max(0, $s['last'] - $s['first']);
                $durMin = max(1, (int)round($dur/60));
                $urlCount = count($s['urls']);
                // extrait 2 urls représentatives
                $samples = array_slice(array_keys($s['urls']),0,2);
            ?>
              <tr>
                <td class="mono"><?=h($s['ip'])?></td>
                <td><?=date('H:i', $s['first'])?></td>
                <td><?=date('H:i', $s['last'])?></td>
                <td><?=$durMin?> min</td>
                <td><?=h((string)$s['hits'])?></td>
                <td class="url">
                  <?php foreach ($samples as $u): ?>
                    <div class="mono" title="<?=h($u)?>"><?=h(mb_strimwidth($u,0,64,'…'))?></div>
                  <?php endforeach; ?>
                  <?php if ($urlCount > 2): ?><small class="muted">+<?=($urlCount-2)?> autres</small><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </article>

      <article class="card">
        <h4>Top URLs (24h)</h4>
        <?php if (!$topUrls24): ?>
          <p class="muted">Aucune donnée.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>URL</th><th>Hits</th></tr></thead>
            <tbody>
            <?php $i=0; foreach ($topUrls24 as $u=>$c): $i++; if ($i>20) break; ?>
              <tr>
                <td class="mono url"><?=h(mb_strimwidth($u?:'(vide)',0,80,'…'))?></td>
                <td><?=h((string)$c)?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <p class="muted" style="margin-top:.5rem">Astuce : les URLs contenant <code>?room=…</code> indiquent des joueurs ayant scanné un QR code / rejoint une room.</p>
      </article>
    </section>

    <section class="card" style="margin-top:1rem">
      <h4>Rooms actives (24h, approx.)</h4>
      <?php if (!$rooms24): ?>
        <p class="muted">Pas de rooms détectées.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Room</th><th>Joueurs uniques</th></tr></thead>
          <tbody>
            <?php $i=0; foreach ($rooms24 as $rid=>$cnt): $i++; if ($i>20) break; ?>
              <tr><td class="mono"><?=h($rid)?></td><td><?=$cnt?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p class="muted">Basé sur les URLs vues (<code>?room=ID</code>) ; pour des stats <em>intra-partie</em> précises, on pourrait loguer côté <code>api.php</code> les événements (create/join/start).</p>
    </section>

    

  </main>

  <script>
    (() => {
      const ctx = document.getElementById('chart24');
      if (!ctx) return;
      const labels = <?=json_encode($labels)?>;
      const data   = <?=json_encode($data)?>;
      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{ label: 'Événements / h', data, tension:.25 }]
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero:true, ticks:{ precision:0 } } },
          plugins: { legend: { display:false } }
        }
      });
    })();
  </script>
</body>
</html>
<?php
}
