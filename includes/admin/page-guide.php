<?php
/**
 * Usage Guide admin page template — animated modern UI.
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$settings_url  = admin_url( 'admin.php?page=sfba-settings' );
$generate_url  = admin_url( 'admin.php?page=sfba-generate' );
$voice_url     = admin_url( 'admin.php?page=sfba-brand-voice' );
$calendar_url  = admin_url( 'admin.php?page=sfba-content-calendar' );
$repurpose_url = admin_url( 'admin.php?page=sfba-repurpose' );
$links_url     = admin_url( 'admin.php?page=sfba-internal-links' );
$perf_url      = admin_url( 'admin.php?page=sfba-performance' );
$costs_url     = admin_url( 'admin.php?page=sfba-cost-tracker' );
$routing_url   = admin_url( 'admin.php?page=sfba-model-routing' );

$steps = [
	[
		'num'   => '01',
		'icon'  => '🔌',
		'title' => __( 'Connect an AI Provider', 'super-fast-blog-ai' ),
		'sub'   => __( 'Required — everything else depends on this', 'super-fast-blog-ai' ),
		'color' => '#6366f1',
		'glow'  => 'rgba(99,102,241,.25)',
		'url'   => $settings_url . '#providers',
		'cta'   => __( 'Go to Providers', 'super-fast-blog-ai' ),
		'body'  => __( 'Paste an API key from any of the 7 supported providers. The plugin is Bring-Your-Own-Key — no subscriptions, no hidden fees. OpenAI, Anthropic, Gemini, Mistral, DeepSeek, OpenRouter, or Ollama (local).', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Settings → Providers → paste your key', 'super-fast-blog-ai' ),
			__( 'Click Save, then Test Connection', 'super-fast-blog-ai' ),
			__( 'Set it as the default provider', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '02',
		'icon'  => '✍️',
		'title' => __( 'Configure Content Settings', 'super-fast-blog-ai' ),
		'sub'   => __( 'Language, tone, structure, word count', 'super-fast-blog-ai' ),
		'color' => '#3b82f6',
		'glow'  => 'rgba(59,130,246,.25)',
		'url'   => $settings_url . '#content',
		'cta'   => __( 'Content Settings', 'super-fast-blog-ai' ),
		'body'  => __( 'Tell the AI how to write. Choose language, writing style, tone, target word count, heading structure, and toggle sections like FAQ, Table of Contents, and Pros & Cons.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Pick language and writing style', 'super-fast-blog-ai' ),
			__( 'Set word count (300–10,000)', 'super-fast-blog-ai' ),
			__( 'Enable FAQ, TOC, Pros & Cons', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '03',
		'icon'  => '🎨',
		'title' => __( 'Build Your Brand Voice', 'super-fast-blog-ai' ),
		'sub'   => __( 'AI learns to write exactly like you', 'super-fast-blog-ai' ),
		'color' => '#8b5cf6',
		'glow'  => 'rgba(139,92,246,.25)',
		'url'   => $voice_url,
		'cta'   => __( 'Brand Voice', 'super-fast-blog-ai' ),
		'body'  => __( 'Brand Voice scans your published posts (locally, zero AI cost) to detect your sentence length, vocabulary, tone, and style markers. That profile is injected into every generation so content sounds like you.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Click "Analyze Writing Style"', 'super-fast-blog-ai' ),
			__( 'Review your detected profile', 'super-fast-blog-ai' ),
			__( 'Enable Brand Voice and save', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '04',
		'icon'  => '🔤',
		'title' => __( 'Generate Blog Titles', 'super-fast-blog-ai' ),
		'sub'   => __( '10–20 catchy title ideas in seconds', 'super-fast-blog-ai' ),
		'color' => '#06b6d4',
		'glow'  => 'rgba(6,182,212,.25)',
		'url'   => $generate_url . '&tab=titles',
		'cta'   => __( 'Generate Titles', 'super-fast-blog-ai' ),
		'body'  => __( 'Type a topic, choose a style, and hit Generate. You get multiple high-converting title variations instantly. Click "Use for Article" on any title to pass it straight into the article generator.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Enter a topic or keyword phrase', 'super-fast-blog-ai' ),
			__( 'Choose variation count (5–20)', 'super-fast-blog-ai' ),
			__( 'Copy, save, or convert to article', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '05',
		'icon'  => '📝',
		'title' => __( 'Write a Full AI Article', 'super-fast-blog-ai' ),
		'sub'   => __( 'Full SEO post saved as WP draft', 'super-fast-blog-ai' ),
		'color' => '#10b981',
		'glow'  => 'rgba(16,185,129,.25)',
		'url'   => $generate_url . '&tab=article',
		'cta'   => __( 'Write Article', 'super-fast-blog-ai' ),
		'body'  => __( 'Paste a title, generate an outline, edit it, then write the full article. Headings, body, FAQ, meta description — all saved to WordPress as a draft ready for your review before publishing.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Paste your chosen title', 'super-fast-blog-ai' ),
			__( 'Generate & review the outline', 'super-fast-blog-ai' ),
			__( 'Write Full Article → WP draft', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '06',
		'icon'  => '📅',
		'title' => __( 'Plan with Content Calendar', 'super-fast-blog-ai' ),
		'sub'   => __( 'AI topic ideas mapped to publish dates', 'super-fast-blog-ai' ),
		'color' => '#f59e0b',
		'glow'  => 'rgba(245,158,11,.25)',
		'url'   => $calendar_url,
		'cta'   => __( 'Content Calendar', 'super-fast-blog-ai' ),
		'body'  => __( 'Fill your editorial calendar in one click with "AI Suggest Topics". Assign dates, track status (Idea → In Progress → Published), and launch article generation directly from any calendar entry.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Click "AI Suggest Topics" for a full month', 'super-fast-blog-ai' ),
			__( 'Assign dates and track status', 'super-fast-blog-ai' ),
			__( 'Generate articles from any entry', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '07',
		'icon'  => '♻️',
		'title' => __( 'Repurpose Your Content', 'super-fast-blog-ai' ),
		'sub'   => __( '1 article → 6 platform-native posts', 'super-fast-blog-ai' ),
		'color' => '#ef4444',
		'glow'  => 'rgba(239,68,68,.25)',
		'url'   => $repurpose_url,
		'cta'   => __( 'Repurpose', 'super-fast-blog-ai' ),
		'body'  => __( 'Pick any published post and instantly rewrite it as tweets, a LinkedIn post, email newsletter, Facebook post, Instagram caption, or YouTube script. Platform-native tone and formatting, automatically.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Select a post from your library', 'super-fast-blog-ai' ),
			__( 'Choose one or more platforms', 'super-fast-blog-ai' ),
			__( 'Copy output with one click', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '08',
		'icon'  => '🔗',
		'title' => __( 'Build Internal Links', 'super-fast-blog-ai' ),
		'sub'   => __( 'SEO-boosting links across your posts', 'super-fast-blog-ai' ),
		'color' => '#0ea5e9',
		'glow'  => 'rgba(14,165,233,.25)',
		'url'   => $links_url,
		'cta'   => __( 'Internal Links', 'super-fast-blog-ai' ),
		'body'  => __( 'Build the keyword index once, then the AI suggests relevant internal links between your posts. Accept a suggestion to insert the link automatically — no manual editing needed.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Click "Build Link Index"', 'super-fast-blog-ai' ),
			__( 'Review AI-suggested links', 'super-fast-blog-ai' ),
			__( 'Accept to auto-insert', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '09',
		'icon'  => '📊',
		'title' => __( 'Track Performance', 'super-fast-blog-ai' ),
		'sub'   => __( 'AI vs. manual content in Search Console', 'super-fast-blog-ai' ),
		'color' => '#14b8a6',
		'glow'  => 'rgba(20,184,166,.25)',
		'url'   => $perf_url,
		'cta'   => __( 'Performance', 'super-fast-blog-ai' ),
		'body'  => __( 'Connect Google Search Console to pull real clicks and impressions data. The dashboard compares AI-generated articles against manually written ones so you can see exactly what\'s performing.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'Paste OAuth token in Settings → Search Console', 'super-fast-blog-ai' ),
			__( 'View top posts, clicks, impressions', 'super-fast-blog-ai' ),
			__( 'Compare AI vs. manual performance', 'super-fast-blog-ai' ),
		],
	],
	[
		'num'   => '10',
		'icon'  => '💰',
		'title' => __( 'Monitor AI Costs', 'super-fast-blog-ai' ),
		'sub'   => __( 'Set a budget cap, never get surprised', 'super-fast-blog-ai' ),
		'color' => '#f97316',
		'glow'  => 'rgba(249,115,22,.25)',
		'url'   => $costs_url,
		'cta'   => __( 'Cost Tracker', 'super-fast-blog-ai' ),
		'body'  => __( 'Every generation is logged with token count and estimated cost. Set a monthly budget in Settings → Advanced — you get a warning at 80% and the plugin stops at 100% to protect you from overspend.', 'super-fast-blog-ai' ),
		'bullets' => [
			__( 'View per-generation cost breakdown', 'super-fast-blog-ai' ),
			__( 'Set monthly budget cap in Advanced', 'super-fast-blog-ai' ),
			__( 'Filter by provider or date', 'super-fast-blog-ai' ),
		],
	],
];
?>
<style>
/* ═══════════════════════════════════════════════════════════════
   SUPER FAST BLOG AI — Usage Guide (Animated Modern UI)
═══════════════════════════════════════════════════════════════ */

/* ── Reset / scope ─────────────────────────────────────────── */
#sfba-guide * { box-sizing: border-box; }
#sfba-guide { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

/* ── Keyframes ──────────────────────────────────────────────── */
@keyframes sfba-g-floatA {
	0%,100% { transform: translate(0,0) scale(1); }
	50%      { transform: translate(18px,-22px) scale(1.06); }
}
@keyframes sfba-g-floatB {
	0%,100% { transform: translate(0,0) scale(1); }
	50%      { transform: translate(-14px,18px) scale(1.04); }
}
@keyframes sfba-g-floatC {
	0%,100% { transform: translate(0,0) scale(1); }
	40%      { transform: translate(10px,14px) scale(1.08); }
}
@keyframes sfba-g-heroIn {
	from { opacity:0; transform:translateY(28px); }
	to   { opacity:1; transform:translateY(0); }
}
@keyframes sfba-g-badgePop {
	0%   { opacity:0; transform:scale(.7) translateY(10px); }
	70%  { transform:scale(1.06) translateY(-2px); }
	100% { opacity:1; transform:scale(1) translateY(0); }
}
@keyframes sfba-g-lineGrow {
	from { transform: scaleY(0); }
	to   { transform: scaleY(1); }
}
@keyframes sfba-g-cardIn {
	from { opacity:0; transform:translateY(32px); }
	to   { opacity:1; transform:translateY(0); }
}
@keyframes sfba-g-numPulse {
	0%,100% { box-shadow: 0 0 0 0 var(--sfba-g-glow); }
	50%      { box-shadow: 0 0 0 8px transparent; }
}
@keyframes sfba-g-shimmer {
	0%   { background-position: -400px 0; }
	100% { background-position: 400px 0; }
}
@keyframes sfba-g-tipFade {
	from { opacity:0; transform:translateX(-12px); }
	to   { opacity:1; transform:translateX(0); }
}
@keyframes sfba-g-orbit {
	from { transform: rotate(0deg) translateX(70px) rotate(0deg); }
	to   { transform: rotate(360deg) translateX(70px) rotate(-360deg); }
}
@keyframes sfba-g-provIn {
	from { opacity:0; transform:scale(.85); }
	to   { opacity:1; transform:scale(1); }
}

/* ── Hero ───────────────────────────────────────────────────── */
.sfba-g-hero {
	position: relative;
	border-radius: 24px;
	overflow: hidden;
	padding: 60px 64px 60px;
	margin-bottom: 48px;
	background: #0f172a;
	color: #fff;
	min-height: 300px;
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
}
.sfba-g-hero-blobs {
	position: absolute;
	inset: 0;
	pointer-events: none;
	overflow: hidden;
}
.sfba-g-blob {
	position: absolute;
	border-radius: 50%;
	filter: blur(60px);
	opacity: .55;
}
.sfba-g-blob--a {
	width: 420px; height: 420px;
	background: #4f46e5;
	top: -140px; right: -60px;
	animation: sfba-g-floatA 9s ease-in-out infinite;
}
.sfba-g-blob--b {
	width: 300px; height: 300px;
	background: #7c3aed;
	bottom: -100px; left: 10%;
	animation: sfba-g-floatB 11s ease-in-out infinite;
}
.sfba-g-blob--c {
	width: 200px; height: 200px;
	background: #06b6d4;
	top: 20%; left: 55%;
	animation: sfba-g-floatC 7s ease-in-out infinite;
}

/* grid dots */
.sfba-g-hero-grid {
	position: absolute;
	inset: 0;
	background-image:
		radial-gradient(circle, rgba(255,255,255,.12) 1px, transparent 1px);
	background-size: 32px 32px;
}

.sfba-g-hero-inner {
	position: relative;
	z-index: 2;
	animation: sfba-g-heroIn .9s cubic-bezier(.22,.6,.36,1) both;
}
.sfba-g-hero-pill {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	background: rgba(255,255,255,.1);
	backdrop-filter: blur(8px);
	border: 1px solid rgba(255,255,255,.2);
	border-radius: 100px;
	padding: 5px 14px 5px 8px;
	font-size: 12px;
	font-weight: 600;
	color: rgba(255,255,255,.9);
	margin-bottom: 20px;
	animation: sfba-g-badgePop .7s .2s cubic-bezier(.34,1.56,.64,1) both;
}
.sfba-g-hero-pill-dot {
	width: 8px; height: 8px;
	border-radius: 50%;
	background: #34d399;
	box-shadow: 0 0 6px #34d399;
}
.sfba-g-hero h1 {
	margin: 0 0 12px;
	font-size: 38px;
	font-weight: 800;
	line-height: 1.15;
	letter-spacing: -.5px;
	color: #fff;
	max-width: 600px;
}
.sfba-g-hero h1 .sfba-g-hl {
	background: linear-gradient(90deg,#818cf8,#38bdf8);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}
.sfba-g-hero-sub {
	font-size: 15.5px;
	color: rgba(255,255,255,.65);
	margin: 0 0 32px;
	max-width: 520px;
	line-height: 1.65;
}
.sfba-g-hero-stats {
	display: flex;
	gap: 32px;
	flex-wrap: wrap;
}
.sfba-g-stat {
	display: flex;
	flex-direction: column;
}
.sfba-g-stat-num {
	font-size: 26px;
	font-weight: 800;
	color: #fff;
	line-height: 1;
	letter-spacing: -.5px;
}
.sfba-g-stat-lbl {
	font-size: 11.5px;
	font-weight: 500;
	color: rgba(255,255,255,.5);
	text-transform: uppercase;
	letter-spacing: .5px;
	margin-top: 3px;
}

/* ── Section label ──────────────────────────────────────────── */
.sfba-g-section-label {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 28px;
}
.sfba-g-section-label h2 {
	margin: 0;
	font-size: 20px;
	font-weight: 800;
	color: #0f172a;
}
.sfba-g-section-label-line {
	flex: 1;
	height: 1.5px;
	background: linear-gradient(90deg,#e2e8f0 0%,transparent 100%);
}

/* ── Timeline layout ────────────────────────────────────────── */
.sfba-g-timeline {
	position: relative;
	padding-left: 56px;
	margin-bottom: 56px;
}
.sfba-g-timeline-line {
	position: absolute;
	left: 20px;
	top: 24px;
	bottom: 24px;
	width: 2px;
	background: linear-gradient(180deg,#6366f1 0%,#06b6d4 50%,#f97316 100%);
	transform-origin: top center;
	border-radius: 2px;
}

/* ── Step card ──────────────────────────────────────────────── */
.sfba-g-step {
	--sfba-g-color: #6366f1;
	--sfba-g-glow:  rgba(99,102,241,.2);
	position: relative;
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 20px;
	padding: 28px 32px 28px 28px;
	margin-bottom: 20px;
	display: grid;
	grid-template-columns: auto 1fr auto;
	gap: 0 24px;
	align-items: start;
	transition: border-color .22s, box-shadow .22s, transform .22s;
	opacity: 0;
	transform: translateY(28px);
	cursor: default;
}
.sfba-g-step.sfba-g-visible {
	animation: sfba-g-cardIn .55s cubic-bezier(.22,.6,.36,1) forwards;
}
.sfba-g-step:hover {
	border-color: var(--sfba-g-color);
	box-shadow: 0 8px 36px var(--sfba-g-glow);
	transform: translateY(-3px);
}

/* connector dot on timeline */
.sfba-g-step::before {
	content: '';
	position: absolute;
	left: -43px;
	top: 32px;
	width: 12px; height: 12px;
	border-radius: 50%;
	background: var(--sfba-g-color);
	box-shadow: 0 0 0 4px rgba(255,255,255,1), 0 0 0 6px var(--sfba-g-color);
}

/* ── Step number bubble ─────────────────────────────────────── */
.sfba-g-step-num {
	width: 48px; height: 48px;
	border-radius: 14px;
	background: linear-gradient(135deg, var(--sfba-g-color), color-mix(in srgb, var(--sfba-g-color) 60%, #000));
	display: flex; align-items: center; justify-content: center;
	font-size: 11px; font-weight: 800;
	color: #fff;
	letter-spacing: .5px;
	flex-shrink: 0;
	box-shadow: 0 4px 14px var(--sfba-g-glow);
	animation: sfba-g-numPulse 3s ease-in-out infinite;
	position: relative;
	z-index: 1;
}

/* ── Step body ──────────────────────────────────────────────── */
.sfba-g-step-body {}
.sfba-g-step-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 8px;
}
.sfba-g-step-icon {
	font-size: 20px;
	line-height: 1;
}
.sfba-g-step-title {
	font-size: 15.5px;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	line-height: 1.3;
}
.sfba-g-step-sub {
	font-size: 11.5px;
	font-weight: 500;
	color: #94a3b8;
	text-transform: uppercase;
	letter-spacing: .4px;
	margin: 0 0 12px 30px;
}
.sfba-g-step-desc {
	font-size: 13.5px;
	color: #475569;
	line-height: 1.7;
	margin: 0 0 14px;
}
.sfba-g-step-bullets {
	display: flex;
	flex-direction: column;
	gap: 5px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.sfba-g-step-bullets li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12.5px;
	color: #64748b;
	line-height: 1.5;
}
.sfba-g-step-bullets li::before {
	content: '';
	width: 6px; height: 6px;
	border-radius: 50%;
	background: var(--sfba-g-color);
	flex-shrink: 0;
}

/* ── Step CTA ───────────────────────────────────────────────── */
.sfba-g-step-cta-wrap {
	display: flex;
	align-items: flex-start;
	padding-top: 4px;
}
.sfba-g-step-cta {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 9px 18px;
	background: var(--sfba-g-color);
	color: #fff;
	border-radius: 10px;
	font-size: 12.5px;
	font-weight: 600;
	text-decoration: none;
	white-space: nowrap;
	box-shadow: 0 2px 10px var(--sfba-g-glow);
	transition: opacity .18s, transform .18s, box-shadow .18s;
	flex-shrink: 0;
}
.sfba-g-step-cta:hover {
	opacity: .9;
	transform: translateY(-1px);
	box-shadow: 0 6px 20px var(--sfba-g-glow);
	color: #fff;
}
.sfba-g-step-cta svg {
	width: 13px; height: 13px;
	flex-shrink: 0;
}

/* ── Providers strip ────────────────────────────────────────── */
.sfba-g-providers {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 12px;
}
.sfba-g-prov {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 5px 12px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	font-size: 12px;
	font-weight: 600;
	color: #334155;
	animation: sfba-g-provIn .4s ease both;
}
.sfba-g-prov:nth-child(1) { animation-delay: .05s; }
.sfba-g-prov:nth-child(2) { animation-delay: .10s; }
.sfba-g-prov:nth-child(3) { animation-delay: .15s; }
.sfba-g-prov:nth-child(4) { animation-delay: .20s; }
.sfba-g-prov:nth-child(5) { animation-delay: .25s; }
.sfba-g-prov:nth-child(6) { animation-delay: .30s; }
.sfba-g-prov:nth-child(7) { animation-delay: .35s; }

/* ── Tips ───────────────────────────────────────────────────── */
.sfba-g-tips {
	margin-bottom: 48px;
}
.sfba-g-tips-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 16px;
}
.sfba-g-tip {
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 16px;
	padding: 20px 22px;
	display: flex;
	gap: 14px;
	transition: border-color .2s, box-shadow .2s, transform .2s;
	opacity: 0;
}
.sfba-g-tip.sfba-g-visible {
	animation: sfba-g-tipFade .5s ease forwards;
}
.sfba-g-tip:hover {
	border-color: #818cf8;
	box-shadow: 0 4px 20px rgba(99,102,241,.1);
	transform: translateY(-2px);
}
.sfba-g-tip-icon {
	font-size: 22px;
	flex-shrink: 0;
	margin-top: 1px;
}
.sfba-g-tip-text {
	font-size: 13px;
	color: #475569;
	line-height: 1.65;
	margin: 0;
}
.sfba-g-tip-text strong {
	display: block;
	color: #0f172a;
	font-size: 13.5px;
	margin-bottom: 3px;
}

/* ── Quick Links ────────────────────────────────────────────── */
.sfba-g-links-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
	gap: 10px;
	margin-bottom: 48px;
}
.sfba-g-link {
	display: flex;
	align-items: center;
	gap: 9px;
	padding: 11px 15px;
	background: #fff;
	border: 1.5px solid #e2e8f0;
	border-radius: 12px;
	font-size: 13px;
	font-weight: 600;
	color: #334155;
	text-decoration: none;
	transition: background .18s, border-color .18s, transform .18s, color .18s;
}
.sfba-g-link:hover {
	background: #eff6ff;
	border-color: #93c5fd;
	color: #1d4ed8;
	transform: translateY(-2px);
}
.sfba-g-link-icon { font-size: 16px; }
</style>

<div class="wrap sfba-admin-wrap" id="sfba-guide">

	<!-- ═══ HERO ════════════════════════════════════════════════════ -->
	<div class="sfba-g-hero">
		<div class="sfba-g-hero-blobs">
			<div class="sfba-g-blob sfba-g-blob--a"></div>
			<div class="sfba-g-blob sfba-g-blob--b"></div>
			<div class="sfba-g-blob sfba-g-blob--c"></div>
		</div>
		<div class="sfba-g-hero-grid"></div>
		<div class="sfba-g-hero-inner">
			<div class="sfba-g-hero-pill">
				<span class="sfba-g-hero-pill-dot"></span>
				<?php esc_html_e( 'Getting Started Guide', 'super-fast-blog-ai' ); ?>
			</div>
			<h1><?php esc_html_e( 'How to Use', 'super-fast-blog-ai' ); ?><br><span class="sfba-g-hl"><?php esc_html_e( 'Super Fast Blog AI', 'super-fast-blog-ai' ); ?></span></h1>
			<p class="sfba-g-hero-sub"><?php esc_html_e( 'Follow these 10 steps to start generating AI-powered content. Everything starts with connecting a provider — it takes under a minute.', 'super-fast-blog-ai' ); ?></p>
			<div class="sfba-g-hero-stats">
				<div class="sfba-g-stat">
					<span class="sfba-g-stat-num">7</span>
					<span class="sfba-g-stat-lbl"><?php esc_html_e( 'AI Providers', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div class="sfba-g-stat">
					<span class="sfba-g-stat-num">10</span>
					<span class="sfba-g-stat-lbl"><?php esc_html_e( 'Modules', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div class="sfba-g-stat">
					<span class="sfba-g-stat-num">$0</span>
					<span class="sfba-g-stat-lbl"><?php esc_html_e( 'Subscriptions', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div class="sfba-g-stat">
					<span class="sfba-g-stat-num">BYOK</span>
					<span class="sfba-g-stat-lbl"><?php esc_html_e( 'Bring Your Own Key', 'super-fast-blog-ai' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- ═══ STEPS ═══════════════════════════════════════════════════ -->
	<div class="sfba-g-section-label">
		<h2><?php esc_html_e( 'Step-by-Step Setup', 'super-fast-blog-ai' ); ?></h2>
		<div class="sfba-g-section-label-line"></div>
	</div>

	<div class="sfba-g-timeline">
		<div class="sfba-g-timeline-line"></div>

		<?php foreach ( $steps as $i => $step ) : ?>
		<div class="sfba-g-step sfba-g-observe"
		     style="--sfba-g-color:<?php echo esc_attr( $step['color'] ); ?>;--sfba-g-glow:<?php echo esc_attr( $step['glow'] ); ?>;animation-delay:<?php echo esc_attr( $i * 0.06 ); ?>s;">

			<!-- Number -->
			<div class="sfba-g-step-num"><?php echo esc_html( $step['num'] ); ?></div>

			<!-- Body -->
			<div class="sfba-g-step-body">
				<div class="sfba-g-step-header">
					<span class="sfba-g-step-icon"><?php echo esc_html( $step['icon'] ); ?></span>
					<p class="sfba-g-step-title"><?php echo esc_html( $step['title'] ); ?></p>
				</div>
				<p class="sfba-g-step-sub"><?php echo esc_html( $step['sub'] ); ?></p>
				<p class="sfba-g-step-desc"><?php echo esc_html( $step['body'] ); ?></p>
				<ul class="sfba-g-step-bullets">
					<?php foreach ( $step['bullets'] as $b ) : ?>
					<li><?php echo esc_html( $b ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( '01' === $step['num'] ) : ?>
				<div class="sfba-g-providers">
					<div class="sfba-g-prov">🤖 OpenAI</div>
					<div class="sfba-g-prov">🧠 Anthropic</div>
					<div class="sfba-g-prov">💎 Gemini</div>
					<div class="sfba-g-prov">🌊 Mistral</div>
					<div class="sfba-g-prov">🔍 DeepSeek</div>
					<div class="sfba-g-prov">📡 OpenRouter</div>
					<div class="sfba-g-prov">🖥️ Ollama</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- CTA -->
			<div class="sfba-g-step-cta-wrap">
				<a href="<?php echo esc_url( $step['url'] ); ?>" class="sfba-g-step-cta">
					<?php echo esc_html( $step['cta'] ); ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</a>
			</div>

		</div>
		<?php endforeach; ?>
	</div>

	<!-- ═══ TIPS ═════════════════════════════════════════════════════ -->
	<div class="sfba-g-tips">
		<div class="sfba-g-section-label">
			<h2><?php esc_html_e( 'Pro Tips', 'super-fast-blog-ai' ); ?></h2>
			<div class="sfba-g-section-label-line"></div>
		</div>
		<div class="sfba-g-tips-grid">
			<?php
			$tips = [
				[ '🎯', __( 'Enable Brand Voice first', 'super-fast-blog-ai' ),           __( 'The more posts you have, the better the profile. Analyze at least 10–20 published posts before generating new content.', 'super-fast-blog-ai' ) ],
				[ '⚡', __( 'DeepSeek cuts costs by ~10×', 'super-fast-blog-ai' ),          __( 'DeepSeek is dramatically cheaper than GPT-4o and produces excellent long-form articles. Great for high-volume use.', 'super-fast-blog-ai' ) ],
				[ '🔀', __( 'Route by task', 'super-fast-blog-ai' ),                        __( 'Use Model Routing to assign a cheap model to title generation and a premium model to full articles. Best quality-to-cost ratio.', 'super-fast-blog-ai' ) ],
				[ '📐', __( 'Always review before publishing', 'super-fast-blog-ai' ),      __( 'Articles save as drafts. Edit, verify facts, add images, and optimize before going live. AI is a first draft, not a final one.', 'super-fast-blog-ai' ) ],
				[ '🗓️', __( 'Batch plan once a month', 'super-fast-blog-ai' ),             __( 'Open the Content Calendar, click "AI Suggest Topics" and get a full month of ideas in one shot. Then work through them.', 'super-fast-blog-ai' ) ],
				[ '♻️', __( '1 article → 6 social posts', 'super-fast-blog-ai' ),           __( 'After publishing, immediately repurpose into Twitter, LinkedIn, Email, Facebook, Instagram and YouTube to maximise reach.', 'super-fast-blog-ai' ) ],
			];
			foreach ( $tips as $idx => $t ) :
			?>
			<div class="sfba-g-tip sfba-g-observe" style="animation-delay:<?php echo esc_attr( $idx * 0.08 ); ?>s;">
				<div class="sfba-g-tip-icon"><?php echo esc_html( $t[0] ); ?></div>
				<p class="sfba-g-tip-text"><strong><?php echo esc_html( $t[1] ); ?></strong><?php echo esc_html( $t[2] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- ═══ QUICK LINKS ══════════════════════════════════════════════ -->
	<div class="sfba-g-section-label">
		<h2><?php esc_html_e( 'Quick Links', 'super-fast-blog-ai' ); ?></h2>
		<div class="sfba-g-section-label-line"></div>
	</div>
	<div class="sfba-g-links-grid">
		<?php
		$links = [
			[ '🔌', __( 'Providers',        'super-fast-blog-ai' ), $settings_url . '#providers' ],
			[ '✍️', __( 'Content Settings', 'super-fast-blog-ai' ), $settings_url . '#content'   ],
			[ '🔍', __( 'SEO Settings',     'super-fast-blog-ai' ), $settings_url . '#seo'        ],
			[ '🖼️', __( 'Image Settings',   'super-fast-blog-ai' ), $settings_url . '#images'     ],
			[ '⏰', __( 'Scheduling',       'super-fast-blog-ai' ), $settings_url . '#schedule'   ],
			[ '🛠️', __( 'Advanced / Budget','super-fast-blog-ai' ), $settings_url . '#advanced'   ],
			[ '📝', __( 'Generate Content', 'super-fast-blog-ai' ), $generate_url                 ],
			[ '🎨', __( 'Brand Voice',      'super-fast-blog-ai' ), $voice_url                    ],
			[ '📅', __( 'Content Calendar', 'super-fast-blog-ai' ), $calendar_url                 ],
			[ '♻️', __( 'Repurpose',        'super-fast-blog-ai' ), $repurpose_url                ],
			[ '🔗', __( 'Internal Links',   'super-fast-blog-ai' ), $links_url                    ],
			[ '📊', __( 'Performance',      'super-fast-blog-ai' ), $perf_url                     ],
			[ '💰', __( 'Cost Tracker',     'super-fast-blog-ai' ), $costs_url                    ],
			[ '🔀', __( 'Model Routing',    'super-fast-blog-ai' ), $routing_url                  ],
		];
		foreach ( $links as $l ) :
		?>
		<a href="<?php echo esc_url( $l[2] ); ?>" class="sfba-g-link">
			<span class="sfba-g-link-icon"><?php echo esc_html( $l[0] ); ?></span>
			<?php echo esc_html( $l[1] ); ?>
		</a>
		<?php endforeach; ?>
	</div>

</div><!-- /#sfba-guide -->

<script>
( function () {
	'use strict';

	/* ── Intersection Observer — animate cards in on scroll ── */
	if ( ! window.IntersectionObserver ) {
		/* fallback: show everything immediately */
		document.querySelectorAll( '.sfba-g-observe' ).forEach( function ( el ) {
			el.classList.add( 'sfba-g-visible' );
		} );
		return;
	}

	var io = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'sfba-g-visible' );
					io.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
	);

	document.querySelectorAll( '.sfba-g-observe' ).forEach( function ( el ) {
		io.observe( el );
	} );

	/* ── Animate timeline line growing on scroll ── */
	var line = document.querySelector( '.sfba-g-timeline-line' );
	if ( line ) {
		line.style.cssText += 'transform-origin:top center;';
		var lineIO = new IntersectionObserver(
			function ( entries ) {
				if ( entries[0].isIntersecting ) {
					line.style.transition = 'transform 1.8s cubic-bezier(.22,.6,.36,1)';
					line.style.transform  = 'scaleY(1)';
					lineIO.disconnect();
				}
			},
			{ threshold: 0.05 }
		);
		line.style.transform = 'scaleY(0)';
		lineIO.observe( line );
	}

	/* ── Stat counter animation ── */
	document.querySelectorAll( '.sfba-g-stat-num' ).forEach( function ( el ) {
		var raw = el.textContent.trim();
		var num = parseInt( raw, 10 );
		if ( isNaN( num ) ) return;
		var suffix = raw.replace( num.toString(), '' );
		var start  = 0;
		var dur    = 900;
		var t0     = null;
		function tick( ts ) {
			if ( ! t0 ) t0 = ts;
			var prog = Math.min( ( ts - t0 ) / dur, 1 );
			var ease = 1 - Math.pow( 1 - prog, 3 );
			el.textContent = Math.round( start + ( num - start ) * ease ) + suffix;
			if ( prog < 1 ) requestAnimationFrame( tick );
		}
		/* fire when visible */
		var cIO = new IntersectionObserver( function ( entries ) {
			if ( entries[0].isIntersecting ) { requestAnimationFrame( tick ); cIO.disconnect(); }
		} );
		cIO.observe( el );
	} );
} )();
</script>
