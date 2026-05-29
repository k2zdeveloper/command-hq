<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#070712">
<title>Command HQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
<link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; }

:root {
  --bg:        #07070F;
  --surface:   #0D0D1A;
  --surface2:  #111126;
  --border:    rgba(255,255,255,0.055);
  --border-hi: rgba(232,181,71,0.22);
  --text:      #E4E4F0;
  --muted:     #52527A;
  --cyan:      #E8B547;
  --amber:     #E8B547;
  --green:     #34D399;
  --red:       #FF4757;
  --purple:    #C9A84C;
  --glow:      rgba(232,181,71,0.15);
}

html, body {
  height: 100dvh; overflow: hidden;
  background: var(--bg); color: var(--text);
  font-family: 'Geist', system-ui, sans-serif;
  font-size: 14px; -webkit-font-smoothing: antialiased;
}

/* ── Layout ── */
.layout { display: flex; height: 100dvh; }

/* ── Sidebar ── */
.sidebar {
  width: 252px; flex-shrink: 0;
  border-right: 1px solid rgba(79,200,255,0.1);
  display: flex; flex-direction: column;
  overflow: hidden; position: relative;
  /* Circuit board background */
  background-color: #0A0A14;
  background-image:
    radial-gradient(circle, rgba(232,181,71,0.13) 1px, transparent 1px),
    linear-gradient(rgba(232,181,71,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(232,181,71,0.025) 1px, transparent 1px);
  background-size: 28px 28px;
}
/* Amber glow line on right edge */
.sidebar::after {
  content: '';
  position: absolute; top: 0; right: -1px; bottom: 0; width: 1px;
  background: linear-gradient(180deg, transparent 0%, #E8B547 40%, #C9A84C 70%, transparent 100%);
  opacity: 0.35;
  animation: edge-pulse 4s ease-in-out infinite;
}
@keyframes edge-pulse {
  0%,100% { opacity: 0.2; }
  50%      { opacity: 0.55; }
}

/* ── Main panel ── */
.main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }

/* ── Chat scroll area ── */
.chat-area {
  flex: 1; overflow-y: auto;
  padding: 24px 20px;
}
.chat-area::-webkit-scrollbar { width: 3px; }
.chat-area::-webkit-scrollbar-track { background: transparent; }
.chat-area::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 2px; }

/* ── Company row ── */
.co-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; margin: 0 6px;
  border-radius: 8px; cursor: pointer;
  border: 1px solid transparent;
  transition: background 0.15s, border-color 0.15s;
}
.co-row:hover { background: rgba(232,181,71,0.04); }
.co-row.is-active {
  background: rgba(232,181,71,0.07);
  border-color: rgba(232,181,71,0.2);
  box-shadow: 0 0 12px rgba(232,181,71,0.06);
}

/* ── Avatar ── */
.av {
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 600; color: #fff; flex-shrink: 0;
  position: relative;
}
.av-36 { width: 36px; height: 36px; font-size: 13px; }
.av-28 { width: 28px; height: 28px; font-size: 11px; border-radius: 7px; }
.av-dot {
  position: absolute; bottom: -2px; right: -2px;
  width: 7px; height: 7px; border-radius: 50%;
  border: 2px solid var(--surface);
}

/* ── Chat bubbles ── */
.bubble-me {
  background: rgba(232,181,71,0.06);
  border: 1px solid rgba(232,181,71,0.16);
  border-radius: 14px 3px 14px 14px;
  padding: 10px 14px;
  box-shadow: 0 0 10px rgba(232,181,71,0.05);
}
.bubble-ceo {
  background: rgba(13,13,26,0.95);
  border: 1px solid rgba(255,255,255,0.055);
  border-radius: 3px 14px 14px 14px;
  padding: 10px 14px;
  display: inline-block; width: 100%;
}

/* ── Compose ── */
.compose-wrap {
  flex-shrink: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  padding: 10px 16px 14px;
}
.compose-box {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s;
  overflow: hidden;
}
.compose-box:focus-within { border-color: rgba(232,181,71,0.28); }
.compose-input {
  width: 100%; background: transparent;
  border: none; outline: none; resize: none;
  color: var(--text); font-family: 'Geist', sans-serif;
  font-size: 14px; line-height: 1.5;
  max-height: 120px; padding: 11px 14px 5px;
}
.compose-input::placeholder { color: var(--muted); }

/* ── Send button ── */
.send-btn {
  width: 40px; height: 40px; border-radius: 10px;
  background: linear-gradient(135deg, #E8B547, #C9893A);
  color: #07070F; border: none; cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: opacity 0.15s, transform 0.1s, box-shadow 0.2s;
  box-shadow: 0 0 14px rgba(232,181,71,0.28);
}
.send-btn:hover:not(:disabled) { box-shadow: 0 0 22px rgba(232,181,71,0.45); opacity: 0.9; }
.send-btn:active:not(:disabled) { transform: scale(0.93); }
.send-btn:disabled { opacity: 0.22; cursor: not-allowed; box-shadow: none; }

/* ── Priority buttons ── */
.prio-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 8px; border-radius: 5px;
  border: 1px solid transparent; background: transparent;
  cursor: pointer; font-family: 'Geist Mono', monospace;
  font-size: 9px; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--muted); transition: all 0.15s;
}
.prio-btn:hover { color: var(--text); background: rgba(255,255,255,0.04); }
.active-critical { color: var(--red)   !important; border-color: rgba(248,113,113,0.25) !important; background: rgba(248,113,113,0.07) !important; }
.active-high     { color: var(--amber) !important; border-color: rgba(232,181,71,0.25)  !important; background: rgba(232,181,71,0.07)  !important; }
.active-medium   { color: var(--cyan)  !important; border-color: rgba(56,189,248,0.25)  !important; background: rgba(56,189,248,0.07)  !important; }
.active-low      { color: var(--green) !important; border-color: rgba(52,211,153,0.25)  !important; background: rgba(52,211,153,0.07)  !important; }

/* ── Quick chips ── */
.chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 20px;
  border: 1px solid var(--border); background: transparent;
  cursor: pointer; font-family: 'Geist Mono', monospace;
  font-size: 9px; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--muted); transition: all 0.15s; white-space: nowrap;
}
.chip:hover { border-color: var(--border-hi); color: var(--text); }

/* ── System divider ── */
.sys-line {
  display: flex; align-items: center; gap: 10px;
  margin: 10px 0; color: var(--muted);
  font-family: 'Geist Mono', monospace; font-size: 9px;
  letter-spacing: 0.1em; text-transform: uppercase;
}
.sys-line::before, .sys-line::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── Badge ── */
.badge {
  min-width: 17px; height: 17px; padding: 0 4px;
  border-radius: 9px; background: var(--cyan);
  color: #070712; font-family: 'Geist Mono', monospace;
  font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}


/* ── Cancel button ── */
.cancel-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 8px; border-radius: 5px;
  border: 1px solid rgba(248,113,113,0.2);
  background: rgba(248,113,113,0.05);
  color: var(--muted); font-family: 'Geist Mono', monospace;
  font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em;
  cursor: pointer; transition: all 0.15s;
}
.cancel-btn:hover { border-color: rgba(248,113,113,0.4); color: var(--red); background: rgba(248,113,113,0.08); }
.cancel-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* ── Markdown ── */
.md h1,.md h2,.md h3 { color: var(--text); font-weight: 600; margin: 12px 0 6px; }
.md h1 { font-size: 16px; }
.md h2 { font-size: 14px; }
.md h3 { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 500; }
.md p  { margin: 0 0 8px; font-size: 14px; line-height: 1.65; }
.md p:last-child { margin-bottom: 0; }
.md ul,.md ol { padding-left: 18px; margin: 4px 0 8px; }
.md li { font-size: 14px; line-height: 1.6; margin-bottom: 3px; }
.md strong { font-weight: 600; }
.md a { color: var(--amber); text-decoration: none; border-bottom: 1px solid rgba(232,181,71,0.3); transition: border-color 0.15s; }
.md a:hover { border-bottom-color: var(--amber); }
.md code { font-family: 'Geist Mono', monospace; font-size: 12px; background: rgba(232,181,71,0.06); border: 1px solid rgba(232,181,71,0.15); border-radius: 4px; padding: 1px 5px; color: var(--amber); }
.md pre  { background: rgba(0,0,0,0.35); border: 1px solid var(--border); border-radius: 8px; padding: 12px; overflow-x: auto; margin: 8px 0; }
.md pre code { background: none; border: none; padding: 0; color: #7ECFE0; font-size: 12px; }
.md hr  { border: none; border-top: 1px solid var(--border); margin: 10px 0; }
.md blockquote { border-left: 2px solid var(--cyan); padding-left: 12px; margin: 8px 0; color: var(--muted); }
.md ul li::marker { color: var(--cyan); }

/* ── Scrollbar hide ── */
.no-sb { scrollbar-width: none; -ms-overflow-style: none; }
.no-sb::-webkit-scrollbar { display: none; }

/* ── Animations ── */
@keyframes spin      { to { transform: rotate(360deg); } }
@keyframes blink     { 0%,100%{opacity:1} 50%{opacity:.3} }
@keyframes ring-ping { 0%{transform:scale(1);opacity:0.6} 100%{transform:scale(3.5);opacity:0} }

/* ── Mobile ── */
@media (max-width: 640px) {
  .sidebar  { display: none; }
  .mob-tabs { display: flex !important; }
}
.mob-tabs { display: none; }
</style>
</head>

<body x-data="commandHQ()" x-init="init()">
<div class="layout">

<!-- ══════════════════════════ SIDEBAR ══════════════════════════ -->
<aside class="sidebar">

  <!-- Brand -->
  <div style="padding:14px 14px 12px;border-bottom:1px solid var(--border);flex-shrink:0;">
    <div style="display:flex;align-items:center;gap:9px;">
      <div style="width:30px;height:30px;border-radius:8px;background:rgba(232,181,71,0.07);border:1px solid rgba(232,181,71,0.22);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 10px rgba(232,181,71,0.12);">
        <svg style="width:14px;height:14px;color:var(--cyan)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z"/>
        </svg>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:var(--text);letter-spacing:-0.01em;">Command HQ</div>
        <div style="font-family:'Geist Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:0.1em;text-transform:uppercase;margin-top:1px;">Paperclip Interface</div>
      </div>
      <!-- Live dot -->
      <div :title="online ? 'Connected' : 'Disconnected'"
           style="width:6px;height:6px;border-radius:50%;flex-shrink:0;transition:all 0.4s;"
           :style="online ? 'background:var(--green);box-shadow:0 0 5px var(--green)' : 'background:var(--red)'"></div>
    </div>
  </div>

  <!-- Companies list -->
  <div style="flex:1;overflow-y:auto;padding:8px 0;" class="no-sb">
    <div style="padding:0 14px 5px;font-family:'Geist Mono',monospace;font-size:8px;text-transform:uppercase;letter-spacing:0.14em;color:var(--muted);">Companies</div>

    <template x-for="company in companies" :key="company.key">
      <div class="co-row" :class="activeCompanyKey === company.key ? 'is-active' : ''"
           @click="selectCompany(company.key)">
        <div class="av av-36" :class="company.avatarClass" x-text="company.initial">
          <div class="av-dot" style="background:var(--green);"></div>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;"
               :style="activeCompanyKey === company.key ? 'color:var(--text)' : 'color:#8080A8'"
               x-text="company.name"></div>
          <div style="font-family:'Geist Mono',monospace;font-size:9px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
               :style="activeCompanyKey === company.key ? 'color:var(--cyan)' : 'color:var(--muted)'"
               x-text="company.ceoName + ' · ' + company.tag"></div>
        </div>
        <span class="badge" x-show="company.unread > 0" x-text="company.unread"></span>
      </div>
    </template>
  </div>

  <!-- Bottom bar — sign out + live pulse -->
  <div style="border-top:1px solid rgba(232,181,71,0.08);padding:10px 14px;flex-shrink:0;display:flex;align-items:center;gap:8px;">
    <!-- Live indicator -->
    <div style="flex:1;display:flex;align-items:center;gap:6px;">
      <div style="position:relative;width:6px;height:6px;">
        <div style="position:absolute;inset:0;border-radius:50%;transition:background 0.3s;"
             :style="online ? 'background:var(--amber);box-shadow:0 0 6px var(--amber)' : 'background:var(--red)'"></div>
        <div x-show="online" style="position:absolute;inset:-3px;border-radius:50%;border:1px solid var(--amber);opacity:0.4;animation:ring-ping 2s ease-out infinite;"></div>
      </div>
      <span style="font-family:'Geist Mono',monospace;font-size:8px;letter-spacing:0.12em;text-transform:uppercase;"
            :style="online ? 'color:var(--amber)' : 'color:var(--red)'"
            x-text="online ? 'Connected' : 'Offline'"></span>
    </div>
    <a href="<?= site_url('logout') ?>"
       style="display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:7px;border:1px solid rgba(232,181,71,0.1);color:var(--muted);text-decoration:none;font-family:'Geist Mono',monospace;font-size:8px;text-transform:uppercase;letter-spacing:0.08em;transition:all 0.15s;"
       onmouseover="this.style.borderColor='rgba(255,71,87,0.35)';this.style.color='var(--red)'"
       onmouseout="this.style.borderColor='rgba(232,181,71,0.1)';this.style.color='var(--muted)'">
      <svg style="width:10px;height:10px" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
      </svg>
      Exit
    </a>
  </div>

</aside>

<!-- ══════════════════════════ MAIN ══════════════════════════ -->
<div class="main">

  <!-- Mobile company tabs -->
  <div class="mob-tabs no-sb" style="overflow-x:auto;background:var(--surface);border-bottom:1px solid var(--border);padding:8px 12px;gap:6px;align-items:center;">
    <template x-for="company in companies" :key="company.key">
      <button @click="selectCompany(company.key)"
              style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:20px;border:1px solid;white-space:nowrap;cursor:pointer;font-size:12px;background:transparent;font-family:inherit;transition:all 0.15s;"
              :style="activeCompanyKey === company.key ? 'border-color:rgba(56,189,248,0.3);color:var(--cyan);background:rgba(56,189,248,0.06)' : 'border-color:var(--border);color:var(--muted)'">
        <div class="av av-28" :class="company.avatarClass" x-text="company.initial"></div>
        <span x-text="company.name"></span>
      </button>
    </template>
  </div>

  <!-- Chat header -->
  <div style="flex-shrink:0;background:var(--surface);border-bottom:1px solid var(--border);padding:10px 18px;display:flex;align-items:center;gap:12px;" x-show="activeCompany">
    <div class="av av-28" :class="activeCompany?.avatarClass" x-text="activeCompany?.initial"></div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:6px;">
        <span x-text="activeCompany?.ceoName"></span>
        <span style="font-family:'Geist Mono',monospace;font-size:8px;padding:1px 5px;border-radius:3px;background:rgba(56,189,248,0.07);border:1px solid rgba(56,189,248,0.15);color:var(--cyan);">CEO</span>
      </div>
      <div style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted);margin-top:1px;" x-text="activeCompany?.tag"></div>
    </div>
    <!-- Counters -->
    <div style="display:flex;gap:12px;align-items:center;flex-shrink:0;">
      <div style="text-align:center;">
        <div style="font-family:'Geist Mono',monospace;font-size:14px;font-weight:600;color:var(--text)" x-text="activeCompany?.openTasks ?? 0"></div>
        <div style="font-family:'Geist Mono',monospace;font-size:7px;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted)">Open</div>
      </div>
      <div style="width:1px;height:22px;background:var(--border);"></div>
      <div style="text-align:center;">
        <div style="font-family:'Geist Mono',monospace;font-size:14px;font-weight:600;color:var(--green)" x-text="activeCompany?.doneTasks ?? 0"></div>
        <div style="font-family:'Geist Mono',monospace;font-size:7px;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted)">Done</div>
      </div>
    </div>
  </div>

  <!-- ══ MESSAGES ══ -->
  <main class="chat-area" x-ref="chatArea">
    <div style="max-width:700px;margin:0 auto;">

      <!-- Loading -->
      <div x-show="loading" style="display:flex;justify-content:center;padding:60px 0;">
        <svg style="width:16px;height:16px;animation:spin 1s linear infinite;color:var(--cyan)" fill="none" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-opacity="0.15"/>
          <path fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-2a8 8 0 0 0-8-8V2z"/>
        </svg>
      </div>

      <!-- Thread -->
      <template x-for="msg in activeMessages" :key="msg.id">
        <div style="margin-bottom:16px;">

          <!-- SYSTEM -->
          <template x-if="msg.role === 'system'">
            <div class="sys-line"><span x-text="msg.text"></span></div>
          </template>

          <!-- BOSS -->
          <template x-if="msg.role === 'boss'">
            <div style="display:flex;flex-direction:column;align-items:flex-end;">
              <div class="bubble-me" style="max-width:76%;">
                <p style="font-size:14px;line-height:1.6;margin:0;white-space:pre-wrap;" x-text="msg.text"></p>
              </div>
              <div style="display:flex;align-items:center;gap:5px;margin-top:4px;margin-right:2px;">
                <span style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted)" x-text="formatTime(msg.created_at)"></span>
                <svg style="width:12px;height:12px;color:var(--cyan);opacity:0.55" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </div>
            </div>
          </template>

          <!-- CEO -->
          <template x-if="msg.role === 'ceo'">
            <div style="display:flex;gap:9px;align-items:flex-start;">
              <div class="av av-28" :class="activeCompany?.avatarClass" x-text="activeCompany?.initial" style="flex-shrink:0;margin-top:2px;"></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;">
                  <span style="font-size:12px;font-weight:600;color:var(--text)" x-text="activeCompany?.ceoName"></span>
                  <span style="font-family:'Geist Mono',monospace;font-size:8px;padding:1px 5px;border-radius:3px;background:rgba(56,189,248,0.07);border:1px solid rgba(56,189,248,0.13);color:var(--cyan)">CEO</span>
                  <span style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted)" x-text="formatTime(msg.created_at)"></span>
                </div>

                <div class="bubble-ceo md">
                  <div x-html="renderMarkdown(msg.text)"></div>

                  <!-- Attachment cards -->
                  <template x-if="msg.task && msg.task.attachments && msg.task.attachments.length">
                    <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;">
                      <template x-for="att in msg.task.attachments" :key="att.id">
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 11px;border-radius:8px;background:rgba(0,0,0,0.2);border:1px solid var(--border);">
                          <svg style="width:22px;height:22px;flex-shrink:0;color:var(--amber)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                          </svg>
                          <div style="flex:1;min-width:0;">
                            <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="att.filename"></div>
                            <div style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted);margin-top:2px;" x-text="formatBytes(att.byteSize)"></div>
                          </div>
                          <div style="display:flex;gap:4px;flex-shrink:0;">
                            <a :href="`<?= site_url('api/attachment') ?>/${activeCompanyKey}/${att.id}?filename=${encodeURIComponent(att.filename)}`"
                               target="_blank" rel="noopener noreferrer"
                               style="display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:5px;background:rgba(56,189,248,0.07);border:1px solid rgba(56,189,248,0.18);color:var(--cyan);font-family:'Geist Mono',monospace;font-size:9px;text-transform:uppercase;letter-spacing:0.06em;text-decoration:none;transition:background 0.15s;"
                               onmouseover="this.style.background='rgba(56,189,248,0.14)'"
                               onmouseout="this.style.background='rgba(56,189,248,0.07)'">
                              <svg style="width:9px;height:9px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><circle cx="12" cy="12" r="3"/></svg>
                              View
                            </a>
                            <a :href="`<?= site_url('api/attachment') ?>/${activeCompanyKey}/${att.id}?download=1&filename=${encodeURIComponent(att.filename)}`"
                               :download="att.filename"
                               style="display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:5px;background:rgba(255,255,255,0.03);border:1px solid var(--border);color:var(--muted);font-family:'Geist Mono',monospace;font-size:9px;text-transform:uppercase;letter-spacing:0.06em;text-decoration:none;transition:all 0.15s;"
                               onmouseover="this.style.background='rgba(255,255,255,0.07)';this.style.color='var(--text)'"
                               onmouseout="this.style.background='rgba(255,255,255,0.03)';this.style.color='var(--muted)'">
                              <svg style="width:9px;height:9px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                              Save
                            </a>
                          </div>
                        </div>
                      </template>
                    </div>
                  </template>

                  <!-- Legacy task card -->
                  <template x-if="msg.task && msg.task.status">
                    <div style="margin-top:10px;padding:9px 11px;border-radius:8px;background:rgba(0,0,0,0.2);border:1px solid var(--border);border-left:2px solid var(--amber);">
                      <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                        <span style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--amber)" x-text="msg.task.id?.slice(0,8)"></span>
                        <span style="font-family:'Geist Mono',monospace;font-size:9px;"
                              :style="msg.task.status==='done'?'color:var(--green)':'color:var(--muted)'"
                              x-text="msg.task.status.toUpperCase()"></span>
                      </div>
                      <div style="font-size:12px;font-weight:500;line-height:1.4;" x-text="msg.task.title"></div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </template>

        </div>
      </template>

      <!-- Empty state -->
      <div x-show="!loading && activeMessages.length === 0"
           style="display:flex;flex-direction:column;align-items:center;padding:80px 20px;text-align:center;">
        <div style="width:44px;height:44px;border-radius:11px;background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
          <svg style="width:19px;height:19px;color:var(--cyan);opacity:0.35" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
          </svg>
        </div>
        <div style="font-size:13px;font-weight:500;margin-bottom:4px;">Ready for your command</div>
        <div style="font-family:'Geist Mono',monospace;font-size:9px;text-transform:uppercase;letter-spacing:0.12em;color:var(--muted);" x-text="(activeCompany?.ceoName || '—') + ' · Standby'"></div>
      </div>

      <!-- Processing indicator -->
      <div x-show="isTyping" style="display:flex;gap:9px;align-items:flex-start;margin-bottom:16px;">
        <div class="av av-28" :class="activeCompany?.avatarClass" x-text="activeCompany?.initial" style="flex-shrink:0;margin-top:2px;"></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <div class="bubble-ceo" style="padding:9px 13px;display:inline-flex;align-items:center;gap:8px;width:auto;">
            <svg style="width:11px;height:11px;animation:spin 1.3s linear infinite;color:var(--cyan);flex-shrink:0" fill="none" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-opacity="0.15"/>
              <path fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-2a8 8 0 0 0-8-8V2z"/>
            </svg>
            <span style="font-family:'Geist Mono',monospace;font-size:9px;letter-spacing:0.08em;color:var(--muted);" x-text="(activeCompany?.ceoName || 'CEO') + ' · Working'"></span>
          </div>
          <!-- Cancel button — visible while task is in progress -->
          <template x-if="pendingIssueId">
            <button class="cancel-btn" :disabled="cancelling" @click="cancelTask(pendingIssueId)">
              <svg style="width:8px;height:8px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
              </svg>
              <span x-text="cancelling ? 'Cancelling…' : 'Cancel task'"></span>
            </button>
          </template>
        </div>
      </div>

    </div>
  </main>

  <!-- ══ COMPOSE ══ -->
  <div class="compose-wrap">

    <!-- Quick chips -->
    <div class="no-sb" x-show="!draft" style="overflow-x:auto;margin-bottom:8px;">
      <div style="display:flex;gap:5px;min-width:min-content;">
        <template x-for="chip in quickChips" :key="chip.label">
          <button class="chip" @click="draft = chip.label">
            <svg style="width:9px;height:9px;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path x-bind:d="chip.iconPath" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span x-text="chip.label"></span>
          </button>
        </template>
      </div>
    </div>

    <div style="display:flex;align-items:flex-end;gap:8px;">
      <div class="compose-box" style="flex:1;min-width:0;">
        <textarea
          x-model="draft"
          @input="autoResize($event)"
          @keydown.enter.prevent="if (!$event.shiftKey) send()"
          rows="1"
          :placeholder="`Message ${activeCompany?.ceoName || 'CEO'}…`"
          class="compose-input"
        ></textarea>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 12px 8px;">
          <div style="display:flex;gap:2px;">
            <template x-for="p in priorities" :key="p.value">
              <button class="prio-btn" :class="priority === p.value ? 'active-' + p.value : ''"
                      @click="priority = p.value" :title="p.label">
                <span x-html="p.icon"></span>
                <span x-text="p.label"></span>
              </button>
            </template>
          </div>
          <span style="font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted)" x-text="draft.length + '/2000'"></span>
        </div>
      </div>

      <button @click="send()" :disabled="!draft.trim() || sending" class="send-btn" aria-label="Send">
        <svg x-show="!sending" style="width:15px;height:15px" fill="currentColor" viewBox="0 0 24 24">
          <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"/>
        </svg>
        <svg x-show="sending" style="width:13px;height:13px;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-opacity="0.25"/>
          <path fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-2a8 8 0 0 0-8-8V2z"/>
        </svg>
      </button>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 1px 0;font-family:'Geist Mono',monospace;font-size:9px;color:var(--muted);">
      <span>↵ Send · ⇧↵ Newline</span>
      <span x-text="activeCompany ? activeCompany.key.toUpperCase() + ' · ' + activeCompany.name : ''"></span>
    </div>
  </div>

</div><!-- .main -->
</div><!-- .layout -->

<script>
const BOOTSTRAP_COMPANIES = <?= json_encode($companies) ?>;
const PAPERCLIP_BASE_URL  = <?= json_encode($paperclipUrl) ?>;
const POLL_INTERVAL_MS = 5000;

function commandHQ() {
  return {
    companies: BOOTSTRAP_COMPANIES.map(c => ({ ...c, unread: 0, openTasks: 0, doneTasks: 0 })),
    activeCompanyKey: BOOTSTRAP_COMPANIES[0]?.key ?? null,
    threads: {},
    draft: '',
    priority: 'medium',
    loading: false,
    sending: false,
    cancelling: false,
    isTyping: false,
    pendingIssueId: null,
    online: true,
    pollTimer: null,
    uptimeTimer: null,
    pollInterval: 5000,
    sessionStart: Date.now(),
    lastSync: null,
    uptime: '0s',

    priorities: [
      { value: 'critical', label: 'Critical', icon: '<svg style="width:9px;height:9px" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V3a.75.75 0 0 1 .75-.75Zm-9 13.5a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z"/></svg>' },
      { value: 'high',     label: 'High',     icon: '<svg style="width:9px;height:9px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>' },
      { value: 'medium',   label: 'Medium',   icon: '<svg style="width:9px;height:9px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>' },
      { value: 'low',      label: 'Low',      icon: '<svg style="width:9px;height:9px" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>' },
    ],

    quickChips: [
      { label: 'Status update?',   iconPath: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99' },
      { label: 'Daily summary',    iconPath: 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z' },
      { label: 'Show open issues', iconPath: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z' },
      { label: 'Any blockers?',    iconPath: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z' },
    ],

    get activeCompany() { return this.companies.find(c => c.key === this.activeCompanyKey); },
    get activeMessages() { return this.threads[this.activeCompanyKey] || []; },

    async init() {
      await this.loadThread(this.activeCompanyKey);
      this.startPolling();
      this.startUptime();
    },

    startPolling() {
      // setTimeout chain instead of setInterval — next poll only fires AFTER
      // the response arrives, preventing request pileup on a slow server.
      const schedule = () => {
        this.pollTimer = setTimeout(async () => {
          await this.loadThread(this.activeCompanyKey, true);
          schedule();
        }, this.pollInterval);
      };
      schedule();
    },

    startUptime() {
      this.uptimeTimer = setInterval(() => {
        const s = Math.floor((Date.now() - this.sessionStart) / 1000);
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        this.uptime = h > 0 ? `${h}h ${m}m` : m > 0 ? `${m}m ${sec}s` : `${sec}s`;
      }, 1000);
    },

    async loadThread(key, silent = false) {
      if (!key) return;
      if (!silent) this.loading = true;
      if (!this.threads[key]) this.threads[key] = [];
      try {
        const res = await fetch(`<?= site_url('api/messages') ?>/${key}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        const prevLen = this.threads[key].length;
        this.threads[key] = json.data || [];
        if (key === this.activeCompanyKey) {
          // Dynamic poll interval: 5s when work is pending, 30s when idle
          this.pollInterval = json.pollIn || (json.pending ? 5000 : 30000);
          this.isTyping = json.pending === true;
          if (json.pending) {
            const synced = new Set(
              (json.data || []).filter(m => m.role === 'ceo' && m.task?.synced).map(m => m.issue_id).filter(Boolean)
            );
            const pm = [...(json.data || [])].reverse().find(m => m.role === 'boss' && m.issue_id && !synced.has(m.issue_id));
            this.pendingIssueId = pm?.issue_id || null;
          } else {
            this.pendingIssueId = null;
          }
        }
        this.online = true;
        this.lastSync = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        if (!silent || this.threads[key].length !== prevLen) {
          this.$nextTick(() => this.scrollToBottom());
        }
      } catch (e) {
        this.online = false;
        console.error('loadThread failed:', e);
      } finally {
        this.loading = false;
      }
    },

    async selectCompany(key) {
      this.activeCompanyKey = key;
      await this.loadThread(key);
    },

    autoResize(e) {
      e.target.style.height = 'auto';
      e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
    },

    async send() {
      const text = this.draft.trim();
      if (!text || this.sending) return;
      this.sending = true;
      this.isTyping = true;
      if (!this.threads[this.activeCompanyKey]) this.threads[this.activeCompanyKey] = [];

      try {
        const res = await fetch(`<?= site_url('api/messages') ?>/${this.activeCompanyKey}`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ text, priority: this.priority }),
        });
        const json = await res.json();

        if (!res.ok) {
          this.isTyping = false;
          this.threads[this.activeCompanyKey].push({
            id: 'err-' + Date.now(), role: 'system',
            text: 'Delivery failed: ' + (json.error || 'unknown error'),
            created_at: new Date().toISOString(),
          });
        } else {
          if (json.bossMsg) {
            this.threads[this.activeCompanyKey].push(json.bossMsg);
            if (json.bossMsg.issue_id) this.pendingIssueId = json.bossMsg.issue_id;
          }
        }

        this.draft = '';
        this.$nextTick(() => {
          const ta = document.querySelector('textarea');
          if (ta) ta.style.height = 'auto';
          this.scrollToBottom();
        });
      } catch (e) {
        this.online = false;
        console.error('send failed:', e);
        this.isTyping = false;
      } finally {
        this.sending = false;
      }
    },

    async cancelTask(issueId) {
      if (this.cancelling || !issueId) return;
      if (!confirm('Cancel this task in Paperclip?')) return;
      this.cancelling = true;
      try {
        const res = await fetch(`<?= site_url('api/cancel') ?>/${this.activeCompanyKey}/${issueId}`, {
          method: 'POST', credentials: 'same-origin',
        });
        const json = await res.json();
        if (res.ok) {
          if (json.sysMsg) this.threads[this.activeCompanyKey].push(json.sysMsg);
          this.isTyping = false;
          this.pendingIssueId = null;
          this.$nextTick(() => this.scrollToBottom());
        } else {
          const detail = json.detail?.message || json.detail?.error || json.detail?.raw || JSON.stringify(json.detail || {});
          alert('Cancel failed (HTTP ' + json.paperclipStatus + '): ' + detail);
        }
      } catch (e) {
        console.error('cancelTask failed:', e);
      } finally {
        this.cancelling = false;
      }
    },

    renderMarkdown(text) {
      if (!text) return '';
      const resolved = text.replace(/\]\((\/)([^)]+)\)/g, `](${PAPERCLIP_BASE_URL}/$2)`);
      let html = marked.parse(resolved, { breaks: true, gfm: true });
      html = html.replace(/<a /g, '<a target="_blank" rel="noopener noreferrer" ');
      return html;
    },

    formatBytes(bytes) {
      if (!bytes) return '';
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / 1048576).toFixed(1) + ' MB';
    },

    formatTime(iso) {
      if (!iso) return '';
      try {
        return new Date(iso.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      } catch (_) { return ''; }
    },

    scrollToBottom() {
      const el = this.$refs.chatArea;
      if (el) el.scrollTop = el.scrollHeight;
    },
  };
}
</script>
</body>
</html>
