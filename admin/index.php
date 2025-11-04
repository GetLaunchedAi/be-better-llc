<?php
// Security headers (optional but recommended)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: interest-cohort=()');

// Read expected token from env; cover PHP-FPM/redirect quirks
$expected =
  ($_SERVER['ADMIN_TOKEN'] ?? '') ?:
  ($_SERVER['REDIRECT_ADMIN_TOKEN'] ?? '') ?:
  (getenv('ADMIN_TOKEN') ?: '');

function is_https() {
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function set_auth_cookie_and_redirect() {
  setcookie('admin_auth', '1', [
    'expires'  => time() + 60 * 60 * 6, // 6 hours
    'path'     => '/admin',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  header('Location: /admin/products.html', true, 302);
  exit;
}

// Accept ?token=... or POST token
$provided = '';
if (isset($_GET['token']))  $provided = trim($_GET['token']);
if (isset($_POST['token'])) $provided = trim($_POST['token']);

// If already has cookie, go straight in
if (!empty($_COOKIE['admin_auth']) && $_COOKIE['admin_auth'] === '1') {
  header('Location: /admin/products.html', true, 302);
  exit;
}

$err = false;
if ($provided !== '') {
  if ($expected !== '' && hash_equals($expected, $provided)) {
    set_auth_cookie_and_redirect();
  } else {
    $err = true;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Access</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root{ --bg:#faf7f2; --card:#fff; --line:#e7e1d8; --ink:#111827; --muted:#6b7280; --brand:#0a62ff; }
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;display:grid;place-items:center}
    .wrap{width:min(600px,92vw)}
    .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px 18px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
    h1{margin:0 0 6px;font-size:20px}
    p.sub{margin:0 0 16px;color:var(--muted)}
    form{display:flex;gap:8px;align-items:center}
    input[type="password"]{flex:1;height:44px;border:1px solid var(--line);border-radius:12px;padding:0 12px;font:15px/1.2 inherit;outline:none;background:#fff}
    input[type="password"]::placeholder{color:#9ca3af}
    input[type="password"]:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(10,98,255,.15)}
    button{height:44px;padding:0 14px;border:0;border-radius:12px;cursor:pointer;font-weight:700;color:#fff;background:var(--brand)}
    .err{margin-top:10px;color:#0D9488;background:#E0F2F1;border:1px solid #B2DFDB;padding:8px 10px;border-radius:10px;<?= $err ? '' : 'display:none;' ?>}
    .hint{margin-top:10px;color:var(--muted);font-size:12px}
    .footer{margin-top:14px;display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:12px}
    .link{color:var(--brand);text-decoration:none}
  </style>
</head>
<body>
  <main class="wrap">
    <div class="card" role="dialog" aria-labelledby="ttl" aria-modal="true">
      <h1 id="ttl">Admin Access</h1>
      <p class="sub">Paste your access token to continue to the product editor.</p>
      <form method="post" action="/admin/index.php" autocomplete="off">
        <input type="password" name="token" placeholder="Paste token…" aria-label="Access token" autofocus
               inputmode="text" autocapitalize="none" autocomplete="off" spellcheck="false">
        <button type="submit">Enter</button>
      </form>
      <div class="err">Invalid token. Try again.</div>
      <div class="hint">Tip: you can also open <code>/admin?token=YOUR_TOKEN</code>.</div>
      <div class="footer">
        <span>Session lasts ~6 hours or until you log out.</span>
        <a class="link" href="/admin/logout.php">Log out</a>
      </div>
    </div>
  </main>
</body>
</html>
