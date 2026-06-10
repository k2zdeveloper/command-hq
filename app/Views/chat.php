<?php /** @var array $agent */ ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#010912">
<title><?= esc($agent['name']) ?> · Command HQ</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --cyan:  #00c8f0;
    --surf:  rgba(0,10,22,0.90);
    --surf2: rgba(0,18,36,0.70);
    --bdr:   rgba(0,190,255,0.10);
    --bdr-h: rgba(0,190,255,0.38);
    --text:  #b4d4ee;
    --muted: #375870;
    color-scheme: dark;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;}
body {
    font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
    background:
        radial-gradient(ellipse 900px 500px at   0% -10%, rgba(0, 90,200,.13) 0%,transparent 55%),
        radial-gradient(ellipse 800px 500px at 100%  110%, rgba(80,  0,200,.10) 0%,transparent 50%),
        #010912;
    color:var(--text);
    display:flex; flex-direction:column;
}
body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background:url('/logos/bg.png') center center / cover no-repeat fixed;
    opacity:.10;}
body::before {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background-image:
        radial-gradient(circle, rgba(0,185,255,.10) 1px,transparent 1px),
        linear-gradient(rgba(0,185,255,.028) 1px,transparent 1px),
        linear-gradient(90deg, rgba(0,185,255,.028) 1px,transparent 1px);
    background-size:40px 40px;
}
body::after {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='98'%3E%3Cpath d='M28 1l27 15.6v31.2L28 63.4 1 47.8V16.6z' fill='none' stroke='%2300c8f0' stroke-width='0.7' stroke-opacity='0.04'/%3E%3Cpath d='M28 63.4l27 15.6V110L28 126l-27-15.6V79z' fill='none' stroke='%2300c8f0' stroke-width='0.7' stroke-opacity='0.04'/%3E%3C/svg%3E");
    background-size:56px 98px;
}
header,.body-wrap,.composer,.skills-panel,#skill-modal { position:relative; z-index:1; }

.mono{font-family:'JetBrains Mono',monospace;}
.gtxt{background:linear-gradient(90deg,#00c8f0,#0070ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.gtxt-v{background:linear-gradient(90deg,#a070ff,#6030ef);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hex-clip{clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);}

.glass  {background:var(--surf); backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--bdr);}
.glass2 {background:var(--surf2);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--bdr);}

/* Chat bubbles */
.b-user {
    background:linear-gradient(135deg,rgba(0,200,240,.15),rgba(0,200,240,.07));
    border:1px solid rgba(0,200,240,.22);
    border-radius:16px 16px 4px 16px;
}
.b-bot {
    background:rgba(0,15,30,.75);
    border:1px solid rgba(0,190,255,.10);
    border-radius:16px 16px 16px 4px;
}
/* Typing */
.typing span{display:inline-block;width:5px;height:5px;border-radius:9999px;background:#50788a;margin:0 1.5px;animation:tb 1.2s ease-in-out infinite;}
.typing span:nth-child(2){animation-delay:.15s}.typing span:nth-child(3){animation-delay:.30s}
@keyframes tb{0%,80%,100%{transform:translateY(0);opacity:.3}40%{transform:translateY(-5px);opacity:1}}

/* Skills panel */
.skills-panel{width:268px;flex-shrink:0;display:flex;flex-direction:column;}
@media(max-width:767px){
    .skills-panel{position:fixed;right:0;top:0;bottom:0;width:min(84vw,310px);z-index:40;transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);}
    .skills-panel.open{transform:translateX(0);}
    .sk-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;backdrop-filter:blur(4px);}
    .sk-overlay.open{display:block;}
}

/* Skill item */
.sk-item{background:rgba(0,14,28,.6);border:1px solid rgba(0,190,255,.09);border-radius:12px;transition:border-color .15s;}
.sk-item:hover{border-color:rgba(112,64,239,.3);}

/* Button */
.btn-send{background:linear-gradient(135deg,#00c8f0,#0060ef);color:#020e1c;font-weight:600;border-radius:12px;transition:opacity .15s;border:none;}
.btn-send:disabled{opacity:.35;}
.btn-send:not(:disabled):hover{opacity:.85;}
.btn-add{border:1px dashed rgba(112,64,239,.35);color:#a070ff;border-radius:12px;transition:all .15s;background:transparent;}
.btn-add:hover{border-color:rgba(112,64,239,.6);background:rgba(112,64,239,.06);}

/* Logo */
.logo{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#00c8f0,#0055ff,#7040ef);display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* Modal */
#skill-modal{position:fixed;inset:0;z-index:60;display:none;align-items:center;justify-content:center;padding:16px;}
#skill-modal.open{display:flex;}
#mbkg{position:absolute;inset:0;background:rgba(1,9,18,.75);backdrop-filter:blur(8px);}
#mbox{position:relative;width:100%;max-width:430px;max-height:84vh;display:flex;flex-direction:column;border-radius:20px;overflow:hidden;}

/* Header top-accent */
.h-accent::before{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#00c8f0 35%,#0055ff 65%,transparent);}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:rgba(0,190,255,.15);border-radius:4px;}

/* ── Entrance animations ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideRight{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:translateX(0)}}
header{opacity:0;animation:slideDown .5s cubic-bezier(.22,1,.36,1) forwards;}
.body-wrap{opacity:0;animation:fadeUp .55s cubic-bezier(.22,1,.36,1) .1s forwards;}
#sk-panel{opacity:0;animation:slideRight .5s cubic-bezier(.22,1,.36,1) .15s forwards;}
.composer{opacity:0;animation:fadeUp .45s cubic-bezier(.22,1,.36,1) .2s forwards;}
</style>
</head>
<body>

<!-- ══ SVG SYMBOLS ══ -->
<svg hidden aria-hidden="true"><defs>
<symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></symbol>
<symbol id="i-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
  <rect x="7" y="4.5" width="10" height="7" rx="1.5"/>
  <rect x="4" y="11.5" width="16" height="8" rx="2"/>
  <line x1="12" y1="2" x2="12" y2="4.5"/>
  <circle cx="12" cy="1.5" r="1" fill="currentColor" stroke="none"/>
  <circle cx="9.5" cy="8" r="1.2" fill="currentColor" stroke="none"/>
  <circle cx="14.5" cy="8" r="1.2" fill="currentColor" stroke="none"/>
  <path d="M9 16h6M4 15H2M22 15h-2"/>
</symbol>
<symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4.5 13H12L10.5 22L20 11H13L13 2Z"/></symbol>
<symbol id="i-chip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
  <rect x="7" y="7" width="10" height="10" rx="1.5"/>
  <path d="M9 7V4M12 7V4M15 7V4M9 20v-3M12 20v-3M15 20v-3M7 9H4M7 12H4M7 15H4M20 9h-3M20 12h-3M20 15h-3"/>
  <rect x="10" y="10" width="4" height="4" fill="currentColor" stroke="none"/>
</symbol>
<symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></symbol>
<symbol id="i-pause" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></symbol>
<symbol id="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3l14 9-14 9V3z"/></symbol>
<symbol id="i-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4.5 13H12L10.5 22L20 11H13L13 2Z"/></symbol>
</defs></svg>

<!-- ══ HEADER ══ -->
<header class="glass flex-shrink-0 relative h-accent" style="border-bottom:1px solid rgba(0,190,255,.07);">
<div class="px-4 sm:px-5 h-14 flex items-center gap-3">
    <a href="/" class="p-1.5 rounded-lg transition-colors hover:bg-white/5 flex-shrink-0" style="color:var(--muted);text-decoration:none;">
        <svg width="18" height="18"><use href="#i-back"/></svg>
    </a>
    <div class="mono text-[9px] tracking-[.18em] uppercase hidden sm:block" style="color:rgba(0,190,255,.25);">Command HQ /</div>
    <!-- Agent hex icon -->
    <div class="hex-clip flex-shrink-0" style="width:30px;height:34px;background:linear-gradient(135deg,rgba(0,200,240,.22),rgba(0,100,255,.16));display:flex;align-items:center;justify-content:center;">
        <svg width="14" height="14" style="color:#00c8f0;"><use href="#i-bot"/></svg>
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-semibold leading-tight truncate text-sm" style="color:#d0e8ff;"><?= esc($agent['name']) ?></div>
        <div class="mono text-[9px] tracking-widest uppercase truncate gtxt"><?= esc($agent['role_title']) ?></div>
    </div>
    <div class="hidden sm:block mono text-[9px] flex-shrink-0" style="color:var(--muted);"><?= esc($agent['model'] ?? '') ?></div>
    <!-- Heartbeat status pill -->
    <div id="hb-pill" class="hidden sm:flex items-center gap-1.5 px-2 py-1 rounded-lg flex-shrink-0"
        style="background:rgba(0,14,28,.6);border:1px solid rgba(0,190,255,.09);">
        <div id="hb-dot" style="width:6px;height:6px;border-radius:9999px;flex-shrink:0;transition:background .3s;"></div>
        <span id="hb-label" class="mono text-[9px] uppercase tracking-widest" style="min-width:36px;"></span>
    </div>
    <!-- Heartbeat controls -->
    <button id="hb-toggle-btn" onclick="toggleHB()" title="Pause / Resume agent"
        class="hidden sm:flex items-center p-1.5 rounded-lg transition-colors hover:bg-white/5 flex-shrink-0"
        style="color:var(--muted);">
        <svg id="hb-icon" width="14" height="14"><use id="hb-icon-use" href="#i-pause"/></svg>
    </button>
    <button onclick="triggerHB()" title="Trigger heartbeat"
        class="hidden sm:flex items-center p-1.5 rounded-lg transition-colors hover:bg-white/5 flex-shrink-0"
        style="color:var(--muted);"
        onmouseenter="this.style.color='#00c8f0'" onmouseleave="this.style.color='var(--muted)'">
        <svg width="14" height="14"><use href="#i-zap"/></svg>
    </button>
    <!-- Mobile skills toggle -->
    <button id="sk-toggle" onclick="togglePanel()" title="Skills" class="md:hidden p-1.5 rounded-lg transition-colors hover:bg-white/5 flex-shrink-0" style="color:var(--muted);">
        <svg width="17" height="17"><use href="#i-bolt"/></svg>
    </button>
</div>
</header>

<!-- ══ BODY ══ -->
<div class="body-wrap flex flex-1 overflow-hidden" style="min-height:0;">

    <!-- ── CHAT COLUMN ── -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <div id="messages" class="flex-1 overflow-y-auto">
            <div id="msgs" class="max-w-2xl mx-auto px-4 py-5 flex flex-col gap-3">
                <div id="loader" class="text-center text-xs py-4 mono" style="color:var(--muted);">// Loading conversation…</div>
            </div>
        </div>

        <!-- Composer -->
        <div class="composer glass flex-shrink-0 px-3 py-3" style="border-top:1px solid rgba(0,190,255,.07);padding-bottom:max(.75rem,env(safe-area-inset-bottom));">
            <!-- Image preview strip -->
            <div id="img-preview-wrap" style="display:none;" class="max-w-2xl mx-auto mb-2">
                <div class="flex items-center gap-3 px-3 py-2 rounded-xl" style="background:rgba(0,20,40,.7);border:1px solid rgba(0,200,240,.2);">
                    <img id="img-thumb" src="" alt="" style="height:44px;width:auto;max-width:72px;border-radius:7px;object-fit:cover;border:1px solid rgba(0,190,255,.2);">
                    <div class="flex-1 min-w-0">
                        <div id="img-fname" class="text-xs truncate" style="color:#c8e0f0;"></div>
                        <div id="img-fsize" class="mono text-[9px] mt-0.5" style="color:var(--muted);"></div>
                    </div>
                    <button type="button" onclick="clearImage()" title="Remove image"
                        style="color:rgba(248,113,113,.6);background:none;border:none;cursor:pointer;padding:4px 6px;font-size:15px;flex-shrink:0;transition:color .15s;"
                        onmouseenter="this.style.color='#f87171'" onmouseleave="this.style.color='rgba(248,113,113,.6)'">✕</button>
                </div>
            </div>
            <form id="frm" class="max-w-2xl mx-auto flex items-end gap-2">
                <input type="file" id="img-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <button type="button" id="img-btn" onclick="document.getElementById('img-input').click()" title="Attach image (or paste from clipboard)"
                    class="flex-shrink-0 rounded-xl transition-all"
                    style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:rgba(0,10,22,.8);border:1px solid rgba(0,190,255,.12);color:var(--muted);"
                    onmouseenter="if(!window.imgAttached){this.style.borderColor='rgba(0,190,255,.35)';this.style.color='#00c8f0';}"
                    onmouseleave="if(!window.imgAttached){this.style.borderColor='rgba(0,190,255,.12)';this.style.color='var(--muted)';}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                    </svg>
                </button>
                <textarea id="inp" rows="1"
                    placeholder="Message <?= esc($agent['name']) ?>… (or paste an image)"
                    class="flex-1 resize-none rounded-xl px-4 py-2.5 text-sm focus:outline-none max-h-40"
                    style="background:rgba(0,10,22,.8);border:1px solid rgba(0,190,255,.12);color:#c8e0f0;transition:border-color .15s;"
                    onfocus="this.style.borderColor='rgba(0,200,240,.35)'"
                    onblur="this.style.borderColor='rgba(0,190,255,.12)'"></textarea>
                <button id="snd" type="submit" class="btn-send px-4 py-2.5 text-sm flex items-center gap-1.5 flex-shrink-0">
                    <span>Send</span>
                    <svg width="12" height="12"><use href="#i-send"/></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- ── SKILLS PANEL ── -->
    <aside id="sk-panel" class="skills-panel glass" style="border-left:1px solid rgba(0,190,255,.07);">
        <div class="px-4 py-3 flex items-center justify-between flex-shrink-0" style="border-bottom:1px solid rgba(0,190,255,.07);">
            <div class="flex items-center gap-2">
                <svg width="13" height="13" style="color:#a070ff;"><use href="#i-bolt"/></svg>
                <span class="mono text-[9px] tracking-[.2em] uppercase font-medium" style="color:#c8d8e8;">Skills</span>
            </div>
            <div class="flex items-center gap-2">
                <span id="sk-count" class="mono text-[9px] px-1.5 py-0.5 rounded" style="color:var(--muted);background:rgba(0,15,30,.6);border:1px solid rgba(0,190,255,.08);">0</span>
                <button class="md:hidden p-1 rounded" style="color:var(--muted);" onclick="togglePanel()">
                    <svg width="14" height="14"><use href="#i-x"/></svg>
                </button>
            </div>
        </div>

        <div id="sk-list" class="flex-1 overflow-y-auto p-3 space-y-2">
            <div id="sk-loader" class="text-center py-6 mono text-xs" style="color:var(--muted);">// Loading skills…</div>
        </div>

        <div class="p-3 flex-shrink-0" style="border-top:1px solid rgba(0,190,255,.07);">
            <button onclick="openModal()" class="btn-add w-full py-2.5 text-xs mono font-medium flex items-center justify-center gap-1.5">
                <svg width="13" height="13"><use href="#i-plus"/></svg>
                Add Skill
            </button>
        </div>
    </aside>
</div>

<!-- Mobile overlay -->
<div id="sk-overlay" class="sk-overlay" onclick="togglePanel()"></div>

<!-- ══ ADD SKILL MODAL ══ -->
<div id="skill-modal">
    <div id="mbkg" onclick="closeModal()"></div>
    <div id="mbox" class="glass">
        <div class="px-5 py-4 flex items-start justify-between gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(0,190,255,.07);">
            <div>
                <div class="mono text-[9px] tracking-[.2em] uppercase mb-1 gtxt-v">Assign to <?= esc($agent['name']) ?></div>
                <h3 class="font-semibold text-base" style="color:#d0e8ff;">Add Skill</h3>
            </div>
            <button onclick="closeModal()" class="p-1.5 rounded-lg hover:bg-white/5 flex-shrink-0" style="color:var(--muted);">
                <svg width="15" height="15"><use href="#i-x"/></svg>
            </button>
        </div>
        <div class="px-4 py-3 flex-shrink-0" style="border-bottom:1px solid rgba(0,190,255,.07);">
            <div class="flex items-center gap-2 rounded-lg px-3 py-2" style="background:rgba(0,10,22,.8);border:1px solid rgba(0,190,255,.12);">
                <svg width="13" height="13" style="color:var(--muted);flex-shrink:0;"><use href="#i-search"/></svg>
                <input id="sk-search" type="text" placeholder="Search skills…"
                    class="flex-1 text-sm bg-transparent focus:outline-none"
                    style="color:#c8e0f0;" oninput="filterAll(this.value)">
            </div>
        </div>
        <div id="all-sk" class="overflow-y-auto p-3 space-y-2" style="max-height:52vh;">
            <div class="text-center py-4 mono text-xs" style="color:var(--muted);">// Loading…</div>
        </div>
    </div>
</div>

<script>
const SLUG    = <?= json_encode($agent['slug']) ?>;
const ANAME   = <?= json_encode($agent['name']) ?>;
const AGENT_ACTIVE_INIT = <?= json_encode((bool)($agent['is_active'] ?? true)) ?>;

const msgsEl = document.getElementById('messages');
const innerEl = document.getElementById('msgs');
const loaderEl = document.getElementById('loader');
const inp = document.getElementById('inp');
const snd = document.getElementById('snd');
const frm = document.getElementById('frm');

// ── helpers ──────────────────────────────────────────────────────────────
function h(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function md(t) {
    return h(t)
        .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
        .replace(/`([^`]+)`/g,'<code style="background:rgba(0,20,40,.8);border-radius:4px;padding:1px 6px;font-family:\'JetBrains Mono\',monospace;font-size:.8em;color:#00c8f0;">$1</code>')
        .replace(/\n{2,}/g,'</p><p style="margin-bottom:.35rem;">')
        .replace(/\n/g,'<br>');
}
function bubble(role, text, turnId=null) {
    const isU = role === 'user';
    const w = document.createElement('div');
    w.className += ' chat-bubble';
    if(turnId) w.dataset.turnId = turnId;
    const delU = `<button onclick="deleteTurnBubble(this)" title="Delete" style="opacity:0;color:rgba(0,200,240,.45);background:none;border:none;cursor:pointer;font-size:15px;line-height:1;padding:3px 5px;transition:opacity .15s,color .15s;flex-shrink:0;" onmouseenter="this.style.color='#f87171'" onmouseleave="this.style.color='rgba(0,200,240,.45)'">✕</button>`;
    const delB = `<button onclick="deleteTurnBubble(this)" title="Delete" style="color:rgba(0,200,240,.35);background:rgba(0,10,22,.5);border:1px solid rgba(0,200,240,.12);border-radius:4px;cursor:pointer;font-size:9px;font-family:'JetBrains Mono',monospace;letter-spacing:.08em;text-transform:uppercase;padding:2px 7px;transition:all .15s;" onmouseenter="this.style.color='#f87171';this.style.borderColor='rgba(248,113,113,.3)'" onmouseleave="this.style.color='rgba(0,200,240,.35)';this.style.borderColor='rgba(0,200,240,.12)'">✕ Del</button>`;
    if(isU){
        w.className='flex justify-end items-start gap-1 chat-bubble';
        w.innerHTML=`${delU}<div class="b-user px-4 py-3 text-sm leading-relaxed" style="max-width:87%;"
            onmouseenter="this.previousElementSibling.style.opacity='1'"
            onmouseleave="this.previousElementSibling.style.opacity='0'"><p style="margin-bottom:.35rem;">${md(text)}</p></div>`;
    } else {
        w.className='flex justify-start flex-col gap-1 chat-bubble';
        w.innerHTML=`<div class="b-bot px-4 py-3 text-sm leading-relaxed" style="max-width:87%;"><p style="margin-bottom:.35rem;">${md(text)}</p></div>
        <div style="padding-left:.25rem;">${delB}</div>`;
    }
    innerEl.appendChild(w);
    msgsEl.scrollTop = msgsEl.scrollHeight;
    return w;
}

async function deleteTurnBubble(btn){
    const wrap = btn.closest('.chat-bubble');
    if(!wrap) return;
    const turnId = wrap.dataset.turnId;
    btn.disabled = true;
    wrap.style.transition = 'opacity .2s';
    wrap.style.opacity = '.35';
    if(turnId){
        try{
            const r = await fetch('/api/chat/delete-turn',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:turnId})});
            const d = await r.json();
            if(!d.ok){ wrap.style.opacity='1'; btn.disabled=false; return; }
        }catch(_){ wrap.style.opacity='1'; btn.disabled=false; return; }
    }
    setTimeout(()=>wrap.remove(), 200);
}
function typing() {
    const w = document.createElement('div');
    w.id = 'typing-row'; w.className = 'flex justify-start';
    w.innerHTML = `<div class="b-bot px-4 py-3 text-sm"><div class="typing"><span></span><span></span><span></span></div></div>`;
    innerEl.appendChild(w);
    msgsEl.scrollTop = msgsEl.scrollHeight;
    return w;
}

// ── textarea auto-grow ────────────────────────────────────────────────────
inp.addEventListener('input',()=>{ inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,160)+'px'; });
inp.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();frm.requestSubmit();} });

// ── image upload & paste ──────────────────────────────────────────────────
window.imgAttached = null; // {base64, mime, name, dataUrl}
const imgInput = document.getElementById('img-input');
const imgBtn   = document.getElementById('img-btn');

function setImgAttached(file) {
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) { alert('Image too large — max 4 MB.'); return; }
    const validTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!validTypes.includes(file.type)) { alert('Unsupported format. Use JPEG, PNG, GIF, or WebP.'); return; }
    const reader = new FileReader();
    reader.onload = e => {
        const dataUrl = e.target.result;
        window.imgAttached = { base64: dataUrl.split(',')[1], mime: file.type, name: file.name, dataUrl };
        document.getElementById('img-thumb').src = dataUrl;
        document.getElementById('img-fname').textContent = file.name || 'Pasted image';
        document.getElementById('img-fsize').textContent = (file.size / 1024).toFixed(0) + ' KB';
        document.getElementById('img-preview-wrap').style.display = '';
        imgBtn.style.borderColor = 'rgba(0,200,240,.5)';
        imgBtn.style.color = '#00c8f0';
    };
    reader.readAsDataURL(file);
}

imgInput.addEventListener('change', () => { setImgAttached(imgInput.files[0]); imgInput.value=''; });

// Paste image from clipboard (e.g. screenshot Ctrl+V into the textarea)
inp.addEventListener('paste', e => {
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            e.preventDefault();
            setImgAttached(item.getAsFile());
            return;
        }
    }
});

function clearImage() {
    window.imgAttached = null;
    document.getElementById('img-thumb').src = '';
    document.getElementById('img-preview-wrap').style.display = 'none';
    imgBtn.style.borderColor = 'rgba(0,190,255,.12)';
    imgBtn.style.color = 'var(--muted)';
}

// ── load history ─────────────────────────────────────────────────────────
(async()=>{
    try {
        const r = await fetch(`/api/history/${encodeURIComponent(SLUG)}`);
        const d = await r.json();
        loaderEl.remove();
        if(d.ok && d.turns?.length) d.turns.forEach(t=>bubble(t.role,t.content,t.id));
        else bubble('assistant',`Hi — I'm ${ANAME}. How can I help?`);
    } catch(_){ loaderEl.textContent='// Could not load history.'; }
})();

// ── send ─────────────────────────────────────────────────────────────────
frm.addEventListener('submit', async e => {
    e.preventDefault();
    const txt = inp.value.trim();
    if (!txt && !window.imgAttached) return;
    snd.disabled = true; inp.value = ''; inp.style.height = 'auto';

    // Show user bubble
    const displayTxt = txt || '📷 (image)';
    const userWrap = bubble('user', displayTxt);

    // Append image thumbnail inside the user bubble
    if (window.imgAttached) {
        const imgEl = document.createElement('img');
        imgEl.src = window.imgAttached.dataUrl;
        imgEl.style.cssText = 'max-width:200px;max-height:150px;border-radius:10px;object-fit:cover;border:1px solid rgba(0,190,255,.2);margin-top:6px;display:block;';
        const bub = userWrap.querySelector('.b-user');
        if (bub) bub.appendChild(imgEl);
    }

    const payload = { slug: SLUG, message: txt || 'Please analyze this image.' };
    if (window.imgAttached) { payload.imageData = window.imgAttached.base64; payload.imageMime = window.imgAttached.mime; }
    clearImage();

    const t = typing();
    try {
        const r = await fetch('/api/chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const d = await r.json(); t.remove();
        const aWrap = bubble('assistant', d.ok ? d.reply : '⚠ '+(d.error||'Error.'));
        if(d.ok && d.turn_ids){
            if(d.turn_ids.user)      userWrap.dataset.turnId = d.turn_ids.user;
            if(d.turn_ids.assistant) aWrap.dataset.turnId    = d.turn_ids.assistant;
        }
    } catch(_){ t.remove(); bubble('assistant','⚠ Network error.'); }
    finally { snd.disabled=false; inp.focus(); }
});

// ── skills panel toggle (mobile) ─────────────────────────────────────────
function togglePanel(){
    document.getElementById('sk-panel').classList.toggle('open');
    document.getElementById('sk-overlay').classList.toggle('open');
}

// ── skills state ─────────────────────────────────────────────────────────
let assignedIds = new Set();
let allSkills   = [];

// ── load assigned skills ─────────────────────────────────────────────────
async function loadSkills(){
    try {
        const r = await fetch(`/api/skills/${encodeURIComponent(SLUG)}`);
        const d = await r.json();
        renderAssigned(d.ok ? (d.skills||[]) : []);
    } catch(_){ document.getElementById('sk-list').innerHTML='<div class="text-center py-4 mono text-xs" style="color:var(--muted);">// Load failed.</div>'; }
}

function renderAssigned(rows){
    const el = document.getElementById('sk-list');
    const ct = document.getElementById('sk-count');
    assignedIds = new Set(rows.map(r=>r.skill?.id).filter(Boolean));
    ct.textContent = rows.length;
    if(!rows.length){
        el.innerHTML=`<div class="text-center py-8" style="color:var(--muted);">
            <svg width="28" height="28" class="mx-auto mb-2" style="color:rgba(112,64,239,.4);"><use href="#i-bolt"/></svg>
            <div class="mono text-xs">No skills assigned.</div>
            <div class="mono text-[10px] mt-1" style="color:#1e3448;">Add skills to enhance this agent.</div>
        </div>`;
        return;
    }
    el.innerHTML = rows.map(row=>{
        const s = row.skill||{};
        return `<div class="sk-item p-3 space-y-1.5" data-id="${h(s.id)}">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg width="11" height="11" style="color:#a070ff;flex-shrink:0;"><use href="#i-bolt"/></svg>
                    <span class="text-sm font-medium truncate" style="color:#c8e0f0;">${h(s.name||'Unknown')}</span>
                </div>
                <button onclick="removeSkill('${h(s.id)}')" class="text-xs transition-colors flex-shrink-0" style="color:var(--muted);" onmouseenter="this.style.color='#ef4444'" onmouseleave="this.style.color='var(--muted)'" title="Remove">✕</button>
            </div>
            <div class="mono text-[9px] uppercase tracking-widest" style="color:var(--muted);">${h(s.skill_type||'')}</div>
            ${s.payload?.context?`<div class="text-[11px] leading-snug" style="color:#50708a;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${h(s.payload.context)}</div>`:''}
            <div class="flex items-center gap-1.5">
                <div style="width:5px;height:5px;border-radius:9999px;background:${s.is_active?'#00e896':'#2a3e52'};flex-shrink:0;"></div>
                <span class="mono text-[9px]" style="color:var(--muted);">${s.is_active?'Active':'Inactive'}</span>
                <span class="mono text-[9px] ml-auto" style="color:#1e3448;">p${row.priority??100}</span>
            </div>
        </div>`;
    }).join('');
}

async function removeSkill(id){
    const card=document.querySelector(`[data-id="${id}"]`);
    if(card) card.style.opacity='.4';
    try {
        const r=await fetch('/api/skills/remove',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,skill_id:id})});
        const d=await r.json();
        if(d.ok){ loadSkills(); if(allSkills.length) renderAll(allSkills); }
        else if(card) card.style.opacity='1';
    } catch(_){ if(card) card.style.opacity='1'; }
}

// ── modal ─────────────────────────────────────────────────────────────────
async function openModal(){
    document.getElementById('skill-modal').classList.add('open');
    document.getElementById('sk-search').value='';
    if(!allSkills.length){
        try{const r=await fetch('/api/all-skills');const d=await r.json();allSkills=d.ok?(d.skills||[]):[];}
        catch(_){allSkills=[];}
    }
    renderAll(allSkills);
}
function closeModal(){ document.getElementById('skill-modal').classList.remove('open'); }
function filterAll(q){ renderAll(allSkills.filter(s=>s.name.toLowerCase().includes(q.toLowerCase())||(s.skill_type||'').toLowerCase().includes(q.toLowerCase()))); }

function renderAll(skills){
    const el=document.getElementById('all-sk');
    if(!skills.length){el.innerHTML='<div class="text-center py-5 mono text-xs" style="color:var(--muted);">// No skills found.</div>';return;}
    el.innerHTML=skills.map(s=>{
        const got=assignedIds.has(s.id);
        return `<div class="sk-item p-3 flex items-start gap-3">
            <div class="flex-1 min-w-0 space-y-0.5">
                <div class="flex items-center gap-1.5"><svg width="11" height="11" style="color:#a070ff;flex-shrink:0;"><use href="#i-bolt"/></svg><span class="text-sm font-medium truncate" style="color:#c8e0f0;">${h(s.name)}</span></div>
                <div class="mono text-[9px] uppercase tracking-widest" style="color:var(--muted);">${h(s.skill_type||'')}</div>
                ${s.payload?.context?`<div class="text-[11px] leading-snug" style="color:#50708a;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${h(s.payload.context)}</div>`:''}
            </div>
            <button onclick="assign('${h(s.id)}')" ${got?'disabled':''} class="flex-shrink-0 mono text-[10px] px-2.5 py-1 rounded-lg transition-colors"
                style="${got?'background:rgba(0,232,150,.1);color:#00e896;border:1px solid rgba(0,232,150,.2);cursor:default;':'background:rgba(112,64,239,.1);color:#a070ff;border:1px solid rgba(112,64,239,.2);'}">
                ${got?'✓ Added':'+ Add'}
            </button>
        </div>`;
    }).join('');
}

async function assign(id){
    try {
        const r=await fetch('/api/skills/assign',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:SLUG,skill_id:id})});
        const d=await r.json();
        if(d.ok){ assignedIds.add(id); filterAll(document.getElementById('sk-search').value); loadSkills(); }
    } catch(_){}
}

loadSkills();

// ── heartbeat ─────────────────────────────────────────────────────────────
let hbActive = AGENT_ACTIVE_INIT;

function renderHBPill() {
    const dot     = document.getElementById('hb-dot');
    const label   = document.getElementById('hb-label');
    const iconUse = document.getElementById('hb-icon-use');
    const btn     = document.getElementById('hb-toggle-btn');
    if (hbActive) {
        dot.style.background   = '#00e896';
        dot.style.boxShadow    = '0 0 6px #00e89680';
        label.textContent      = 'Active';
        label.style.color      = '#00e896';
        iconUse.setAttribute('href', '#i-pause');
        btn.title              = 'Pause agent';
        btn.onmouseenter       = () => btn.style.color = '#f59e0b';
        btn.onmouseleave       = () => btn.style.color = 'var(--muted)';
    } else {
        dot.style.background   = '#f59e0b';
        dot.style.boxShadow    = '0 0 6px #f59e0b60';
        label.textContent      = 'Paused';
        label.style.color      = '#f59e0b';
        iconUse.setAttribute('href', '#i-play');
        btn.title              = 'Resume agent';
        btn.onmouseenter       = () => btn.style.color = '#00e896';
        btn.onmouseleave       = () => btn.style.color = 'var(--muted)';
    }
}

function toggleHB() {
    hbActive = !hbActive;
    renderHBPill();
    const status = hbActive ? 'RESUMED' : 'PAUSED';
    bubble('assistant', `// Heartbeat ${status}. Agent is now ${hbActive ? 'active' : 'paused'}.`);
}

async function triggerHB() {
    if (!hbActive) {
        bubble('assistant', '// Agent is paused. Resume first to trigger heartbeat.');
        return;
    }
    const t = typing();
    try {
        const r = await fetch('/api/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: SLUG, message: 'HEARTBEAT: Provide a brief status update on your current tasks, priorities, and any blockers.' })
        });
        const d = await r.json();
        t.remove();
        bubble('assistant', d.ok ? d.reply : '⚠ ' + (d.error || 'Error.'));
    } catch (_) { t.remove(); bubble('assistant', '⚠ Network error.'); }
}

renderHBPill();
</script>
</body>
</html>
