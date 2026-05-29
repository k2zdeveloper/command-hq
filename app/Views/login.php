<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#07070F">
<title>Command HQ — Access</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
<style>
html, body {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.login-wrap {
  position: relative;
  width: 100%;
  max-width: 360px;
  z-index: 10;
}

.corner { position: absolute; width: 14px; height: 14px; }
.corner-tl { top: -1px; left: -1px; border-top: 2px solid var(--amber); border-left: 2px solid var(--amber); border-radius: 2px 0 0 0; }
.corner-tr { top: -1px; right: -1px; border-top: 2px solid var(--amber); border-right: 2px solid var(--amber); border-radius: 0 2px 0 0; }
.corner-bl { bottom: -1px; left: -1px; border-bottom: 2px solid var(--amber); border-left: 2px solid var(--amber); border-radius: 0 0 0 2px; }
.corner-br { bottom: -1px; right: -1px; border-bottom: 2px solid var(--amber); border-right: 2px solid var(--amber); border-radius: 0 0 2px 0; }

.scanline {
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.03) 2px, rgba(0,0,0,0.03) 4px);
}

.login-submit {
  width: 100%;
  padding: 14px;
  font-size: 14px;
  font-weight: 500;
  font-family: 'Geist', sans-serif;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  border: none;
}
</style>
</head>
<body class="grid-bg">
<div class="scanline"></div>

<div class="login-wrap">
  <div class="login-card" style="padding: 32px 28px; position: relative;">
    <!-- Corner accents -->
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <!-- Logo -->
    <div style="text-align:center; margin-bottom:28px;">
      <!-- Icon container -->
      <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; border-radius:16px; background:rgba(232,181,71,0.08); border:1px solid rgba(232,181,71,0.2); margin-bottom:18px;">
        <svg width="26" height="26" style="color:var(--amber)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z"/>
        </svg>
      </div>

      <div style="font-family:'Geist Mono',monospace; font-size:9px; letter-spacing:0.3em; color:var(--muted); text-transform:uppercase; margin-bottom:6px;">
        SYSTEM // SECURE ACCESS
      </div>
      <div style="font-family:'Instrument Serif',serif; font-style:italic; font-size:26px; color:var(--text); line-height:1.1;">
        Command HQ
      </div>
      <div style="font-family:'Geist Mono',monospace; font-size:9px; letter-spacing:0.15em; color:var(--muted); text-transform:uppercase; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:6px;">
        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--low); animation:pulse 2s ease-in-out infinite;"></span>
        BRIDGE ONLINE
      </div>
    </div>

    <!-- Error message -->
    <?php if (session()->getFlashdata('error')): ?>
    <div style="margin-bottom:16px; padding:10px 14px; border-radius:8px; background:rgba(255,71,87,0.08); border:1px solid rgba(255,71,87,0.25); color:#FF4757; font-size:13px; display:flex; align-items:center; gap:8px;">
      <svg width="16" height="16" style="flex-shrink:0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
      </svg>
      <?= esc(session()->getFlashdata('error')) ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?= site_url('login') ?>">
      <?= csrf_field() ?>

      <div style="margin-bottom:8px; font-family:'Geist Mono',monospace; font-size:9px; letter-spacing:0.15em; color:var(--muted); text-transform:uppercase;">
        Authentication PIN
      </div>

      <div style="position:relative; margin-bottom:20px;">
        <input type="password" name="pin" inputmode="numeric"
               autocomplete="current-password" autofocus
               class="pin-input" placeholder="••••••">
        <svg width="16" height="16" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
        </svg>
      </div>

      <button type="submit" class="send-btn login-submit">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
        </svg>
        Authenticate
      </button>
    </form>

    <!-- Footer -->
    <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
      <div style="font-family:'Geist Mono',monospace; font-size:9px; color:var(--muted); text-transform:uppercase; letter-spacing:0.1em;">
        Paperclip Bridge v1.0
      </div>
      <div style="font-family:'Geist Mono',monospace; font-size:9px; color:var(--muted); display:flex; align-items:center; gap:4px;">
        AES-256
        <span style="display:inline-block; width:6px; height:6px; border-radius:1px; background:var(--low);"></span>
      </div>
    </div>
  </div>
</div>

</body>
</html>
