<?php
// api.php — handler du formulaire de contact + upload
// --------------------------------------------------
// Place ce fichier à la racine de ton site (même niveau que /contact).
// Crée un dossier /uploads (chmod 755) ou laisse le script le créer.

// ---------- CONFIG ----------
$TO_EMAIL   = 'philippe25@gmail.com';
$SUBJECT    = 'Contact zfx.fr';
$UPLOAD_DIR = __DIR__ . '/uploads';
$MAX_SIZE   = 10 * 1024 * 1024; // 10 Mo

// Extensions autorisées (minimales). Ajuste si besoin.
$ALLOWED_EXT = [
  'jpg','jpeg','png','gif','pdf',
  'zip','rar','7z',
  'txt','md',
  'doc','docx','xls','xlsx'
];

// Extensions explicitement interdites (sécurité)
$BLOCKED_EXT = ['php','phtml','phar','pl','cgi','exe','sh','bat','cmd'];

// ---------- UTILS ----------
function is_ajax(): bool {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function respond(string $status): void {
  if (is_ajax()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status], JSON_UNESCAPED_UNICODE);
  } else {
    header('Location: /contact?sent=' . urlencode($status));
  }
  exit;
}

function server_base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

// ---------- METHOD CHECK ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond('method');
}

// ---------- HONEYPOT ----------
if (!empty($_POST['website'] ?? '')) {
  respond('bot');
}

// ---------- INPUTS ----------
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// Validation basique
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
  respond('invalid');
}

// ---------- FILE (OPTIONNEL) ----------
$uploaded_path = null;
$uploaded_name = null;
$uploaded_url  = null;

if (isset($_FILES['file']) && is_array($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
  $err = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);

  if ($err !== UPLOAD_ERR_OK) {
    // Fichier fourni mais erreur → on signale un échec d’envoi
    respond('fail');
  }

  $size = (int)($_FILES['file']['size'] ?? 0);
  if ($size <= 0 || $size > $MAX_SIZE) {
    respond('fail');
  }

  $origName = (string)($_FILES['file']['name'] ?? 'file');
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

  if (in_array($ext, $BLOCKED_EXT, true)) {
    respond('fail');
  }
  if (!in_array($ext, $ALLOWED_EXT, true)) {
    respond('fail');
  }

  // Crée /uploads si besoin
  if (!is_dir($UPLOAD_DIR)) {
    if (!@mkdir($UPLOAD_DIR, 0755, true) && !is_dir($UPLOAD_DIR)) {
      respond('fail');
    }
  }

  // Nom de fichier sûr et unique
  $base = pathinfo($origName, PATHINFO_FILENAME);
  $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base);
  $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;
  $dest  = $UPLOAD_DIR . '/' . $fname;

  if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    respond('fail');
  }
  @chmod($dest, 0644);

  $uploaded_path = $dest;
  $uploaded_name = $origName;

  // URL publique si /uploads est servi par le webserver
  $uploaded_url = server_base_url() . '/uploads/' . rawurlencode($fname);
}

// ---------- BUILD EMAIL ----------
$ip   = $_SERVER['REMOTE_ADDR']  ?? 'unknown';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$date = date('Y-m-d H:i:s');

$lines = [];
$lines[] = "NOUVEAU MESSAGE via zfx.fr";
$lines[] = "Date : {$date}";
$lines[] = "IP   : {$ip}";
$lines[] = "UA   : {$ua}";
$lines[] = "";
$lines[] = "De   : {$email}";
$lines[] = "";
$lines[] = "Message :";
$lines[] = $message;
$lines[] = "";

if ($uploaded_path) {
  $lines[] = "Fichier reçu : {$uploaded_name}";
  $lines[] = "Stocké sous : " . basename($uploaded_path);
  if ($uploaded_url) {
    $lines[] = "URL (si accessible) : {$uploaded_url}";
  }
  $lines[] = "";
}

$body = implode("\r\n", $lines);

// Headers : From = domaine local, Reply-To = expéditeur
$fromDomain = $_SERVER['SERVER_NAME'] ?? 'zfx.fr';
$headers = [
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'From: zfx.fr <no-reply@' . $fromDomain . '>',
  'Reply-To: ' . $email,
  'X-Mailer: PHP/' . PHP_VERSION
];
$headersStr = implode("\r\n", $headers);

// ---------- SEND ----------
$ok = @mail($TO_EMAIL, '=?UTF-8?B?'.base64_encode($SUBJECT).'?=', $body, $headersStr);

// Si le mail échoue, tu peux décider de garder le statut ok si l’upload a réussi,
// mais ici on reste strict: il faut que le mail parte.
respond($ok ? 'ok' : 'fail');
