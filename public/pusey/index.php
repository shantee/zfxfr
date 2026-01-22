<?php
/* ------------------------------------------------------------
   Share & Preview with Thumbnails (cached) — nicer UI
   - Thumbs: PSD/PDF/JPG/PNG via Imagick; fallback GD for JPG/PNG
   - Download endpoint: ?dl=FILENAME  (forces attachment)
   - Thumb endpoint:    ?thumb=FILENAME (serves cached JPEG)
   - Cache dir: .thumbs/
   ------------------------------------------------------------ */

$dir = __DIR__;
$thumbDir = $dir . DIRECTORY_SEPARATOR . '.thumbs';
@mkdir($thumbDir, 0755, true);

function has_imagick(){ return class_exists('Imagick'); }
function ext_of($f){ return strtolower(pathinfo($f, PATHINFO_EXTENSION)); }
function starts_with($h,$n){ return strpos($h,$n) === 0; }
$baseReal = realpath($dir);

function is_safe_file($base, $f){
    global $baseReal;
    $f = str_replace(['..','\\'], ['','/'], $f);
    $rp = realpath($base . DIRECTORY_SEPARATOR . $f);
    return $rp !== false && starts_with($rp, $baseReal . DIRECTORY_SEPARATOR) && is_file($rp);
}
function human_size($bytes){
    $u=['B','KB','MB','GB','TB']; $i=0;
    while($bytes>=1024 && $i<count($u)-1){ $bytes/=1024; $i++; }
    return number_format($bytes, $i?2:0) . ' ' . $u[$i];
}
function base_url(){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . ($path ? $path.'/' : '/');
}

/* ---------- Force download: ?dl=FILENAME ---------- */
if (isset($_GET['dl'])) {
    $file = basename($_GET['dl']);
    if (!is_safe_file($dir, $file)) { http_response_code(404); exit('Not found'); }
    $src = $dir . DIRECTORY_SEPARATOR . $file;
    $name = $file;

    // Détecte un type MIME raisonnable (sinon binaire)
    $mime = function_exists('mime_content_type') ? @mime_content_type($src) : 'application/octet-stream';
    if (!$mime) $mime = 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($src));
    header('Content-Disposition: attachment; filename="'.rawurlencode($name).'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-cache');
    readfile($src);
    exit;
}

/* ---------- Thumbnail endpoint: ?thumb=FILENAME ---------- */
if (isset($_GET['thumb'])) {
    $file = basename($_GET['thumb']);
    if (!is_safe_file($dir, $file)) { http_response_code(404); exit('Not found'); }
    $src = $dir . DIRECTORY_SEPARATOR . $file;
    $ext = ext_of($file);
    $thumbable = ['jpg','jpeg','png','psd','pdf'];
    if (!in_array($ext, $thumbable, true)) { http_response_code(415); exit('Unsupported'); }

    $thumbName = $file . '.jpg';
    $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $thumbName;

    if (!file_exists($thumbPath) || filemtime($thumbPath) < filemtime($src)) {
        $ok = false;
        if (has_imagick()) {
            try {
                $im = new Imagick();
                if ($ext === 'pdf') {
                    $im->setResolution(144, 144);
                    $im->readImage($src . '[0]');
                } elseif ($ext === 'psd') {
                    $im->readImage($src . '[0]');
                } else {
                    $im->readImage($src);
                }
                if (method_exists($im,'autoOrientImage')) $im->autoOrientImage();
                $im->setImageBackgroundColor('white');
                if ($im->getNumberImages() > 1 && method_exists($im,'mergeImageLayers')) {
                    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                } elseif (method_exists($im,'setImageAlphaChannel')) {
                    $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                }
                $im->thumbnailImage(360, 360, true);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(85);
                $im->writeImage($thumbPath);
                $im->clear(); $im->destroy();
                $ok = true;
            } catch(Throwable $e){ $ok = false; }
        }
        if (!$ok && in_array($ext, ['jpg','jpeg','png'], true)) {
            // Fallback GD
            try {
                $srcImg = ($ext==='png') ? @imagecreatefrompng($src) : @imagecreatefromjpeg($src);
                if ($srcImg) {
                    $w=imagesx($srcImg); $h=imagesy($srcImg);
                    $max=360; $ratio=min($max/$w, $max/$h, 1);
                    $tw=max(1,(int)floor($w*$ratio)); $th=max(1,(int)floor($h*$ratio));
                    $dst=imagecreatetruecolor($tw,$th);
                    $white=imagecolorallocate($dst,255,255,255);
                    imagefill($dst,0,0,$white);
                    imagecopyresampled($dst,$srcImg,0,0,0,0,$tw,$th,$w,$h);
                    imagejpeg($dst,$thumbPath,85);
                    imagedestroy($dst); imagedestroy($srcImg);
                    $ok = true;
                }
            } catch(Throwable $e){ $ok = false; }
        }
        if (!$ok) { http_response_code(500); exit('Thumb generation failed'); }
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    header('Cache-Control: max-age=86400, public');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($thumbPath)) . ' GMT');
    readfile($thumbPath);
    exit;
}

/* ---------------- Directory listing page ----------------- */
$items = array_values(array_filter(scandir($dir), function($f) use ($dir){
    if ($f === '.' || $f === '..' || $f[0] === '.') return false;
    if ($f === 'index.php' || $f === '.htaccess' || $f === '.thumbs') return false;
    if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'php') return false;
    return is_file($dir . DIRECTORY_SEPARATOR . $f);
}));

function is_previewable($f){ return in_array(ext_of($f), ['jpg','jpeg','png','psd','pdf'], true); }

$base = base_url();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Partage de fichiers</title>
<style>
  :root{ color-scheme: light dark; --bg: #0b0d12; --fg:#e7ecf3; --muted:#8b93a6; --card:#131720; --line:#222738; --pri:#4f8cff; --priF:#0b1a33; }
  @media (prefers-color-scheme: light){
    :root{ --bg:#f6f7fb; --fg:#131620; --muted:#5e6473; --card:#ffffff; --line:#e7e9f1; --pri:#1f65ff; --priF:#e7f0ff; }
  }
  *{box-sizing:border-box}
  body{
    margin:0; padding:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;
    background: radial-gradient(1000px 600px at 50% -200px, var(--priF), transparent), var(--bg);
    color: var(--fg);
  }
  .wrap{max-width:1100px;margin:2.5rem auto;padding:0 1rem}
  header{ text-align:center; margin-bottom:1.5rem; }
  h1{ margin:0; font-weight:800; letter-spacing:.3px; }
  .sub{ color:var(--muted); margin-top:.4rem; }

  .card{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1rem; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
  table{ width:100%; border-collapse:separate; border-spacing:0 8px; }
  thead th{ font-size:.85rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); text-align:left; padding:.25rem .75rem; }
  tbody tr{ background:linear-gradient(180deg, rgba(255,255,255,.02), transparent); border:1px solid var(--line); }
  tbody td{ padding:.75rem; vertical-align:middle; }
  tbody tr:hover{ background:rgba(79,140,255,.06); }
  .thumb{ width:72px; height:72px; object-fit:cover; border-radius:10px; border:1px solid var(--line); display:block }
  .name{ font-weight:600; }
  .muted{ color:var(--muted); }
  .btns a, .btn{
    display:inline-block; padding:.45rem .7rem; border-radius:10px; text-decoration:none; border:1px solid var(--line);
    margin-right:.35rem; font-size:.9rem; transition:.15s transform ease, .15s background ease;
    background:#0e1220; color:var(--fg);
  }
  @media (prefers-color-scheme: light){
    .btns a, .btn{ background:#fff; }
  }
  .btn:hover{ transform: translateY(-1px); }
  .btn.primary{ border-color:color-mix(in oklab, var(--pri), #000 10%); background:linear-gradient(180deg, var(--priF), transparent); color:inherit; }
  .row{ border-radius:12px; overflow:hidden; }
  .empty{ padding:1rem; text-align:center; color:var(--muted); }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin: .5rem 0 1rem; }
  .legend{ font-size:.9rem; color:var(--muted); }
  .copy{ cursor:pointer; }
</style>
</head>
<body>
  <div class="wrap">
    <header>
      <h1>Partage de fichiers</h1>
      <div class="sub"><?= count($items) ?> élément<?= count($items)>1?'s':'' ?> — miniatures locales (cache <code>.thumbs</code>)</div>
      <?php if (!has_imagick()): ?>
        <div class="sub">Imagick absent : thumbs PSD/PDF désactivées (PNG/JPG OK via GD).</div>
      <?php endif; ?>
    </header>

    <div class="card">
      <?php if (!$items): ?>
        <div class="empty">Aucun fichier pour l’instant.</div>
      <?php else: ?>
        <div class="topbar">


        </div>
        <table>
          <thead>
            <tr>
              <th style="width:88px">Aperçu</th>
              <th>Nom</th>
              <th style="width:120px">Taille</th>
              <th style="width:140px">Modifié</th>
              <th style="width:320px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $f):
                $path = $dir . DIRECTORY_SEPARATOR . $f;
                $mtime = date('Y-m-d H:i', filemtime($path));
                $size  = human_size(filesize($path));
                $href  = rawurlencode($f);
                $prev  = is_previewable($f) ? ('?thumb=' . rawurlencode($f)) : null;
                $abs   = $base . $href;
            ?>
            <tr class="row">
              <td>
                <?php if ($prev): ?>
                  <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer" title="Ouvrir">
                    <img class="thumb" src="<?= $prev ?>" alt="miniature de <?= htmlspecialchars($f) ?>">
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="name"><?= htmlspecialchars($f) ?></td>
              <td class="muted"><?= $size ?></td>
              <td class="muted"><?= $mtime ?></td>
              <td class="btns">
                <a class="btn" href="<?= $href ?>" target="_blank" rel="noopener noreferrer">Ouvrir</a>
                <a class="btn primary" href="?dl=<?= rawurlencode($f) ?>">Télécharger</a>
                <button aria-label="copier le lien" aria-hidden="true" class="btn copy" type="button" data-url="<?= htmlspecialchars($abs) ?>">Copier le lien</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  
  </div>

<script>
  // Copier le lien (absolu) dans le presse-papiers
  document.addEventListener('click', async (e)=>{
    const b = e.target.closest('.copy');
    if(!b) return;
    const url = b.getAttribute('data-url');
    try{
      await navigator.clipboard.writeText(url);
      b.textContent = 'Copié ✔';
      setTimeout(()=>{ b.textContent = 'Copier le lien'; }, 1200);
    }catch(err){
      b.textContent = 'Échec copie';
      setTimeout(()=>{ b.textContent = 'Copier le lien'; }, 1200);
    }
  });
</script>
</body>
</html>

