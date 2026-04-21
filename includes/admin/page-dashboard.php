<?php
/**
 * Dashboard admin page template — Modern animated UI.
 *
 * Variables:
 *   $summary          array   — total_cost_usd, total_generations, this_month_cost, …
 *   $voice_status     bool    — brand voice profile active?
 *   $providers        array   — slug => { name, has_key, … }
 *   $connected        int     — providers with keys
 *   $default_provider string  — active default provider slug
 *   $api_base         string
 *   $nonce            string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/* ── Derived values ─────────────────────────────────────────────────────── */
$gen_count      = (int)   ( $summary['total_generations'] ?? 0 );
$month_cost     = (float) ( $summary['this_month_cost']   ?? 0 );
$total_cost     = (float) ( $summary['total_cost_usd']    ?? 0 );
$dp_name        = '';
$dp_connected   = false;
if ( $default_provider && isset( $providers[ $default_provider ] ) ) {
	$dp_name      = $providers[ $default_provider ]['name'] ?? ucfirst( $default_provider );
	$dp_connected = ! empty( $providers[ $default_provider ]['has_key'] );
}

$provider_meta = [
	'openai'     => [ 'label' => 'OpenAI',     'logo' => 'https://cdn.simpleicons.org/openai/10a37f',      'color' => '#10a37f', 'bg' => '#f0fdf9', 'fallback' => '🤖' ],
	'anthropic'  => [ 'label' => 'Anthropic',  'logo' => 'https://cdn.simpleicons.org/anthropic/e07b39',   'color' => '#e07b39', 'bg' => '#fff8f4', 'fallback' => '🧠' ],
	'gemini'     => [ 'label' => 'Gemini',     'logo' => 'https://cdn.simpleicons.org/googlegemini/4285f4','color' => '#4285f4', 'bg' => '#eff6ff', 'fallback' => '💎' ],
	'mistral'    => [ 'label' => 'Mistral',    'logo' => 'https://cdn.simpleicons.org/mistralai/ff7000',   'color' => '#ff7000', 'bg' => '#fff7ed', 'fallback' => '🌊' ],
	'deepseek'   => [ 'label' => 'DeepSeek',   'logo' => 'https://cdn.simpleicons.org/deepseek/4d6bfe',    'color' => '#4d6bfe', 'bg' => '#f0f0ff', 'fallback' => '🔍' ],
	'openrouter' => [ 'label' => 'OpenRouter',  'logo' => 'https://cdn.simpleicons.org/openrouter/6467f2', 'color' => '#6467f2', 'bg' => '#eef2ff', 'fallback' => '📡' ],
	'ollama'     => [ 'label' => 'Ollama',     'logo' => 'https://cdn.simpleicons.org/ollama/000000',      'color' => '#374151', 'bg' => '#f9fafb', 'fallback' => '🖥️' ],
];

$quick_links = [
	[ 'icon'=>'📝', 'color'=>'#8b5cf6', 'bg'=>'#f5f3ff', 'title'=> __('Generate Article','super-fast-blog-ai'), 'desc'=> __('Write a full AI blog post','super-fast-blog-ai'), 'slug'=>'sfba-generate', 'query'=>'&tab=article' ],
	[ 'icon'=>'🔤', 'color'=>'#3b82f6', 'bg'=>'#eff6ff', 'title'=> __('Generate Titles','super-fast-blog-ai'),  'desc'=> __('10–20 catchy titles in seconds','super-fast-blog-ai'), 'slug'=>'sfba-generate', 'query'=>'&tab=titles'  ],
	[ 'icon'=>'📅', 'color'=>'#16a34a', 'bg'=>'#f0fdf4', 'title'=> __('Content Calendar','super-fast-blog-ai'), 'desc'=> __('Plan your publishing schedule','super-fast-blog-ai'), 'slug'=>'sfba-content-calendar', 'query'=>'' ],
	[ 'icon'=>'♻️', 'color'=>'#6366f1', 'bg'=>'#eef2ff', 'title'=> __('Repurpose','super-fast-blog-ai'),         'desc'=> __('Turn posts into social content','super-fast-blog-ai'), 'slug'=>'sfba-repurpose', 'query'=>'' ],
	[ 'icon'=>'🔗', 'color'=>'#0ea5e9', 'bg'=>'#f0f9ff', 'title'=> __('Internal Links','super-fast-blog-ai'),   'desc'=> __('AI-suggested link insertion','super-fast-blog-ai'), 'slug'=>'sfba-internal-links', 'query'=>'' ],
	[ 'icon'=>'🎨', 'color'=>'#7c3aed', 'bg'=>'#f5f3ff', 'title'=> __('Brand Voice','super-fast-blog-ai'),       'desc'=> __('Make AI write like you','super-fast-blog-ai'), 'slug'=>'sfba-brand-voice', 'query'=>'' ],
];

$all_modules = [
	[ 'icon'=>'📊', 'color'=>'#14b8a6', 'title'=> __('Performance','super-fast-blog-ai'),   'slug'=>'sfba-performance',  'query'=>'' ],
	[ 'icon'=>'💰', 'color'=>'#9333ea', 'title'=> __('Cost Tracker','super-fast-blog-ai'),   'slug'=>'sfba-cost-tracker', 'query'=>'' ],
	[ 'icon'=>'🔀', 'color'=>'#6366f1', 'title'=> __('Model Routing','super-fast-blog-ai'),  'slug'=>'sfba-model-routing','query'=>'' ],
	[ 'icon'=>'⚙️', 'color'=>'#64748b', 'title'=> __('Settings','super-fast-blog-ai'),       'slug'=>'sfba-settings',     'query'=>'' ],
];
?>
<style>
/* ═══════════════════════════════════════════════════════════════
   SFBA Dashboard — Modern Animated UI
═══════════════════════════════════════════════════════════════ */
#sfba-dash * { box-sizing: border-box; }
#sfba-dash { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

/* ── Keyframes ──────────────────────────────────────────────── */
@keyframes sfba-d-blob1 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(20px,-24px) scale(1.07)} }
@keyframes sfba-d-blob2 { 0%,100%{transform:translate(0,0) scale(1)} 60%{transform:translate(-16px,20px) scale(1.05)} }
@keyframes sfba-d-blob3 { 0%,100%{transform:translate(0,0) scale(1)} 40%{transform:translate(12px,12px) scale(1.09)} }
@keyframes sfba-d-slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:none} }
@keyframes sfba-d-fadeIn  { from{opacity:0} to{opacity:1} }
@keyframes sfba-d-badgePop{ 0%{opacity:0;transform:scale(.7)} 70%{transform:scale(1.06)} 100%{opacity:1;transform:scale(1)} }
@keyframes sfba-d-pulse { 0%,100%{box-shadow:0 0 0 0 var(--pulse-c)} 60%{box-shadow:0 0 0 7px transparent} }
@keyframes sfba-d-cardIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
@keyframes sfba-d-statCount { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
@keyframes sfba-d-shimmer { 0%{background-position:-600px 0} 100%{background-position:600px 0} }

/* ── Hero ───────────────────────────────────────────────────── */
.sfba-d-hero {
	position: relative;
	border-radius: 20px;
	overflow: hidden;
	background: #0f172a;
	padding: 0;
	margin-bottom: 28px;
	min-height: 220px;
	display: flex;
	align-items: stretch;
}
.sfba-d-hero-blobs {
	position: absolute;
	inset: 0;
	overflow: hidden;
	pointer-events: none;
}
.sfba-d-blob {
	position: absolute;
	border-radius: 50%;
	filter: blur(55px);
	opacity: .5;
}
.sfba-d-blob--1 { width:360px;height:360px;background:#4f46e5;top:-130px;right:-40px;  animation:sfba-d-blob1 9s ease-in-out infinite; }
.sfba-d-blob--2 { width:260px;height:260px;background:#7c3aed;bottom:-90px;left:8%;    animation:sfba-d-blob2 11s ease-in-out infinite; }
.sfba-d-blob--3 { width:180px;height:180px;background:#06b6d4;top:10%;left:48%;        animation:sfba-d-blob3 7s ease-in-out infinite; }
.sfba-d-hero-grid {
	position: absolute;
	inset: 0;
	background-image: radial-gradient(circle,rgba(255,255,255,.1) 1px,transparent 1px);
	background-size: 28px 28px;
}
.sfba-d-hero-inner {
	position: relative;
	z-index: 2;
	padding: 36px 44px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 32px;
	flex: 1;
	animation: sfba-d-slideUp .7s cubic-bezier(.22,.6,.36,1) both;
}
.sfba-d-hero-left {}
.sfba-d-hero-pill {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	background: rgba(255,255,255,.1);
	backdrop-filter: blur(8px);
	border: 1px solid rgba(255,255,255,.18);
	border-radius: 100px;
	padding: 4px 13px 4px 7px;
	font-size: 11.5px;
	font-weight: 600;
	color: rgba(255,255,255,.9);
	margin-bottom: 14px;
	animation: sfba-d-badgePop .6s .15s cubic-bezier(.34,1.56,.64,1) both;
}
.sfba-d-hero-dot {
	width: 7px; height: 7px;
	border-radius: 50%;
	background: #34d399;
	box-shadow: 0 0 5px #34d399;
}
.sfba-d-hero h1 {
	margin: 0 0 8px;
	font-size: 26px;
	font-weight: 800;
	color: #fff;
	line-height: 1.2;
	letter-spacing: -.3px;
}
.sfba-d-hero h1 .sfba-d-hl {
	background: linear-gradient(90deg,#818cf8,#38bdf8);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}
.sfba-d-hero-sub {
	font-size: 13.5px;
	color: rgba(255,255,255,.6);
	margin: 0;
	line-height: 1.6;
}
.sfba-d-hero-right {
	display: flex;
	flex-direction: column;
	gap: 10px;
	flex-shrink: 0;
}
.sfba-d-hero-cta {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 11px 22px;
	border-radius: 10px;
	font-size: 13px;
	font-weight: 700;
	text-decoration: none;
	white-space: nowrap;
	transition: transform .18s, box-shadow .18s, opacity .18s;
}
.sfba-d-hero-cta:hover { transform:translateY(-2px); opacity:.9; }
.sfba-d-hero-cta--primary {
	background: #fff;
	color: #1e1b4b;
	box-shadow: 0 4px 16px rgba(0,0,0,.25);
}
.sfba-d-hero-cta--secondary {
	background: rgba(255,255,255,.12);
	border: 1px solid rgba(255,255,255,.22);
	color: #fff;
}
.sfba-d-hero-cta--secondary:hover { color:#fff; }

/* ── Status strip ───────────────────────────────────────────── */
.sfba-d-status-strip {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
	margin-bottom: 28px;
}
.sfba-d-stat {
	--pulse-c: rgba(99,102,241,.3);
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 16px;
	padding: 20px 22px;
	display: flex;
	align-items: center;
	gap: 16px;
	transition: border-color .2s, box-shadow .2s, transform .2s;
	animation: sfba-d-cardIn .5s ease both;
}
.sfba-d-stat:nth-child(1){ animation-delay:.05s }
.sfba-d-stat:nth-child(2){ animation-delay:.10s }
.sfba-d-stat:nth-child(3){ animation-delay:.15s }
.sfba-d-stat:nth-child(4){ animation-delay:.20s }
.sfba-d-stat:hover {
	border-color: var(--sfba-d-accent, #6366f1);
	box-shadow: 0 6px 24px rgba(99,102,241,.12);
	transform: translateY(-3px);
}
.sfba-d-stat-icon {
	width: 46px; height: 46px;
	border-radius: 13px;
	display: flex; align-items: center; justify-content: center;
	font-size: 21px;
	flex-shrink: 0;
}
.sfba-d-stat-val {
	font-size: 22px;
	font-weight: 800;
	line-height: 1.1;
	color: var(--sfba-d-accent, #6366f1);
	letter-spacing: -.3px;
}
.sfba-d-stat-val sup { font-size: 13px; font-weight: 600; vertical-align: super; }
.sfba-d-stat-val .sfba-d-muted { font-size: 14px; font-weight: 500; color: #cbd5e1; }
.sfba-d-stat-lbl {
	font-size: 11.5px;
	font-weight: 500;
	color: #94a3b8;
	margin-top: 2px;
	line-height: 1.4;
}

/* ── Section header ─────────────────────────────────────────── */
.sfba-d-sec {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 16px;
}
.sfba-d-sec h2 {
	margin: 0;
	font-size: 14px;
	font-weight: 700;
	color: #0f172a;
	white-space: nowrap;
}
.sfba-d-sec-line {
	flex: 1;
	height: 1.5px;
	background: linear-gradient(90deg,#e2e8f0,transparent);
}

/* ── Quick access grid ──────────────────────────────────────── */
.sfba-d-quick {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
	margin-bottom: 28px;
}
.sfba-d-qcard {
	--c: #6366f1;
	--bg: #eef2ff;
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 16px;
	padding: 20px 22px;
	text-decoration: none;
	color: inherit;
	display: flex;
	align-items: center;
	gap: 16px;
	transition: border-color .2s, box-shadow .2s, transform .2s;
	animation: sfba-d-cardIn .5s ease both;
	position: relative;
	overflow: hidden;
}
.sfba-d-qcard:nth-child(1){animation-delay:.06s}
.sfba-d-qcard:nth-child(2){animation-delay:.10s}
.sfba-d-qcard:nth-child(3){animation-delay:.14s}
.sfba-d-qcard:nth-child(4){animation-delay:.18s}
.sfba-d-qcard:nth-child(5){animation-delay:.22s}
.sfba-d-qcard:nth-child(6){animation-delay:.26s}
.sfba-d-qcard:hover {
	border-color: var(--c);
	box-shadow: 0 8px 28px rgba(0,0,0,.09);
	transform: translateY(-3px);
	color: inherit;
}
.sfba-d-qcard::after {
	content: '';
	position: absolute;
	inset: 0;
	background: linear-gradient(135deg, var(--bg) 0%, transparent 60%);
	opacity: 0;
	transition: opacity .2s;
	pointer-events: none;
}
.sfba-d-qcard:hover::after { opacity: 1; }
.sfba-d-qcard-icon {
	width: 44px; height: 44px;
	border-radius: 12px;
	background: var(--bg);
	display: flex; align-items: center; justify-content: center;
	font-size: 20px;
	flex-shrink: 0;
	transition: transform .2s;
	position: relative; z-index: 1;
}
.sfba-d-qcard:hover .sfba-d-qcard-icon { transform: scale(1.12) rotate(-4deg); }
.sfba-d-qcard-body { flex: 1; min-width: 0; position: relative; z-index: 1; }
.sfba-d-qcard-title {
	font-size: 13.5px;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 2px;
	display: block;
}
.sfba-d-qcard-desc {
	font-size: 11.5px;
	color: #94a3b8;
	display: block;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.sfba-d-qcard-arr {
	flex-shrink: 0;
	color: #e2e8f0;
	transition: color .2s, transform .2s;
	position: relative; z-index: 1;
}
.sfba-d-qcard:hover .sfba-d-qcard-arr { color: var(--c); transform: translateX(3px); }

/* ── Two-col layout ─────────────────────────────────────────── */
.sfba-d-cols {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 28px;
}

/* ── Provider status card ───────────────────────────────────── */
.sfba-d-card {
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 16px;
	padding: 22px 24px;
}
.sfba-d-prov-default {
	display: flex;
	align-items: center;
	gap: 10px;
	background: #f8fafc;
	border: 1.5px solid #e2e8f0;
	border-radius: 10px;
	padding: 11px 14px;
	margin-bottom: 16px;
}
.sfba-d-prov-default-icon { width:44px;height:44px;flex-shrink:0;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px; }
.sfba-d-prov-default-body { flex: 1; min-width: 0; }
.sfba-d-prov-default-name {
	font-size: 13px;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 1px;
}
.sfba-d-prov-default-lbl {
	font-size: 11px;
	color: #94a3b8;
	font-weight: 500;
}
.sfba-d-prov-pill {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: 100px;
	font-size: 11px;
	font-weight: 700;
	flex-shrink: 0;
}
.sfba-d-prov-pill--on  { background:#dcfce7; color:#15803d; }
.sfba-d-prov-pill--off { background:#fee2e2; color:#b91c1c; }
.sfba-d-prov-pill-dot {
	width: 6px; height: 6px;
	border-radius: 50%;
}
.sfba-d-prov-pill--on  .sfba-d-prov-pill-dot { background:#16a34a; }
.sfba-d-prov-pill--off .sfba-d-prov-pill-dot { background:#dc2626; }

.sfba-d-prov-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 8px;
}
.sfba-d-prov-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 5px;
	padding: 10px 8px;
	border-radius: 10px;
	border: 1.5px solid #f1f5f9;
	background: #f8fafc;
	font-size: 11px;
	font-weight: 600;
	color: #64748b;
	text-decoration: none;
	transition: border-color .18s, background .18s, transform .18s;
	position: relative;
}
.sfba-d-prov-item:hover { transform: translateY(-2px); color: #334155; }
.sfba-d-prov-item--on  { border-color:#bbf7d0; background:#f0fdf4; color:#15803d; }
.sfba-d-prov-item--on:hover { border-color:#86efac; }
.sfba-d-prov-item--off:hover { border-color:#cbd5e1; background:#f1f5f9; }
.sfba-d-prov-icon { width:20px;height:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.sfba-d-prov-status {
	position: absolute;
	top: 6px; right: 6px;
	width: 7px; height: 7px;
	border-radius: 50%;
}
.sfba-d-prov-item--on  .sfba-d-prov-status { background: #22c55e; }
.sfba-d-prov-item--off .sfba-d-prov-status { background: #e2e8f0; }

/* ── Guide teaser ───────────────────────────────────────────── */
.sfba-d-guide {
	position: relative;
	border-radius: 16px;
	overflow: hidden;
	background: linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#1e3a5f 100%);
	padding: 26px 28px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	gap: 20px;
	min-height: 200px;
}
.sfba-d-guide-grid {
	position: absolute;
	inset: 0;
	background-image: radial-gradient(circle,rgba(255,255,255,.08) 1px,transparent 1px);
	background-size: 24px 24px;
	pointer-events: none;
}
.sfba-d-guide-glow {
	position: absolute;
	width: 200px; height: 200px;
	border-radius: 50%;
	background: #4f46e5;
	filter: blur(50px);
	opacity: .4;
	bottom: -60px; right: -30px;
	pointer-events: none;
}
.sfba-d-guide-top { position: relative; z-index: 1; }
.sfba-d-guide-tag {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: rgba(255,255,255,.1);
	border: 1px solid rgba(255,255,255,.18);
	border-radius: 100px;
	padding: 3px 11px;
	font-size: 11px;
	font-weight: 600;
	color: rgba(255,255,255,.85);
	margin-bottom: 10px;
}
.sfba-d-guide h3 {
	margin: 0 0 6px;
	font-size: 16px;
	font-weight: 800;
	color: #fff;
}
.sfba-d-guide p {
	margin: 0;
	font-size: 12.5px;
	color: rgba(255,255,255,.6);
	line-height: 1.6;
}
.sfba-d-guide-btn {
	position: relative;
	z-index: 1;
	display: inline-flex;
	align-items: center;
	gap: 7px;
	padding: 10px 20px;
	background: #fff;
	color: #1e1b4b;
	border-radius: 10px;
	font-size: 13px;
	font-weight: 700;
	text-decoration: none;
	width: fit-content;
	transition: transform .18s, box-shadow .18s;
}
.sfba-d-guide-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.25); color: #1e1b4b; }

/* ── Other modules row ──────────────────────────────────────── */
.sfba-d-other {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 12px;
	margin-bottom: 32px;
}
.sfba-d-other-item {
	display: flex;
	align-items: center;
	gap: 10px;
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 12px;
	padding: 13px 16px;
	text-decoration: none;
	color: #334155;
	font-size: 13px;
	font-weight: 600;
	transition: border-color .18s, background .18s, transform .18s;
	animation: sfba-d-cardIn .5s ease both;
}
.sfba-d-other-item:nth-child(1){animation-delay:.25s}
.sfba-d-other-item:nth-child(2){animation-delay:.30s}
.sfba-d-other-item:nth-child(3){animation-delay:.35s}
.sfba-d-other-item:nth-child(4){animation-delay:.40s}
.sfba-d-other-item:hover { border-color: var(--c,#6366f1); background: #fafbff; transform: translateY(-2px); color: #0f172a; }
.sfba-d-other-icon { font-size: 18px; }

/* ── No-provider banner ─────────────────────────────────────── */
.sfba-d-alert {
	display: flex;
	align-items: center;
	gap: 16px;
	background: #fff7ed;
	border: 1.5px solid #fed7aa;
	border-radius: 14px;
	padding: 16px 20px;
	margin-bottom: 24px;
	animation: sfba-d-fadeIn .4s ease both;
}
.sfba-d-alert-icon { font-size: 22px; flex-shrink: 0; }
.sfba-d-alert-body { flex: 1; }
.sfba-d-alert-body strong { display:block; font-size:13.5px; color:#92400e; margin-bottom:3px; }
.sfba-d-alert-body p { margin:0; font-size:12.5px; color:#b45309; }
.sfba-d-alert-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 16px;
	background: #d97706;
	color: #fff;
	border-radius: 8px;
	font-size: 12.5px;
	font-weight: 700;
	text-decoration: none;
	flex-shrink: 0;
	transition: opacity .15s;
}
.sfba-d-alert-btn:hover { opacity: .88; color:#fff; }
</style>

<div class="wrap sfba-admin-wrap" id="sfba-dash">

	<!-- ═══ HERO ════════════════════════════════════════════════════ -->
	<div class="sfba-d-hero">
		<div class="sfba-d-hero-blobs">
			<div class="sfba-d-blob sfba-d-blob--1"></div>
			<div class="sfba-d-blob sfba-d-blob--2"></div>
			<div class="sfba-d-blob sfba-d-blob--3"></div>
		</div>
		<div class="sfba-d-hero-grid"></div>
		<div class="sfba-d-hero-inner">
			<div class="sfba-d-hero-left">
				<div class="sfba-d-hero-pill">
					<span class="sfba-d-hero-dot"></span>
					v<?php echo esc_html( SFBA_VERSION ); ?> &nbsp;·&nbsp; <?php echo $connected > 0 ? esc_html( $connected . ' ' . __( 'provider(s) connected', 'super-fast-blog-ai' ) ) : esc_html__( 'No providers yet', 'super-fast-blog-ai' ); ?>
				</div>
				<h1><?php esc_html_e( 'Super Fast', 'super-fast-blog-ai' ); ?> <span class="sfba-d-hl"><?php esc_html_e( 'Blog AI', 'super-fast-blog-ai' ); ?></span></h1>
				<p class="sfba-d-hero-sub"><?php esc_html_e( 'AI-powered content generation, repurposing, and SEO tools — all in one WordPress plugin.', 'super-fast-blog-ai' ); ?></p>
			</div>
			<div class="sfba-d-hero-right">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-generate&tab=article' ) ); ?>" class="sfba-d-hero-cta sfba-d-hero-cta--primary">
					📝 <?php esc_html_e( 'Write Article', 'super-fast-blog-ai' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-guide' ) ); ?>" class="sfba-d-hero-cta sfba-d-hero-cta--secondary">
					📖 <?php esc_html_e( 'Usage Guide', 'super-fast-blog-ai' ); ?>
				</a>
			</div>
		</div>
	</div>

	<?php if ( $connected === 0 ) : ?>
	<!-- ═══ ALERT ═══════════════════════════════════════════════════ -->
	<div class="sfba-d-alert">
		<div class="sfba-d-alert-icon">⚠️</div>
		<div class="sfba-d-alert-body">
			<strong><?php esc_html_e( 'No AI provider connected', 'super-fast-blog-ai' ); ?></strong>
			<p><?php esc_html_e( 'Connect at least one API key in Settings to start generating content.', 'super-fast-blog-ai' ); ?></p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings#providers' ) ); ?>" class="sfba-d-alert-btn">
			🔌 <?php esc_html_e( 'Connect Provider', 'super-fast-blog-ai' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- ═══ STATS ════════════════════════════════════════════════════ -->
	<div class="sfba-d-status-strip">

		<div class="sfba-d-stat" style="--sfba-d-accent:#6366f1;">
			<div class="sfba-d-stat-icon" style="background:#eef2ff;">✨</div>
			<div>
				<div class="sfba-d-stat-val" data-count="<?php echo esc_attr( $gen_count ); ?>"><?php echo esc_html( number_format( $gen_count ) ); ?></div>
				<div class="sfba-d-stat-lbl"><?php esc_html_e( 'Generations this month', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>

		<div class="sfba-d-stat" style="--sfba-d-accent:#3b82f6;">
			<div class="sfba-d-stat-icon" style="background:#eff6ff;">💸</div>
			<div>
				<div class="sfba-d-stat-val"><sup>$</sup><?php echo esc_html( number_format( $month_cost, 2 ) ); ?></div>
				<div class="sfba-d-stat-lbl"><?php esc_html_e( 'API spend this month', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>

		<div class="sfba-d-stat" style="--sfba-d-accent:#10b981;">
			<div class="sfba-d-stat-icon" style="background:#f0fdf4;">🔌</div>
			<div>
				<div class="sfba-d-stat-val"><?php echo esc_html( $connected ); ?><span class="sfba-d-muted">/7</span></div>
				<div class="sfba-d-stat-lbl"><?php esc_html_e( 'Providers connected', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>

		<div class="sfba-d-stat" style="--sfba-d-accent:<?php echo $voice_status ? '#7c3aed' : '#94a3b8'; ?>;">
			<div class="sfba-d-stat-icon" style="background:<?php echo $voice_status ? '#f5f3ff' : '#f8fafc'; ?>;">🎨</div>
			<div>
				<div class="sfba-d-stat-val"><?php echo $voice_status ? esc_html__( 'Active', 'super-fast-blog-ai' ) : esc_html__( 'Off', 'super-fast-blog-ai' ); ?></div>
				<div class="sfba-d-stat-lbl"><?php esc_html_e( 'Brand Voice', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>

	</div>

	<!-- ═══ QUICK ACCESS ════════════════════════════════════════════ -->
	<div class="sfba-d-sec"><h2><?php esc_html_e( 'Quick Access', 'super-fast-blog-ai' ); ?></h2><div class="sfba-d-sec-line"></div></div>
	<div class="sfba-d-quick">
		<?php foreach ( $quick_links as $ql ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $ql['slug'] . $ql['query'] ) ); ?>"
		   class="sfba-d-qcard"
		   style="--c:<?php echo esc_attr( $ql['color'] ); ?>;--bg:<?php echo esc_attr( $ql['bg'] ); ?>;">
			<div class="sfba-d-qcard-icon"><?php echo esc_html( $ql['icon'] ); ?></div>
			<div class="sfba-d-qcard-body">
				<span class="sfba-d-qcard-title"><?php echo esc_html( $ql['title'] ); ?></span>
				<span class="sfba-d-qcard-desc"><?php echo esc_html( $ql['desc'] ); ?></span>
			</div>
			<svg class="sfba-d-qcard-arr" width="16" height="16" viewBox="0 0 20 20" fill="none">
				<path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</a>
		<?php endforeach; ?>
	</div>

	<!-- ═══ PROVIDER STATUS + USAGE GUIDE ══════════════════════════ -->
	<div class="sfba-d-cols">

		<!-- Provider status -->
		<div class="sfba-d-card">
			<div class="sfba-d-sec" style="margin-bottom:14px;">
				<h2><?php esc_html_e( 'Provider Status', 'super-fast-blog-ai' ); ?></h2>
				<div class="sfba-d-sec-line"></div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings#providers' ) ); ?>"
				   style="font-size:11.5px;font-weight:600;color:#6366f1;text-decoration:none;white-space:nowrap;flex-shrink:0;">
					<?php esc_html_e( 'Manage →', 'super-fast-blog-ai' ); ?>
				</a>
			</div>

			<!-- Default provider -->
			<div class="sfba-d-prov-default">
				<div class="sfba-d-prov-default-icon" style="<?php
					$dp_meta = $provider_meta[ $default_provider ] ?? [];
					if ( $dp_meta ) echo 'background:' . esc_attr( $dp_meta['bg'] ) . ';border:1.5px solid ' . esc_attr( $dp_meta['color'] ) . '44;';
				?>">
					<?php if ( ! empty( $dp_meta['logo'] ) ) : ?>
					<img src="<?php echo esc_url( $dp_meta['logo'] ); ?>"
					     alt="<?php echo esc_attr( $dp_meta['label'] ); ?>"
					     width="26" height="26"
					     style="object-fit:contain;display:block;"
					     onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
					<span style="display:none;font-size:22px;"><?php echo esc_html( $dp_meta['fallback'] ?? '🔌' ); ?></span>
					<?php else : ?>
					<span style="font-size:22px;">🔌</span>
					<?php endif; ?>
				</div>
				<div class="sfba-d-prov-default-body">
					<p class="sfba-d-prov-default-name">
						<?php echo $dp_name ? esc_html( $dp_name ) : esc_html__( 'Not set', 'super-fast-blog-ai' ); ?>
					</p>
					<p class="sfba-d-prov-default-lbl"><?php esc_html_e( 'Default provider', 'super-fast-blog-ai' ); ?></p>
				</div>
				<?php if ( $dp_name ) : ?>
				<span class="sfba-d-prov-pill <?php echo $dp_connected ? 'sfba-d-prov-pill--on' : 'sfba-d-prov-pill--off'; ?>">
					<span class="sfba-d-prov-pill-dot"></span>
					<?php echo $dp_connected ? esc_html__( 'Connected', 'super-fast-blog-ai' ) : esc_html__( 'No key', 'super-fast-blog-ai' ); ?>
				</span>
				<?php endif; ?>
			</div>

			<!-- All providers -->
			<div class="sfba-d-prov-grid">
				<?php foreach ( $provider_meta as $slug => $pm ) :
					$has_key = ! empty( $providers[ $slug ]['has_key'] );
					$cls     = $has_key ? 'sfba-d-prov-item--on' : 'sfba-d-prov-item--off';
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings#providers' ) ); ?>"
				   class="sfba-d-prov-item <?php echo esc_attr( $cls ); ?>">
					<span class="sfba-d-prov-status"></span>
					<span class="sfba-d-prov-icon">
						<img src="<?php echo esc_url( $pm['logo'] ); ?>"
						     alt="<?php echo esc_attr( $pm['label'] ); ?>"
						     width="18" height="18"
						     style="object-fit:contain;display:block;vertical-align:middle;"
						     onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
						<span style="display:none;font-size:16px;"><?php echo esc_html( $pm['fallback'] ); ?></span>
					</span>
					<span><?php echo esc_html( $pm['label'] ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Usage guide teaser -->
		<div class="sfba-d-guide">
			<div class="sfba-d-guide-grid"></div>
			<div class="sfba-d-guide-glow"></div>
			<div class="sfba-d-guide-top">
				<div class="sfba-d-guide-tag">📖 <?php esc_html_e( 'Documentation', 'super-fast-blog-ai' ); ?></div>
				<h3><?php esc_html_e( 'New to Super Fast Blog AI?', 'super-fast-blog-ai' ); ?></h3>
				<p><?php esc_html_e( 'Follow our 10-step guide to set up providers, configure content settings, build your brand voice, and publish your first AI article in minutes.', 'super-fast-blog-ai' ); ?></p>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-guide' ) ); ?>" class="sfba-d-guide-btn">
				<?php esc_html_e( 'View Usage Guide', 'super-fast-blog-ai' ); ?>
				<svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 10h10M12 6l4 4-4 4"/></svg>
			</a>
		</div>

	</div>

	<!-- ═══ OTHER MODULES ════════════════════════════════════════════ -->
	<div class="sfba-d-sec"><h2><?php esc_html_e( 'More Tools', 'super-fast-blog-ai' ); ?></h2><div class="sfba-d-sec-line"></div></div>
	<div class="sfba-d-other">
		<?php foreach ( $all_modules as $m ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $m['slug'] . $m['query'] ) ); ?>"
		   class="sfba-d-other-item"
		   style="--c:<?php echo esc_attr( $m['color'] ); ?>;">
			<span class="sfba-d-other-icon"><?php echo esc_html( $m['icon'] ); ?></span>
			<?php echo esc_html( $m['title'] ); ?>
		</a>
		<?php endforeach; ?>
	</div>

</div><!-- /#sfba-dash -->

<script>
( function () {
	'use strict';

	/* Count-up animation for stat numbers */
	function countUp( el, target ) {
		if ( isNaN( target ) || target === 0 ) return;
		var start = 0;
		var dur   = 900;
		var t0    = null;
		function step( ts ) {
			if ( ! t0 ) t0 = ts;
			var p = Math.min( ( ts - t0 ) / dur, 1 );
			var e = 1 - Math.pow( 1 - p, 3 );
			el.textContent = Math.round( start + ( target - start ) * e ).toLocaleString();
			if ( p < 1 ) requestAnimationFrame( step );
		}
		requestAnimationFrame( step );
	}

	document.querySelectorAll( '.sfba-d-stat-val[data-count]' ).forEach( function ( el ) {
		var n = parseInt( el.getAttribute( 'data-count' ), 10 );
		if ( window.IntersectionObserver ) {
			var io = new IntersectionObserver( function ( entries ) {
				if ( entries[0].isIntersecting ) { countUp( el, n ); io.disconnect(); }
			} );
			io.observe( el );
		} else {
			countUp( el, n );
		}
	} );
} )();
</script>
