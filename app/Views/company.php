<?php
/**
 * @var array      $company   id, name, description, mock
 * @var array      $agents    [{id,slug,name,role_title,parent_id,is_active,system_prompt,temperature,model}]
 * @var array|null $root      root agent row
 * @var array      $chairman  name, title, initials
 */
$isMock   = $company['mock'] ?? false;
$rootSlug = $root ? $root['slug'] : null;
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b0a08">
<title><?= esc($company['name']) ?> · Command HQ</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
    --c:#e8b454;--cb:#ffcf6e;--cd:#b8842c;--v:#c89050;--g:#3dd68c;--a:#e8a838;
    --bg:#0b0a08;
    --s:rgba(24,22,17,.92);--s2:rgba(32,29,22,.72);--s3:rgba(16,14,11,.96);
    --b:rgba(235,225,200,.08);--t:#ece6da;--t2:#b6ad9a;--m:#80786a;
    color-scheme:dark;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;}
body{
    font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
    background:radial-gradient(ellipse 1100px 600px at 0% -5%,rgba(232,180,84,.07) 0%,transparent 52%),
        radial-gradient(ellipse 900px 500px at 100% 110%,rgba(150,120,60,.05) 0%,transparent 50%),#0b0a08;
    color:var(--t);display:flex;flex-direction:column;
}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background-image:
        linear-gradient(rgba(235,225,200,.02) 1px,transparent 1px),
        linear-gradient(90deg,rgba(235,225,200,.02) 1px,transparent 1px);
    background-size:44px 44px;}
.circuit-bg{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.5;}
.gear{position:fixed;z-index:0;pointer-events:none;color:var(--c);opacity:.05;}
.gear svg{display:block;animation:gspin 26s linear infinite;}
.gear.rev svg{animation:gspin 34s linear infinite reverse;}
@keyframes gspin{to{transform:rotate(360deg);}}
header,.app-body,.modal-wrap{position:relative;z-index:1;}

.mono{font-family:'JetBrains Mono',monospace;}
.glass{background:var(--s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--b);}
.glass3{background:var(--s3);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(235,225,200,.12);}
.hex{clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);}
.gt{background:linear-gradient(90deg,#e8b454,#b8842c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.gtg{background:linear-gradient(90deg,#3dd68c,#1f9e62);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:8px;font-size:.75rem;font-family:'JetBrains Mono',monospace;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;border:none;transition:all .15s;}
.btn-cyan{background:linear-gradient(135deg,#e8b454,#b8842c);color:#1a1206;font-weight:600;}
.btn-cyan:hover{opacity:.85;}.btn-cyan:disabled{opacity:.35;cursor:not-allowed;}
.btn-ghost{background:transparent;border:1px solid rgba(235,225,200,.2);color:var(--c);}
.btn-ghost:hover{background:rgba(232,180,84,.07);}
.btn-violet{background:rgba(200,144,80,.15);border:1px solid rgba(200,144,80,.25);color:#e0a850;}
.btn-violet:hover{background:rgba(200,144,80,.25);}
.btn-amber{background:rgba(240,160,0,.12);border:1px solid rgba(240,160,0,.2);color:#f0c040;}
.btn-amber:hover{background:rgba(240,160,0,.22);}
.btn-sm{padding:.3rem .7rem;font-size:10px;}.btn-xs{padding:.2rem .5rem;font-size:9px;}

.inp{background:rgba(16,14,11,.8);border:1px solid rgba(235,225,200,.14);color:#ece6da;border-radius:10px;padding:.5rem .75rem;font-size:.875rem;width:100%;transition:border-color .15s;font-family:'Inter',sans-serif;}
.inp:focus{outline:none;border-color:rgba(232,180,84,.38);}
.inp::placeholder{color:var(--m);}
select.inp option{background:#1a1206;}
textarea.inp{resize:vertical;min-height:70px;}

.h-accent::before{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#e8b454 30%,#b8842c 55%,#b8842c 75%,transparent);}

/* Status */
.sdot{width:7px;height:7px;border-radius:9999px;flex-shrink:0;}
.sdot.active{background:#3dd68c;box-shadow:0 0 6px rgba(61,214,140,.6);}
.sdot.working{background:#3dd68c;animation:spl 1.4s ease-in-out infinite;}
.sdot.paused{background:#f0a000;box-shadow:0 0 4px rgba(240,160,0,.4);}
.sdot.inactive{background:#3a352a;}
@keyframes spl{0%{box-shadow:0 0 0 0 rgba(61,214,140,.7);}70%{box-shadow:0 0 0 7px rgba(61,214,140,0);}100%{box-shadow:0 0 0 0 rgba(61,214,140,0);}}

.sbadge{display:inline-flex;align-items:center;gap:4px;padding:.15rem .5rem;border-radius:9999px;font-family:'JetBrains Mono',monospace;font-size:8px;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap;}
.sbadge.working{background:rgba(61,214,140,.08);border:1px solid rgba(61,214,140,.25);color:#3dd68c;}
.sbadge.done{background:rgba(47,184,120,.1);border:1px solid rgba(47,184,120,.25);color:#2fb878;}
.sbadge.review{background:rgba(240,160,0,.08);border:1px solid rgba(240,160,0,.25);color:#f0a000;}
.sbadge.error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#f87171;}
.sbadge.idle{background:rgba(90,82,64,.12);border:1px solid rgba(90,82,64,.25);color:var(--m);}

/* Chat bubbles */
.b-user{background:linear-gradient(135deg,rgba(232,180,84,.16),rgba(232,180,84,.08));border:1px solid rgba(232,180,84,.24);border-radius:16px 16px 4px 16px;}
.b-bot{background:rgba(18,16,12,.78);border:1px solid rgba(235,225,200,.10);border-radius:16px 16px 16px 4px;}
.b-delegate{background:linear-gradient(135deg,rgba(232,180,84,.06),rgba(200,144,80,.06));border:1px solid rgba(235,225,200,.18);border-left:2.5px solid var(--c);border-radius:0 12px 12px 0;}

/* Typing */
.typing span{display:inline-block;width:5px;height:5px;border-radius:9999px;background:#8a7850;margin:0 1.5px;animation:tb 1.2s ease-in-out infinite;}
.typing span:nth-child(2){animation-delay:.15s}.typing span:nth-child(3){animation-delay:.30s}
@keyframes tb{0%,80%,100%{transform:translateY(0);opacity:.3}40%{transform:translateY(-5px);opacity:1}}

/* Tabs */
.tab-btn{position:relative;padding:.5rem 1rem;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--m);transition:color .15s;background:none;border:none;cursor:pointer;white-space:nowrap;}
.tab-btn.active{color:var(--c);}
.tab-btn.active::after{content:'';position:absolute;bottom:0;left:.5rem;right:.5rem;height:1.5px;background:var(--c);border-radius:2px;}
.tab-btn:hover:not(.active){color:var(--t);}

/* Modal */
.modal-wrap{position:fixed;inset:0;z-index:60;display:none;align-items:flex-start;justify-content:center;padding:20px 16px;overflow-y:auto;}
.modal-wrap.open{display:flex;}
.modal-bkg{position:fixed;inset:0;background:rgba(8,7,5,.82);backdrop-filter:blur(8px);}
.modal-box{position:relative;width:100%;max-width:540px;border-radius:20px;overflow:hidden;margin:auto;}

input[type=range]{-webkit-appearance:none;width:100%;height:3px;border-radius:9999px;background:rgba(235,225,200,.15);outline:none;cursor:pointer;}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:14px;height:14px;border-radius:9999px;background:var(--c);cursor:pointer;box-shadow:0 0 6px rgba(232,180,84,.5);}
.toggle{position:relative;width:36px;height:20px;flex-shrink:0;}
.toggle input{opacity:0;width:0;height:0;}
.toggle-track{position:absolute;inset:0;border-radius:9999px;background:rgba(235,225,200,.1);border:1px solid rgba(235,225,200,.2);cursor:pointer;transition:background .2s;}
.toggle input:checked+.toggle-track{background:rgba(61,214,140,.2);border-color:rgba(61,214,140,.4);}
.toggle-thumb{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:9999px;background:#80786a;transition:transform .2s,background .2s;}
.toggle input:checked~.toggle-thumb{transform:translateX(16px);background:#3dd68c;}

/* ── NEW LAYOUT ── */
.app-body{display:flex;flex:1;min-height:0;overflow:hidden;}

/* Sidebar */
.sidebar{width:196px;flex-shrink:0;display:flex;flex-direction:column;border-right:1px solid rgba(235,225,200,.07);background:rgba(12,10,7,.65);}
.nav-item{display:flex;align-items:center;gap:10px;padding:.48rem .75rem;border-radius:8px;cursor:pointer;font-size:.775rem;color:var(--m);transition:all .15s;border:none;background:none;width:100%;text-align:left;}
.nav-item:hover{background:rgba(235,225,200,.06);color:var(--t);}
.nav-item.active{background:rgba(232,180,84,.09);color:var(--c);}
.nav-item.active svg{opacity:1;}
.nav-item svg{flex-shrink:0;opacity:.6;}
.nav-item:hover svg,.nav-item.active svg{opacity:1;}

/* Sections */
.section{display:none;flex:1;min-height:0;}
.section.active{display:flex;flex-direction:column;}

/* Dashboard */
.metric-card{padding:1rem;border-radius:12px;border:1px solid rgba(235,225,200,.08);background:rgba(16,14,11,.55);transition:border-color .2s,background .2s;cursor:pointer;}
.metric-card:hover{border-color:rgba(235,225,200,.18);background:rgba(22,20,15,.65);}
.metric-val{font-size:1.75rem;font-weight:700;font-family:'JetBrains Mono',monospace;color:#ece6da;line-height:1;}
.metric-lbl{font-size:.68rem;font-family:'JetBrains Mono',monospace;letter-spacing:.14em;text-transform:uppercase;color:var(--m);margin-top:.4rem;}
.metric-desc{font-size:.7rem;color:rgba(200,190,160,.45);margin-top:.2rem;}

/* Issue + inbox rows */
.issue-row{display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem;border-bottom:1px solid rgba(235,225,200,.05);transition:background .15s;cursor:pointer;}
.issue-row:hover{background:rgba(235,225,200,.04);}
.issue-row:last-child{border-bottom:none;}
.inbox-row{display:flex;gap:.75rem;padding:.7rem 1rem;border-bottom:1px solid rgba(235,225,200,.05);transition:background .15s;cursor:pointer;position:relative;}
.inbox-row:hover{background:rgba(235,225,200,.04);}
.inbox-row.unread{background:rgba(235,225,200,.025);}
.inbox-row.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--c);}

/* Agent grid */
.agent-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:.6rem;}
.a-card{padding:.85rem;border-radius:12px;border:1px solid rgba(235,225,200,.08);background:rgba(16,14,11,.55);cursor:pointer;transition:all .18s;position:relative;overflow:hidden;}
.a-card:hover{border-color:rgba(235,225,200,.22);background:rgba(22,20,15,.7);}
.a-card.selected{border-color:rgba(232,180,84,.38);background:rgba(28,25,19,.72);box-shadow:0 0 22px -6px rgba(232,180,84,.14);}
.a-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(232,180,84,.18),transparent);}
.a-card.is-working{border-color:rgba(61,214,140,.22);}
.a-card.is-working::before{background:linear-gradient(90deg,transparent,rgba(61,214,140,.3),transparent);}

/* Agent detail panel */
.detail-panel{width:400px;flex-shrink:0;border-left:1px solid rgba(235,225,200,.07);display:flex;flex-direction:column;overflow:hidden;}

/* Section label */
.sl{display:flex;align-items:center;gap:8px;font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.22em;text-transform:uppercase;color:var(--m);}
.sl::before{content:'//';color:rgba(232,180,84,.3);margin-right:2px;}
.sl::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,rgba(235,225,200,.18),transparent);}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:rgba(235,225,200,.15);border-radius:4px;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}

/* ── Top-down org chart (flowchart) ── */
.orgchart{text-align:center;min-width:max-content;padding:8px 4px 16px;}
.orgchart ul{display:flex;justify-content:center;padding-top:24px;position:relative;list-style:none;margin:0;}
.orgchart li{list-style:none;position:relative;padding:24px 12px 0;}
.orgchart li::before,.orgchart li::after{content:'';position:absolute;top:0;right:50%;border-top:1.5px solid rgba(232,180,84,.28);width:50%;height:24px;}
.orgchart li::after{right:auto;left:50%;border-left:1.5px solid rgba(232,180,84,.28);}
.orgchart li:only-child::after,.orgchart li:only-child::before{display:none;}
.orgchart li:only-child{padding-top:24px;}
.orgchart li:first-child::before,.orgchart li:last-child::after{border:0 none;}
.orgchart li:last-child::before{border-right:1.5px solid rgba(232,180,84,.28);border-radius:0 7px 0 0;}
.orgchart li:first-child::after{border-radius:7px 0 0 0;}
.orgchart ul ul::before{content:'';position:absolute;top:0;left:50%;border-left:1.5px solid rgba(232,180,84,.28);width:0;height:24px;}
.orgchart > ul{padding-top:0;}
.orgchart > ul > li{padding-top:0;}
.orgchart li.collapsed > ul{display:none;}
.ocard{position:relative;display:inline-flex;flex-direction:column;align-items:center;gap:5px;min-width:128px;max-width:180px;padding:.8rem .85rem .7rem;border-radius:14px;border:1px solid rgba(235,225,200,.08);background:rgba(24,22,17,.7);cursor:pointer;transition:border-color .15s,transform .15s,box-shadow .15s;vertical-align:top;}
.ocard::before{content:'';position:absolute;top:0;left:18px;right:18px;height:2px;border-radius:2px;background:linear-gradient(90deg,transparent,#e8b454,transparent);opacity:.5;}
.ocard:hover{border-color:rgba(232,180,84,.32);transform:translateY(-2px);box-shadow:0 8px 26px -10px rgba(232,180,84,.25);}
.ocard.is-root{border-color:rgba(232,180,84,.4);background:linear-gradient(160deg,rgba(232,180,84,.13),rgba(24,22,17,.85));}
.ocard .oav{display:grid;place-items:center;font-weight:700;flex-shrink:0;}
.ocard .onm{font-size:.8rem;font-weight:600;color:#ece6da;line-height:1.2;text-align:center;}
.ocard .orl{font-size:.62rem;font-family:'JetBrains Mono',monospace;color:var(--m);text-align:center;line-height:1.2;}
.octog{display:inline-flex;align-items:center;gap:4px;margin-top:3px;padding:2px 9px;border-radius:99px;font-family:'JetBrains Mono',monospace;font-size:9px;background:rgba(232,180,84,.1);border:1px solid rgba(232,180,84,.25);color:#e8b454;cursor:pointer;transition:all .15s;}
.octog:hover{background:rgba(232,180,84,.2);}
.octog .chev{transition:transform .2s;display:inline-block;}
li.collapsed > .ocard .octog .chev{transform:rotate(-90deg);}

/* ── Mobile responsiveness ── */
.mobile-menu-btn{display:none;}
.sidebar-backdrop{display:none;}
@media (max-width: 860px){
    .mobile-menu-btn{display:inline-flex;}
    .sidebar{
        position:fixed;top:0;left:0;bottom:0;z-index:80;width:248px;
        transform:translateX(-100%);transition:transform .25s ease;
        box-shadow:10px 0 50px rgba(0,0,0,.6);
    }
    .sidebar.open{transform:translateX(0);}
    .sidebar-backdrop{position:fixed;inset:0;z-index:75;background:rgba(0,0,0,.6);backdrop-filter:blur(2px);}
    .sidebar-backdrop.open{display:block;}
    .detail-panel{position:fixed!important;inset:0;width:100%!important;z-index:70;}
    .modal-box .grid{grid-template-columns:1fr!important;}
    #new-task-panel .grid{grid-template-columns:1fr!important;}
    #dtp-instructions .grid{grid-template-columns:1fr!important;}
    .modal-box{max-width:100%!important;}
}
@media (max-width: 560px){
    header .h-12{height:auto;padding-top:.4rem;padding-bottom:.4rem;}
    #s-dashboard .grid-cols-2{grid-template-columns:1fr 1fr;}
    .agent-grid{grid-template-columns:1fr 1fr;}
    #new-task-panel{padding-left:.75rem;padding-right:.75rem;}
}
@media (max-width: 380px){
    .agent-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ══ CIRCUIT BOARD BACKGROUND ══ -->
<svg class="circuit-bg" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <pattern id="pcb" width="180" height="180" patternUnits="userSpaceOnUse">
      <g stroke="#e8b454" stroke-opacity="0.06" stroke-width="1" fill="none" stroke-linecap="round">
        <path d="M0 34 H44 M44 34 V78 M44 78 H96"/>
        <path d="M180 56 H132 V120 H78"/>
        <path d="M92 0 V40 M92 40 L122 70"/>
        <path d="M22 180 V134 H66"/>
        <path d="M150 180 V146 H180"/>
        <path d="M0 150 H30 V120"/>
      </g>
      <g fill="#e8b454" fill-opacity="0.10">
        <circle cx="44" cy="34" r="2.4"/><circle cx="96" cy="78" r="2.4"/>
        <circle cx="132" cy="56" r="2.4"/><circle cx="78" cy="120" r="2.4"/>
        <circle cx="92" cy="40" r="2.4"/><circle cx="122" cy="70" r="2.4"/>
        <circle cx="66" cy="134" r="2.4"/><circle cx="150" cy="146" r="2.4"/>
        <circle cx="30" cy="120" r="2.4"/>
      </g>
      <g stroke="#e8b454" stroke-opacity="0.04" fill="none">
        <rect x="106" y="22" width="16" height="9" rx="1"/>
        <rect x="14" y="92" width="9" height="16" rx="1"/>
      </g>
      <g stroke="#e8b454" stroke-opacity="0.05" fill="none">
        <path d="M148 86 l14 8.1 v16.2 l-14 8.1 l-14 -8.1 v-16.2 z"/>
        <path d="M34 30 l9 5.2 v10.4 l-9 5.2 l-9 -5.2 v-10.4 z"/>
      </g>
    </pattern>
  </defs>
  <rect width="100%" height="100%" fill="url(#pcb)"/>
</svg>

<!-- ══ ROTATING GEARS (decorative) ══ -->
<div class="gear" style="top:-28px;right:6%;">
  <svg width="140" height="140" viewBox="-14 -14 28 28"><path fill="currentColor" d="M0 -9 L2 -9 L2.5 -6 L4.5 -5 L7 -6.5 L8.5 -5 L7 -2.5 L8 -0.5 L11 0 L11 2 L8 2.5 L7 4.5 L8.5 7 L7 8.5 L4.5 7 L2.5 8 L2 11 L0 11 L-0.5 8 L-2.5 7 L-5 8.5 L-6.5 7 L-5 4.5 L-6 2.5 L-9 2 L-9 0 L-6 -0.5 L-5 -2.5 L-6.5 -5 L-5 -6.5 L-2.5 -5 L-0.5 -6 Z"/><circle cx="1" cy="1" r="3.2" fill="var(--bg)"/></svg>
</div>
<div class="gear rev" style="bottom:4%;right:10%;">
  <svg width="190" height="190" viewBox="-14 -14 28 28"><path fill="currentColor" d="M0 -9 L2 -9 L2.5 -6 L4.5 -5 L7 -6.5 L8.5 -5 L7 -2.5 L8 -0.5 L11 0 L11 2 L8 2.5 L7 4.5 L8.5 7 L7 8.5 L4.5 7 L2.5 8 L2 11 L0 11 L-0.5 8 L-2.5 7 L-5 8.5 L-6.5 7 L-5 4.5 L-6 2.5 L-9 2 L-9 0 L-6 -0.5 L-5 -2.5 L-6.5 -5 L-5 -6.5 L-2.5 -5 L-0.5 -6 Z"/><circle cx="1" cy="1" r="3.2" fill="var(--bg)"/></svg>
</div>

<!-- ══ SVG SYMBOLS ══ -->
<svg hidden aria-hidden="true"><defs>
<symbol id="i-brain" viewBox="0 0 48 48" fill="none"><path d="M24 3 L42 13.5 V34.5 L24 45 L6 34.5 V13.5 Z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M24 14 C19 13, 15 15.5, 14.5 19.5 C11.5 20.5, 10.5 24.5, 13.5 26.5 C11.5 30.5, 15 33.5, 19 31.5 C20 34.5, 24 34.5, 24 31" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round"/><path d="M24 14 C29 13, 33 15.5, 33.5 19.5 C36.5 20.5, 37.5 24.5, 34.5 26.5 C36.5 30.5, 33 33.5, 29 31.5 C28 34.5, 24 34.5, 24 31" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round"/><rect x="20" y="20" width="8" height="8" rx="1" fill="currentColor"/><path d="M22 20 V17 M26 20 V17 M22 28 V31 M26 28 V31 M20 22 H17 M20 26 H17 M28 22 H31 M28 26 H31" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></symbol>
<symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></symbol>
<symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></symbol>
<symbol id="i-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></symbol>
<symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4.5 13H12L10.5 22L20 11H13L13 2Z"/></symbol>
<symbol id="i-pause" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></symbol>
<symbol id="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3l14 9-14 9V3z"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></symbol>
<symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
<symbol id="i-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
<symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></symbol>
<symbol id="i-nodes" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><circle cx="4" cy="20" r="2"/><circle cx="20" cy="20" r="2"/><path d="M12 6v4m0 4l-6 4.5M12 10l6 4.5"/></symbol>
<symbol id="i-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="4.5" width="10" height="7" rx="1.5"/><rect x="4" y="11.5" width="16" height="8" rx="2"/><line x1="12" y1="2" x2="12" y2="4.5"/><circle cx="12" cy="1.5" r="1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="8" r="1.2" fill="currentColor" stroke="none"/><circle cx="14.5" cy="8" r="1.2" fill="currentColor" stroke="none"/><path d="M9 16h6M4 15H2M22 15h-2"/></symbol>
<symbol id="i-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
<symbol id="i-inbox" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></symbol>
<symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></symbol>
<symbol id="i-tasks" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></symbol>
</defs></svg>

<!-- ════════ HIRE AGENT MODAL ════════ -->
<div id="hire-modal" class="modal-wrap" onclick="if(event.target===this)closeHireModal()">
    <div class="modal-bkg" onclick="closeHireModal()"></div>
    <div class="modal-box glass3">
        <div class="px-5 py-4 flex items-center justify-between h-accent relative" style="border-bottom:1px solid rgba(235,225,200,.08);">
            <div>
                <div class="mono text-[9px] tracking-[.22em] uppercase mb-0.5" style="color:var(--m);">// New Hire</div>
                <div class="font-semibold gt" style="font-size:1rem;">Hire an Agent</div>
            </div>
            <button onclick="closeHireModal()" class="p-1.5 rounded-lg hover:bg-white/5" style="color:var(--m);"><svg width="15" height="15"><use href="#i-x"/></svg></button>
        </div>
        <div class="p-5 space-y-4" style="max-height:75vh;overflow-y:auto;">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Agent Name *</label>
                    <input id="hire-name" type="text" class="inp" placeholder="e.g. Sales Agent" style="font-size:.82rem;">
                </div>
                <div>
                    <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Role Title *</label>
                    <input id="hire-role" type="text" class="inp" placeholder="e.g. Sales Manager" style="font-size:.82rem;">
                </div>
            </div>
            <div>
                <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Reports To</label>
                <select id="hire-parent" class="inp" style="font-size:.82rem;"><option value="">— No parent (standalone) —</option></select>
            </div>
            <div>
                <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">System Prompt</label>
                <textarea id="hire-prompt" class="inp" style="min-height:90px;font-size:.8rem;line-height:1.55;" placeholder="Describe this agent's role, responsibilities, and behavior…"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Model</label>
                    <select id="hire-model" class="inp" style="font-size:.8rem;">
                        <option value="claude-haiku-4-5-20251001">Haiku 4.5 · Fast</option>
                        <option value="claude-sonnet-4-6" selected>Sonnet 4.6 · Balanced</option>
                        <option value="claude-opus-4-7">Opus 4.7 · Powerful</option>
                    </select>
                </div>
                <div>
                    <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Temperature · <span id="hire-temp-val">0.70</span></label>
                    <input type="range" id="hire-temp" min="0" max="1" step="0.05" value="0.7" oninput="document.getElementById('hire-temp-val').textContent=parseFloat(this.value).toFixed(2)">
                </div>
            </div>
            <div>
                <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Assign Skills</label>
                <div id="hire-skills-list" class="space-y-1.5 max-h-32 overflow-y-auto p-2 rounded-xl" style="background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.08);">
                    <div class="mono text-[9px] text-center py-2" style="color:var(--m);">Loading skills…</div>
                </div>
            </div>
            <div id="hire-error" class="hidden mono text-xs text-center py-2 rounded-lg" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;"></div>
            <div id="hire-success" class="hidden mono text-xs text-center py-2 rounded-lg" style="background:rgba(61,214,140,.08);border:1px solid rgba(61,214,140,.2);color:#3dd68c;"></div>
            <div class="flex justify-end gap-2">
                <button onclick="closeHireModal()" class="btn btn-ghost btn-sm">Cancel</button>
                <button id="hire-submit" onclick="submitHireAgent()" class="btn btn-cyan btn-sm"><svg width="11" height="11"><use href="#i-plus"/></svg>Hire Agent</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════ HEADER ════════ -->
<header class="glass flex-shrink-0 relative h-accent" style="border-bottom:1px solid rgba(235,225,200,.07);">
<div class="px-4 h-12 flex items-center gap-3">
    <button onclick="toggleSidebar()" class="mobile-menu-btn p-1.5 rounded-lg hover:bg-white/5 flex-shrink-0 items-center justify-center" style="color:var(--c);border:none;background:none;cursor:pointer;">
        <svg width="20" height="20"><use href="#i-menu"/></svg>
    </button>
    <a href="/" class="p-1.5 rounded-lg hover:bg-white/5 flex-shrink-0" style="color:var(--m);text-decoration:none;">
        <svg width="16" height="16"><use href="#i-back"/></svg>
    </a>
    <div class="flex-shrink-0 grid place-items-center rounded-lg" style="width:30px;height:30px;background:radial-gradient(circle at 50% 38%,rgba(232,180,84,.18),rgba(16,14,11,.6));border:1px solid rgba(232,180,84,.28);">
        <svg width="19" height="19" style="color:var(--c);"><use href="#i-brain"/></svg>
    </div>
    <div class="mono text-[9px] tracking-[.18em] uppercase hidden sm:block" style="color:rgba(235,225,200,.22);">Command HQ /</div>
    <div class="hex flex-shrink-0 grid place-items-center font-bold text-sm" style="width:28px;height:32px;background:linear-gradient(135deg,rgba(232,180,84,.22),rgba(200,144,80,.16));color:#e8b454;">
        <?= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company['name']), 0, 2)) ?>
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-semibold text-sm leading-tight truncate gt"><?= esc($company['name']) ?></div>
        <div class="flex items-center gap-2 mt-0.5">
            <?php if ($isMock): ?>
            <span class="mono text-[8px] tracking-widest uppercase px-1.5 py-0.5 rounded" style="background:rgba(240,160,0,.07);border:1px solid rgba(240,160,0,.18);color:#f0c040;">Demo</span>
            <?php else: ?>
            <span class="mono text-[8px] tracking-widest uppercase px-1.5 py-0.5 rounded" style="background:rgba(61,214,140,.07);border:1px solid rgba(61,214,140,.18);color:#3dd68c;">Live</span>
            <?php endif; ?>
            <span class="mono text-[9px] hidden sm:block" style="color:var(--m);"><?= count($agents) ?> agents<?= $root ? ' · '.esc($root['name']).' directing' : '' ?></span>
        </div>
    </div>
    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg flex-shrink-0" style="background:rgba(22,20,15,.6);border:1px solid rgba(235,225,200,.09);">
        <div class="hex grid place-items-center font-bold text-[10px]" style="width:18px;height:21px;background:linear-gradient(135deg,rgba(232,180,84,.22),rgba(200,144,80,.18));color:#e8b454;"><?= esc($chairman['initials']) ?></div>
        <div>
            <div class="mono text-[9px] font-medium" style="color:#ddd0b4;"><?= esc($chairman['name']) ?></div>
            <div class="mono text-[8px]" style="color:var(--m);">Commanding</div>
        </div>
    </div>
</div>
</header>

<!-- ════════ APP BODY ════════ -->
<div class="app-body">

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<!-- ── SIDEBAR ── -->
<nav class="sidebar glass">
    <!-- Company info -->
    <div class="px-3 pt-4 pb-3 flex-shrink-0">
        <div class="flex items-center gap-2.5 px-1">
            <div class="hex grid place-items-center font-bold flex-shrink-0" style="width:28px;height:32px;font-size:.7rem;background:linear-gradient(135deg,rgba(232,180,84,.22),rgba(200,144,80,.16));color:#e8b454;">
                <?= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company['name']), 0, 2)) ?>
            </div>
            <div class="min-w-0">
                <div class="font-semibold truncate gt" style="font-size:.78rem;"><?= esc($company['name']) ?></div>
                <div class="mono text-[8px]" style="color:var(--m);"><?= count($agents) ?> agents</div>
            </div>
        </div>
    </div>
    <div class="mx-3 mb-2" style="height:1px;background:rgba(235,225,200,.07);"></div>

    <!-- Navigation -->
    <div class="flex-1 px-2 overflow-y-auto space-y-0.5">
        <button class="nav-item active" id="nav-chat" onclick="navTo('chat')">
            <svg width="15" height="15"><use href="#i-chat"/></svg>
            <span>Chat</span>
        </button>
        <button class="nav-item" id="nav-dashboard" onclick="navTo('dashboard')">
            <svg width="15" height="15"><use href="#i-grid"/></svg>
            <span>Dashboard</span>
        </button>
        <button class="nav-item" id="nav-inbox" onclick="navTo('inbox')">
            <svg width="15" height="15"><use href="#i-inbox"/></svg>
            <span>Inbox</span>
            <span id="inbox-badge" class="hidden ml-auto mono text-[8px] px-1.5 py-0.5 rounded-full" style="background:rgba(232,180,84,.12);color:var(--c);">0</span>
        </button>
        <button class="nav-item" id="nav-issues" onclick="navTo('issues')">
            <svg width="15" height="15"><use href="#i-tasks"/></svg>
            <span>Issues</span>
            <span id="issues-badge" class="hidden ml-auto mono text-[8px] px-1.5 py-0.5 rounded-full" style="background:rgba(61,214,140,.10);color:#3dd68c;">0</span>
        </button>
        <button class="nav-item" id="nav-agents" onclick="navTo('agents')">
            <svg width="15" height="15"><use href="#i-bot"/></svg>
            <span>Agents</span>
        </button>
        <button class="nav-item" id="nav-org" onclick="navTo('org')">
            <svg width="15" height="15"><use href="#i-nodes"/></svg>
            <span>Org Chart</span>
        </button>
        <button class="nav-item" id="nav-files" onclick="navTo('files')">
            <svg width="15" height="15"><use href="#i-download"/></svg>
            <span>Files</span>
        </button>
    </div>

    <!-- Hire -->
    <div class="flex-shrink-0 px-2 pb-3 pt-2" style="border-top:1px solid rgba(235,225,200,.06);">
        <button onclick="openHireModal()" class="nav-item w-full">
            <svg width="15" height="15"><use href="#i-plus"/></svg>
            <span>Hire Agent</span>
        </button>
    </div>
</nav>

<!-- ── CONTENT AREA ── -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

<!-- ══ SECTION: CHAT ══ -->
<div id="s-chat" class="section active">
    <!-- Channel header -->
    <div class="px-4 py-2 flex items-center justify-between flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.06);background:rgba(14,12,9,.4);">
        <div class="flex items-center gap-2">
            <svg width="11" height="11" style="color:rgba(232,180,84,.5);"><use href="#i-nodes"/></svg>
            <span class="mono text-[9px] tracking-[.2em] uppercase" style="color:var(--m);">Command Channel</span>
            <?php if ($isMock): ?>
            <span class="mono text-[9px]" style="color:#f0a000;">· Demo mode</span>
            <?php elseif ($root): ?>
            <span class="mono text-[9px]" style="color:var(--m);">· via <span class="gt"><?= esc($root['name']) ?></span></span>
            <?php endif; ?>
        </div>
        <div id="typing-status" class="hidden items-center gap-1.5 mono text-[9px]" style="color:var(--m);">
            <div class="sdot working" style="width:5px;height:5px;"></div>Processing…
        </div>
    </div>

    <!-- Messages -->
    <div id="messages" class="flex-1 overflow-y-auto">
        <div id="msgs" class="max-w-3xl mx-auto px-4 py-5 flex flex-col gap-3">
            <div id="loader" class="text-center mono text-xs py-4" style="color:var(--m);">// Connecting to command channel…</div>
        </div>
    </div>

    <!-- Composer -->
    <div class="flex-shrink-0 px-3 py-3" style="border-top:1px solid rgba(235,225,200,.07);background:rgba(12,10,8,.6);padding-bottom:max(.75rem,env(safe-area-inset-bottom));">
        <?php if ($isMock): ?>
        <div class="max-w-3xl mx-auto mono text-xs text-center py-3" style="color:#f0a000;">// Demo company — connect to Supabase to enable live commands.</div>
        <?php elseif (!$root): ?>
        <div class="max-w-3xl mx-auto mono text-xs text-center py-3" style="color:var(--m);">// No director agent configured.</div>
        <?php else: ?>
        <form id="frm" class="max-w-3xl mx-auto flex items-end gap-2">
            <textarea id="inp" rows="1"
                placeholder="Issue a command to <?= esc($company['name']) ?>…"
                class="flex-1 resize-none rounded-xl px-4 py-2.5 text-sm focus:outline-none max-h-36"
                style="background:rgba(16,14,11,.8);border:1px solid rgba(235,225,200,.12);color:#ece6da;transition:border-color .15s;"
                onfocus="this.style.borderColor='rgba(232,180,84,.35)'"
                onblur="this.style.borderColor='rgba(235,225,200,.12)'"></textarea>
            <button id="snd" type="submit" class="btn btn-cyan px-4 py-2.5 flex-shrink-0" style="font-size:.85rem;">
                <svg width="12" height="12"><use href="#i-send"/></svg>Send
            </button>
        </form>
        <?php endif; ?>
    </div>
</div><!-- /s-chat -->

<!-- ══ SECTION: DASHBOARD ══ -->
<div id="s-dashboard" class="section">
    <div class="flex-1 overflow-y-auto">
        <div class="p-5 max-w-5xl mx-auto w-full space-y-5">
            <!-- Page title -->
            <div>
                <h2 class="font-bold text-base" style="color:#ece6da;">Dashboard</h2>
                <p class="mono text-[10px] mt-0.5" style="color:var(--m);"><?= esc($company['name']) ?> · Overview</p>
            </div>

            <!-- Metric cards -->
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                <div class="metric-card" onclick="navTo('agents')">
                    <div class="metric-val gt" id="m-total">0</div>
                    <div class="metric-lbl">Total Agents</div>
                    <div class="metric-desc">deployed on platform</div>
                </div>
                <div class="metric-card" onclick="navTo('issues')">
                    <div class="metric-val" id="m-working" style="color:#3dd68c;">0</div>
                    <div class="metric-lbl">Active Tasks</div>
                    <div class="metric-desc">currently working</div>
                </div>
                <div class="metric-card" onclick="navTo('issues')">
                    <div class="metric-val" id="m-done" style="color:#2fb878;">0</div>
                    <div class="metric-lbl">Completed</div>
                    <div class="metric-desc">tasks done this session</div>
                </div>
                <div class="metric-card" onclick="navTo('inbox')">
                    <div class="metric-val" id="m-inbox" style="color:var(--c);">0</div>
                    <div class="metric-lbl">Inbox</div>
                    <div class="metric-desc">unread messages</div>
                </div>
            </div>

            <!-- Two columns -->
            <div class="grid md:grid-cols-2 gap-4">
                <!-- Recent Tasks -->
                <div>
                    <h3 class="text-sm font-semibold mb-3" style="color:var(--m);letter-spacing:.06em;text-transform:uppercase;font-size:.72rem;font-family:'JetBrains Mono',monospace;">// Recent Tasks</h3>
                    <div class="rounded-xl overflow-hidden" style="border:1px solid rgba(235,225,200,.08);">
                        <div id="dash-tasks" class="divide-y" style="border-color:rgba(235,225,200,.05);">
                            <div class="p-4 text-sm" style="color:var(--m);">No tasks issued yet.</div>
                        </div>
                    </div>
                </div>
                <!-- Active Agents -->
                <div>
                    <h3 class="text-sm font-semibold mb-3" style="color:var(--m);letter-spacing:.06em;text-transform:uppercase;font-size:.72rem;font-family:'JetBrains Mono',monospace;">// Active Agents</h3>
                    <div class="rounded-xl overflow-hidden" style="border:1px solid rgba(235,225,200,.08);">
                        <div id="dash-active" class="divide-y" style="border-color:rgba(235,225,200,.05);">
                            <div class="p-4 text-sm" style="color:var(--m);">// No agents currently working.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Agents summary -->
            <div>
                <h3 class="text-sm font-semibold mb-3" style="color:var(--m);letter-spacing:.06em;text-transform:uppercase;font-size:.72rem;font-family:'JetBrains Mono',monospace;">// Agent Roster</h3>
                <div class="rounded-xl overflow-hidden" style="border:1px solid rgba(235,225,200,.08);">
                    <div id="dash-roster" class="divide-y" style="border-color:rgba(235,225,200,.05);max-height:300px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div><!-- /s-dashboard -->

<!-- ══ SECTION: INBOX ══ -->
<div id="s-inbox" class="section">
    <!-- Inbox header -->
    <div class="px-4 py-3 flex items-center justify-between flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);background:rgba(14,12,9,.4);">
        <div>
            <h2 class="font-semibold text-sm" style="color:#ece6da;">Inbox</h2>
            <p class="mono text-[9px]" style="color:var(--m);">Assistant messages from your command sessions</p>
        </div>
        <button onclick="markAllInboxRead()" class="btn btn-ghost btn-sm" style="font-size:9px;">Mark All Read</button>
    </div>
    <!-- Inbox tabs -->
    <div class="flex flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);">
        <button class="tab-btn active" id="itab-all" onclick="switchInboxTab('all')">All</button>
        <button class="tab-btn" id="itab-unread" onclick="switchInboxTab('unread')">Unread <span id="itab-unread-count" class="ml-1 mono text-[8px] opacity-60"></span></button>
    </div>
    <!-- Inbox list -->
    <div class="flex-1 overflow-y-auto" id="inbox-list">
        <div class="p-8 text-center mono text-xs" style="color:var(--m);">// No messages yet. Send a command to start.</div>
    </div>
</div><!-- /s-inbox -->

<!-- ══ SECTION: ISSUES ══ -->
<div id="s-issues" class="section">
    <!-- Issues header -->
    <div class="px-4 py-3 flex items-center gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);background:rgba(14,12,9,.4);">
        <div class="flex-1">
            <h2 class="font-semibold text-sm" style="color:#ece6da;">Issues</h2>
            <p class="mono text-[9px]" style="color:var(--m);">Assigned tasks + session commands</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 flex-shrink-0" style="background:rgba(16,14,11,.7);border:1px solid rgba(235,225,200,.10);">
            <svg width="11" height="11" style="color:var(--m);flex-shrink:0;"><use href="#i-search"/></svg>
            <input id="issue-search" type="text" placeholder="Filter…" class="bg-transparent focus:outline-none mono text-[11px] w-28" style="color:#ece6da;" oninput="issueFilter=this.value;renderIssues()">
        </div>
        <button onclick="toggleNewTaskPanel()" id="nt-toggle-btn" class="btn btn-cyan btn-sm">
            <svg width="10" height="10"><use href="#i-plus"/></svg>New Task
        </button>
        <button onclick="clearTaskLog()" class="btn btn-ghost btn-sm" style="font-size:9px;">Clear Log</button>
    </div>

    <!-- New Task Panel (hidden by default) -->
    <div id="new-task-panel" class="hidden flex-shrink-0 px-4 py-4" style="border-bottom:1px solid rgba(235,225,200,.08);background:rgba(14,12,9,.6);">
        <div class="max-w-2xl space-y-3">
            <div class="mono text-[9px] tracking-[.18em] uppercase mb-1" style="color:var(--m);">// Assign a task — agent will process it autonomously</div>
            <div class="grid grid-cols-2 gap-3">
                <input id="nt-title" type="text" class="inp" placeholder="Task title *" style="font-size:.82rem;">
                <select id="nt-agent" class="inp" style="font-size:.82rem;">
                    <option value="">— Select Agent —</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?= esc($a['id']) ?>"><?= esc($a['name']) ?> · <?= esc($a['role_title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <textarea id="nt-desc" class="inp" style="min-height:64px;font-size:.8rem;line-height:1.55;" placeholder="Describe what the agent should produce (optional)…"></textarea>
            <div class="flex items-center justify-between gap-3">
                <select id="nt-priority" class="inp" style="font-size:.8rem;max-width:160px;">
                    <option value="low">Low Priority</option>
                    <option value="medium" selected>Medium Priority</option>
                    <option value="high">High Priority</option>
                    <option value="critical">Critical</option>
                </select>
                <div class="flex items-center gap-2">
                    <button onclick="toggleNewTaskPanel()" class="btn btn-ghost btn-sm">Cancel</button>
                    <button id="nt-submit" onclick="submitNewTask()" class="btn btn-cyan btn-sm">
                        <svg width="10" height="10"><use href="#i-plus"/></svg>Assign Task
                    </button>
                </div>
            </div>
            <div id="nt-msg" class="hidden mono text-xs px-3 py-2 rounded-lg"></div>
        </div>
    </div>

    <!-- Issues list -->
    <div class="flex-1 overflow-y-auto" id="issues-list">
        <div class="p-8 text-center mono text-xs" style="color:var(--m);">// Loading…</div>
    </div>
</div><!-- /s-issues -->

<!-- ══ SECTION: AGENTS ══ -->
<div id="s-agents" class="section" style="flex-direction:row;">

    <!-- Agent grid (left) -->
    <div class="flex flex-col flex-1 min-w-0">
        <!-- Agents header -->
        <div class="px-4 py-3 flex items-center gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);background:rgba(14,12,9,.4);">
            <div class="flex-1">
                <h2 class="font-semibold text-sm" style="color:#ece6da;">Agents</h2>
                <p class="mono text-[9px]" id="ag-count" style="color:var(--m);">0 agents</p>
            </div>
            <div class="flex items-center gap-2 rounded-lg px-2.5 py-1.5" style="background:rgba(16,14,11,.7);border:1px solid rgba(235,225,200,.10);">
                <svg width="11" height="11" style="color:var(--m);flex-shrink:0;"><use href="#i-search"/></svg>
                <input id="grid-search" type="text" placeholder="Filter agents…" class="bg-transparent focus:outline-none mono text-[11px] w-28" style="color:#ece6da;" oninput="gridFilter=this.value;renderAgentGrid()">
            </div>
            <button onclick="openHireModal()" class="btn btn-cyan btn-sm">
                <svg width="10" height="10"><use href="#i-plus"/></svg>Hire
            </button>
        </div>
        <!-- Grid -->
        <div class="flex-1 overflow-y-auto p-4">
            <div id="agent-grid" class="agent-grid"></div>
        </div>
    </div>

    <!-- Agent detail panel (right) -->
    <div id="det-panel" class="detail-panel" style="display:none;">
        <!-- Panel header -->
        <div class="px-4 py-3 flex items-center gap-3 flex-shrink-0 h-accent relative" style="border-bottom:1px solid rgba(235,225,200,.08);">
            <div class="hex grid place-items-center font-bold flex-shrink-0" id="det-hex" style="width:34px;height:39px;font-size:.75rem;background:linear-gradient(135deg,rgba(232,180,84,.22),rgba(200,144,80,.18));color:#e8b454;"></div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold gt truncate" id="det-name" style="font-size:.9rem;"></div>
                <div class="mono text-[9px] truncate" id="det-role" style="color:var(--m);"></div>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <span id="det-badge" class="sbadge idle">Idle</span>
                <button onclick="closeAgentDetail()" class="p-1.5 rounded-lg hover:bg-white/5" style="color:var(--m);">
                    <svg width="13" height="13"><use href="#i-x"/></svg>
                </button>
            </div>
        </div>
        <!-- Tab bar -->
        <div class="flex flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);">
            <button class="tab-btn active" id="dtab-overview" onclick="switchDetailTab('overview')">Overview</button>
            <button class="tab-btn" id="dtab-instructions" onclick="switchDetailTab('instructions')">Instructions</button>
            <button class="tab-btn" id="dtab-skills" onclick="switchDetailTab('skills')">Skills</button>
            <button class="tab-btn" id="dtab-runs" onclick="switchDetailTab('runs')">Runs</button>
        </div>
        <!-- Tab content -->
        <div class="flex-1 overflow-y-auto">
            <div id="dtp-overview"></div>
            <div id="dtp-instructions" class="hidden"></div>
            <div id="dtp-skills" class="hidden"></div>
            <div id="dtp-runs" class="hidden"></div>
        </div>
    </div>

</div><!-- /s-agents -->

<!-- ══ SECTION: ORG CHART ══ -->
<div id="s-org" class="section">
    <div class="px-4 py-3 flex items-center gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);background:rgba(12,10,8,.4);">
        <div class="flex-1">
            <h2 class="font-semibold text-sm" style="color:#ece6da;">Org Chart</h2>
            <p class="mono text-[9px]" style="color:var(--m);">Reporting hierarchy · click an agent to open it</p>
        </div>
        <span class="mono text-[9px]" id="org-count" style="color:var(--m);"></span>
    </div>
    <div class="flex-1 overflow-auto p-5">
        <div id="org-tree"></div>
    </div>
</div><!-- /s-org -->

<!-- ══ SECTION: FILES ══ -->
<div id="s-files" class="section">
    <div class="px-4 py-3 flex items-center gap-3 flex-shrink-0" style="border-bottom:1px solid rgba(235,225,200,.07);background:rgba(12,10,8,.4);">
        <div class="flex-1">
            <h2 class="font-semibold text-sm" style="color:#ece6da;">Files</h2>
            <p class="mono text-[9px]" style="color:var(--m);">Images & documents produced by your agents</p>
        </div>
        <button onclick="loadFiles()" class="btn btn-ghost btn-sm" style="font-size:9px;">Refresh</button>
        <span class="mono text-[9px]" id="files-count" style="color:var(--m);"></span>
    </div>
    <div class="flex-1 overflow-y-auto p-4" id="files-list">
        <div class="p-8 text-center mono text-xs" style="color:var(--m);">// Loading…</div>
    </div>
</div><!-- /s-files -->

</div><!-- /content area -->
</div><!-- /app-body -->

<script>
// ══ PHP DATA ══
const CO     = <?= json_encode(['id'=>$company['id'],'name'=>$company['name'],'mock'=>$isMock]) ?>;
const ROOT   = <?= $rootSlug ? json_encode(['id'=>$root['id'],'slug'=>$root['slug'],'name'=>$root['name']]) : 'null' ?>;
const IS_MOCK= <?= $isMock ? 'true' : 'false' ?>;
const AGENTS = <?= json_encode(array_values($agents)) ?>;

// ══ STATE ══
let currentSection   = 'chat';
let taskLog          = [];   // [{id,command,agentId,agentName,status,ts,output}] — localStorage
let dbTasks          = [];   // [{id,title,status,priority,agent_id,output,...}]  — Supabase
let inbox            = [];   // [{id,content,ts,read,agentName}]
let inboxTab         = 'all';
let issueFilter      = '';
let gridFilter       = '';
let selectedAgentId  = null;
let detailAgent      = null;
let detailTab        = 'overview';
let agSkills         = {};   // {agentId:[rows]}
let allSkills        = [];
let hbState          = {};   // {agentId:'active'|'paused'}
let tasks            = {};   // {agentId:{text,ts,status,output}}
let addSkillOpen     = false;
let hireSkillIds     = new Set();
let newTaskPanelOpen = false;
let issuesTimer      = null; // auto-refresh interval for issues section

AGENTS.forEach(a => { hbState[a.id] = a.is_active ? 'active' : 'paused'; });

// ══ PERSISTENCE ══
const LS_TASKS  = `mosbat_tasks_${CO.id}`;
const LS_INBOX  = `mosbat_inbox_${CO.id}`;
function loadState(){
    try{ const t=localStorage.getItem(LS_TASKS); if(t) taskLog=JSON.parse(t); }catch(_){}
    try{ const i=localStorage.getItem(LS_INBOX); if(i) inbox=JSON.parse(i); }catch(_){}
    // Restore runtime tasks map
    taskLog.forEach(t=>{ if(t.agentId) tasks[t.agentId]={text:t.command.substring(0,70),ts:t.ts,status:t.status,output:t.output}; });
}
function saveState(){
    try{ localStorage.setItem(LS_TASKS, JSON.stringify(taskLog.slice(-100))); }catch(_){}
    try{ localStorage.setItem(LS_INBOX, JSON.stringify(inbox.slice(-50))); }catch(_){}
}

// ══ HELPERS ══
function h(s){ return s==null?'':String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function initials(n){ return (n||'?').split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase(); }
function nowStr(){ return new Date().toLocaleTimeString('en-GB',{hour12:false}); }
function timeAgo(ts){ const d=new Date(ts||Date.now()); const s=Math.floor((Date.now()-d)/1000); if(s<60)return s+'s ago'; if(s<3600)return Math.floor(s/60)+'m ago'; return Math.floor(s/3600)+'h ago'; }

function extractField(block, name){
    // Multi-line field (value on next line)
    var ml = block.match(new RegExp('^'+name+':\\s*\\n([\\s\\S]*?)(?=\\n\\w[\\w\\s]*:|$)','im'));
    if(ml && ml[1].trim()) return ml[1].trim();
    // Single-line field
    var sl = block.match(new RegExp('^'+name+':\\s*(.+)$','im'));
    return sl ? sl[1].trim() : '';
}

function renderToolBlock(tag, content){
    tag = tag.toUpperCase();
    if(tag === 'SEND_EMAIL'){
        var to      = extractField(content,'to');
        var subject = extractField(content,'subject');
        var body    = extractField(content,'body');
        return '<div style="margin:.5rem 0;padding:.6rem .85rem;background:rgba(61,214,140,.06);border:1px solid rgba(61,214,140,.18);border-radius:10px;">'
            +'<div style="font-size:.7rem;font-family:\'JetBrains Mono\',monospace;color:rgba(61,214,140,.7);margin-bottom:.35rem;">📧 SEND EMAIL</div>'
            +'<div style="font-size:.8rem;color:#ece6da;"><strong style="color:#d0c7b0;">To:</strong> '+h(to)+'</div>'
            +'<div style="font-size:.8rem;color:#ece6da;"><strong style="color:#d0c7b0;">Subject:</strong> '+h(subject)+'</div>'
            +(body?'<div style="margin-top:.25rem;font-size:.75rem;color:#9a8f78;white-space:pre-wrap;">'+h(body.substring(0,120))+(body.length>120?'…':'')+'</div>':'')
            +'</div>';
    }
    if(tag === 'SEARCH'){
        var query = extractField(content,'query');
        return '<div style="margin:.5rem 0;padding:.5rem .75rem;background:rgba(235,225,200,.06);border:1px solid rgba(235,225,200,.15);border-radius:10px;">'
            +'<div style="font-size:.7rem;font-family:\'JetBrains Mono\',monospace;color:rgba(232,180,84,.7);margin-bottom:.2rem;">🔍 WEB SEARCH</div>'
            +'<div style="font-size:.8rem;color:#ece6da;">'+h(query)+'</div>'
            +'</div>';
    }
    if(tag === 'CREATE_TASK'){
        var agent   = extractField(content,'agent');
        var title   = extractField(content,'title');
        var priority= extractField(content,'priority');
        return '<div style="margin:.5rem 0;padding:.5rem .75rem;background:rgba(200,144,80,.06);border:1px solid rgba(200,144,80,.18);border-radius:10px;">'
            +'<div style="font-size:.7rem;font-family:\'JetBrains Mono\',monospace;color:rgba(224,168,80,.8);margin-bottom:.3rem;">📋 ASSIGN TASK</div>'
            +'<div style="font-size:.8rem;color:#ece6da;"><strong style="color:#d0c7b0;">To:</strong> '+h(agent||'Agent')+'</div>'
            +'<div style="font-size:.8rem;color:#ece6da;"><strong style="color:#d0c7b0;">Task:</strong> '+h(title)+'</div>'
            +(priority?'<div style="font-size:.75rem;color:#9a8f78;">Priority: '+h(priority)+'</div>':'')
            +'</div>';
    }
    if(tag === 'GENERATE_DOC'){
        var dtitle = extractField(content,'title');
        return '<div style="margin:.5rem 0;padding:.5rem .75rem;background:rgba(232,180,84,.06);border:1px solid rgba(232,180,84,.18);border-radius:10px;">'
            +'<div style="font-size:.7rem;font-family:\'JetBrains Mono\',monospace;color:rgba(232,180,84,.8);margin-bottom:.2rem;">📄 GENERATE DOCUMENT</div>'
            +'<div style="font-size:.8rem;color:#ece6da;">'+h(dtitle)+'</div></div>';
    }
    // Unknown tool — show as code block
    return '<div style="margin:.5rem 0;padding:.5rem .75rem;background:rgba(16,14,11,.7);border:1px solid rgba(235,225,200,.1);border-radius:8px;font-family:\'JetBrains Mono\',monospace;font-size:.75rem;color:rgba(232,180,84,.7);">['+tag+']</div>';
}

function md(raw){
    if(!raw) return '';
    // ── Render tool call blocks BEFORE HTML escaping ─────────────────────
    var processed = raw.replace(/\[(SEND_EMAIL|SEARCH|CREATE_TASK|GENERATE_DOC)\]([\s\S]*?)\[\/\1\]/gi, function(match, tag, content){
        return '\x00TOOL:'+tag+'\x01'+content+'\x02';
    });
    let s = h(processed);
    // Restore rendered tool cards
    s = s.replace(/\x00TOOL:(\w+)\x01([\s\S]*?)\x02/g, function(m, tag, content){
        return renderToolBlock(tag, content.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&').replace(/&quot;/g,'"'));
    });
    s = s.replace(/`([^`\n]+)`/g,'<code style="background:rgba(34,26,12,.85);border-radius:4px;padding:1px 6px;font-family:\'JetBrains Mono\',monospace;font-size:.78em;color:#e8b454;">$1</code>');
    s = s.replace(/^### (.+)$/gm,'<div style="font-size:.82rem;font-weight:700;color:#ece6da;margin:.55rem 0 .15rem;">$1</div>');
    s = s.replace(/^## (.+)$/gm,'<div style="font-size:.92rem;font-weight:700;color:#ffcf6e;margin:.65rem 0 .2rem;">$1</div>');
    s = s.replace(/^# (.+)$/gm,'<div style="font-size:1rem;font-weight:700;color:#e8b454;margin:.75rem 0 .25rem;">$1</div>');
    s = s.replace(/\*\*\*(.+?)\*\*\*/g,'<strong><em>$1</em></strong>');
    s = s.replace(/\*\*(.+?)\*\*/g,'<strong style="color:#ece6da;font-weight:600;">$1</strong>');
    s = s.replace(/_([^_\n]+)_/g,'<em style="color:#c8bfa8;">$1</em>');
    s = s.replace(/→ ASSIGNED TO:\s*([^|\n]+)\|\s*TASK:\s*([^|\n]+)\|\s*GOAL:\s*([^\n]+)/gi,
        '<div style="margin:.4rem 0;padding:.4rem .75rem;background:rgba(232,180,84,.06);border-left:2.5px solid #e8b454;border-radius:0 8px 8px 0;">'
        +'<div style="font-size:.72rem;font-family:\'JetBrains Mono\',monospace;color:rgba(232,180,84,.5);margin-bottom:2px;">→ ASSIGNED</div>'
        +'<div style="color:#e8b454;font-weight:600;font-size:.85rem;">$1</div>'
        +'<div style="color:#b6ad9a;font-size:.8rem;"><strong style="color:#d0c7b0;">Task:</strong> $2</div>'
        +'<div style="color:#b6ad9a;font-size:.8rem;"><strong style="color:#d0c7b0;">Goal:</strong> $3</div>'
        +'</div>');
    s = s.replace(/^→ (.+)$/gm,'<div style="margin:.2rem 0;padding:.2rem .6rem;border-left:2px solid rgba(232,180,84,.3);color:#b6ad9a;font-size:.85rem;">→ $1</div>');
    s = s.replace(/^[•\-\*] (.+)$/gm,'<div style="margin:.15rem 0 .15rem .25rem;display:flex;gap:.4rem;"><span style="color:#e8b454;flex-shrink:0;margin-top:.05em;">▸</span><span>$1</span></div>');
    s = s.replace(/^(\d+)\. (.+)$/gm,'<div style="margin:.15rem 0 .15rem .25rem;display:flex;gap:.4rem;"><span style="color:#e8b454;font-family:\'JetBrains Mono\',monospace;font-size:.78em;flex-shrink:0;margin-top:.15em;">$1.</span><span>$2</span></div>');
    s = s.replace(/^---+$/gm,'<hr style="border:none;border-top:1px solid rgba(235,225,200,.12);margin:.5rem 0;">');
    s = s.replace(/\n{2,}/g,'</p><p style="margin:.3rem 0;">');
    s = s.replace(/\n/g,'<br>');
    return s;
}

// ══ MOBILE SIDEBAR ══
function toggleSidebar(){
    document.querySelector('.sidebar')?.classList.toggle('open');
    document.querySelector('.sidebar-backdrop')?.classList.toggle('open');
}
function closeSidebar(){
    document.querySelector('.sidebar')?.classList.remove('open');
    document.querySelector('.sidebar-backdrop')?.classList.remove('open');
}

// ══ NAVIGATION ══
function navTo(section){
    if(issuesTimer){ clearInterval(issuesTimer); issuesTimer=null; }
    closeSidebar();   // collapse drawer on mobile after picking a section

    currentSection = section;
    ['chat','dashboard','inbox','issues','agents','org','files'].forEach(s=>{
        document.getElementById('s-'+s)?.classList.toggle('active', s===section);
        document.getElementById('nav-'+s)?.classList.toggle('active', s===section);
    });
    if(section==='dashboard') renderDashboard();
    if(section==='inbox')     renderInbox();
    if(section==='issues'){
        renderIssues();          // render immediately with current data
        loadDbTasks();           // then refresh from DB
        issuesTimer = setInterval(loadDbTasks, 30000);
    }
    if(section==='agents') renderAgentGrid();
    if(section==='org')    renderOrgChart();
    if(section==='files')  loadFiles();
}

// ══ FILES (artifacts) ══
let artifacts = [];
async function loadFiles(){
    const el = document.getElementById('files-list'); if(!el) return;
    if(IS_MOCK){ el.innerHTML='<div class="p-8 text-center mono text-xs" style="color:var(--m);">// Demo mode — no stored files.</div>'; return; }
    el.innerHTML = '<div class="p-8 text-center mono text-xs" style="color:var(--m);">// Loading…</div>';
    try{
        const r = await fetch('/api/artifacts/'+encodeURIComponent(CO.id));
        const d = await r.json();
        artifacts = (d.ok && Array.isArray(d.artifacts)) ? d.artifacts : [];
    }catch(_){ artifacts = []; }
    renderFiles();
}
function renderFiles(){
    const el = document.getElementById('files-list'); if(!el) return;
    const cnt = document.getElementById('files-count'); if(cnt) cnt.textContent = artifacts.length + ' file' + (artifacts.length!==1?'s':'');
    if(!artifacts.length){
        el.innerHTML = '<div class="p-10 text-center"><svg width="30" height="30" class="mx-auto mb-2" style="color:rgba(232,180,84,.2);"><use href="#i-download"/></svg>'
            + '<div class="mono text-xs" style="color:var(--m);">// No files yet.</div>'
            + '<div class="text-xs mt-1" style="color:var(--m);">Ask an agent to generate an image or a document.</div></div>';
        return;
    }
    const images = artifacts.filter(a=>a.type==='image');
    const docs   = artifacts.filter(a=>a.type!=='image');
    let html = '';

    if(images.length){
        html += '<div class="sl mb-3">Images</div><div class="grid gap-3 mb-6" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));">';
        images.forEach(a=>{
            html += '<div class="rounded-xl overflow-hidden" style="border:1px solid rgba(235,225,200,.08);background:rgba(16,14,11,.6);">'
                + '<a href="'+h(a.file_url)+'" target="_blank" rel="noopener"><img src="'+h(a.file_url)+'" loading="lazy" style="width:100%;height:120px;object-fit:cover;display:block;"></a>'
                + '<div class="p-2"><div class="text-xs truncate" style="color:#ece6da;">'+h(a.title)+'</div>'
                + '<div class="mono text-[8px] mt-0.5" style="color:var(--m);">'+h(getAgentName(a.agent_id))+' · '+timeAgo(a.created_at)+'</div></div></div>';
        });
        html += '</div>';
    }

    if(docs.length){
        html += '<div class="sl mb-3">Documents</div><div class="space-y-2">';
        docs.forEach(a=>{
            html += '<a href="'+h(a.file_url)+'" target="_blank" rel="noopener" class="flex items-center gap-3 px-3 py-2.5 rounded-xl" style="border:1px solid rgba(235,225,200,.08);background:rgba(16,14,11,.6);text-decoration:none;">'
                + '<div class="grid place-items-center rounded-lg flex-shrink-0" style="width:30px;height:30px;background:rgba(232,180,84,.1);"><svg width="14" height="14" style="color:var(--c);"><use href="#i-download"/></svg></div>'
                + '<div class="flex-1 min-w-0"><div class="text-sm truncate" style="color:#ece6da;">'+h(a.title)+'</div>'
                + '<div class="mono text-[9px]" style="color:var(--m);">'+h(getAgentName(a.agent_id))+' · '+timeAgo(a.created_at)+'</div></div>'
                + '<span class="mono text-[9px]" style="color:var(--c);">Open ↗</span></a>';
        });
        html += '</div>';
    }
    el.innerHTML = html;
}

// ══ ORG CHART (top-down flowchart, collapsible) ══
function toggleOrg(el){
    const li = el.closest('li');
    if(li) li.classList.toggle('collapsed');
    event.stopPropagation();
}
function renderOrgChart(){
    const el = document.getElementById('org-tree'); if(!el) return;
    const cnt = document.getElementById('org-count'); if(cnt) cnt.textContent = AGENTS.length + ' agents';
    if(!AGENTS.length){ el.innerHTML='<div class="mono text-xs" style="color:var(--m);">// No agents.</div>'; return; }

    const byParent = {};
    AGENTS.forEach(a=>{ const p = a.parent_id || '__root'; (byParent[p]=byParent[p]||[]).push(a); });
    const ids = new Set(AGENTS.map(a=>a.id));
    const roots = AGENTS.filter(a=> !a.parent_id || !ids.has(a.parent_id));

    function nodeCard(a, isRoot, childCount){
        const st = tasks[a.id]?.status || 'idle';
        const dot = st==='working'?'working':hbState[a.id]==='paused'?'paused':a.is_active!==false?'active':'inactive';
        return '<div class="ocard '+(isRoot?'is-root':'')+'" onclick="navTo(\'agents\');setTimeout(()=>openAgentDetail(\''+h(a.id)+'\'),60)">'
            + '<div class="oav hex" style="width:30px;height:34px;font-size:.6rem;background:linear-gradient(135deg,rgba(232,180,84,.2),rgba(200,144,80,.15));color:#e8b454;">'+initials(a.name)+'</div>'
            + '<div class="flex items-center gap-1.5"><div class="sdot '+dot+'" style="width:5px;height:5px;flex-shrink:0;"></div><span class="onm">'+h(a.name)+'</span></div>'
            + '<div class="orl">'+h(a.role_title||'')+'</div>'
            + (childCount>0 ? '<span class="octog" onclick="toggleOrg(this)"><span class="chev">▾</span> '+childCount+'</span>' : '')
            + '</div>';
    }
    function renderNode(a, isRoot){
        const children = byParent[a.id] || [];
        let html = '<li>' + nodeCard(a, isRoot, children.length);
        if(children.length){
            html += '<ul>' + children.map(c=>renderNode(c,false)).join('') + '</ul>';
        }
        return html + '</li>';
    }

    el.innerHTML = '<div class="orgchart"><ul>' + roots.map(r=>renderNode(r,true)).join('') + '</ul></div>';
}

// ══ CHAT BUBBLES ══
const msgsEl  = document.getElementById('messages');
const innerEl = document.getElementById('msgs');

function bubble(role, text, type='normal'){
    const wrap = document.createElement('div');
    const isUser = role==='user';
    if(type==='delegate'){
        wrap.className='px-1';
        wrap.innerHTML=`<div class="b-delegate px-4 py-3 text-xs">${text}</div>`;
    } else if(isUser){
        wrap.className='flex justify-end';
        wrap.innerHTML=`<div class="b-user px-4 py-3 text-sm leading-relaxed" style="max-width:88%;"><p>${md(text)}</p></div>`;
    } else {
        wrap.className='flex justify-start flex-col gap-1';
        const rawText=text;
        wrap.innerHTML=`<div class="b-bot px-4 py-3 text-sm leading-relaxed" style="max-width:88%;"><p>${md(text)}</p></div>
        <div class="flex items-center gap-2 pl-1" style="max-width:88%;">
            <button onclick="copyBubble(this)" data-text="${h(rawText)}" class="flex items-center gap-1 px-2 py-0.5 rounded mono text-[9px] uppercase tracking-widest transition-colors" style="color:var(--m);background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.07);" onmouseenter="this.style.color='#e8b454'" onmouseleave="this.style.color='var(--m)'"><svg width="10" height="10"><use href="#i-copy"/></svg>Copy</button>
            <button onclick="downloadBubble(this)" data-text="${h(rawText)}" class="flex items-center gap-1 px-2 py-0.5 rounded mono text-[9px] uppercase tracking-widest transition-colors" style="color:var(--m);background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.07);" onmouseenter="this.style.color='#e8b454'" onmouseleave="this.style.color='var(--m)'"><svg width="10" height="10"><use href="#i-download"/></svg>Download</button>
        </div>`;
    }
    innerEl.appendChild(wrap);
    msgsEl.scrollTop=msgsEl.scrollHeight;
    return wrap;
}

function copyBubble(btn){
    navigator.clipboard.writeText(btn.dataset.text||'').then(()=>{
        const o=btn.innerHTML; btn.innerHTML='<svg width="10" height="10"><use href="#i-check"/></svg>Copied!'; btn.style.color='#3dd68c';
        setTimeout(()=>{ btn.innerHTML=o; btn.style.color='var(--m)'; },2000);
    });
}
function downloadBubble(btn){
    const text=btn.dataset.text||'', ts=new Date().toISOString().slice(0,19).replace(/[T:]/g,'-');
    const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([text],{type:'text/plain'}));
    a.download=`command-output-${ts}.txt`; a.click(); URL.revokeObjectURL(a.href);
}
function typingRow(){
    const w=document.createElement('div'); w.id='typing-row'; w.className='flex justify-start';
    w.innerHTML=`<div class="b-bot px-4 py-3 text-sm"><div class="typing"><span></span><span></span><span></span></div></div>`;
    innerEl.appendChild(w); msgsEl.scrollTop=msgsEl.scrollHeight; return w;
}

// ══ DELEGATION DETECTION ══
function detectDelegations(text){
    return AGENTS.filter(a=>(!ROOT||a.id!==ROOT.id)&&(text.toLowerCase().includes(a.name.toLowerCase())||text.toLowerCase().includes((a.role_title||'').toLowerCase())));
}
function buildDelegateCard(agents, txt){
    return `<div class="flex items-center gap-2 mb-2 mono text-[9px] uppercase tracking-widest" style="color:rgba(232,180,84,.6);"><svg width="10" height="10"><use href="#i-nodes"/></svg>Delegating task</div>`
        + agents.map(a=>`<div class="flex items-center gap-2 py-0.5"><div class="hex grid place-items-center font-bold text-[9px] flex-shrink-0" style="width:18px;height:21px;background:rgba(232,180,84,.15);color:#e8b454;">${initials(a.name)}</div><span style="color:#ece6da;font-size:.8rem;">${h(a.name)}</span><span class="mono text-[9px]" style="color:var(--m);">· ${h(a.role_title)}</span></div>`).join('');
}

// ══ LOAD HISTORY ══
(async()=>{
    if(IS_MOCK||!ROOT){ document.getElementById('loader').remove(); addWelcome(); return; }
    try{
        const r=await fetch(`/api/history/${encodeURIComponent(ROOT.slug)}`);
        const d=await r.json();
        document.getElementById('loader').remove();
        if(d.ok&&d.turns?.length) d.turns.forEach(t=>bubble(t.role,t.content));
        else addWelcome();
    }catch(_){ document.getElementById('loader').textContent='// Could not load history.'; }
})();

function addWelcome(){
    if(IS_MOCK) bubble('assistant',`// Demo mode — preview of ${CO.name}. Connect Supabase to enable live commands.`);
    else if(ROOT) bubble('assistant',`Command channel open. I'm **${ROOT.name}**, directing the **${CO.name}** team. Issue a command and I'll coordinate with our agents.`);
}

// ══ SEND / STREAM ══
const frm=document.getElementById('frm');
const inp=document.getElementById('inp');
const snd=document.getElementById('snd');

if(inp){
    inp.addEventListener('input',()=>{ inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,144)+'px'; });
    inp.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();frm?.requestSubmit();} });
}

frm?.addEventListener('submit', async e=>{
    e.preventDefault();
    const txt=inp.value.trim(); if(!txt||!ROOT) return;
    snd.disabled=true; inp.value=''; inp.style.height='auto';
    bubble('user',txt);

    // Add to task log
    const tlId = addToTaskLog(txt, ROOT.id, ROOT.name);

    const typRow=typingRow();
    document.getElementById('typing-status').classList.remove('hidden');
    document.getElementById('typing-status').classList.add('flex');

    let streamWrap=null, streamP=null, fullText='', delegated=[];

    function ensureStreamBubble(){
        if(streamWrap) return;
        typRow.remove();
        document.getElementById('typing-status').classList.add('hidden');
        document.getElementById('typing-status').classList.remove('flex');
        streamWrap=document.createElement('div'); streamWrap.className='flex justify-start';
        streamP=document.createElement('div'); streamP.className='b-bot px-4 py-3 text-sm leading-relaxed'; streamP.style.maxWidth='88%';
        streamP.innerHTML='<span class="stream-cursor" style="display:inline-block;width:2px;height:1em;background:var(--c);vertical-align:text-bottom;animation:blink 1s step-end infinite;margin-left:1px;"></span>';
        streamWrap.appendChild(streamP); innerEl.appendChild(streamWrap); msgsEl.scrollTop=msgsEl.scrollHeight;
    }

    try{
        const r=await fetch('/api/chat/stream',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:ROOT.slug,message:txt})});
        if(!r.ok||!r.body) throw new Error('Stream failed: '+r.status);

        const reader=r.body.getReader(), decoder=new TextDecoder();
        let lineBuf='';

        while(true){
            const {done,value}=await reader.read();
            if(done) break;
            lineBuf+=decoder.decode(value,{stream:true});
            const lines=lineBuf.split('\n'); lineBuf=lines.pop();

            for(const line of lines){
                if(!line.startsWith('data: ')) continue;
                let ev; try{ ev=JSON.parse(line.slice(6)); }catch(_){ continue; }

                if(ev.type==='chunk'&&ev.text){
                    ensureStreamBubble();
                    fullText+=ev.text;
                    streamP.innerHTML='<p style="margin-bottom:.35rem;">'+md(fullText)+'</p>'
                        +'<span class="stream-cursor" style="display:inline-block;width:2px;height:1em;background:var(--c);vertical-align:text-bottom;animation:blink 1s step-end infinite;margin-left:1px;"></span>';
                    msgsEl.scrollTop=msgsEl.scrollHeight;

                    if(ev.text.includes(' ')||ev.text.includes('\n')){
                        const det=detectDelegations(fullText);
                        if(det.length>delegated.length){
                            delegated=det;
                            delegated.forEach(a=>{
                                if(!tasks[a.id]||tasks[a.id].status==='idle'){
                                    tasks[a.id]={text:txt.substring(0,70),ts:nowStr(),status:'working',output:null};
                                    addToTaskLog(txt.substring(0,70), a.id, a.name);
                                    setTimeout(()=>{ if(tasks[a.id]?.status==='working'){ tasks[a.id].status='review'; updateTaskInLog(a.id,'review'); } },25000);
                                }
                            });
                        }
                    }
                }
                if(ev.type==='error'){
                    ensureStreamBubble();
                    streamP.innerHTML='<p style="color:#f87171;">⚠ '+(ev.error||'Error')+'</p>';
                    updateTaskInLogById(tlId,'error');
                }
                if(ev.type==='tool_result'&&ev.results){
                    // Show tool execution results as a card below the response
                    const tc=document.createElement('div');
                    tc.className='px-1';
                    const lines=ev.results.split('\n').filter(l=>l.trim());
                    const resultsHtml=lines.map(l=>{
                        if(l.startsWith('IMAGE_URL:')){
                            const url=l.slice(10).trim();
                            return '<div style="margin:.5rem 0;"><img src="'+h(url)+'" alt="Generated image" style="max-width:100%;border-radius:12px;border:1px solid rgba(232,180,84,.2);" loading="lazy"><div style="margin-top:.3rem;"><a href="'+h(url)+'" target="_blank" rel="noopener" style="font-size:.7rem;color:#e8b454;">Open full size ↗</a></div></div>';
                        }
                        if(l.startsWith('FILE_URL:')){
                            const furl=l.slice(9).trim();
                            return '<div style="margin:.3rem 0;"><a href="'+h(furl)+'" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#e8b454;"><svg width="12" height="12"><use href="#i-download"/></svg>Open document ↗</a></div>';
                        }
                        if(l.startsWith('✓')) return '<div style="color:#3dd68c;font-size:.8rem;">'+h(l)+'</div>';
                        if(l.startsWith('⚠')) return '<div style="color:#f87171;font-size:.8rem;">'+h(l)+'</div>';
                        if(l.startsWith('[TOOL_RESULT:')) return '<div style="font-family:\'JetBrains Mono\',monospace;font-size:.7rem;color:rgba(232,180,84,.5);margin-top:.5rem;">'+h(l)+'</div>';
                        if(l.startsWith('[/TOOL_RESULT')) return '';
                        return '<div style="font-size:.8rem;color:#b6ad9a;">'+h(l)+'</div>';
                    }).join('');
                    tc.innerHTML='<div style="margin:.3rem 0;padding:.6rem .85rem;background:rgba(16,14,11,.7);border:1px solid rgba(235,225,200,.12);border-left:2.5px solid #3dd68c;border-radius:0 10px 10px 0;">'
                        +'<div style="font-size:.68rem;font-family:\'JetBrains Mono\',monospace;color:rgba(232,180,84,.5);margin-bottom:.35rem;">⚡ TOOLS EXECUTED</div>'
                        +resultsHtml+'</div>';
                    innerEl.appendChild(tc);
                    msgsEl.scrollTop=msgsEl.scrollHeight;
                }
                if(ev.type==='done'){
                    if(streamP) streamP.innerHTML='<p style="margin-bottom:.35rem;">'+md(fullText)+'</p>';
                    if(streamWrap&&fullText){
                        const tb=document.createElement('div'); tb.className='flex items-center gap-2 pl-1'; tb.style.maxWidth='88%';
                        tb.innerHTML=`<button onclick="copyBubble(this)" data-text="${h(fullText)}" class="flex items-center gap-1 px-2 py-0.5 rounded mono text-[9px] uppercase tracking-widest transition-colors" style="color:var(--m);background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.07);" onmouseenter="this.style.color='#e8b454'" onmouseleave="this.style.color='var(--m)'"><svg width="10" height="10"><use href="#i-copy"/></svg>Copy</button>
                        <button onclick="downloadBubble(this)" data-text="${h(fullText)}" class="flex items-center gap-1 px-2 py-0.5 rounded mono text-[9px] uppercase tracking-widest transition-colors" style="color:var(--m);background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.07);" onmouseenter="this.style.color='#e8b454'" onmouseleave="this.style.color='var(--m)'"><svg width="10" height="10"><use href="#i-download"/></svg>Download</button>`;
                        streamWrap.appendChild(tb);
                    }
                    // Add to inbox
                    if(fullText) addToInbox(fullText, ROOT.name);
                    // Update task log
                    updateTaskInLogById(tlId, delegated.length?'done':'done');
                    delegated.forEach(a=>{ if(tasks[a.id]) tasks[a.id].output=fullText; });
                    // Delegation trail
                    if(delegated.length){
                        const trail=document.createElement('div'); trail.className='px-1';
                        trail.innerHTML='<div class="b-delegate px-4 py-3 text-xs">'+buildDelegateCard(delegated,fullText)+'</div>';
                        innerEl.insertBefore(trail,streamWrap);
                    }
                    if(currentSection==='dashboard') renderDashboard();
                }
            }
        }
        if(streamP) streamP.innerHTML='<p style="margin-bottom:.35rem;">'+md(fullText)+'</p>';
    }catch(err){
        if(typRow.parentNode) typRow.remove();
        document.getElementById('typing-status').classList.add('hidden');
        document.getElementById('typing-status').classList.remove('flex');
        if(streamWrap) streamP.innerHTML='<p style="color:#f87171;">⚠ Connection error.</p>';
        else bubble('assistant','⚠ Connection error.');
        updateTaskInLogById(tlId,'error');
    }
    snd.disabled=false; inp?.focus();
});

// ══ TASK LOG ══
function addToTaskLog(command, agentId, agentName){
    const id=Date.now().toString(36)+Math.random().toString(36).slice(2,5);
    taskLog.push({id, command:command.substring(0,120), agentId, agentName:agentName||'', status:'working', ts:nowStr(), output:null});
    saveState(); updateIssuesBadge();
    if(currentSection==='issues') renderIssues();
    return id;
}
function updateTaskInLogById(id, status, output){
    const t=taskLog.find(x=>x.id===id);
    if(t){ t.status=status; if(output) t.output=output; saveState(); updateIssuesBadge(); }
    if(currentSection==='issues') renderIssues();
    if(currentSection==='dashboard') renderDashboard();
}
function updateTaskInLog(agentId, status){
    const t=[...taskLog].reverse().find(x=>x.agentId===agentId);
    if(t){ t.status=status; saveState(); updateIssuesBadge(); }
}
function clearTaskLog(){ if(!confirm('Clear all task history?')) return; taskLog=[]; Object.keys(tasks).forEach(k=>delete tasks[k]); saveState(); updateIssuesBadge(); renderIssues(); renderDashboard(); }

function updateIssuesBadge(){
    const sessionWorking=taskLog.filter(t=>t.status==='working').length;
    const dbWorking=dbTasks.filter(t=>t.status==='working'||t.status==='pending').length;
    const n=sessionWorking+dbWorking;
    const b=document.getElementById('issues-badge');
    if(b){ b.textContent=n||''; b.classList.toggle('hidden',!n); }
}

// ── DB TASKS ──
async function loadDbTasks(){
    if(IS_MOCK){ renderIssues(); return; }
    try{
        const r = await fetch('/api/tasks/'+encodeURIComponent(CO.id));
        if(!r.ok){ console.warn('Tasks API returned', r.status); renderIssues(); return; }
        const d = await r.json();
        if(d.ok && Array.isArray(d.tasks)) dbTasks = d.tasks;
    } catch(err){
        console.warn('loadDbTasks failed:', err);
    }
    renderIssues();
}

function getAgentName(agentId){
    if(!agentId) return 'Unassigned';
    return AGENTS.find(a=>a.id===agentId)?.name||'Unknown';
}

// ── Task display helpers ──
function priorityBadge(p){
    const cfg={
        critical:{bg:'rgba(239,68,68,.12)',  border:'rgba(239,68,68,.28)',  color:'#f87171', label:'‼ Critical'},
        high:    {bg:'rgba(240,160,0,.10)',  border:'rgba(240,160,0,.28)',  color:'#f0c040', label:'↑ High'},
        medium:  {bg:'rgba(235,225,200,.08)',  border:'rgba(235,225,200,.20)',  color:'#e8b454', label:'– Medium'},
        low:     {bg:'rgba(90,82,64,.10)',  border:'rgba(90,82,64,.22)',  color:'#8a7850', label:'↓ Low'},
    };
    const c=cfg[p]||cfg.medium;
    return `<span class="mono" style="font-size:8px;padding:2px 7px;border-radius:9999px;background:${c.bg};border:1px solid ${c.border};color:${c.color};white-space:nowrap;">${c.label}</span>`;
}

function statusDot(s){
    const cls=s==='working'?'working':s==='done'?'active':s==='failed'||s==='cancelled'?'inactive':'paused';
    return `<div class="sdot ${cls}" style="width:7px;height:7px;flex-shrink:0;margin-top:3px;"></div>`;
}

function toggleTaskOutput(id){
    const el=document.getElementById('to-'+id);
    const arrow=document.getElementById('toa-'+id);
    if(!el) return;
    const hidden=el.classList.toggle('hidden');
    if(arrow) arrow.textContent=hidden?'▶':'▼';
}

function downloadTaskOutput(taskId, title){
    const t=dbTasks.find(x=>x.id===taskId);
    if(!t||!t.output) return;
    const ts=new Date().toISOString().slice(0,10);
    const name=(title||'output').replace(/[^a-z0-9]/gi,'-').toLowerCase()+'-'+ts+'.txt';
    const a=document.createElement('a');
    a.href=URL.createObjectURL(new Blob([t.output],{type:'text/plain'}));
    a.download=name; a.click(); URL.revokeObjectURL(a.href);
}

async function retryDbTask(taskId){
    const btn = event?.target;
    if(btn){ btn.disabled=true; btn.textContent='…'; }
    try{
        await fetch('/api/tasks/update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:taskId,status:'pending'})});
        const t=dbTasks.find(function(x){ return x.id===taskId; });
        if(t){ t.status='pending'; t.error_message=null; }
        if(currentSection==='issues') renderIssues();
        if(currentSection==='agents'&&detailAgent) renderDetailOverview();
        updateIssuesBadge();
    }catch(_){
        if(btn){ btn.disabled=false; btn.textContent='↺ Retry'; }
    }
}

function wordCount(str){ return str ? Math.round(str.split(/\s+/).filter(Boolean).length) : 0; }

// Build HTML for one DB task card (no nested template literals)
function buildTaskCard(t){
    const agName   = getAgentName(t.agent_id);
    const agIni    = initials(agName);
    const hasOut   = !!(t.output && t.output.length > 0);
    const wc       = wordCount(t.output);
    const canCancel= t.status==='pending' || t.status==='working';
    const canRetry = t.status==='failed';
    const tid      = h(t.id);

    var out = '<div class="px-4 py-3 transition-colors" style="border-bottom:1px solid rgba(235,225,200,.05);">';
    out += '<div class="flex items-start gap-3">';
    out += statusDot(t.status);
    out += '<div class="flex-1 min-w-0">';

    // Title row
    out += '<div class="flex items-center gap-2 flex-wrap mb-1">';
    out += priorityBadge(t.priority);
    out += '<span class="text-sm font-medium" style="color:#ece6da;">'+h(t.title)+'</span>';
    out += '</div>';

    // Meta row
    out += '<div class="flex items-center gap-2 flex-wrap mb-1">';
    out += '<div class="hex grid place-items-center font-bold" style="width:16px;height:18px;font-size:.5rem;flex-shrink:0;background:linear-gradient(135deg,rgba(232,180,84,.2),rgba(200,144,80,.15));color:#e8b454;">'+agIni+'</div>';
    out += '<span class="mono text-[9px]" style="color:var(--m);">'+h(agName)+'</span>';
    out += '<span class="mono text-[9px]" style="color:rgba(235,225,200,.2);">·</span>';
    out += '<span class="mono text-[9px]" style="color:var(--m);">'+timeAgo(t.created_at)+'</span>';
    if(t.completed_at){
        out += '<span class="mono text-[9px]" style="color:rgba(235,225,200,.2);">·</span>';
        out += '<span class="mono text-[9px]" style="color:#3dd68c;">✓ done '+timeAgo(t.completed_at)+'</span>';
    }
    out += '</div>';

    // Error
    if(t.error_message){
        out += '<div class="mt-1 px-2.5 py-1.5 rounded-lg mono text-[9px]" style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);color:#f87171;">⚠ '+h(t.error_message)+'</div>';
    }

    // Output toggle
    if(hasOut){
        out += '<div class="mt-2">';
        out += '<button onclick="toggleTaskOutput(\''+tid+'\')" class="flex items-center gap-1.5 mono text-[9px]" style="color:var(--c);">';
        out += '<span id="toa-'+tid+'">▶</span>';
        out += '<span>View output</span>';
        out += '<span style="color:var(--m);">(~'+wc+' words)</span>';
        out += '</button>';
        out += '<div id="to-'+tid+'" class="hidden mt-2">';
        out += '<div class="p-3 rounded-xl text-xs leading-relaxed" style="background:rgba(16,14,11,.85);border:1px solid rgba(235,225,200,.12);color:#ece6da;max-height:320px;overflow-y:auto;">';
        out += md(t.output);
        out += '</div>';
        out += '<button onclick="downloadTaskOutput(\''+tid+'\',\''+h(t.title).replace(/'/g,"\\'")+'\')" class="mt-1.5 flex items-center gap-1 mono text-[9px]" style="color:var(--m);">';
        out += '<svg width="10" height="10"><use href="#i-download"/></svg>Download as .txt';
        out += '</button>';
        out += '</div>';
        out += '</div>';
    }

    out += '</div>'; // flex-1

    // Action buttons
    out += '<div class="flex flex-col items-end gap-1.5 flex-shrink-0 ml-2">';
    out += '<span class="sbadge '+t.status+'">'+t.status+'</span>';
    if(canCancel){
        out += '<button id="cancel-btn-'+tid+'" onclick="initCancelConfirm(\''+tid+'\')" class="btn btn-xs" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.22);color:#f87171;padding:2px 8px;transition:all .15s;">✕ Cancel</button>';
    }
    if(canRetry){
        out += '<button onclick="retryDbTask(\''+tid+'\')" class="btn btn-xs" style="background:rgba(235,225,200,.08);border:1px solid rgba(235,225,200,.2);color:var(--c);padding:2px 8px;">↺ Retry</button>';
    }
    out += '</div>';

    out += '</div></div>'; // flex, card
    return out;
}

function renderIssues(){
    const el = document.getElementById('issues-list');
    if(!el) return;

    try {
        const q = issueFilter.toLowerCase().trim();
        var html = '';

        // ── Assigned Tasks (Supabase) ────────────────────────────────
        const filteredDb = q
            ? dbTasks.filter(function(t){ return t.title.toLowerCase().includes(q) || (t.description||'').toLowerCase().includes(q) || getAgentName(t.agent_id).toLowerCase().includes(q); })
            : dbTasks;

        var working = dbTasks.filter(function(t){ return t.status==='working'; }).length;
        var pending = dbTasks.filter(function(t){ return t.status==='pending'; }).length;

        // Section header
        html += '<div class="px-4 py-2 flex items-center justify-between" style="background:rgba(12,10,8,.5);border-bottom:1px solid rgba(235,225,200,.05);">';
        html += '<div class="flex items-center gap-2">';
        html += '<span class="mono text-[9px] uppercase tracking-widest" style="color:rgba(232,180,84,.4);">// Assigned Tasks</span>';
        if(working > 0) html += '<span class="mono text-[8px] px-1.5 py-0.5 rounded-full" style="background:rgba(61,214,140,.1);border:1px solid rgba(61,214,140,.2);color:#3dd68c;">'+working+' working</span>';
        if(pending > 0) html += '<span class="mono text-[8px] px-1.5 py-0.5 rounded-full" style="background:rgba(240,160,0,.08);border:1px solid rgba(240,160,0,.2);color:#f0c040;">'+pending+' pending</span>';
        html += '</div>';
        html += '<span class="mono text-[9px]" style="color:var(--m);">'+filteredDb.length+' total</span>';
        html += '</div>';

        if(!filteredDb.length){
            html += '<div class="px-4 py-8 text-center">';
            html += '<svg width="28" height="28" class="mx-auto mb-2" style="color:rgba(235,225,200,.15);"><use href="#i-tasks"/></svg>';
            html += '<div class="mono text-xs" style="color:var(--m);">// No assigned tasks yet.</div>';
            html += '<div class="text-xs mt-1" style="color:var(--m);">Click <span style="color:var(--c);">New Task</span> to assign work to an agent.</div>';
            html += '</div>';
        } else {
            for(var i=0; i<filteredDb.length; i++){
                html += buildTaskCard(filteredDb[i]);
            }
        }

        // ── Session Activity (localStorage) ──────────────────────────
        const src = q
            ? taskLog.filter(function(t){ return t.command.toLowerCase().includes(q) || (t.agentName||'').toLowerCase().includes(q); })
            : taskLog;
        const list = src.slice().reverse();

        html += '<div class="px-4 py-2 flex items-center justify-between" style="background:rgba(12,10,8,.5);border-top:1px solid rgba(235,225,200,.07);border-bottom:1px solid rgba(235,225,200,.05);">';
        html += '<span class="mono text-[9px] uppercase tracking-widest" style="color:rgba(232,180,84,.4);">// Session Activity</span>';
        html += '<span class="mono text-[9px]" style="color:var(--m);">'+list.length+' command'+(list.length!==1?'s':'')+'</span>';
        html += '</div>';

        if(!list.length){
            html += '<div class="px-4 py-6 mono text-xs text-center" style="color:var(--m);">// No commands this session. Issue one in Chat.</div>';
        } else {
            for(var j=0; j<list.length; j++){
                var t2 = list[j];
                var dotCls = t2.status==='working'?'working':t2.status==='done'||t2.status==='review'?'active':'inactive';
                html += '<div class="px-4 py-2.5 flex items-center gap-3" style="border-bottom:1px solid rgba(235,225,200,.04);">';
                html += '<div class="sdot '+dotCls+'" style="width:6px;height:6px;flex-shrink:0;"></div>';
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="text-sm truncate" style="color:#ece6da;">'+h(t2.command)+'</div>';
                html += '<div class="mono text-[9px] mt-0.5" style="color:var(--m);">'+h(t2.agentName||'CEO')+' · '+t2.ts+'</div>';
                html += '</div>';
                html += '<span class="sbadge '+t2.status+'">'+t2.status+'</span>';
                html += '</div>';
            }
        }

        el.innerHTML = html;
    } catch(err) {
        el.innerHTML = '<div class="p-6 mono text-xs text-center" style="color:#f87171;">// Render error: '+h(String(err))+'</div>';
        console.error('renderIssues error:', err);
    }
}

// ── NEW TASK PANEL ──
function toggleNewTaskPanel(){
    newTaskPanelOpen=!newTaskPanelOpen;
    const panel=document.getElementById('new-task-panel');
    const btn=document.getElementById('nt-toggle-btn');
    if(panel) panel.classList.toggle('hidden',!newTaskPanelOpen);
    if(btn){
        btn.innerHTML=newTaskPanelOpen
            ?'<svg width="10" height="10"><use href="#i-x"/></svg>Cancel'
            :'<svg width="10" height="10"><use href="#i-plus"/></svg>New Task';
    }
    if(newTaskPanelOpen) document.getElementById('nt-title')?.focus();
}

async function submitNewTask(){
    const title   =document.getElementById('nt-title')?.value.trim();
    const agentId =document.getElementById('nt-agent')?.value||null;
    const desc    =document.getElementById('nt-desc')?.value.trim()||'';
    const priority=document.getElementById('nt-priority')?.value||'medium';
    const msgEl   =document.getElementById('nt-msg');
    const btn     =document.getElementById('nt-submit');

    if(msgEl){ msgEl.classList.add('hidden'); }
    if(!title){
        if(msgEl){ msgEl.textContent='Task title is required.'; msgEl.style.cssText='background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;'; msgEl.classList.remove('hidden'); }
        return;
    }
    if(IS_MOCK){
        if(msgEl){ msgEl.textContent='// Demo mode — connect Supabase to assign real tasks.'; msgEl.style.cssText='background:rgba(240,160,0,.08);border:1px solid rgba(240,160,0,.2);color:#f0c040;'; msgEl.classList.remove('hidden'); }
        return;
    }

    if(btn){ btn.disabled=true; btn.textContent='Assigning…'; }

    try{
        const r=await fetch('/api/tasks/create',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({title,description:desc,company_id:CO.id,agent_id:agentId,priority})});
        const d=await r.json();
        if(d.ok){
            if(d.task) dbTasks.unshift(d.task);
            renderIssues();
            updateIssuesBadge();
            if(msgEl){ msgEl.textContent='✓ Task assigned. Agent will pick it up on the next runner cycle.'; msgEl.style.cssText='background:rgba(61,214,140,.08);border:1px solid rgba(61,214,140,.2);color:#3dd68c;'; msgEl.classList.remove('hidden'); }
            document.getElementById('nt-title').value='';
            document.getElementById('nt-desc').value='';
            setTimeout(()=>{ msgEl?.classList.add('hidden'); toggleNewTaskPanel(); },3000);
        } else {
            if(msgEl){ msgEl.textContent='Error: '+(d.error||'Unknown'); msgEl.style.cssText='background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;'; msgEl.classList.remove('hidden'); }
        }
    }catch(_){
        if(msgEl){ msgEl.textContent='Network error. Try again.'; msgEl.style.cssText='background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;'; msgEl.classList.remove('hidden'); }
    }

    if(btn){ btn.disabled=false; btn.innerHTML='<svg width="10" height="10"><use href="#i-plus"/></svg>Assign Task'; }
}

// ── Two-step cancel (no browser confirm() dialog) ──
var cancelConfirmTimers = {};

function initCancelConfirm(taskId){
    const btn = document.getElementById('cancel-btn-'+taskId);
    if(!btn) return;
    // Clear any pending reset
    if(cancelConfirmTimers[taskId]) clearTimeout(cancelConfirmTimers[taskId]);
    // Switch to confirm state
    btn.textContent = 'Confirm cancel?';
    btn.style.background   = 'rgba(239,68,68,.25)';
    btn.style.borderColor  = 'rgba(239,68,68,.5)';
    btn.setAttribute('onclick', 'confirmCancelTask("'+taskId+'")');
    // Auto-reset after 4 seconds if not confirmed
    cancelConfirmTimers[taskId] = setTimeout(function(){
        resetCancelBtn(taskId);
    }, 4000);
}

function resetCancelBtn(taskId){
    const btn = document.getElementById('cancel-btn-'+taskId);
    if(!btn) return;
    btn.textContent = '✕ Cancel';
    btn.style.background  = 'rgba(239,68,68,.1)';
    btn.style.borderColor = 'rgba(239,68,68,.22)';
    btn.setAttribute('onclick', 'initCancelConfirm("'+taskId+'")');
    btn.disabled = false;
}

async function confirmCancelTask(taskId){
    if(cancelConfirmTimers[taskId]){ clearTimeout(cancelConfirmTimers[taskId]); delete cancelConfirmTimers[taskId]; }
    const btn = document.getElementById('cancel-btn-'+taskId);
    if(btn){ btn.disabled = true; btn.textContent = 'Cancelling…'; }

    try{
        const r = await fetch('/api/tasks/cancel', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: taskId})
        });
        const d = await r.json();
        if(d.ok){
            const t = dbTasks.find(function(x){ return x.id === taskId; });
            if(t){ t.status = 'cancelled'; t.error_message = null; }
            // Refresh whichever view is visible
            if(currentSection === 'issues') renderIssues();
            if(currentSection === 'agents' && detailAgent) renderDetailOverview();
            updateIssuesBadge();
        } else {
            console.warn('Cancel failed:', d.error);
            resetCancelBtn(taskId);
        }
    } catch(err){
        console.error('Cancel error:', err);
        resetCancelBtn(taskId);
    }
}

// ══ INBOX ══
function addToInbox(content, agentName){
    const id=Date.now().toString(36);
    inbox.unshift({id, content, ts:nowStr(), read:false, agentName:agentName||'CEO'});
    if(inbox.length>60) inbox.pop();
    saveState(); updateInboxBadge();
    if(currentSection==='inbox') renderInbox();
}
function updateInboxBadge(){
    const n=inbox.filter(m=>!m.read).length;
    const b=document.getElementById('inbox-badge');
    if(b){ b.textContent=n||''; b.classList.toggle('hidden',!n); }
    const ub=document.getElementById('itab-unread-count');
    if(ub) ub.textContent=n>0?`(${n})`:'';
}
function switchInboxTab(tab){
    inboxTab=tab;
    ['all','unread'].forEach(t=>{ document.getElementById(`itab-${t}`)?.classList.toggle('active',t===tab); });
    renderInbox();
}
function markInboxRead(id){
    const m=inbox.find(x=>x.id===id); if(m){ m.read=true; saveState(); updateInboxBadge(); renderInbox(); }
}
function markAllInboxRead(){ inbox.forEach(m=>m.read=true); saveState(); updateInboxBadge(); renderInbox(); }

function renderInbox(){
    const el=document.getElementById('inbox-list'); if(!el) return;
    const list=inboxTab==='unread' ? inbox.filter(m=>!m.read) : inbox;
    if(!list.length){
        el.innerHTML=`<div class="p-8 text-center mono text-xs" style="color:var(--m);">${inboxTab==='unread'?'// No unread messages.':'// Inbox empty. Send a command to start.'}</div>`; return;
    }
    el.innerHTML=list.map(m=>`
        <div class="inbox-row ${m.read?'':'unread'}" onclick="markInboxRead('${m.id}')">
            <div class="hex grid place-items-center font-bold flex-shrink-0 mt-0.5" style="width:30px;height:35px;font-size:.68rem;background:linear-gradient(135deg,rgba(232,180,84,.2),rgba(200,144,80,.15));color:#e8b454;">${initials(m.agentName||'CEO')}</div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-0.5">
                    <span class="text-sm font-medium" style="color:${m.read?'var(--m)':'#ece6da'};">${h(m.agentName||'CEO')}</span>
                    <span class="mono text-[9px] flex-shrink-0" style="color:var(--m);">${m.ts}</span>
                </div>
                <div class="text-xs truncate" style="color:var(--m);max-width:100%;">${h(m.content.substring(0,140))}${m.content.length>140?'…':''}</div>
            </div>
        </div>`).join('');
}

// ══ DASHBOARD ══
function renderDashboard(){
    const total=AGENTS.length;
    const working=Object.values(tasks).filter(t=>t?.status==='working').length;
    const done=taskLog.filter(t=>t.status==='done').length;
    const unread=inbox.filter(m=>!m.read).length;
    const mTotal=document.getElementById('m-total'); if(mTotal) mTotal.textContent=total;
    const mWork=document.getElementById('m-working'); if(mWork) mWork.textContent=working;
    const mDone=document.getElementById('m-done'); if(mDone) mDone.textContent=done;
    const mInbox=document.getElementById('m-inbox'); if(mInbox) mInbox.textContent=unread;

    // Recent tasks
    const tasksEl=document.getElementById('dash-tasks');
    if(tasksEl){
        if(!taskLog.length) tasksEl.innerHTML='<div class="p-4 text-sm" style="color:var(--m);">No tasks issued yet.</div>';
        else tasksEl.innerHTML=taskLog.slice().reverse().slice(0,8).map(t=>`
            <div class="issue-row" onclick="navTo('issues')">
                <div class="sdot ${t.status==='working'?'working':t.status==='done'||t.status==='review'?'active':'inactive'}" style="width:6px;height:6px;flex-shrink:0;"></div>
                <div class="flex-1 min-w-0"><div class="text-xs truncate" style="color:#ece6da;">${h(t.command)}</div><div class="mono text-[9px]" style="color:var(--m);">${h(t.agentName||'CEO')}</div></div>
                <span class="sbadge ${t.status}">${t.status}</span>
            </div>`).join('');
    }

    // Active agents
    const activeEl=document.getElementById('dash-active');
    if(activeEl){
        const wAgents=AGENTS.filter(a=>tasks[a.id]?.status==='working');
        if(!wAgents.length) activeEl.innerHTML='<div class="p-4 text-sm" style="color:var(--m);">// No agents currently working.</div>';
        else activeEl.innerHTML=wAgents.map(a=>`
            <div class="issue-row" onclick="navTo('agents');setTimeout(()=>openAgentDetail('${h(a.id)}'),50)">
                <div class="hex grid place-items-center font-bold flex-shrink-0" style="width:24px;height:28px;font-size:.62rem;background:linear-gradient(135deg,rgba(232,180,84,.2),rgba(200,144,80,.15));color:#e8b454;">${initials(a.name)}</div>
                <div class="flex-1 min-w-0"><div class="text-xs font-medium" style="color:#ece6da;">${h(a.name)}</div><div class="mono text-[9px] truncate" style="color:rgba(61,214,140,.7);">→ ${h(tasks[a.id]?.text||'Working…')}</div></div>
                <span class="sbadge working">Working</span>
            </div>`).join('');
    }

    // Roster
    const rosterEl=document.getElementById('dash-roster');
    if(rosterEl){
        rosterEl.innerHTML=AGENTS.map(a=>{
            const st=tasks[a.id]?.status||'idle';
            const dot=st==='working'?'working':hbState[a.id]==='paused'?'paused':'active';
            return `<div class="issue-row" onclick="navTo('agents');setTimeout(()=>openAgentDetail('${h(a.id)}'),50)">
                <div class="sdot ${dot}" style="width:6px;height:6px;flex-shrink:0;"></div>
                <div class="flex-1 min-w-0"><div class="text-xs" style="color:#ece6da;">${h(a.name)}</div><div class="mono text-[9px]" style="color:var(--m);">${h(a.role_title||'')}</div></div>
                <span class="sbadge ${st}">${st}</span>
            </div>`;
        }).join('');
    }
}

// ══ AGENT GRID ══
function renderAgentGrid(){
    const el=document.getElementById('agent-grid'); if(!el) return;
    const q=gridFilter.toLowerCase().trim();
    const list=q ? AGENTS.filter(a=>a.name.toLowerCase().includes(q)||(a.role_title||'').toLowerCase().includes(q)) : AGENTS;
    const countEl=document.getElementById('ag-count'); if(countEl) countEl.textContent=list.length+' agents';
    if(!list.length){
        el.innerHTML='<div class="p-8 text-center mono text-xs col-span-full" style="color:var(--m);">// No agents match your filter.</div>'; return;
    }
    el.innerHTML=list.map(a=>{
        const task=tasks[a.id]; const st=task?.status||'idle';
        const dot=st==='working'?'working':hbState[a.id]==='paused'?'paused':'active';
        const sel=selectedAgentId===a.id;
        return `<div class="a-card ${st==='working'?'is-working':''} ${sel?'selected':''}" onclick="openAgentDetail('${h(a.id)}')">
            <div class="flex items-center gap-2 mb-2">
                <div class="hex grid place-items-center font-bold flex-shrink-0" style="width:30px;height:35px;font-size:.7rem;background:linear-gradient(135deg,rgba(232,180,84,.2),rgba(200,144,80,.15));color:#e8b454;">${initials(a.name)}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <div class="sdot ${dot}" style="width:5px;height:5px;flex-shrink:0;"></div>
                        <span class="font-semibold truncate" style="font-size:.76rem;color:#ece0c4;">${h(a.name)}</span>
                    </div>
                    <div class="mono truncate" style="font-size:.67rem;color:var(--m);">${h(a.role_title||'')}</div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <span class="mono" style="font-size:.63rem;color:rgba(232,180,84,.45);">${(a.model||'').replace('claude-','').replace('-20251001','')}</span>
                <span class="sbadge ${st}">${st}</span>
            </div>
            ${task?.text?`<div class="mt-1.5 mono truncate" style="font-size:.63rem;color:rgba(61,214,140,.65);">→ ${h(task.text.substring(0,45))}</div>`:''}
        </div>`;
    }).join('');
}

// ══ AGENT DETAIL PANEL ══
function openAgentDetail(agentId){
    selectedAgentId=agentId;
    detailAgent=AGENTS.find(a=>a.id===agentId);
    if(!detailAgent) return;
    addSkillOpen=false;

    const panel=document.getElementById('det-panel');
    panel.style.display='flex';
    document.getElementById('det-hex').textContent=initials(detailAgent.name);
    document.getElementById('det-name').textContent=detailAgent.name;
    document.getElementById('det-role').textContent=detailAgent.role_title||'';
    const task=tasks[agentId]; const st=task?.status||'idle';
    const badge=document.getElementById('det-badge');
    if(badge){ badge.className='sbadge '+st; badge.textContent=st; }

    renderAgentGrid();
    // Ensure DB tasks are loaded so Overview tab can show them
    if(dbTasks.length === 0 && !IS_MOCK) loadDbTasks();
    switchDetailTab('overview');
}

function closeAgentDetail(){
    selectedAgentId=null; detailAgent=null;
    document.getElementById('det-panel').style.display='none';
    renderAgentGrid();
}

function switchDetailTab(tab){
    detailTab=tab;
    ['overview','instructions','skills','runs'].forEach(t=>{
        document.getElementById(`dtab-${t}`)?.classList.toggle('active',t===tab);
        document.getElementById(`dtp-${t}`)?.classList.toggle('hidden',t!==tab);
    });
    if(tab==='overview')      renderDetailOverview();
    if(tab==='instructions')  renderDetailInstructions();
    if(tab==='skills')        loadAndRenderDetailSkills();
    if(tab==='runs')          loadDetailRuns();
}

// ── OVERVIEW TAB ──
function buildDetailTaskRow(t){
    var tid = h(t.id);
    var canCancel = t.status === 'pending' || t.status === 'working';
    var canRetry  = t.status === 'failed';
    var dot = t.status==='working'?'working':t.status==='done'?'active':t.status==='failed'||t.status==='cancelled'?'inactive':'paused';

    var row = '<div class="flex items-start gap-2 p-2.5 rounded-xl mb-1.5" style="background:rgba(16,14,11,.55);border:1px solid rgba(235,225,200,.08);">';
    row += '<div class="sdot '+dot+'" style="width:6px;height:6px;flex-shrink:0;margin-top:4px;"></div>';
    row += '<div class="flex-1 min-w-0">';
    row += '<div class="text-xs font-medium truncate mb-0.5" style="color:#ece6da;">'+h(t.title)+'</div>';
    row += '<div class="flex items-center gap-1.5 flex-wrap">';
    row += priorityBadge(t.priority);
    row += '<span class="mono text-[8px]" style="color:var(--m);">'+timeAgo(t.created_at)+'</span>';
    if(t.completed_at) row += '<span class="mono text-[8px]" style="color:#3dd68c;">done '+timeAgo(t.completed_at)+'</span>';
    row += '</div>';
    if(t.error_message) row += '<div class="mt-1 mono text-[8px]" style="color:#f87171;">⚠ '+h(t.error_message.substring(0,60))+'</div>';
    row += '</div>';
    // Right side
    row += '<div class="flex flex-col items-end gap-1 flex-shrink-0">';
    row += '<span class="sbadge '+t.status+'">'+t.status+'</span>';
    if(canCancel){
        row += '<button id="cancel-btn-'+tid+'" onclick="initCancelConfirm(\''+tid+'\')" class="btn btn-xs" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.22);color:#f87171;padding:1px 6px;font-size:8px;transition:all .15s;">✕ Cancel</button>';
    }
    if(canRetry){
        row += '<button onclick="retryDbTask(\''+tid+'\')" class="btn btn-xs" style="background:rgba(235,225,200,.08);border:1px solid rgba(235,225,200,.2);color:var(--c);padding:1px 6px;font-size:8px;">↺ Retry</button>';
    }
    row += '</div>';
    row += '</div>';
    return row;
}

function renderDetailOverview(){
    if(!detailAgent) return;
    const el=document.getElementById('dtp-overview'); if(!el) return;

    const sessionTask = tasks[detailAgent.id];
    const st  = sessionTask?.status || 'idle';
    const hb  = hbState[detailAgent.id] || 'active';
    const temp = parseFloat(detailAgent.temperature ?? 0.7).toFixed(2);
    const modelShort = (detailAgent.model||'').replace('claude-','').replace('-20251001','');

    // Agent's assigned tasks from DB
    var agentDbTasks = dbTasks.filter(function(t){ return t.agent_id === detailAgent.id; });
    var activeTasks  = agentDbTasks.filter(function(t){ return t.status==='pending'||t.status==='working'; });

    var html = '<div class="p-4 space-y-4">';

    // ── Agent Info ─────────────────────────────────────────────────────
    html += '<div class="rounded-xl p-3 space-y-1.5" style="background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.09);">';
    html += '<div class="sl mb-2">Agent Info</div>';
    html += '<div class="flex justify-between items-center py-0.5"><span class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">Model</span><span class="mono text-[10px]" style="color:#ece6da;">'+h(modelShort)+'</span></div>';
    html += '<div class="flex justify-between items-center py-0.5"><span class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">Temperature</span><span class="mono text-[10px]" style="color:#ece6da;">'+temp+'</span></div>';
    html += '<div class="flex justify-between items-center py-0.5"><span class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">Status</span><span class="sbadge '+st+'">'+st+'</span></div>';
    html += '<div class="flex justify-between items-center py-0.5"><span class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">Heartbeat</span><span class="mono text-[10px]" style="color:'+(hb==='active'?'#3dd68c':'#f0a000')+';">'+hb+'</span></div>';
    html += '<div class="flex justify-between items-center py-0.5"><span class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">Active</span><span class="mono text-[10px]" style="color:'+(detailAgent.is_active!==false?'#3dd68c':'#f87171')+'">'+(detailAgent.is_active!==false?'Yes':'No')+'</span></div>';
    html += '</div>';

    // ── Assigned Tasks (DB) ────────────────────────────────────────────
    html += '<div>';
    html += '<div class="flex items-center justify-between mb-2">';
    html += '<div class="sl" style="flex:1;">Assigned Tasks</div>';
    if(activeTasks.length > 0){
        html += '<span class="mono text-[8px] px-1.5 py-0.5 rounded-full ml-2" style="background:rgba(61,214,140,.1);border:1px solid rgba(61,214,140,.2);color:#3dd68c;flex-shrink:0;">'+activeTasks.length+' active</span>';
    }
    html += '</div>';

    if(agentDbTasks.length === 0){
        html += '<div class="rounded-xl p-3 text-center" style="background:rgba(16,14,11,.4);border:1px solid rgba(235,225,200,.06);">';
        html += '<div class="mono text-[9px] mb-1.5" style="color:var(--m);">No tasks assigned yet.</div>';
        html += '<button onclick="navTo(\'issues\')" class="btn btn-ghost btn-xs" style="font-size:8px;">+ Assign from Issues</button>';
        html += '</div>';
    } else {
        // Show active tasks first, then recent completed
        var sorted = agentDbTasks.slice().sort(function(a,b){
            var order = {working:0,pending:1,failed:2,done:3,cancelled:4};
            return (order[a.status]??5) - (order[b.status]??5);
        });
        var shown = sorted.slice(0, 5);
        for(var i=0; i<shown.length; i++){
            html += buildDetailTaskRow(shown[i]);
        }
        if(agentDbTasks.length > 5){
            html += '<button onclick="navTo(\'issues\')" class="btn btn-ghost btn-xs w-full justify-center mt-1" style="font-size:8px;">View all '+(agentDbTasks.length)+' tasks in Issues</button>';
        } else {
            html += '<button onclick="navTo(\'issues\')" class="btn btn-ghost btn-xs w-full justify-center mt-1" style="font-size:8px;color:var(--m);">View in Issues →</button>';
        }
    }
    html += '</div>';

    // ── Session Task (from chat, localStorage) ─────────────────────────
    if(sessionTask && sessionTask.text){
        html += '<div class="rounded-xl p-3" style="background:rgba(61,214,140,.05);border:1px solid rgba(61,214,140,.12);">';
        html += '<div class="mono text-[9px] uppercase tracking-widest mb-1.5" style="color:rgba(61,214,140,.6);">Session Task</div>';
        html += '<div class="text-xs leading-relaxed" style="color:#ece6da;">'+h(sessionTask.text)+'</div>';
        html += '<div class="mt-2 flex items-center gap-2"><span class="sbadge '+sessionTask.status+'">'+sessionTask.status+'</span><span class="mono text-[9px]" style="color:var(--m);">'+sessionTask.ts+'</span></div>';
        html += '</div>';
    }

    // ── Actions ────────────────────────────────────────────────────────
    html += '<div class="sl">Actions</div>';
    html += '<div class="space-y-2">';
    html += '<button onclick="triggerDetailHB()" class="btn btn-ghost btn-sm w-full justify-center"><svg width="11" height="11"><use href="#i-zap"/></svg>Run Heartbeat</button>';
    html += '<button onclick="toggleDetailHB()" id="det-hb-btn" class="btn '+(hb==='active'?'btn-amber':'btn-violet')+' btn-sm w-full justify-center">';
    html += '<svg width="11" height="11"><use href="'+(hb==='active'?'#i-pause':'#i-play')+'"/></svg>';
    html += (hb==='active'?'Pause Heartbeat':'Resume Heartbeat');
    html += '</button>';
    html += '</div>';

    // ── Direct Command ─────────────────────────────────────────────────
    html += '<div class="sl">Direct Command</div>';
    html += '<form id="det-dm-form" class="flex gap-2">';
    html += '<input id="det-dm-inp" type="text" class="inp flex-1" placeholder="Send a direct command…" style="font-size:.8rem;">';
    html += '<button type="submit" class="btn btn-cyan btn-sm flex-shrink-0"><svg width="11" height="11"><use href="#i-send"/></svg></button>';
    html += '</form>';
    html += '<div id="det-dm-reply" class="hidden p-3 rounded-xl text-xs leading-relaxed" style="background:rgba(16,14,11,.7);border:1px solid rgba(235,225,200,.1);color:#ece6da;"></div>';

    html += '</div>'; // p-4
    el.innerHTML = html;

    document.getElementById('det-dm-form')?.addEventListener('submit', async ev=>{
        ev.preventDefault();
        if(IS_MOCK||!detailAgent) return;
        const msg=document.getElementById('det-dm-inp').value.trim(); if(!msg) return;
        const replyEl=document.getElementById('det-dm-reply');
        const btn=ev.target.querySelector('button[type=submit]');
        btn.disabled=true; btn.textContent='…';
        replyEl.classList.remove('hidden');
        replyEl.innerHTML='<div class="typing"><span></span><span></span><span></span></div>';
        try{
            const r=await fetch('/api/chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:detailAgent.slug,message:msg})});
            const d=await r.json();
            const reply=d.ok?d.reply:'⚠ '+(d.error||'Error');
            replyEl.innerHTML=`<p>${md(reply)}</p>`;
            if(d.ok){
                document.getElementById('det-dm-inp').value='';
                addToInbox(reply, detailAgent.name);
                tasks[detailAgent.id]={...(tasks[detailAgent.id]||{}),status:'working',text:msg.substring(0,70),ts:nowStr()};
                addToTaskLog(msg.substring(0,70), detailAgent.id, detailAgent.name);
                const badge=document.getElementById('det-badge'); if(badge){ badge.className='sbadge working'; badge.textContent='working'; }
            }
        }catch(_){ replyEl.innerHTML='<p style="color:#f87171;">⚠ Network error.</p>'; }
        btn.disabled=false; btn.innerHTML='<svg width="11" height="11"><use href="#i-send"/></svg>';
    });
}

// ── HEARTBEAT ──
function toggleDetailHB(){
    if(!detailAgent) return;
    const cur=hbState[detailAgent.id]||'active';
    hbState[detailAgent.id]=cur==='active'?'paused':'active';
    renderDetailOverview(); renderAgentGrid();
}
async function triggerDetailHB(){
    if(!detailAgent||IS_MOCK) return;
    if(hbState[detailAgent.id]==='paused'){ alert(`${detailAgent.name} heartbeat is paused. Resume first.`); return; }
    navTo('chat');
    const t=typingRow();
    try{
        const r=await fetch('/api/chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:detailAgent.slug,message:'HEARTBEAT: Provide a brief status update — current tasks, priorities, any blockers.'})});
        const d=await r.json(); t.remove();
        const reply=d.ok?d.reply:'⚠ '+(d.error||'Error');
        bubble('assistant',reply);
        if(d.ok) addToInbox(reply, detailAgent.name);
    }catch(_){ t.remove(); bubble('assistant','⚠ Network error.'); }
}

// ── INSTRUCTIONS TAB ──
function renderDetailInstructions(){
    if(!detailAgent) return;
    const el=document.getElementById('dtp-instructions'); if(!el) return;
    const temp=detailAgent.temperature??0.7;
    el.innerHTML=`
    <div class="p-4 space-y-3">
        <div>
            <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">System Prompt</label>
            <textarea id="det-sysprompt" class="inp" style="min-height:200px;font-size:.78rem;line-height:1.6;">${h(detailAgent.system_prompt||'')}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Model</label>
                <select id="det-model" class="inp" style="font-size:.8rem;">
                    <option value="claude-haiku-4-5-20251001" ${detailAgent.model==='claude-haiku-4-5-20251001'?'selected':''}>Haiku 4.5 · Fast</option>
                    <option value="claude-sonnet-4-6" ${detailAgent.model==='claude-sonnet-4-6'?'selected':''}>Sonnet 4.6 · Balanced</option>
                    <option value="claude-opus-4-7" ${detailAgent.model==='claude-opus-4-7'?'selected':''}>Opus 4.7 · Powerful</option>
                </select>
            </div>
            <div>
                <label class="mono text-[9px] tracking-widest uppercase mb-1.5 block" style="color:var(--m);">Temp · <span id="det-temp-val">${parseFloat(temp).toFixed(2)}</span></label>
                <input type="range" id="det-temp" min="0" max="1" step="0.05" value="${temp}" oninput="document.getElementById('det-temp-val').textContent=parseFloat(this.value).toFixed(2)">
                <div class="flex justify-between mono text-[9px] mt-1" style="color:var(--m);"><span>Precise</span><span>Creative</span></div>
            </div>
        </div>
        <div class="flex items-center justify-between py-2 px-3 rounded-xl" style="background:rgba(16,14,11,.5);border:1px solid rgba(235,225,200,.09);">
            <div>
                <div class="text-sm font-medium" style="color:#ece6da;">Agent Active</div>
                <div class="mono text-[9px]" style="color:var(--m);">Heartbeat &amp; task assignments</div>
            </div>
            <label class="toggle">
                <input type="checkbox" id="det-active" ${detailAgent.is_active!==false?'checked':''}>
                <div class="toggle-track"></div>
                <div class="toggle-thumb"></div>
            </label>
        </div>
        <div id="det-save-msg" class="hidden mono text-xs text-center py-2 rounded-lg" style="background:rgba(61,214,140,.08);border:1px solid rgba(61,214,140,.2);color:#3dd68c;">// Saved successfully.</div>
        <button onclick="saveAgentInstructions()" class="btn btn-cyan btn-sm w-full justify-center">
            <svg width="11" height="11"><use href="#i-check"/></svg>Save Changes
        </button>

        <!-- Danger zone -->
        <div class="mt-4 pt-3" style="border-top:1px solid rgba(239,68,68,.15);">
            <div class="mono text-[9px] uppercase tracking-widest mb-2" style="color:rgba(239,68,68,.6);">// Danger Zone</div>
            <button id="det-delete-btn" onclick="initDeleteAgent()" class="btn btn-sm w-full justify-center" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;transition:all .15s;">
                <svg width="11" height="11"><use href="#i-x"/></svg>Delete Agent
            </button>
            <div class="mono text-[8px] mt-1.5 text-center" style="color:var(--m);">Permanently removes this agent. Child agents re-parent automatically.</div>
        </div>
    </div>`;
}

// ── DELETE AGENT (two-step confirm) ──
let deleteConfirmTimer = null;
function initDeleteAgent(){
    const btn = document.getElementById('det-delete-btn');
    if(!btn) return;
    if(deleteConfirmTimer) clearTimeout(deleteConfirmTimer);
    btn.innerHTML = '<svg width="11" height="11"><use href="#i-x"/></svg>Click again to confirm';
    btn.style.background = 'rgba(239,68,68,.28)';
    btn.style.borderColor = 'rgba(239,68,68,.55)';
    btn.setAttribute('onclick', 'confirmDeleteAgent()');
    deleteConfirmTimer = setTimeout(()=>{
        btn.innerHTML = '<svg width="11" height="11"><use href="#i-x"/></svg>Delete Agent';
        btn.style.background = 'rgba(239,68,68,.1)';
        btn.style.borderColor = 'rgba(239,68,68,.25)';
        btn.setAttribute('onclick', 'initDeleteAgent()');
    }, 4000);
}

async function confirmDeleteAgent(){
    if(deleteConfirmTimer){ clearTimeout(deleteConfirmTimer); deleteConfirmTimer=null; }
    if(!detailAgent||IS_MOCK) return;
    const btn = document.getElementById('det-delete-btn');
    const id  = detailAgent.id;
    const name = detailAgent.name;
    if(btn){ btn.disabled=true; btn.textContent='Deleting…'; }
    try{
        const r = await fetch('/api/agents/delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
        const d = await r.json();
        if(d.ok){
            const idx = AGENTS.findIndex(a=>a.id===id);
            if(idx>=0) AGENTS.splice(idx,1);
            delete hbState[id]; delete tasks[id]; delete agSkills[id];
            closeAgentDetail();
            renderAgentGrid();
            if(currentSection==='dashboard') renderDashboard();
        } else {
            if(btn){ btn.disabled=false; btn.textContent='Delete failed — retry'; }
        }
    }catch(_){
        if(btn){ btn.disabled=false; btn.textContent='Network error — retry'; }
    }
}

async function saveAgentInstructions(){
    if(!detailAgent||IS_MOCK) return;
    const payload={
        id:detailAgent.id,
        system_prompt:document.getElementById('det-sysprompt').value,
        model:document.getElementById('det-model').value,
        temperature:parseFloat(document.getElementById('det-temp').value),
        is_active:document.getElementById('det-active').checked,
    };
    try{
        const r=await fetch('/api/agents/update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const d=await r.json();
        if(d.ok){
            const idx=AGENTS.findIndex(a=>a.id===detailAgent.id);
            if(idx>=0) Object.assign(AGENTS[idx],{system_prompt:payload.system_prompt,model:payload.model,temperature:payload.temperature,is_active:payload.is_active});
            detailAgent=AGENTS[idx];
            hbState[detailAgent.id]=payload.is_active?'active':'paused';
            const msg=document.getElementById('det-save-msg');
            if(msg){ msg.classList.remove('hidden'); setTimeout(()=>msg.classList.add('hidden'),2500); }
        }
    }catch(_){}
}

// ── SKILLS TAB ──
async function loadAndRenderDetailSkills(){
    if(!detailAgent) return;
    const el=document.getElementById('dtp-skills'); if(!el) return;
    el.innerHTML='<div class="p-8 text-center mono text-xs" style="color:var(--m);">// Loading skills…</div>';
    await loadAgentSkills(detailAgent.id);
    renderDetailSkills();
    // Update skills tab badge
    const count=(agSkills[detailAgent.id]||[]).length;
    const tabBtn=document.getElementById('dtab-skills');
    if(tabBtn) tabBtn.textContent='Skills'+(count>0?` (${count})`:'');
}

async function loadAgentSkills(agentId){
    try{
        const r=await fetch(`/api/skills/${encodeURIComponent(AGENTS.find(a=>a.id===agentId)?.slug||agentId)}`);
        const d=await r.json();
        agSkills[agentId]=d.ok?(d.skills||[]):[];
    }catch(_){ agSkills[agentId]=[]; }
}

// Skill type color config
function skillTypeStyle(type){
    const cfg={
        context:    {color:'#e8b454', bg:'rgba(232,180,84,.1)',  border:'rgba(232,180,84,.2)',  label:'context'},
        instruction:{color:'#e0a850', bg:'rgba(200,144,80,.1)', border:'rgba(200,144,80,.2)', label:'instruction'},
        tool:       {color:'#f0c040', bg:'rgba(240,160,0,.1)',  border:'rgba(240,160,0,.2)',  label:'tool'},
        memory:     {color:'#3dd68c', bg:'rgba(61,214,140,.1)',  border:'rgba(61,214,140,.2)',  label:'memory'},
    };
    return cfg[type]||cfg.context;
}

function skillTypeBadge(type){
    const s=skillTypeStyle(type);
    return `<span class="mono" style="font-size:8px;padding:1px 6px;border-radius:4px;background:${s.bg};border:1px solid ${s.border};color:${s.color};">${s.label}</span>`;
}

function renderDetailSkills(){
    if(!detailAgent) return;
    const el=document.getElementById('dtp-skills'); if(!el) return;
    const rows=agSkills[detailAgent.id]||[];

    let assignedHtml='';
    if(!rows.length){
        assignedHtml=`<div class="py-8 text-center">
            <svg width="28" height="28" class="mx-auto mb-2" style="color:rgba(200,144,80,.2);"><use href="#i-bolt"/></svg>
            <div class="mono text-xs mb-1" style="color:var(--m);">No skills assigned yet.</div>
            <div class="text-xs" style="color:var(--m);">Skills inject extra knowledge into this agent's brain.</div>
        </div>`;
    } else {
        assignedHtml=`<div class="space-y-2">`
            +rows.map(row=>{
                const s=row.skill||{};
                const ts=skillTypeStyle(s.skill_type||'context');
                const ctx=s.payload?.context||'';
                const preview=ctx.split('\n')[0].substring(0,80)+(ctx.split('\n')[0].length>80?'…':'');
                const isActive=s.is_active!==false;
                return `<div class="rounded-xl p-3" style="background:rgba(16,14,11,.55);border:1px solid ${ts.border};">
                    <div class="flex items-start gap-2.5">
                        <div class="mt-0.5 flex-shrink-0 grid place-items-center rounded-lg" style="width:26px;height:26px;background:${ts.bg};">
                            <svg width="11" height="11" style="color:${ts.color};"><use href="#i-bolt"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                <span class="text-xs font-semibold" style="color:#ece6da;">${h(s.name||'Unknown')}</span>
                                ${skillTypeBadge(s.skill_type||'context')}
                                ${!isActive?`<span class="mono text-[8px] px-1.5 py-0.5 rounded" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.15);color:#f87171;">inactive</span>`:''}
                            </div>
                            ${preview?`<div class="mono text-[9px] truncate" style="color:var(--m);">${h(preview)}</div>`:''}
                        </div>
                        <button onclick="removeAgentSkillDet('${h(detailAgent.id)}','${h(s.id)}')"
                            class="flex-shrink-0 p-1 rounded-lg transition-colors"
                            style="color:var(--m);"
                            onmouseenter="this.style.background='rgba(239,68,68,.1)';this.style.color='#f87171'"
                            onmouseleave="this.style.background='transparent';this.style.color='var(--m)'"
                            title="Remove skill">
                            <svg width="11" height="11"><use href="#i-x"/></svg>
                        </button>
                    </div>
                </div>`;
            }).join('')
            +`</div>`;
    }

    el.innerHTML=`<div class="p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div class="mono text-[9px] uppercase tracking-widest" style="color:var(--m);">${rows.length} skill${rows.length!==1?'s':''} assigned</div>
            <button onclick="toggleAddSkillPanel()" class="btn btn-violet btn-sm">
                <svg width="10" height="10"><use href="#i-plus"/></svg>${addSkillOpen?'Close':'Assign Skill'}
            </button>
        </div>

        ${assignedHtml}

        <div id="det-add-skill" class="${addSkillOpen?'':'hidden'} space-y-2 pt-1">
            <div class="sl">Available Skills</div>
            <div class="flex items-center gap-2 rounded-xl px-3 py-2" style="background:rgba(16,14,11,.8);border:1px solid rgba(235,225,200,.12);">
                <svg width="11" height="11" style="color:var(--m);flex-shrink:0;"><use href="#i-search"/></svg>
                <input id="det-sk-search" type="text" placeholder="Search by name or type…" class="flex-1 bg-transparent focus:outline-none text-sm" style="color:#ece6da;font-size:.8rem;" oninput="filterSkillSearch(this.value)">
            </div>
            <div id="det-all-skills" class="space-y-1.5" style="max-height:220px;overflow-y:auto;"></div>
        </div>
    </div>`;
    if(addSkillOpen) renderAddSkillListDet(allSkills);
}

async function toggleAddSkillPanel(){
    addSkillOpen=!addSkillOpen;
    if(addSkillOpen&&!allSkills.length){
        try{ const r=await fetch('/api/all-skills'); const d=await r.json(); allSkills=d.ok?(d.skills||[]):[]; }catch(_){ allSkills=[]; }
    }
    renderDetailSkills();
}

function filterSkillSearch(q){
    renderAddSkillListDet(allSkills.filter(s=>s.name.toLowerCase().includes(q.toLowerCase())));
}

function renderAddSkillListDet(skills){
    const el=document.getElementById('det-all-skills'); if(!el) return;
    const assigned=new Set((agSkills[detailAgent?.id]||[]).map(r=>r.skill?.id).filter(Boolean));
    if(!skills.length){
        el.innerHTML='<div class="mono text-xs text-center py-4" style="color:var(--m);">No skills found.</div>';
        return;
    }
    el.innerHTML=skills.map(s=>{
        const got=assigned.has(s.id);
        const ts=skillTypeStyle(s.skill_type||'context');
        const ctx=s.payload?.context||'';
        const preview=ctx.split('\n')[0].substring(0,60)+(ctx.split('\n')[0].length>60?'…':'');
        return `<div class="flex items-center gap-2.5 px-3 py-2 rounded-xl" style="background:rgba(16,14,11,.4);border:1px solid rgba(235,225,200,.05);">
            <div class="flex-shrink-0 grid place-items-center rounded-lg" style="width:22px;height:22px;background:${ts.bg};">
                <svg width="9" height="9" style="color:${ts.color};"><use href="#i-bolt"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                    <span class="text-xs font-medium truncate" style="color:#ece6da;">${h(s.name)}</span>
                    ${skillTypeBadge(s.skill_type||'context')}
                </div>
                ${preview?`<div class="mono truncate" style="font-size:8px;color:var(--m);">${h(preview)}</div>`:''}
            </div>
            <button onclick="assignDetSkill('${h(s.id)}')"
                ${got||IS_MOCK?'disabled':''}
                class="btn btn-xs flex-shrink-0"
                style="${got
                    ?'background:rgba(61,214,140,.08);color:#3dd68c;border:1px solid rgba(61,214,140,.2);cursor:default;'
                    :'background:rgba(200,144,80,.1);color:#e0a850;border:1px solid rgba(200,144,80,.22);'
                }">
                ${got?'✓ Added':'+ Assign'}
            </button>
        </div>`;
    }).join('');
}

async function assignDetSkill(skillId){
    if(IS_MOCK||!detailAgent) return;
    const slug=detailAgent.slug; if(!slug) return;
    const r=await fetch('/api/skills/assign',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug,skill_id:skillId})});
    const d=await r.json();
    if(d.ok){ await loadAgentSkills(detailAgent.id); renderDetailSkills(); }
}
async function removeAgentSkillDet(agentId, skillId){
    if(IS_MOCK) return;
    const slug=AGENTS.find(a=>a.id===agentId)?.slug; if(!slug) return;
    const r=await fetch('/api/skills/remove',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug,skill_id:skillId})});
    const d=await r.json();
    if(d.ok){ await loadAgentSkills(agentId); renderDetailSkills(); }
}

// ── RUNS TAB ──
async function loadDetailRuns(){
    if(!detailAgent) return;
    const el=document.getElementById('dtp-runs'); if(!el) return;
    el.innerHTML='<div class="mono text-xs text-center py-6" style="color:var(--m);">// Loading runs…</div>';
    try{
        const r=await fetch(`/api/agent-history/${encodeURIComponent(detailAgent.slug)}`);
        const d=await r.json();
        if(d.ok&&d.turns?.length){
            el.innerHTML='<div class="divide-y" style="border-color:rgba(235,225,200,.06);">'
                +d.turns.map(t=>{
                    const isU=t.role==='user';
                    return `<div class="flex ${isU?'justify-end':'justify-start'} px-4 py-3 gap-2">
                        <div class="${isU?'b-user':'b-bot'} px-3 py-2 text-xs leading-relaxed" style="max-width:90%;">
                            <div class="mono text-[8px] mb-1" style="color:var(--m);">${isU?'You':h(detailAgent.name)} · ${t.created_at?new Date(t.created_at).toLocaleTimeString():'--'}</div>
                            <p>${md(t.content)}</p>
                        </div>
                    </div>`;
                }).join('')+'</div>';
        } else {
            el.innerHTML='<div class="p-8 text-center"><svg width="28" height="28" class="mx-auto mb-2" style="color:rgba(235,225,200,.2);"><use href="#i-clock"/></svg><div class="mono text-xs" style="color:var(--m);">// No conversation history yet.</div></div>';
        }
    }catch(_){
        el.innerHTML='<div class="mono text-xs text-center py-6" style="color:var(--m);">// Load failed.</div>';
    }
}

// ══ HIRE AGENT ══
async function openHireModal(){
    if(IS_MOCK){ alert('Connect to Supabase to hire real agents.'); return; }
    hireSkillIds=new Set();
    ['hire-name','hire-role','hire-prompt'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('hire-temp').value=0.7;
    document.getElementById('hire-temp-val').textContent='0.70';
    document.getElementById('hire-model').value='claude-sonnet-4-6';
    document.getElementById('hire-error').classList.add('hidden');
    document.getElementById('hire-success').classList.add('hidden');
    const sel=document.getElementById('hire-parent');
    sel.innerHTML='<option value="">— No parent (standalone) —</option>';
    AGENTS.forEach(a=>{ const o=document.createElement('option'); o.value=a.id; o.textContent=`${a.name} (${a.role_title})`; sel.appendChild(o); });
    const sk=document.getElementById('hire-skills-list');
    sk.innerHTML='<div class="mono text-[9px] text-center py-2" style="color:var(--m);">Loading…</div>';
    document.getElementById('hire-modal').classList.add('open');
    try{
        const r=await fetch('/api/all-skills'); const d=await r.json();
        if(d.ok&&d.skills?.length){
            sk.innerHTML=d.skills.map(s=>`
                <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer hover:bg-white/5">
                    <input type="checkbox" value="${h(s.id)}" onchange="hireSkillIds.has('${h(s.id)}')?hireSkillIds.delete('${h(s.id)}'):hireSkillIds.add('${h(s.id)}')" style="accent-color:#e8b454;">
                    <span class="flex-1 text-xs truncate" style="color:#ddd0b4;">${h(s.name)}</span>
                    <span class="mono text-[8px]" style="color:var(--m);">${h(s.skill_type||'')}</span>
                </label>`).join('');
        } else { sk.innerHTML='<div class="mono text-[9px] text-center py-2" style="color:var(--m);">No skills available.</div>'; }
    }catch(_){ sk.innerHTML='<div class="mono text-[9px] text-center py-2" style="color:var(--m);">// Could not load skills.</div>'; }
}
function closeHireModal(){ document.getElementById('hire-modal').classList.remove('open'); }

async function submitHireAgent(){
    const name=document.getElementById('hire-name').value.trim();
    const role=document.getElementById('hire-role').value.trim();
    const errEl=document.getElementById('hire-error'); const okEl=document.getElementById('hire-success');
    errEl.classList.add('hidden'); okEl.classList.add('hidden');
    if(!name||!role){ errEl.textContent='Agent Name and Role Title are required.'; errEl.classList.remove('hidden'); return; }
    const btn=document.getElementById('hire-submit');
    btn.disabled=true; btn.textContent='Hiring…';
    const payload={name, role_title:role, division:CO.id,
        parent_id:document.getElementById('hire-parent').value||null,
        system_prompt:document.getElementById('hire-prompt').value.trim()||null,
        model:document.getElementById('hire-model').value,
        temperature:parseFloat(document.getElementById('hire-temp').value),
        skill_ids:[...hireSkillIds]};
    try{
        const r=await fetch('/api/agents/create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const d=await r.json();
        if(!d.ok){ errEl.textContent='Error: '+(d.error||'Unknown'); errEl.classList.remove('hidden'); }
        else{
            const a=d.agent;
            if(a){ AGENTS.push(a); hbState[a.id]=a.is_active?'active':'paused'; }
            okEl.textContent=`✓ ${name} hired successfully!`;
            okEl.classList.remove('hidden');
            if(currentSection==='agents') renderAgentGrid();
            if(currentSection==='dashboard') renderDashboard();
            setTimeout(()=>closeHireModal(), 2200);
        }
    }catch(_){ errEl.textContent='Network error.'; errEl.classList.remove('hidden'); }
    btn.disabled=false; btn.innerHTML='<svg width="11" height="11"><use href="#i-plus"/></svg>Hire Agent';
}

// ══ INIT ══
loadState();
updateInboxBadge();
updateIssuesBadge();

// Clean up interval on page leave
window.addEventListener('beforeunload', ()=>{
    if(issuesTimer){ clearInterval(issuesTimer); }
});
</script>
</body>
</html>
