<?php
/**
 * @var array  $chairman    name, title, initials
 * @var array  $companies   [{id, name, description, status, mock, agents[]}]
 * @var int    $totalAgents
 */
$activeCount = count(array_filter($companies, fn($c) => $c['status'] === 'active'));

$activeAgentsTotal = 0;
foreach ($companies as $c) {
    $activeAgentsTotal += count($c['agents']);
}

$hour    = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

// Build JSON payload for JS — strip avatar_emoji, add root agent slug per company
$coData = [];
foreach ($companies as $c) {
    $root = null;
    foreach ($c['agents'] as $a) {
        if (empty($a['parent_id'])) { $root = $a; break; }
    }
    if (!$root && !empty($c['agents'])) $root = $c['agents'][0];
    $coData[] = [
        'id'          => $c['id'],
        'name'        => $c['name'],
        'description' => $c['description'],
        'mock'        => $c['mock'] ?? false,
        'logo'        => $c['logo'] ?? '',
        'agents'      => array_map(fn($a) => [
            'id'        => $a['id'],
            'slug'      => $a['slug'],
            'name'      => $a['name'],
            'role_title'=> $a['role_title'],
            'parent_id' => $a['parent_id'] ?? null,
            'is_active' => $a['is_active'] ?? true,
        ], $c['agents']),
        'root_slug'   => $root ? $root['slug'] : null,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#020617">
<title>Command HQ · Mosbat AI</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
    /* Deep Slate & Indigo — B2B AI/SaaS palette */
    --c:#6366f1; --cb:#818cf8; --cd:#4f46e5;
    --cv:rgba(99,102,241,.10); --cg:rgba(99,102,241,.30);
    --v:#4f46e5; --vv:rgba(79,70,229,.12);
    --g:#22d3ee; --gv:rgba(34,211,238,.12);
    --a:#6366f1;
    --bg:#020617;
    --s:rgba(15,23,42,.88); --s2:rgba(15,23,42,.60); --s3:rgba(2,6,23,.96);
    --b:rgba(100,116,139,.16); --bh:rgba(99,102,241,.45);
    --t:#f8fafc; --t2:#94a3b8; --m:#64748b; --d:#0f172a;
    color-scheme:dark;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
    background:
        radial-gradient(ellipse 1100px 650px at 12% -8%, rgba(99,102,241,.06) 0%, transparent 55%),
        radial-gradient(ellipse 800px 500px at 100% 108%, rgba(34,211,238,.03) 0%, transparent 50%),
        var(--bg);
    min-height:100vh;color:var(--t);}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background-image:
        linear-gradient(rgba(100,116,139,.04) 1px,transparent 1px),
        linear-gradient(90deg,rgba(100,116,139,.04) 1px,transparent 1px);
    background-size:44px 44px;}
.circuit-bg{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.55;}
.gear{position:fixed;z-index:0;pointer-events:none;color:var(--c);opacity:.06;}
.gear svg{display:block;animation:gspin 26s linear infinite;}
.gear.rev svg{animation:gspin 34s linear infinite reverse;}
@keyframes gspin{to{transform:rotate(360deg);}}
header,main,footer,.modal-wrap{position:relative;z-index:1;}

.mono{font-family:'JetBrains Mono',monospace;}
.glass{background:var(--s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--b);}
.glass2{background:var(--s2);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--b);}
.glass3{background:var(--s3);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid var(--b);}

/* Gradient text — indigo/cyan palette */
.gt{background:linear-gradient(90deg,#818cf8,#6366f1,#4f46e5);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.gtg{background:linear-gradient(90deg,#22d3ee,#0ea5e9);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.gtv{background:linear-gradient(90deg,#818cf8,#6366f1);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.gta{background:linear-gradient(90deg,#818cf8,#6366f1);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}

.hex{clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);}

/* Status dots */
.dot-c{width:6px;height:6px;border-radius:9999px;background:var(--c);flex-shrink:0;box-shadow:0 0 0 0 rgba(99,102,241,.6);animation:pC 2.4s cubic-bezier(.4,0,.6,1) infinite;}
.dot-g{width:6px;height:6px;border-radius:9999px;background:var(--g);flex-shrink:0;box-shadow:0 0 0 0 rgba(34,211,238,.6);animation:pG 2.6s cubic-bezier(.4,0,.6,1) infinite;}
.dot-m{width:6px;height:6px;border-radius:9999px;background:var(--m);flex-shrink:0;}
@keyframes pC{0%{box-shadow:0 0 0 0 rgba(99,102,241,.55);}70%{box-shadow:0 0 0 7px rgba(99,102,241,0);}100%{box-shadow:0 0 0 0 rgba(99,102,241,0);}}
@keyframes pG{0%{box-shadow:0 0 0 0 rgba(34,211,238,.55);}70%{box-shadow:0 0 0 7px rgba(34,211,238,0);}100%{box-shadow:0 0 0 0 rgba(34,211,238,0);}}

/* Section label */
.sl{display:flex;align-items:center;gap:10px;font-family:'JetBrains Mono',monospace;font-size:9.5px;letter-spacing:.22em;text-transform:uppercase;color:var(--m);}
.sl::before{content:'//';color:var(--c);opacity:.55;margin-right:2px;}
.sl::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,var(--b),transparent);}

/* Logo — dark box, indigo brain accent */
.logo{width:38px;height:38px;border-radius:10px;background:radial-gradient(circle at 50% 36%,rgba(99,102,241,.18),rgba(2,6,23,.8));border:1px solid rgba(99,102,241,.30);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 18px -5px rgba(99,102,241,.35);}

/* Metric tile */
.tile{position:relative;overflow:hidden;border-radius:16px;padding:1.1rem 1.2rem;background:var(--s);border:1px solid var(--b);transition:border-color .2s,transform .2s;}
.tile:hover{border-color:var(--bh);transform:translateY(-2px);}
.tile-val{font-size:1.9rem;font-weight:700;font-family:'JetBrains Mono',monospace;line-height:1;}
.tile-lbl{font-size:.62rem;font-family:'JetBrains Mono',monospace;letter-spacing:.18em;text-transform:uppercase;color:var(--m);margin-top:.5rem;}
.tile-ico{position:absolute;right:.9rem;top:.9rem;opacity:.28;}

/* Company card */
.co{position:relative;transition:border-color .2s,box-shadow .2s,transform .2s;border:1px solid var(--b);}
.co::before{content:'';position:absolute;top:0;left:1.25rem;right:1.25rem;height:1.5px;background:linear-gradient(90deg,transparent,var(--c) 50%,transparent);opacity:.45;}
.co:hover{border-color:var(--bh)!important;box-shadow:0 8px 36px -12px rgba(99,102,241,.22);transform:translateY(-2px);}

/* Tab bar */
.tab-btn{position:relative;padding:.5rem 1rem;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--m);transition:color .15s;background:none;border:none;cursor:pointer;}
.tab-btn.active{color:var(--c);}
.tab-btn.active::after{content:'';position:absolute;bottom:0;left:.5rem;right:.5rem;height:1.5px;background:var(--c);border-radius:2px;}
.tab-btn:hover:not(.active){color:var(--t);}

.sk-row{display:flex;align-items:start;gap:10px;padding:.75rem;border-radius:10px;border:1px solid var(--b);background:var(--s2);transition:border-color .15s;}
.sk-row:hover{border-color:rgba(99,102,241,.30);}

/* Inputs */
.inp{background:rgba(2,6,23,.8);border:1px solid var(--b);color:var(--t);border-radius:10px;padding:.5rem .75rem;font-size:.875rem;width:100%;transition:border-color .15s;font-family:'Inter',sans-serif;}
.inp:focus{outline:none;border-color:var(--bh);}
.inp::placeholder{color:var(--m);}
#qc-msg::placeholder{color:var(--m);opacity:.35;}
select.inp option{background:#0f172a;}
textarea.inp{resize:vertical;min-height:70px;}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:8px;font-size:.75rem;font-family:'JetBrains Mono',monospace;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;border:none;transition:all .15s;}
.btn-cyan{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#ffffff;font-weight:600;}
.btn-cyan:hover{filter:brightness(1.12);}
.btn-ghost{background:transparent;border:1px solid var(--b);color:var(--c);}
.btn-ghost:hover{background:var(--cv);border-color:var(--bh);}
.btn-violet{background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.28);color:#818cf8;}
.btn-violet:hover{background:rgba(99,102,241,.24);}
.btn-danger{background:rgba(248,113,113,.10);border:1px solid rgba(248,113,113,.22);color:#fca5a5;}
.btn-danger:hover{background:rgba(248,113,113,.20);}
.btn-green{background:rgba(34,211,238,.10);border:1px solid rgba(34,211,238,.22);color:var(--g);}
.btn-sm{padding:.3rem .7rem;font-size:10px;}

/* Modal */
.modal-wrap{position:fixed;inset:0;z-index:50;display:none;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto;}
.modal-wrap.open{display:flex;}
.modal-bkg{position:fixed;inset:0;background:rgba(7,6,4,.82);backdrop-filter:blur(8px);}
.modal-box{position:relative;width:100%;max-width:680px;border-radius:20px;overflow:hidden;margin:auto;}

::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:rgba(99,102,241,.25);border-radius:5px;}
.hidden{display:none!important;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}

/* ── Top-down org chart (flowchart) ── */
.orgchart{text-align:center;min-width:max-content;padding:8px 4px 16px;}
.orgchart ul{display:flex;justify-content:center;padding-top:24px;position:relative;list-style:none;margin:0;}
.orgchart li{list-style:none;position:relative;padding:24px 12px 0;}
/* connector: up-line + across-line */
.orgchart li::before,.orgchart li::after{content:'';position:absolute;top:0;right:50%;border-top:1.5px solid rgba(99,102,241,.30);width:50%;height:24px;}
.orgchart li::after{right:auto;left:50%;border-left:1.5px solid rgba(99,102,241,.30);}
.orgchart li:only-child::after,.orgchart li:only-child::before{display:none;}
.orgchart li:only-child{padding-top:24px;}
.orgchart li:first-child::before,.orgchart li:last-child::after{border:0 none;}
.orgchart li:last-child::before{border-right:1.5px solid rgba(99,102,241,.30);border-radius:0 7px 0 0;}
.orgchart li:first-child::after{border-radius:7px 0 0 0;}
.orgchart ul ul::before{content:'';position:absolute;top:0;left:50%;border-left:1.5px solid rgba(99,102,241,.30);width:0;height:24px;}
.orgchart > ul{padding-top:0;}
.orgchart > ul > li{padding-top:0;}
.orgchart li.collapsed > ul{display:none;}
/* node card */
.ocard{position:relative;display:inline-flex;flex-direction:column;align-items:center;gap:5px;min-width:128px;max-width:180px;padding:.8rem .85rem .7rem;border-radius:14px;border:1px solid var(--b);background:var(--s);transition:border-color .15s,transform .15s,box-shadow .15s;text-decoration:none;vertical-align:top;}
.ocard::before{content:'';position:absolute;top:0;left:18px;right:18px;height:2px;border-radius:2px;background:linear-gradient(90deg,transparent,var(--c),transparent);opacity:.5;}
.ocard:hover{border-color:var(--bh);transform:translateY(-2px);box-shadow:0 8px 26px -10px rgba(99,102,241,.28);}
.ocard.is-chairman{border-color:rgba(99,102,241,.45);background:linear-gradient(160deg,rgba(99,102,241,.12),rgba(15,23,42,.90));}
.ocard.is-company{cursor:pointer;border-color:rgba(99,102,241,.25);}
.ocard .oav{display:grid;place-items:center;font-weight:700;flex-shrink:0;}
.ocard .onm{font-size:.8rem;font-weight:600;color:var(--t);line-height:1.2;text-align:center;}
.ocard .orl{font-size:.62rem;font-family:'JetBrains Mono',monospace;color:var(--m);text-align:center;line-height:1.2;}
.octog{display:inline-flex;align-items:center;gap:4px;margin-top:3px;padding:2px 9px;border-radius:99px;font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.05em;background:rgba(99,102,241,.10);border:1px solid rgba(99,102,241,.28);color:var(--c);cursor:pointer;transition:all .15s;}
.octog:hover{background:rgba(99,102,241,.20);}
.octog .chev{transition:transform .2s;display:inline-block;}
li.collapsed > .ocard .octog .chev{transform:rotate(-90deg);}

/* ── Mobile responsiveness ── */
@media (max-width: 640px){
    .qc-row{flex-direction:column;align-items:stretch;}
    .qc-row select#qc-co{max-width:none!important;width:100%;}
    .qc-row #qc-btn{width:100%;justify-content:center;align-self:stretch!important;}
    .tile{padding:.85rem .9rem;}
    .tile-val{font-size:1.5rem;}
    header .h-16{height:auto;padding-top:.5rem;padding-bottom:.5rem;}
}
@media (max-width: 420px){
    .grid-cols-3{grid-template-columns:1fr 1fr;}
}
</style>
</head>
<body>

<!-- ══ CIRCUIT BOARD BACKGROUND ══ -->
<svg class="circuit-bg" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <pattern id="pcb" width="180" height="180" patternUnits="userSpaceOnUse">
      <g stroke="#6366f1" stroke-opacity="0.08" stroke-width="1" fill="none" stroke-linecap="round">
        <path d="M0 34 H44 M44 34 V78 M44 78 H96"/>
        <path d="M180 56 H132 V120 H78"/>
        <path d="M92 0 V40 M92 40 L122 70"/>
        <path d="M22 180 V134 H66"/>
        <path d="M150 180 V146 H180"/>
        <path d="M0 150 H30 V120"/>
      </g>
      <g fill="#6366f1" fill-opacity="0.14">
        <circle cx="44" cy="34" r="2.4"/><circle cx="96" cy="78" r="2.4"/>
        <circle cx="132" cy="56" r="2.4"/><circle cx="78" cy="120" r="2.4"/>
        <circle cx="92" cy="40" r="2.4"/><circle cx="122" cy="70" r="2.4"/>
        <circle cx="66" cy="134" r="2.4"/><circle cx="150" cy="146" r="2.4"/>
        <circle cx="30" cy="120" r="2.4"/>
      </g>
      <g stroke="#6366f1" stroke-opacity="0.07" fill="none">
        <rect x="106" y="22" width="16" height="9" rx="1"/>
        <rect x="14" y="92" width="9" height="16" rx="1"/>
      </g>
      <g stroke="#6366f1" stroke-opacity="0.07" fill="none">
        <path d="M148 86 l14 8.1 v16.2 l-14 8.1 l-14 -8.1 v-16.2 z"/>
        <path d="M34 30 l9 5.2 v10.4 l-9 5.2 l-9 -5.2 v-10.4 z"/>
      </g>
    </pattern>
  </defs>
  <rect width="100%" height="100%" fill="url(#pcb)"/>
</svg>

<!-- ══ ROTATING GEARS (decorative) ══ -->
<div class="gear" style="top:-26px;right:8%;">
  <svg width="150" height="150" viewBox="-14 -14 28 28"><path fill="currentColor" d="M0 -9 L2 -9 L2.5 -6 L4.5 -5 L7 -6.5 L8.5 -5 L7 -2.5 L8 -0.5 L11 0 L11 2 L8 2.5 L7 4.5 L8.5 7 L7 8.5 L4.5 7 L2.5 8 L2 11 L0 11 L-0.5 8 L-2.5 7 L-5 8.5 L-6.5 7 L-5 4.5 L-6 2.5 L-9 2 L-9 0 L-6 -0.5 L-5 -2.5 L-6.5 -5 L-5 -6.5 L-2.5 -5 L-0.5 -6 Z"/><circle cx="1" cy="1" r="3.2" fill="var(--bg)"/></svg>
</div>
<div class="gear rev" style="bottom:6%;left:-30px;">
  <svg width="200" height="200" viewBox="-14 -14 28 28"><path fill="currentColor" d="M0 -9 L2 -9 L2.5 -6 L4.5 -5 L7 -6.5 L8.5 -5 L7 -2.5 L8 -0.5 L11 0 L11 2 L8 2.5 L7 4.5 L8.5 7 L7 8.5 L4.5 7 L2.5 8 L2 11 L0 11 L-0.5 8 L-2.5 7 L-5 8.5 L-6.5 7 L-5 4.5 L-6 2.5 L-9 2 L-9 0 L-6 -0.5 L-5 -2.5 L-6.5 -5 L-5 -6.5 L-2.5 -5 L-0.5 -6 Z"/><circle cx="1" cy="1" r="3.2" fill="var(--bg)"/></svg>
</div>
<div class="gear" style="top:42%;right:-24px;">
  <svg width="110" height="110" viewBox="-14 -14 28 28"><path fill="currentColor" d="M0 -9 L2 -9 L2.5 -6 L4.5 -5 L7 -6.5 L8.5 -5 L7 -2.5 L8 -0.5 L11 0 L11 2 L8 2.5 L7 4.5 L8.5 7 L7 8.5 L4.5 7 L2.5 8 L2 11 L0 11 L-0.5 8 L-2.5 7 L-5 8.5 L-6.5 7 L-5 4.5 L-6 2.5 L-9 2 L-9 0 L-6 -0.5 L-5 -2.5 L-6.5 -5 L-5 -6.5 L-2.5 -5 L-0.5 -6 Z"/><circle cx="1" cy="1" r="3.2" fill="var(--bg)"/></svg>
</div>

<!-- ══ SVG DEFS ══ -->
<svg hidden aria-hidden="true"><defs>
<symbol id="i-brain" viewBox="0 0 48 48" fill="none"><path d="M24 3 L42 13.5 V34.5 L24 45 L6 34.5 V13.5 Z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M24 14 C19 13, 15 15.5, 14.5 19.5 C11.5 20.5, 10.5 24.5, 13.5 26.5 C11.5 30.5, 15 33.5, 19 31.5 C20 34.5, 24 34.5, 24 31" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round"/><path d="M24 14 C29 13, 33 15.5, 33.5 19.5 C36.5 20.5, 37.5 24.5, 34.5 26.5 C36.5 30.5, 33 33.5, 29 31.5 C28 34.5, 24 34.5, 24 31" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round"/><rect x="20" y="20" width="8" height="8" rx="1" fill="currentColor"/><path d="M22 20 V17 M26 20 V17 M22 28 V31 M26 28 V31 M20 22 H17 M20 26 H17 M28 22 H31 M28 26 H31" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></symbol>
<symbol id="i-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="4.5" width="10" height="7" rx="1.5"/><rect x="4" y="11.5" width="16" height="8" rx="2"/><line x1="12" y1="2" x2="12" y2="4.5"/><circle cx="12" cy="1.5" r="1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="8" r="1.2" fill="currentColor" stroke="none"/><circle cx="14.5" cy="8" r="1.2" fill="currentColor" stroke="none"/><path d="M9 16h6M4 15H2M22 15h-2"/></symbol>
<symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4.5 13H12L10.5 22L20 11H13L13 2Z"/></symbol>
<symbol id="i-gear" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></symbol>
<symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
<symbol id="i-activity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></symbol>
<symbol id="i-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></symbol>
<symbol id="i-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></symbol>
<symbol id="i-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></symbol>
<symbol id="i-building" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21V9h6v12"/></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></symbol>
</defs></svg>

<!-- ════════════════════ SETTINGS MODAL ════════════════════ -->
<div id="settings-modal" class="modal-wrap" onclick="if(event.target===this)closeSettings()">
    <div class="modal-bkg" onclick="closeSettings()"></div>
    <div class="modal-box glass3">
        <div class="px-6 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--b);">
            <div class="flex items-center gap-3">
                <div class="logo"><svg width="18" height="18" style="color:var(--c);"><use href="#i-gear"/></svg></div>
                <div>
                    <div class="mono text-[9px] tracking-[.22em] uppercase" style="color:var(--m);">// System</div>
                    <div class="font-semibold gt">Settings</div>
                </div>
            </div>
            <button onclick="closeSettings()" class="p-1.5 rounded-lg hover:bg-white/5" style="color:var(--m);"><svg width="15" height="15"><use href="#i-x"/></svg></button>
        </div>
        <div class="flex" style="border-bottom:1px solid var(--b);">
            <button class="tab-btn active" id="stab-skills" onclick="switchSettingsTab('skills')">Skills</button>
            <button class="tab-btn" id="stab-system" onclick="switchSettingsTab('system')">System</button>
        </div>

        <!-- SKILLS -->
        <div id="stp-skills" class="p-5 space-y-4" style="max-height:72vh;overflow-y:auto;">
            <div class="glass2 rounded-xl p-4 space-y-3">
                <div class="mono text-[9px] tracking-[.2em] uppercase" style="color:var(--m);">// Create New Skill</div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input id="sk-name" class="inp" placeholder="Skill name…">
                    <select id="sk-type" class="inp">
                        <option value="context">context</option>
                        <option value="instruction">instruction</option>
                        <option value="tool">tool</option>
                        <option value="memory">memory</option>
                    </select>
                </div>
                <textarea id="sk-ctx" class="inp" style="min-height:80px;" placeholder="Context / instructions injected into the agent's system prompt…"></textarea>
                <div class="flex justify-end">
                    <button onclick="createSkill()" class="btn btn-cyan"><svg width="12" height="12"><use href="#i-plus"/></svg>Create Skill</button>
                </div>
            </div>
            <div class="mono text-[9px] tracking-[.2em] uppercase mb-2" style="color:var(--m);">// All Skills</div>
            <div id="st-sk-list" class="space-y-2">
                <div class="mono text-xs text-center py-4" style="color:var(--m);">Loading skills…</div>
            </div>
        </div>

        <!-- SYSTEM -->
        <div id="stp-system" class="hidden p-5 space-y-4" style="max-height:72vh;overflow-y:auto;">
            <div class="glass2 rounded-xl p-4 space-y-3">
                <div class="mono text-[9px] tracking-[.2em] uppercase" style="color:var(--m);">// Heartbeat Schedule</div>
                <div class="flex items-center gap-3">
                    <input id="sys-hb-time" type="time" class="inp" value="17:00" style="max-width:130px;">
                    <select id="sys-hb-freq" class="inp" style="max-width:160px;">
                        <option>Daily</option><option>Weekdays only</option><option>Manual only</option>
                    </select>
                </div>
                <p class="text-xs" style="color:var(--m);">Agents receive an automated wake-up prompt at this time, generating a daily status report.</p>
            </div>
            <div class="glass2 rounded-xl p-4 space-y-3">
                <div class="mono text-[9px] tracking-[.2em] uppercase" style="color:var(--m);">// Memory Window</div>
                <div class="flex items-center gap-3">
                    <input id="sys-mem" type="number" class="inp" value="12" min="4" max="40" style="max-width:100px;">
                    <span class="text-sm" style="color:var(--m);">conversation turns per session</span>
                </div>
            </div>
            <div class="flex justify-end">
                <button onclick="alert('Settings saved (UI only — edit .env to persist)')" class="btn btn-cyan">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════ HEADER ════════════════════ -->
<header class="glass sticky top-0 z-30" style="border-bottom:1px solid var(--b);">
<div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="logo"><svg width="24" height="24" style="color:var(--c);"><use href="#i-brain"/></svg></div>
        <div>
            <div class="mono text-[9px] tracking-[.24em] uppercase" style="color:var(--m);">Mosbat AI</div>
            <div class="text-sm font-semibold tracking-wide leading-none mt-0.5 gt">Command HQ</div>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="hidden sm:flex items-center gap-3 mono text-[10px] px-3 py-1.5 rounded-lg" style="color:var(--m);background:var(--s2);border:1px solid var(--b);">
            <div class="flex items-center gap-1.5"><div class="dot-g"></div><span style="color:var(--g);">All systems nominal</span></div>
            <div style="width:1px;height:11px;background:var(--b);"></div>
            <span id="hdr-clock"></span>
        </div>
        <button onclick="openSettings()" class="btn btn-ghost btn-sm"><svg width="12" height="12"><use href="#i-gear"/></svg>Settings</button>
    </div>
</div>
</header>

<!-- ════════════════════ MAIN ════════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-7 space-y-7">

<!-- ── WELCOME + METRICS ── -->
<section class="grid lg:grid-cols-[1.3fr_2fr] gap-5 items-stretch">

    <!-- Welcome / Chairman -->
    <div class="glass rounded-2xl p-6 flex items-center gap-4 relative overflow-hidden">
        <div style="position:absolute;inset:0;background:radial-gradient(ellipse 300px 180px at 0% 0%,rgba(99,102,241,.10),transparent 70%);pointer-events:none;"></div>
        <?php if (!empty($chairman['logo'])): ?>
        <div class="flex-shrink-0 relative" style="width:60px;height:60px;">
            <img src="<?= esc($chairman['logo']) ?>" width="60" height="60" alt="<?= esc($chairman['name']) ?>" style="display:block;width:60px;height:60px;object-fit:contain;">
        </div>
        <?php else: ?>
        <div class="hex flex-shrink-0 grid place-items-center font-bold text-xl gt relative" style="width:60px;height:68px;background:linear-gradient(135deg,rgba(99,102,241,.22),rgba(79,70,229,.12));"><?= esc($chairman['initials']) ?></div>
        <?php endif; ?>
        <div class="relative">
            <div id="greeting-label" class="mono text-[9px] tracking-[.2em] uppercase mb-1" style="color:var(--m);"><?= $greeting ?></div>
            <h1 class="text-xl font-bold tracking-tight" style="color:var(--t);">Chairman <?= esc($chairman['name']) ?></h1>
            <div class="text-sm mt-0.5" style="color:var(--t2);"><?= esc($chairman['title']) ?></div>
        </div>
    </div>

    <!-- Metric tiles -->
    <div class="grid grid-cols-3 gap-4">
        <div class="tile">
            <svg class="tile-ico" width="20" height="20" style="color:#ffffff;opacity:0.35;"><use href="#i-building"/></svg>
            <div class="tile-val" style="color:#ffffff;"><?= $activeCount ?></div>
            <div class="tile-lbl">Companies</div>
        </div>
        <div class="tile">
            <svg class="tile-ico" width="20" height="20" style="color:#2090e8;opacity:0.35;"><use href="#i-bot"/></svg>
            <div class="tile-val" style="color:#ffffff;"><?= $totalAgents ?></div>
            <div class="tile-lbl">Total Agents</div>
        </div>
        <div class="tile">
            <svg class="tile-ico" width="20" height="20" style="color:var(--g);"><use href="#i-activity"/></svg>
            <div class="tile-val" style="color:#22d3ee;"><?= $activeAgentsTotal ?></div>
            <div class="tile-lbl">Active Agents</div>
        </div>
    </div>
</section>

<!-- ── QUICK COMMAND ── -->
<section class="glass rounded-2xl overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--b);">
        <div class="flex items-center gap-2.5">
            <svg width="15" height="15" style="color:var(--c);flex-shrink:0;"><use href="#i-zap"/></svg>
            <div>
                <span class="font-semibold text-sm" style="color:var(--t);">Quick Command</span>
            </div>
        </div>
        <div id="qc-status" class="hidden items-center gap-1.5 mono text-[9px]" style="color:var(--m);">
            <div class="dot-g"></div>Processing…
        </div>
    </div>
    <div class="p-4 space-y-3">
        <div class="flex gap-3 qc-row">
            <select id="qc-co" class="inp" style="max-width:230px;font-size:.82rem;">
                <option value="" disabled selected>Select a company</option>
                <?php foreach ($coData as $co): if (!$co['root_slug']) continue; ?>
                <option value="<?= esc($co['root_slug']) ?>" data-mock="<?= $co['mock'] ? '1' : '0' ?>"><?= esc($co['name']) ?><?= $co['mock'] ? ' (Demo)' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <textarea id="qc-msg" rows="1" placeholder="Issue a command to the selected company…"
                class="flex-1 resize-none rounded-xl px-4 py-2.5 text-sm focus:outline-none"
                style="background:rgba(14,12,9,.8);border:1px solid var(--b);color:var(--t);transition:border-color .15s;font-family:'Inter',sans-serif;max-height:120px;"
                onfocus="this.style.borderColor='var(--bh)'"
                onblur="this.style.borderColor='var(--b)'"></textarea>
            <button id="qc-btn" onclick="quickSend()" class="btn btn-cyan px-4 flex-shrink-0" style="align-self:flex-end;">
                <svg width="12" height="12"><use href="#i-send"/></svg>Send
            </button>
        </div>
        <div id="qc-reply" class="hidden rounded-xl p-4 text-sm leading-relaxed" style="background:rgba(14,12,9,.7);border:1px solid var(--b);color:var(--t);max-height:280px;overflow-y:auto;"></div>
    </div>
</section>

<!-- ── PORTFOLIO ── -->
<section>
    <div class="sl mb-4">Portfolio<span style="flex:0;"></span></div>
    <div class="grid sm:grid-cols-2 gap-4">
        <?php foreach ($companies as $company): ?>
        <?php $isMock = $company['mock'] ?? false; ?>
        <?php $activeAgents = count($company['agents']); ?>
        <a href="/company/<?= esc($company['id']) ?>" class="co glass rounded-2xl overflow-hidden block" style="text-decoration:none;">
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <?php if (!empty($company['logo'])): ?>
                    <div class="flex-shrink-0 grid place-items-center overflow-hidden" style="width:42px;height:42px;border-radius:10px;">
                        <img src="<?= esc($company['logo']) ?>" width="42" height="42" alt="<?= esc($company['name']) ?>" style="display:block;width:42px;height:42px;border-radius:10px;">
                    </div>
                    <?php else: ?>
                    <div class="hex flex-shrink-0 grid place-items-center" style="width:42px;height:48px;background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(79,70,229,.10));">
                        <svg width="18" height="18" style="color:var(--c);"><use href="#i-building"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-0.5">
                            <span class="font-semibold text-sm" style="color:var(--t);"><?= esc($company['name']) ?></span>
                            <?php if ($isMock): ?>
                            <span class="mono text-[8px] tracking-widest uppercase px-1.5 py-0.5 rounded" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.22);color:#818cf8;">Demo</span>
                            <?php else: ?>
                            <span class="mono text-[8px] tracking-widest uppercase px-1.5 py-0.5 rounded gtg" style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.20);">Live</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs" style="color:var(--m);line-height:1.5;"><?= esc($company['description']) ?></p>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4 pt-3" style="border-top:1px solid var(--b);">
                    <div class="flex items-center gap-4 mono text-[10px]" style="color:var(--m);">
                        <span class="flex items-center gap-1.5">
                            <svg width="11" height="11" style="color:var(--c);"><use href="#i-bot"/></svg>
                            <span class="gt font-medium"><?= $activeAgents ?></span> active · <?= count($company['agents']) ?> total
                        </span>
                        <span class="flex items-center gap-1.5">
                            <div class="<?= $isMock ? 'dot-m' : 'dot-g' ?>"></div>
                            <?= $isMock ? 'Deploying' : 'Online' ?>
                        </span>
                    </div>
                    <span class="btn btn-cyan btn-sm pointer-events-none">
                        <svg width="11" height="11"><use href="#i-arrow"/></svg>Enter
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── ORGANIZATION CHART ── -->
<section>
    <div class="sl mb-4">Organization<span style="flex:0;"></span></div>
    <div class="glass rounded-2xl p-5 overflow-x-auto">
        <div id="org-tree"></div>
    </div>
</section>

<footer class="mono text-center pb-8 pt-2" style="font-size:9.5px;color:var(--d);letter-spacing:.18em;">
    // MOSBAT AI · COMMAND HQ · <?= date('Y') ?> · ALL SYSTEMS NOMINAL
</footer>
</main>

<script>
const COMPANIES = <?= json_encode(array_values($coData)) ?>;
const CHAIRMAN  = <?= json_encode(['name'=>$chairman['name'],'title'=>$chairman['title'],'initials'=>$chairman['initials'],'logo'=>$chairman['logo']??'']) ?>;

let settingsTab = 'skills';
let allSettingsSkills = [];

// ══ ORG CHART (HQ — Chairman → Companies → Agents), top-down + collapsible ══
function ini(n){ return (n||'?').split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase(); }

function toggleOrg(el){
    const li = el.closest('li');
    if(li) li.classList.toggle('collapsed');
}

function renderHQOrg(){
    const el = document.getElementById('org-tree'); if(!el) return;

    function agentNode(a){
        const dot = (a.is_active!==false) ? 'dot-g' : 'dot-m';
        return '<div class="ocard">'
            + '<div class="oav hex" style="width:30px;height:34px;font-size:.6rem;background:linear-gradient(135deg,rgba(99,102,241,.20),rgba(79,70,229,.12));color:var(--c);">'+ini(a.name)+'</div>'
            + '<div class="flex items-center gap-1.5"><div class="'+dot+'" style="width:5px;height:5px;"></div><span class="onm">'+h(a.name)+'</span></div>'
            + '<div class="orl">'+h(a.role_title||'')+'</div></div>';
    }

    function companyAgentTree(agents){
        if(!agents||!agents.length) return '';
        const byParent = {}; const ids = new Set(agents.map(a=>a.id));
        agents.forEach(a=>{ const p=a.parent_id||'__root'; (byParent[p]=byParent[p]||[]).push(a); });
        const roots = agents.filter(a=> !a.parent_id || !ids.has(a.parent_id));
        function node(a){
            const ch = byParent[a.id]||[];
            return '<li>'+agentNode(a)+(ch.length?'<ul>'+ch.map(node).join('')+'</ul>':'')+'</li>';
        }
        return '<ul>'+roots.map(node).join('')+'</ul>';
    }

    // Chairman → companies (companies collapsed by default)
    let companies = COMPANIES.map(co=>{
        const total = (co.agents||[]).length;
        const active = (co.agents||[]).length;
        const coAvatar = co.logo
            ? '<img src="'+co.logo+'" width="36" height="36" alt="'+h(co.name)+'" style="display:block;width:36px;height:36px;object-fit:contain;border-radius:8px;">'
            : '<div class="oav hex" style="width:32px;height:36px;background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(79,70,229,.10));"><svg width="15" height="15" style="color:var(--c);"><use href="#i-building"/></svg></div>';
        const card = '<div class="ocard is-company">'
            + coAvatar
            + '<a href="/company/'+encodeURIComponent(co.id)+'" class="onm" style="text-decoration:none;">'+h(co.name)+'</a>'
            + '<div class="orl">'+active+' / '+total+' active</div>'
            + (total>0 ? '<span class="octog" onclick="toggleOrg(this)" style="color:#22d3ee;"><span class="chev">▾</span> '+total+' agents</span>' : '')
            + '</div>';
        return '<li class="collapsed">'+card+companyAgentTree(co.agents)+'</li>';
    }).join('');

    const chairmanAvatar = CHAIRMAN.logo
        ? '<img src="'+CHAIRMAN.logo+'" width="38" height="38" alt="'+h(CHAIRMAN.name)+'" style="display:block;width:38px;height:38px;object-fit:contain;">'
        : '<div class="oav hex gt" style="width:34px;height:39px;font-size:.78rem;background:linear-gradient(135deg,rgba(99,102,241,.22),rgba(79,70,229,.14));">'+h(CHAIRMAN.initials)+'</div>';
    const chairman = '<div class="ocard is-chairman">'
        + chairmanAvatar
        + '<div class="onm">'+h(CHAIRMAN.name)+'</div>'
        + '<div class="orl">'+h(CHAIRMAN.title)+'</div></div>';

    el.innerHTML = '<div class="orgchart"><ul><li>'+chairman+'<ul>'+companies+'</ul></li></ul></div>';
}
renderHQOrg();

(function tick(){
    const el = document.getElementById('hdr-clock');
    if(el) el.textContent = new Date().toLocaleTimeString('en-GB',{hour12:false});
    setTimeout(tick,1000);
})();

function openSettings(){ document.getElementById('settings-modal').classList.add('open'); loadSettingsSkills(); }
function closeSettings(){ document.getElementById('settings-modal').classList.remove('open'); }

function switchSettingsTab(name){
    settingsTab = name;
    ['skills','system'].forEach(t=>{
        document.getElementById(`stab-${t}`).classList.toggle('active', t===name);
        document.getElementById(`stp-${t}`).classList.toggle('hidden', t!==name);
    });
}

function h(s){ if(s==null)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function loadSettingsSkills(){
    document.getElementById('st-sk-list').innerHTML='<div class="mono text-xs text-center py-4" style="color:var(--m);">Loading…</div>';
    const r=await fetch('/api/all-skills'); const d=await r.json();
    allSettingsSkills=d.ok?(d.skills||[]):[];
    renderSettingsSkills(allSettingsSkills);
}

function renderSettingsSkills(skills){
    const el=document.getElementById('st-sk-list');
    if(!skills.length){el.innerHTML='<div class="mono text-xs text-center py-4" style="color:var(--m);">// No skills yet. Create one above.</div>';return;}
    el.innerHTML=skills.map(s=>`
    <div class="sk-row" id="sk-item-${h(s.id)}">
        <div class="hex grid place-items-center flex-shrink-0" style="width:28px;height:32px;background:rgba(200,144,80,.15);">
            <svg width="12" height="12" style="color:#d8a860;"><use href="#i-bolt"/></svg>
        </div>
        <div class="flex-1 min-w-0 space-y-0.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-medium" style="color:var(--t);">${h(s.name)}</span>
                <span class="mono text-[9px] px-1.5 py-0.5 rounded" style="background:rgba(232,180,84,.07);border:1px solid rgba(232,180,84,.12);color:var(--m);">${h(s.skill_type||'')}</span>
                <span class="mono text-[9px] px-1.5 rounded ${s.is_active?'gtg':''}" style="background:${s.is_active?'rgba(61,214,140,.07)':'rgba(120,110,90,.07)'};border:1px solid ${s.is_active?'rgba(61,214,140,.15)':'rgba(120,110,90,.15)'};">${s.is_active?'Active':'Inactive'}</span>
            </div>
            <div id="sk-view-${h(s.id)}">${s.payload?.context?`<div class="text-xs" style="color:var(--m);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${h(s.payload.context)}</div>`:''}</div>
            <div id="sk-edit-${h(s.id)}" class="hidden space-y-2 pt-1">
                <input id="sk-en-${h(s.id)}" class="inp" style="font-size:.8rem;padding:.35rem .6rem;" value="${h(s.name)}">
                <div class="flex gap-2">
                    <select id="sk-et-${h(s.id)}" class="inp" style="font-size:.8rem;padding:.35rem .6rem;">
                        <option ${s.skill_type==='context'?'selected':''}>context</option>
                        <option ${s.skill_type==='instruction'?'selected':''}>instruction</option>
                        <option ${s.skill_type==='tool'?'selected':''}>tool</option>
                        <option ${s.skill_type==='memory'?'selected':''}>memory</option>
                    </select>
                    <label class="flex items-center gap-1.5 mono text-[10px]" style="color:var(--m);white-space:nowrap;">
                        <input type="checkbox" id="sk-ea-${h(s.id)}" ${s.is_active?'checked':''}> Active
                    </label>
                </div>
                <textarea id="sk-ec-${h(s.id)}" class="inp" style="font-size:.8rem;min-height:60px;">${h(s.payload?.context||'')}</textarea>
                <div class="flex gap-2">
                    <button onclick="saveSkillEdit('${h(s.id)}')" class="btn btn-cyan btn-sm">Save</button>
                    <button onclick="cancelSkillEdit('${h(s.id)}')" class="btn btn-ghost btn-sm">Cancel</button>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-1.5 flex-shrink-0">
            <button onclick="showSkillEdit('${h(s.id)}')" class="btn btn-ghost btn-sm"><svg width="10" height="10"><use href="#i-edit"/></svg></button>
            <button onclick="deleteSkill('${h(s.id)}')" class="btn btn-danger btn-sm"><svg width="10" height="10"><use href="#i-trash"/></svg></button>
        </div>
    </div>`).join('');
}

function showSkillEdit(id){ document.getElementById(`sk-view-${id}`).classList.add('hidden'); document.getElementById(`sk-edit-${id}`).classList.remove('hidden'); }
function cancelSkillEdit(id){ document.getElementById(`sk-view-${id}`).classList.remove('hidden'); document.getElementById(`sk-edit-${id}`).classList.add('hidden'); }

async function createSkill(){
    const name=document.getElementById('sk-name').value.trim();
    const type=document.getElementById('sk-type').value;
    const ctx=document.getElementById('sk-ctx').value.trim();
    if(!name){alert('Skill name is required.');return;}
    const r=await fetch('/api/skills/create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,skill_type:type,context:ctx})});
    const d=await r.json();
    if(d.ok){document.getElementById('sk-name').value='';document.getElementById('sk-ctx').value='';loadSettingsSkills();}
    else alert('Failed: '+(d.error||'unknown'));
}

async function saveSkillEdit(id){
    const name=document.getElementById(`sk-en-${id}`).value.trim();
    const type=document.getElementById(`sk-et-${id}`).value;
    const ctx=document.getElementById(`sk-ec-${id}`).value.trim();
    const active=document.getElementById(`sk-ea-${id}`).checked;
    const r=await fetch('/api/skills/update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,name,skill_type:type,context:ctx,is_active:active})});
    const d=await r.json(); if(d.ok)loadSettingsSkills(); else alert('Update failed.');
}

async function deleteSkill(id){
    if(!confirm('Delete this skill? It will be removed from all agents.'))return;
    const r=await fetch('/api/skills/delete-skill',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    const d=await r.json(); if(d.ok)loadSettingsSkills();
}

// ── QUICK COMMAND ──
function md(raw){
    if(!raw) return '';
    let s = h(raw);
    s = s.replace(/`([^`\n]+)`/g,'<code style="background:rgba(40,30,10,.85);border-radius:4px;padding:1px 6px;font-family:\'JetBrains Mono\',monospace;font-size:.78em;color:#e8b454;">$1</code>');
    s = s.replace(/^### (.+)$/gm,'<div style="font-size:.82rem;font-weight:700;color:var(--t);margin:.55rem 0 .15rem;">$1</div>');
    s = s.replace(/^## (.+)$/gm,'<div style="font-size:.92rem;font-weight:700;color:#ffcf6e;margin:.65rem 0 .2rem;">$1</div>');
    s = s.replace(/^# (.+)$/gm,'<div style="font-size:1rem;font-weight:700;color:#e8b454;margin:.75rem 0 .25rem;">$1</div>');
    s = s.replace(/\*\*\*(.+?)\*\*\*/g,'<strong><em>$1</em></strong>');
    s = s.replace(/\*\*(.+?)\*\*/g,'<strong style="color:var(--t);font-weight:600;">$1</strong>');
    s = s.replace(/_([^_\n]+)_/g,'<em style="color:var(--t2);">$1</em>');
    s = s.replace(/^→ (.+)$/gm,'<div style="margin:.2rem 0;padding:.2rem .6rem;border-left:2px solid rgba(232,180,84,.35);color:var(--t2);font-size:.85rem;">→ $1</div>');
    s = s.replace(/^[•\-\*] (.+)$/gm,'<div style="margin:.15rem 0;display:flex;gap:.4rem;"><span style="color:#e8b454;flex-shrink:0;">▸</span><span>$1</span></div>');
    s = s.replace(/^(\d+)\. (.+)$/gm,'<div style="margin:.15rem 0;display:flex;gap:.4rem;"><span style="color:#e8b454;font-family:\'JetBrains Mono\',monospace;font-size:.78em;flex-shrink:0;margin-top:.15em;">$1.</span><span>$2</span></div>');
    s = s.replace(/\n{2,}/g,'</p><p style="margin:.3rem 0;">');
    s = s.replace(/\n/g,'<br>');
    return s;
}

const qcMsg = document.getElementById('qc-msg');
if(qcMsg){
    qcMsg.addEventListener('input',()=>{ qcMsg.style.height='auto'; qcMsg.style.height=Math.min(qcMsg.scrollHeight,120)+'px'; });
    qcMsg.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();quickSend();} });
}

async function quickSend(){
    const sel = document.getElementById('qc-co');
    const slug = sel?.value?.trim();
    const msg  = document.getElementById('qc-msg')?.value?.trim();
    if(!slug||!msg) return;

    const isMock = sel.options[sel.selectedIndex]?.dataset?.mock === '1';
    const replyEl = document.getElementById('qc-reply');
    const statusEl = document.getElementById('qc-status');
    const btn = document.getElementById('qc-btn');

    if(isMock){
        replyEl.classList.remove('hidden');
        replyEl.innerHTML='<span style="color:#e8a838;">⚠ Demo company — connect Supabase to enable live commands.</span>';
        return;
    }

    btn.disabled=true;
    statusEl.classList.remove('hidden'); statusEl.classList.add('flex');
    replyEl.classList.remove('hidden');
    replyEl.innerHTML='<div style="display:flex;gap:4px;align-items:center;"><span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#80786a;animation:pC 1.2s ease-in-out infinite;"></span><span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#80786a;animation:pC 1.2s ease-in-out .15s infinite;"></span><span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#80786a;animation:pC 1.2s ease-in-out .3s infinite;"></span></div>';

    try{
        const r = await fetch('/api/chat/stream',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug,message:msg})});
        if(!r.ok||!r.body) throw new Error('Stream failed');

        const reader = r.body.getReader(), decoder = new TextDecoder();
        let lineBuf = '', fullText = '';
        replyEl.innerHTML = '';

        while(true){
            const {done,value} = await reader.read();
            if(done) break;
            lineBuf += decoder.decode(value,{stream:true});
            const lines = lineBuf.split('\n'); lineBuf = lines.pop();
            for(const line of lines){
                if(!line.startsWith('data: ')) continue;
                let ev; try{ ev=JSON.parse(line.slice(6)); }catch(_){ continue; }
                if(ev.type==='chunk'&&ev.text){
                    fullText += ev.text;
                    replyEl.innerHTML = '<p style="margin-bottom:.35rem;">'+md(fullText)+'</p>'
                        +'<span style="display:inline-block;width:2px;height:1em;background:#e8b454;vertical-align:text-bottom;animation:blink 1s step-end infinite;margin-left:1px;"></span>';
                }
                if(ev.type==='done'||ev.type==='error'){
                    replyEl.innerHTML = ev.type==='error'
                        ? '<span style="color:#f08a8a;">⚠ '+(ev.error||'Error')+'</span>'
                        : '<p>'+md(fullText)+'</p>';
                    document.getElementById('qc-msg').value='';
                    qcMsg.style.height='auto';
                }
            }
        }
        if(fullText) replyEl.innerHTML='<p>'+md(fullText)+'</p>';
    }catch(err){
        replyEl.innerHTML='<span style="color:#f08a8a;">⚠ Connection error.</span>';
    }
    btn.disabled=false;
    statusEl.classList.add('hidden'); statusEl.classList.remove('flex');
}
</script>
<script>
(function(){
    function updateGreeting(){
        var h = new Date().getHours();
        var label = h < 12 ? 'Good Morning' : h < 18 ? 'Good Afternoon' : 'Good Evening';
        var el = document.getElementById('greeting-label');
        if(el) el.textContent = label;
    }
    updateGreeting();
    setInterval(updateGreeting, 60000);
})();
</script>
</body>
</html>
