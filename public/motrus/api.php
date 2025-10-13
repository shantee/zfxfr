<?php
/**
 * api.php — API pour "Motrus"
 * - Sans comptes, stockage fichiers (JSON) dans ./data
 * - Le serveur gère rooms, sièges, timing, et attribue les mots/roles
 * - Les listes de mots sont dans packs.php (non exposées)
 *
 * Endpoints (JSON):
 *  POST  ?a=create  { durationSec?:int, pack?:"defaut"|"adultes" }
 *        → { ok, roomId, code, seed, durationSec, joinUrl, pack, you:{seat,token,host:true} }
 *  POST  ?a=join    { roomId?:string, code?:string }
 *        → { ok, roomId, code, seed, seat, token, players, durationSec, started, endsAt, pack }
 *  GET   ?a=status  &room=ROOMID[&token=YOU_TOKEN]
 *        → { ok, roomId, players, seats, started, endsAt, durationSec, N, pack, you:{seat} }
 *  POST  ?a=start   { roomId, token } → { ok, started:true, endsAt, N } (rejouer autorisé si manche finie)
 *  POST  ?a=assign  { roomId, token } → { ok, word, youIntrus }
 *  POST  ?a=leave   { roomId, token } → { ok }
 *  POST  ?a=destroy { roomId, token } (host) → { ok }
 */

declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);

define('MOTRUS_INTERNAL', true);          // Pour bloquer l’accès direct à packs.php
require_once __DIR__ . '/packs.php';      // ← listes côté serveur

// ——— CONFIG ———
const DATA_DIR         = __DIR__ . '/data';
const TTL_SECONDS      = 3600;     // 1h par room
const CODE_LEN         = 6;
const ROOM_ID_LEN      = 12;       // base32
const SEED_LEN         = 8;        // base32
const MIN_DURATION     = 120;      // 2 min
const MAX_DURATION     = 3600;     // 60 min
const DEFAULT_DURATION = 300;      // 5 min
const PUBLIC_JOIN_URL_BASE = null; // ex: 'https://zfx.fr/jeu/index.html' (null = auto)

// ——— CORS ———
// header('Access-Control-Allow-Origin: https://zfx.fr');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ——— FS bootstrap ———
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0777, true); }
if (!is_writable(DATA_DIR)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DATA_DIR not writable']); exit; }

// ——— Utils ———
function jread(): array { $raw = file_get_contents('php://input'); $d = $raw ? json_decode($raw,true) : []; return is_array($d) ? $d : []; }
function jout($data, int $code=200){ http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function jerr(string $msg, int $code=400){ jout(['ok'=>false,'error'=>$msg], $code); }
function now(): int { return time(); }
function b32(): string { return '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; } // Crockford (sans I L O U)
function rand_b32(int $len): string { $a=b32(); $n=strlen($a); $bytes=random_bytes($len); $s=''; for($i=0;$i<$len;$i++){ $s.=$a[ord($bytes[$i])%$n]; } return $s; }
function rand_code(int $len=CODE_LEN): string { $s=''; for($i=0;$i<$len;$i++){ $s .= (string)random_int(0,9); } return $s; }
function room_path(string $room): string { return DATA_DIR . "/room_" . $room . ".json"; }
function codes_path(): string { return DATA_DIR . '/codes.json'; }
function read_json_file(string $path): array { if(!file_exists($path)) return []; $fp=fopen($path,'r'); if(!$fp) return []; flock($fp, LOCK_SH); $raw=stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp); $d=json_decode($raw,true); return is_array($d)?$d:[]; }
function write_json_file(string $path, array $data): bool { $tmp=$path.'.tmp'; $fp=fopen($tmp,'c+'); if(!$fp) return false; flock($fp, LOCK_EX); ftruncate($fp,0); fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); fflush($fp); flock($fp, LOCK_UN); fclose($fp); return rename($tmp,$path); }
function get_room(string $room): ?array { $p=room_path($room); if(!file_exists($p)) return null; $r=read_json_file($p); return $r?:null; }
function save_room(array $r): void { write_json_file(room_path($r['id']), $r); }
function base_url(): string {
  if (PUBLIC_JOIN_URL_BASE) return PUBLIC_JOIN_URL_BASE;
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
  return "$scheme://$host$dir/index.html"; // index.html dans le même dossier
}
function cleanup(): void {
  $now = now();
  foreach (glob(DATA_DIR.'/room_*.json') as $file) {
    $room = json_decode(@file_get_contents($file), true);
    if (!is_array($room)) { @unlink($file); continue; }
    if (($room['expiresAt'] ?? 0) < $now) { @unlink($file); }
  }
  $codes = read_json_file(codes_path());
  $changed=false; foreach ($codes as $code=>&$info){ if (($info['expiresAt']??0) < $now) { unset($codes[$code]); $changed=true; } }
  if ($changed) write_json_file(codes_path(), $codes);
}
cleanup();
function ensure_unique_code(int $tries=50): string {
  $codes = read_json_file(codes_path());
  for ($i=0; $i<$tries; $i++){
    $c = rand_code(CODE_LEN);
    if (!isset($codes[$c])) return $c;
  }
  return rand_code(CODE_LEN+1);
}
function map_code(string $code, string $roomId, int $expiresAt): void {
  $codes = read_json_file(codes_path());
  $codes[$code] = ['roomId'=>$roomId,'expiresAt'=>$expiresAt];
  write_json_file(codes_path(), $codes);
}
function resolve_code(string $code): ?string {
  $codes = read_json_file(codes_path());
  $info = $codes[$code] ?? null; if (!$info) return null;
  if (($info['expiresAt']??0) < now()) return null;
  return $info['roomId'];
}
function list_taken_seats(array $r): array { $taken = array_keys($r['players']); sort($taken); return array_map('intval',$taken); }
function first_free_seat(array $taken): int { $s=1; while(in_array($s,$taken,true)) $s++; return $s; }
function is_host(array $r, string $token): bool { return isset($r['hostToken']) && hash_equals($r['hostToken'], $token); }

// ——— RNG déterministe (simple & robuste en PHP) ———
function rng01(string $key, string $label): float {
  $bytes = hash('sha256', $key.'|'.$label, true);        // 32 octets
  $arr = unpack('Nnum', substr($bytes, 0, 4));           // uint32 big-endian
  $u32 = $arr['num'];                                    // 0..4294967295
  return $u32 / 4294967296.0;                            // [0,1)
}
function weighted_pick(array $weights, float $u): int {
  $total = array_sum($weights);
  if ($total <= 0) return 0;
  $x = $u * $total; $acc = 0.0;
  foreach ($weights as $i=>$w){ $acc += $w; if ($x < $acc) return (int)$i; }
  return (int)(count($weights)-1);
}

/** Attribution (commun/intrus) pour CE joueur, à partir des listes du pack */
function assign_for(string $seed, int $N, int $seat, int $salt, array $lists): array {
  $key = "v1|$seed|$N|$salt";

  // Normalise: chaque liste >=2, trim + dédup
  $norm = [];
  foreach ($lists as $arr) {
    if (!is_array($arr)) continue;
    $clean = [];
    foreach ($arr as $w) {
      $w = trim((string)$w);
      if ($w !== '') $clean[$w] = true;
    }
    $L = array_keys($clean);
    if (count($L) >= 2) $norm[] = array_values($L);
  }
  if (!count($norm)) {
    $fallback = ["piscine","lac"];
    $flip = rng01($key,'flip') < 0.5;
    $common = $flip ? $fallback[1] : $fallback[0];
    $intrus = $flip ? $fallback[0] : $fallback[1];
    $intrusSeat = 1 + (int)floor(rng01($key,'intrusSeat') * $N);
    $youIntrus = ($seat === $intrusSeat);
    $word = $youIntrus ? $intrus : $common;
    return ['word'=>$word,'youIntrus'=>$youIntrus];
  }

  // Choix de la liste pondéré par L*(L-1)
  $weights = array_map(fn($L)=> count($L)*(count($L)-1), $norm);
  $idx = weighted_pick($weights, rng01($key,'list'));
  $list = $norm[min($idx, count($norm)-1)];
  $L = count($list);

  // Couple ordonné i != j (sans retry)
  $i = (int)floor(rng01($key,'i') * $L);
  $jraw = (int)floor(rng01($key,'j') * ($L-1));
  $j = ($jraw >= $i) ? ($jraw + 1) : $jraw;

  $common = $list[$i];
  $intrus = $list[$j];
  $intrusSeat = 1 + (int)floor(rng01($key,'intrusSeat') * $N);
  $youIntrus = ($seat === $intrusSeat);
  $word = $youIntrus ? $intrus : $common;

  return ['word'=>$word,'youIntrus'=>$youIntrus];
}

// ——— ROUTER ———
$action = $_GET['a'] ?? $_POST['a'] ?? '';

try {
  switch ($action) {

    case 'create': {
      $j = jread();
      $dur = (int)($j['durationSec'] ?? DEFAULT_DURATION);
      $dur = max(MIN_DURATION, min(MAX_DURATION, $dur));
      $pack = (string)($j['pack'] ?? 'defaut');
      $pack = preg_match('~^[a-z0-9_]+$~i', $pack) ? $pack : 'defaut';

      $now = now();
      $roomId = rand_b32(ROOM_ID_LEN);
      $seed   = rand_b32(SEED_LEN);
      $code   = ensure_unique_code();
      $expiresAt = $now + TTL_SECONDS;
      $hostToken = bin2hex(random_bytes(16));
      $players = [ 1 => $hostToken ]; // host = siège 1

      $room = [
        'id' => $roomId,
        'seed' => $seed,
        'createdAt' => $now,
        'expiresAt' => $expiresAt,
        'durationSec' => $dur,
        'startedAt' => null,
        'endsAt' => null,
        'N' => null,
        'hostToken' => $hostToken,
        'players' => $players, // seat => token
        'code' => $code,
        'pack' => $pack,
      ];
      save_room($room);
      map_code($code, $roomId, $expiresAt);

      $joinUrl = base_url() . '?room=' . $roomId;

      jout([
        'ok'=>true,
        'roomId'=>$roomId,
        'code'=>$code,
        'seed'=>$seed,
        'durationSec'=>$dur,
        'joinUrl'=>$joinUrl,
        'pack'=>$pack,
        'you'=>['seat'=>1,'token'=>$hostToken,'host'=>true]
      ]);
    }

    case 'join': {
      $j = jread();
      $roomId = (string)($j['roomId'] ?? '');
      $code = (string)($j['code'] ?? '');
      if ($roomId==='') {
        if ($code==='') jerr('roomId or code required');
        $roomId = resolve_code($code) ?? '';
        if ($roomId==='') jerr('invalid or expired code', 404);
      }
      $room = get_room($roomId); if (!$room) jerr('room not found', 404);
      if ($room['expiresAt'] < now()) jerr('room expired', 410);

      // attribue un siège libre
      $taken = list_taken_seats($room);
      $seat = first_free_seat($taken);
      $token = bin2hex(random_bytes(16));
      $room['players'][(int)$seat] = $token;
      save_room($room);

      jout([
        'ok'=>true,
        'roomId'=>$room['id'],
        'code'=>$room['code'],
        'seed'=>$room['seed'],
        'seat'=>(int)$seat,
        'token'=>$token,
        'players'=>count($room['players']),
        'durationSec'=>$room['durationSec'],
        'started'=> (bool)$room['startedAt'],
        'endsAt'=>$room['endsAt'],
        'pack'=>$room['pack'],
      ]);
    }

    case 'status': {
      $roomId = (string)($_GET['room'] ?? '');
      if ($roomId==='') jerr('room required');
      $token = (string)($_GET['token'] ?? '');
      $room = get_room($roomId); if (!$room) jerr('room not found', 404);

      $seats = list_taken_seats($room);
      $youSeat = null;
      if ($token) { foreach ($room['players'] as $s=>$t){ if (hash_equals($t,$token)) { $youSeat = (int)$s; break; } } }

      jout([
        'ok'=>true,
        'roomId'=>$room['id'],
        'players'=>count($room['players']),
        'seats'=>$seats,
        'started'=> (bool)$room['startedAt'],
        'endsAt'=>$room['endsAt'],
        'durationSec'=>$room['durationSec'],
        'N'=>$room['N'],
        'pack'=>$room['pack'],
        'you'=>['seat'=>$youSeat],
      ]);
    }

    case 'start': {
      $j = jread();
      $roomId = (string)($j['roomId'] ?? '');
      $token  = (string)($j['token'] ?? '');
      if ($roomId===''||$token==='') jerr('roomId/token required');
      $room = get_room($roomId); if (!$room) jerr('room not found',404);
      if (!is_host($room,$token)) jerr('forbidden',403);

      // Rejouer autorisé uniquement si la manche précédente est finie
      if ($room['startedAt'] && (($room['endsAt'] ?? 0) > now())) jerr('already started',409);

      $room['startedAt'] = now();
      $room['endsAt'] = $room['startedAt'] + (int)$room['durationSec'];
      $room['N'] = count($room['players']);
      save_room($room);
      jout(['ok'=>true,'started'=>true,'endsAt'=>$room['endsAt'],'N'=>$room['N']]);
    }

    case 'assign': {
      $j = jread();
      $roomId = (string)($j['roomId'] ?? '');
      $token  = (string)($j['token'] ?? '');
      if ($roomId===''||$token==='') jerr('roomId/token required');
      $room = get_room($roomId); if (!$room) jerr('room not found',404);
      if (!($room['startedAt'] ?? null)) jerr('game not started',409);

      // trouve le siège
      $seat = null;
      foreach ($room['players'] as $s=>$t){ if (hash_equals($t,$token)) { $seat = (int)$s; break; } }
      if (!$seat) jerr('unknown player',403);

      $N = (int)($room['N'] ?? count($room['players']));
      $seed = (string)$room['seed'];
      $salt = (int)($room['endsAt'] ?? 0);
      $packKey = (string)($room['pack'] ?? 'defaut');
      $lists = packs_get($packKey);
      if (!is_array($lists) || empty($lists)) jerr('no pack',500);

      $res = assign_for($seed, $N, $seat, $salt, $lists);
      jout(['ok'=>true, 'word'=>$res['word'], 'youIntrus'=>$res['youIntrus']]);
    }

    case 'leave': {
      $j = jread();
      $roomId = (string)($j['roomId'] ?? '');
      $token  = (string)($j['token'] ?? '');
      if ($roomId===''||$token==='') jerr('roomId/token required');
      $room = get_room($roomId); if (!$room) jerr('room not found',404);
      foreach ($room['players'] as $s=>$t){ if (hash_equals($t,$token)) { unset($room['players'][$s]); break; } }
      save_room($room);
      jout(['ok'=>true]);
    }

    case 'destroy': {
      $j = jread();
      $roomId = (string)($j['roomId'] ?? '');
      $token  = (string)($j['token'] ?? '');
      if ($roomId===''||$token==='') jerr('roomId/token required');
      $room = get_room($roomId); if (!$room) jerr('room not found',404);
      if (!is_host($room,$token)) jerr('forbidden',403);
      @unlink(room_path($roomId));
      // unmap code
      $codes = read_json_file(codes_path());
      foreach ($codes as $c=>$info){ if (($info['roomId']??'') === $roomId) unset($codes[$c]); }
      write_json_file(codes_path(), $codes);
      jout(['ok'=>true]);
    }

    default:
      jerr('unknown action', 400);
  }
} catch (Throwable $e) {
  jerr('server error: '.$e->getMessage(), 500);
}
