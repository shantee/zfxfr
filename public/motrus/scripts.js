// ============================
// CONFIG
// ============================
const API = './api.php';

// ============================
// STATE
// ============================
const S = {
  // room / joueur
  roomId: null, code: null, seed: null, token: null, seat: null,
  isHost: false, packKey: 'defaut',

  // joueurs / manche
  players: 0, N: null, durationSec: 300,
  started: false,        // le serveur a déjà démarré une manche
  endsAt: 0,             // unix seconds de fin de manche

  // phase UI locale : 'lobby' | 'running' | 'finished'
  roundPhase: 'lobby',

  // timers
  pollInt: null, timerInt: null,

  // autres
  joinUrl: null, youIntrus: false
};

// ============================
// VUES
// ============================
const views = ['home','create','join','game','end'];
function show(v){
  for (const id of views){
    const el = document.getElementById('view-'+id);
    if (el) el.classList.toggle('hidden', id!==v);
  }
}
function isRoundActive(){ return !!(S.endsAt && Date.now() < S.endsAt*1000); }

// ============================
// HELPERS
// ============================
async function api(action, payload){
  const url = `${API}?a=${encodeURIComponent(action)}`;
  const opt = { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload||{}) };
  const r = await fetch(url, opt);
  const t = await r.text();
  let j; try{ j = JSON.parse(t); }catch(e){ throw new Error('Bad JSON: '+t.slice(0,200)); }
  if (!r.ok || j.ok===false) throw new Error(j.error || ('HTTP '+r.status));
  return j;
}
function fmtTime(s){ const m=Math.floor(s/60), ss=String(s%60).padStart(2,'0'); return String(m).padStart(2,'0')+':'+ss; }
function clamp(v,min,max){ return Math.max(min, Math.min(max, v)); }
function toast(m){ console.log('[toast]', m); }
function makeQR(id, text){
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML='';
  new QRCode(el, { text, width:220, height:220, correctLevel: QRCode.CorrectLevel.M });
}

// ============================
// DOM
// ============================
const btnHome   = document.getElementById('btnHome');
const goCreate  = document.getElementById('goCreate');
const goJoin    = document.getElementById('goJoin');

// CREATE
const duration      = document.getElementById('duration');
const packSel       = document.getElementById('pack');
const btnCancelCreate = document.getElementById('btnCancelCreate');
const btnCreate     = document.getElementById('btnCreate');
const shareCard     = document.getElementById('shareCard');
const code6El       = document.getElementById('code6');
const joinCount     = document.getElementById('joinCount');
const btnStart      = document.getElementById('btnStart');

// JOIN
const joinCode   = document.getElementById('joinCode');
const btnJoin    = document.getElementById('btnJoin');
const joinLobby  = document.getElementById('joinLobby');
const joinCount2 = document.getElementById('joinCount2');

// GAME
const gameTimerEl = document.getElementById('gameTimer');
const roleLine    = document.getElementById('roleLine');
const wordEl      = document.getElementById('word');

// END
const endTimeMsg   = document.getElementById('endTimeMsg');
const endIntrusMsg = document.getElementById('endIntrusMsg');

// ============================
// NAV
// ============================
btnHome && (btnHome.onclick = ()=> location.href = location.origin + location.pathname);
goCreate && (goCreate.onclick = ()=> show('create'));
goJoin && (goJoin.onclick   = ()=> show('join'));
btnCancelCreate && (btnCancelCreate.onclick = ()=> show('home'));

// ============================
// CREATE FLOW
// ============================
btnCreate?.addEventListener('click', async ()=>{
  try{
    S.durationSec = clamp((parseInt(duration.value)||5)*60, 120, 3600);
    S.packKey = (packSel?.value) || 'defaut';

    const res = await api('create', { durationSec: S.durationSec, pack: S.packKey });

    // Etat room
    S.roomId = res.roomId; S.code = res.code; S.seed = res.seed;
    S.token = res.you.token; S.seat = res.you.seat; S.isHost = true;
    S.packKey = res.pack || S.packKey;
    S.started = false; S.endsAt = 0; S.N = null;
    S.roundPhase = 'lobby';

    // QR / code
    S.joinUrl = res.joinUrl || (location.origin + location.pathname + '?room=' + encodeURIComponent(S.roomId));
    if (code6El) code6El.value = S.code || '';
    makeQR('qr', S.joinUrl);
    shareCard?.classList.remove('hidden');

    startPolling();
  }catch(e){ toast('Erreur création: '+e.message); }
});

btnStart?.addEventListener('click', async ()=>{
  if (!S.roomId || !S.token) return;
  try{
    const r = await api('start', { roomId:S.roomId, token:S.token });
    S.started = true;
    S.endsAt  = r.endsAt;
    S.N       = r.N || S.N;
    S.roundPhase = 'running';
    onGameStart();
  }catch(e){ toast('Impossible de démarrer: '+e.message); }
});

// ============================
// JOIN FLOW
// ============================
btnJoin?.addEventListener('click', async ()=>{
  const code = (joinCode?.value||'').trim();
  if (!/^\d{6}$/.test(code)) { toast('Code invalide'); return; }
  await doJoin({ code });
});

// Deep-link ?room=...
(async function(){
  const p = new URLSearchParams(location.search);
  if (p.has('room')){
    show('join');
    joinLobby?.classList.remove('hidden');
    await doJoin({ roomId: p.get('room') });
  }
})();

async function doJoin(body){
  try{
    const r = await api('join', body);
    S.roomId=r.roomId; S.code=r.code; S.seed=r.seed; S.seat=r.seat;
    S.token=r.token; S.durationSec=r.durationSec; S.started=r.started; S.endsAt=r.endsAt||0;
    S.isHost=false; S.packKey=r.pack || 'defaut'; S.N=r.N || S.N;

    show('join');
    joinLobby?.classList.remove('hidden');
    S.roundPhase = isRoundActive() ? 'running' : 'lobby';

    startPolling();
    if (isRoundActive()) onGameStart();
  }catch(e){ toast('Erreur rejoindre: '+e.message); }
}

// ============================
// POLLING
// ============================
function startPolling(){
  if (S.pollInt) clearInterval(S.pollInt);
  S.pollInt = setInterval(fetchStatus, 1200);
  fetchStatus();
}

async function fetchStatus(){
  if (!S.roomId) return;
  try{
    const url = `${API}?a=status&room=${encodeURIComponent(S.roomId)}&token=${encodeURIComponent(S.token||'')}`;
    const r = await fetch(url).then(x=>x.json());
    if (!r.ok) throw new Error(r.error||'status');

    // MAJ état depuis le serveur
    S.players = r.players;
    S.durationSec = r.durationSec || S.durationSec;
    S.N = r.N || S.N;
    S.packKey = r.pack || S.packKey;
    if (r.endsAt) S.endsAt = r.endsAt;      // endsAt reste stable pour la manche
    S.started = r.started;

    // UI lobby (compteurs + Start actif)
    joinCount  && (joinCount.textContent  = String(S.players));
    joinCount2 && (joinCount2.textContent = String(S.players));
    if (btnStart) btnStart.disabled = !S.isHost || S.players < 3 || isRoundActive();

    // --- Logique robuste d'affichage ---
    if (isRoundActive()) {
      // La manche est active côté serveur
      if (S.roundPhase !== 'running') {
        S.roundPhase = 'running';
        onGameStart();
      }
    } else {
      // La manche N'EST PAS active côté serveur
      if (S.roundPhase === 'running') {
        // On était en jeu → on passe à la fin (même si le polling a "raté" l'instant exact)
        showEnd('Temps écoulé !');
      }
      // Si on est déjà 'finished' ou 'lobby', on ne force rien.
    }
  }catch(e){
    console.warn('status err', e.message);
  }
}

// ============================
// ECRAN DE JEU
// ============================
async function onGameStart(){
  if (!S.roomId || !S.token) return;
  if (!isRoundActive()) return;

  S.roundPhase = 'running';
  show('game');

  try{
    // Récupère mot + rôle pour CE joueur (côté serveur)
    const a = await api('assign', { roomId:S.roomId, token:S.token });
    S.youIntrus = !!a.youIntrus;

    // Rôle
    if (roleLine){
      roleLine.textContent = a.youIntrus ? 'Tu es l’intrus !' : 'Avec le groupe';
      roleLine.className = 'game-role ' + (a.youIntrus ? 'intrus' : 'groupe');
    }
    // Mot
    if (wordEl){
      wordEl.textContent = a.word;
      wordEl.className = 'mono game-word ' + (a.youIntrus ? 'intrus' : 'groupe');
    }

    // Timer visuel — et sécurité : si ça atteint 0, on affiche la fin côté client aussi.
    if (S.timerInt) clearInterval(S.timerInt);
    S.timerInt = setInterval(()=>{
      const remain = Math.max(0, Math.floor((S.endsAt*1000 - Date.now())/1000));
      if (gameTimerEl) gameTimerEl.textContent = fmtTime(remain);
      if (remain === 0){
        clearInterval(S.timerInt);
        S.timerInt = null;
        // Montre l’écran de fin immédiatement côté client
        // (le polling confirmera et stabilisera l’état)
        if (S.roundPhase === 'running') showEnd('Temps écoulé !');
      }
    }, 250);

  }catch(e){
    toast('Erreur assignation: '+e.message);
  }
}

// ============================
// ECRAN DE FIN
// ============================
function showEnd(reason='Temps écoulé !'){
  // Stop seulement le timer d’affichage (on garde le polling)
  if (S.timerInt) { clearInterval(S.timerInt); S.timerInt = null; }

  endTimeMsg && (endTimeMsg.textContent = reason);
  if (endIntrusMsg){
    if (S.youIntrus) endIntrusMsg.classList.remove('hidden');
    else endIntrusMsg.classList.add('hidden');
  }

  S.roundPhase = 'finished';
  show('end');
}

// ============================
// Actions diverses
// ============================

// Copier le lien d’invitation
document.getElementById('copyLink')?.addEventListener('click', async ()=>{
  try{
    const link = S.joinUrl || (location.origin + location.pathname + (S.roomId ? ('?room='+encodeURIComponent(S.roomId)) : ''));
    await navigator.clipboard.writeText(link);
    toast('Lien copié');
  }catch{ toast('Impossible de copier'); }
});

// REJOUER → retour lobby (on NE COUPE PAS le polling)
document.getElementById('btnReplay')?.addEventListener('click', ()=>{
  if (S.isHost) {
    show('create');
    document.getElementById('shareCard')?.classList.remove('hidden');
  } else {
    show('join');
    document.getElementById('joinLobby')?.classList.remove('hidden');
  }
  S.roundPhase = 'lobby';
  // rafraîchit tout de suite l’état depuis le serveur
  fetchStatus();
});

// QUITTER → retour à l’accueil (reset visuel)
document.getElementById('btnExit')?.addEventListener('click', ()=>{
  location.href = location.origin + location.pathname;
});
