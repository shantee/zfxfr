<?php
// === CONFIG A ADAPTER ===
$to = "contact@zfx.fr";        // <-- mets ton adresse
$subject_prefix = "[Contact zfx.fr] ";
$redirect_base = "/contact";   // page de retour après traitement

// === UTILITAIRE ===
function redirect_with($code) {
  global $redirect_base;
  header("Location: {$redirect_base}?sent={$code}");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_with("method");
}

// Honeypot anti-bot
$hp = trim($_POST["website"] ?? "");
if (!empty($hp)) {
  redirect_with("bot");
}

// Récupération & nettoyage
$name    = trim($_POST["name"] ?? "");
$email   = trim($_POST["email"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");

// Validation minimale
if ($name === "" || $message === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_with("invalid");
}

// Sécurité basique contre l’injection d’en-têtes
$bad = "/(content-type:|bcc:|cc:|to:)/i";
if (preg_match($bad, $name.$email.$subject.$message)) {
  redirect_with("invalid");
}

// Compose l’email
$subject = $subject_prefix . ($subject !== "" ? $subject : "Nouveau message");
$body = "Nom: {$name}\nEmail: {$email}\nIP: ".$_SERVER['REMOTE_ADDR']."\n\nMessage:\n{$message}\n";
$headers = [];
$headers[] = "From: no-reply@zfx.fr";  // <-- adresse de ton domaine (souvent exigé par l’hébergeur)
$headers[] = "Reply-To: {$email}";
$headers[] = "X-Mailer: PHP/" . phpversion();

$ok = @mail($to, $subject, $body, implode("\r\n", $headers));

if ($ok) {
  redirect_with("ok");
} else {
  redirect_with("fail");
}
