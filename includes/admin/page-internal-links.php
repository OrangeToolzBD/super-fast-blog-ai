<?php
/**
 * Internal Links admin page template.
 *
 * Variables:
 *   $index_built     bool      — true if index has been built
 *   $index_count     int       — number of indexed rows
 *   $accepted_count  int       — number of accepted/inserted links
 *   $published_count int       — total published posts + pages
 *   $last_built      string    — timestamp of last build (or false)
 *   $recent_posts    WP_Post[] — last 50 published posts
 *   $api_base        string
 *   $nonce           string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$coverage_pct = $published_count > 0 ? min( 100, round( ( $index_count / $published_count ) * 100 ) ) : 0;
?>
<style>
/* ── Metrics strip ─────────────────────────────────────────────────────────── */
.sfba-metrics {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	background: #fff;
	border: 1.5px solid #e5e7eb;
	border-radius: 16px;
	overflow: hidden;
	margin-bottom: 20px;
	box-shadow: 0 1px 6px rgba(0,0,0,.04);
}
@media (max-width: 700px) { .sfba-metrics { grid-template-columns: repeat(2,1fr); } }
.sfba-metric {
	padding: 20px 22px;
	position: relative;
	border-right: 1.5px solid #f3f4f6;
}
.sfba-metric:last-child { border-right: none; }
.sfba-metric::before {
	content: '';
	position: absolute;
	top: 0; left: 0; right: 0;
	height: 3px;
	border-radius: 0;
}
.sfba-metric--blue::before   { background: linear-gradient(90deg,#3b82f6,#6366f1); }
.sfba-metric--green::before  { background: linear-gradient(90deg,#22c55e,#16a34a); }
.sfba-metric--purple::before { background: linear-gradient(90deg,#a855f7,#7c3aed); }
.sfba-metric--amber::before  { background: linear-gradient(90deg,#f59e0b,#ef4444); }

.sfba-metric__label {
	font-size: 10.5px;
	font-weight: 700;
	letter-spacing: .6px;
	text-transform: uppercase;
	color: #9ca3af;
	margin-bottom: 10px;
	display: flex;
	align-items: center;
	gap: 6px;
}
.sfba-metric__label svg { opacity: .5; }
.sfba-metric__value {
	font-size: 34px;
	font-weight: 800;
	line-height: 1;
	letter-spacing: -1px;
	margin-bottom: 4px;
}
.sfba-metric--blue   .sfba-metric__value { color: #2563eb; }
.sfba-metric--green  .sfba-metric__value { color: #16a34a; }
.sfba-metric--purple .sfba-metric__value { color: #7c3aed; }
.sfba-metric--amber  .sfba-metric__value { color: #d97706; }

.sfba-metric__sub {
	font-size: 11.5px;
	color: #9ca3af;
	line-height: 1.4;
}
.sfba-metric__bar {
	height: 4px;
	background: #f3f4f6;
	border-radius: 99px;
	margin-top: 12px;
	overflow: hidden;
}
.sfba-metric__bar-fill {
	height: 100%;
	border-radius: 99px;
	background: linear-gradient(90deg,#3b82f6,#6366f1);
	transition: width .8s cubic-bezier(.4,0,.2,1);
}

/* ── Section headers ───────────────────────────────────────────────────────── */
.sfba-section-header { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.sfba-section-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.sfba-section-title { font-size:15px; font-weight:700; color:#111827; margin:0 0 2px; }
.sfba-section-sub { font-size:12px; color:#9ca3af; margin:0; }
</style>

<div class="wrap sfba-admin-wrap" style="max-width:1060px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:24px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Internal Links', 'super-fast-blog-ai' ); ?></h1>
				<p class="sfba-dash-hero-sub"><?php esc_html_e( 'Smart internal linking to strengthen your site\'s SEO structure.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>
		<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<?php if ( $index_built ) : ?>
			<span class="sfba-badge sfba-badge-green" style="display:flex;align-items:center;gap:5px;">
				<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#22c55e"/></svg>
				<?php esc_html_e( 'Index Ready', 'super-fast-blog-ai' ); ?>
			</span>
			<?php else : ?>
			<span class="sfba-badge sfba-badge-gray"><?php esc_html_e( 'Index Not Built', 'super-fast-blog-ai' ); ?></span>
			<?php endif; ?>
			<button type="button" id="sfba-build-index-btn"
			        class="sfba-btn <?php echo $index_built ? 'sfba-btn-secondary' : 'sfba-btn-primary'; ?>">
				🔄 <?php echo $index_built ? esc_html__( 'Rebuild Index', 'super-fast-blog-ai' ) : esc_html__( 'Build Index', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<div id="sfba-links-notice" class="sfba-notice" style="display:none;margin-bottom:16px;"></div>

	<!-- ── Metrics strip ──────────────────────────────────────────────────────── -->
	<div class="sfba-metrics">

		<!-- Indexed Posts -->
		<div class="sfba-metric sfba-metric--blue">
			<div class="sfba-metric__label">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
				<?php esc_html_e( 'Indexed Posts', 'super-fast-blog-ai' ); ?>
			</div>
			<div id="sfba-m-indexed" class="sfba-metric__value"><?php echo esc_html( number_format( $index_count ) ); ?></div>
			<div class="sfba-metric__sub"><?php esc_html_e( 'posts in keyword index', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-metric__bar" id="sfba-m-bar-wrap" <?php echo $published_count > 0 ? '' : 'style="display:none;"'; ?>>
				<div id="sfba-m-bar-fill" class="sfba-metric__bar-fill" style="width:<?php echo esc_attr( $coverage_pct ); ?>%;"></div>
			</div>
			<div id="sfba-m-coverage" style="font-size:10px;color:#9ca3af;margin-top:5px;font-weight:600;<?php echo $published_count > 0 ? '' : 'display:none;'; ?>"><?php echo esc_html( $coverage_pct ); ?>% <?php esc_html_e( 'coverage', 'super-fast-blog-ai' ); ?></div>
		</div>

		<!-- Links Inserted -->
		<div class="sfba-metric sfba-metric--green">
			<div class="sfba-metric__label">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
				<?php esc_html_e( 'Links Inserted', 'super-fast-blog-ai' ); ?>
			</div>
			<div id="sfba-m-accepted" class="sfba-metric__value"><?php echo esc_html( number_format( $accepted_count ) ); ?></div>
			<div class="sfba-metric__sub"><?php esc_html_e( 'accepted internal links', 'super-fast-blog-ai' ); ?></div>
		</div>

		<!-- Total Content -->
		<div class="sfba-metric sfba-metric--purple">
			<div class="sfba-metric__label">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
				<?php esc_html_e( 'Total Content', 'super-fast-blog-ai' ); ?>
			</div>
			<div id="sfba-m-published" class="sfba-metric__value"><?php echo esc_html( number_format( $published_count ) ); ?></div>
			<div class="sfba-metric__sub"><?php esc_html_e( 'published posts & pages', 'super-fast-blog-ai' ); ?></div>
		</div>

		<!-- Last Built -->
		<div class="sfba-metric sfba-metric--amber">
			<div class="sfba-metric__label">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
				<?php esc_html_e( 'Last Built', 'super-fast-blog-ai' ); ?>
			</div>
			<div id="sfba-m-date" class="sfba-metric__value" style="font-size:18px;letter-spacing:-.5px;margin-top:4px;">
				<?php echo $last_built ? esc_html( wp_date( 'M j', strtotime( $last_built ) ) ) : '<span style="color:#d1d5db;">—</span>'; ?>
			</div>
			<div id="sfba-m-time" class="sfba-metric__sub">
				<?php echo $last_built ? esc_html( wp_date( 'Y · g:i a', strtotime( $last_built ) ) ) : esc_html__( 'Not built yet', 'super-fast-blog-ai' ); ?>
			</div>
		</div>

	</div>

	<?php if ( ! $index_built ) : ?>
	<div class="sfba-info-box" style="margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;">
		<span style="font-size:22px;flex-shrink:0;">💡</span>
		<div>
			<strong style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Build your index first', 'super-fast-blog-ai' ); ?></strong>
			<p style="margin:0;color:#6b7280;"><?php esc_html_e( 'Click "Build Index" above to scan all published posts and extract keywords. This takes a few seconds and powers all link suggestions below.', 'super-fast-blog-ai' ); ?></p>
		</div>
	</div>
	<?php endif; ?>

	<!-- ── Suggest Internal Links ─────────────────────────────────────────────── -->
	<div class="sfba-card" style="margin-bottom:16px;">

		<div class="sfba-section-header">
			<div class="sfba-section-icon" style="background:#eff6ff;">🔗</div>
			<div>
				<p class="sfba-section-title"><?php esc_html_e( 'Suggest Internal Links', 'super-fast-blog-ai' ); ?></p>
				<p class="sfba-section-sub"><?php esc_html_e( 'Pick a post — we\'ll scan your index and surface the best linking opportunities.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>

		<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
			<div style="flex:1;min-width:220px;">
				<label class="sfba-label" for="sfba-link-post-select"><?php esc_html_e( 'Select Post', 'super-fast-blog-ai' ); ?></label>
				<select id="sfba-link-post-select" class="sfba-input">
					<option value=""><?php esc_html_e( '— Choose a post —', 'super-fast-blog-ai' ); ?></option>
					<?php foreach ( $recent_posts as $post ) : ?>
					<option value="<?php echo esc_attr( $post->ID ); ?>">
						<?php echo esc_html( $post->post_title ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<span class="sfba-field-hint"><?php esc_html_e( 'Shows last 50 published posts.', 'super-fast-blog-ai' ); ?></span>
			</div>
			<div style="padding-bottom:20px;">
				<button type="button" id="sfba-suggest-links-btn" class="sfba-btn sfba-btn-primary" style="height:42px;white-space:nowrap;">
					🔗 <?php esc_html_e( 'Get Suggestions', 'super-fast-blog-ai' ); ?>
				</button>
			</div>
		</div>

		<!-- Results -->
		<div id="sfba-link-suggestions" style="display:none;">
			<div style="border-top:1.5px solid #f3f4f6;padding-top:18px;">
				<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
					<span style="font-size:13px;font-weight:700;color:#111827;">
						💡 <?php esc_html_e( 'Suggested Links', 'super-fast-blog-ai' ); ?>
						<span id="sfba-suggestions-count" style="margin-left:6px;padding:2px 8px;background:#eff6ff;border-radius:20px;font-size:11px;color:#2563eb;font-weight:700;"></span>
					</span>
					<span class="sfba-muted" style="font-size:11px;"><?php esc_html_e( 'Ranked by keyword match score', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div id="sfba-link-suggestions-list" style="display:flex;flex-direction:column;gap:10px;"></div>
			</div>
		</div>
	</div>

	<!-- ── Reverse Link Lookup ────────────────────────────────────────────────── -->
	<div class="sfba-card">

		<div class="sfba-section-header">
			<div class="sfba-section-icon" style="background:#fdf4ff;">🔍</div>
			<div>
				<p class="sfba-section-title"><?php esc_html_e( 'Reverse Link Lookup', 'super-fast-blog-ai' ); ?></p>
				<p class="sfba-section-sub"><?php esc_html_e( 'See which posts already link to a target post, and find new posts that could.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>

		<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
			<div style="flex:1;min-width:220px;">
				<label class="sfba-label" for="sfba-reverse-post-select"><?php esc_html_e( 'Target Post', 'super-fast-blog-ai' ); ?></label>
				<select id="sfba-reverse-post-select" class="sfba-input">
					<option value=""><?php esc_html_e( '— Choose a post —', 'super-fast-blog-ai' ); ?></option>
					<?php foreach ( $recent_posts as $post ) : ?>
					<option value="<?php echo esc_attr( $post->ID ); ?>">
						<?php echo esc_html( $post->post_title ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<span class="sfba-field-hint"><?php esc_html_e( 'Find posts that link — or should link — to this one.', 'super-fast-blog-ai' ); ?></span>
			</div>
			<div style="padding-bottom:20px;">
				<button type="button" id="sfba-reverse-links-btn" class="sfba-btn sfba-btn-secondary" style="height:42px;white-space:nowrap;">
					🔍 <?php esc_html_e( 'Look Up', 'super-fast-blog-ai' ); ?>
				</button>
			</div>
		</div>

		<div id="sfba-reverse-results" style="display:none;">

			<!-- Already inserted links -->
			<div style="border-top:1.5px solid #f3f4f6;padding-top:18px;margin-bottom:22px;">
				<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
					<span style="width:28px;height:28px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;font-size:14px;">✅</span>
					<div>
						<div style="font-size:13px;font-weight:700;color:#111827;"><?php esc_html_e( 'Already Linked Posts', 'super-fast-blog-ai' ); ?>
							<span id="sfba-reverse-count" style="margin-left:6px;padding:2px 8px;background:#f0fdf4;border-radius:20px;font-size:11px;color:#16a34a;font-weight:700;"></span>
						</div>
						<div style="font-size:11px;color:#9ca3af;"><?php esc_html_e( 'Links that were inserted via this tool', 'super-fast-blog-ai' ); ?></div>
					</div>
				</div>
				<div id="sfba-reverse-list" style="display:flex;flex-direction:column;gap:8px;"></div>
			</div>

			<!-- Opportunities -->
			<div style="border-top:1.5px solid #f3f4f6;padding-top:18px;">
				<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
					<span style="width:28px;height:28px;border-radius:8px;background:#fffbeb;display:flex;align-items:center;justify-content:center;font-size:14px;">💡</span>
					<div>
						<div style="font-size:13px;font-weight:700;color:#111827;"><?php esc_html_e( 'Linking Opportunities', 'super-fast-blog-ai' ); ?>
							<span id="sfba-opps-count" style="margin-left:6px;padding:2px 8px;background:#fffbeb;border-radius:20px;font-size:11px;color:#d97706;font-weight:700;"></span>
						</div>
						<div style="font-size:11px;color:#9ca3af;"><?php esc_html_e( 'Posts mentioning related keywords — insert a link with one click', 'super-fast-blog-ai' ); ?></div>
					</div>
				</div>
				<div id="sfba-opportunities-list" style="display:flex;flex-direction:column;gap:10px;"></div>
			</div>

		</div>
	</div>

</div><!-- .wrap -->

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-links-notice' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className   = 'sfba-notice sfba-notice--' + type;
		el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 5000 );
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify( body ),
		} );
		return r.json();
	}
	async function apiGet( path ) {
		const r = await fetch( apiBase + path, { headers: { 'X-WP-Nonce': nonce } } );
		return r.json();
	}
	function escHtml( s ) {
		return String( s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
	function scoreChip( score ) {
		const color  = score >= 5 ? '#dcfce7' : score >= 3 ? '#fef9c3' : '#f3f4f6';
		const border = score >= 5 ? '#86efac' : score >= 3 ? '#fde047' : '#e5e7eb';
		const text   = score >= 5 ? '#15803d' : score >= 3 ? '#854d0e' : '#6b7280';
		return '<span style="flex-shrink:0;padding:3px 10px;border-radius:20px;border:1.5px solid ' + border + ';background:' + color + ';font-size:11px;font-weight:700;color:' + text + ';">Score ' + Number( score ).toFixed(1) + '</span>';
	}
	function spinner() {
		return '<div class="sfba-spinner" style="width:13px;height:13px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px;"></div>';
	}

	// ── Build / Rebuild index ──────────────────────────────────────────────────
	document.getElementById( 'sfba-build-index-btn' )?.addEventListener( 'click', async function () {
		const btn = this;
		btn.disabled = true;
		const orig = btn.innerHTML;
		btn.innerHTML = spinner() + '<?php echo esc_js( __( 'Building…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/internal-links/index', { rebuild: true } );
			if ( data.success ) {
				const d = data.data || {};

				// ── Patch metric numbers in place — no page reload needed ─────
				const fmt = n => Number( n || 0 ).toLocaleString();

				const elIdx  = document.getElementById( 'sfba-m-indexed' );
				const elAcc  = document.getElementById( 'sfba-m-accepted' );
				const elPub  = document.getElementById( 'sfba-m-published' );
				const elDate = document.getElementById( 'sfba-m-date' );
				const elTime = document.getElementById( 'sfba-m-time' );
				const elBar  = document.getElementById( 'sfba-m-bar-fill' );
				const elBarW = document.getElementById( 'sfba-m-bar-wrap' );
				const elCov  = document.getElementById( 'sfba-m-coverage' );

				if ( elIdx  ) { elIdx.textContent  = fmt( d.total_indexed ); }
				if ( elAcc  ) { elAcc.textContent  = fmt( d.total_accepted ); }
				if ( elPub  ) { elPub.textContent  = fmt( d.published_count ); }

				// Format last-built timestamp from ISO-ish MySQL string
				if ( d.last_built ) {
					const dt = new Date( d.last_built.replace( ' ', 'T' ) );
					const dateStr = dt.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
					const timeStr = dt.getFullYear() + ' · ' + dt.toLocaleTimeString( undefined, { hour: 'numeric', minute: '2-digit' } );
					if ( elDate ) elDate.innerHTML = escHtml( dateStr );
					if ( elTime ) elTime.textContent = timeStr;
				}

				// Coverage bar
				const pct = d.coverage_pct || 0;
				if ( elBar  ) { elBar.style.width  = pct + '%'; }
				if ( elBarW ) { elBarW.style.display = ''; }
				if ( elCov  ) { elCov.style.display = ''; elCov.textContent = pct + '% <?php echo esc_js( __( 'coverage', 'super-fast-blog-ai' ) ); ?>'; }

				// Swap badge + button label to "ready" state
				const badge = document.querySelector( '.sfba-badge-gray' );
				if ( badge ) {
					badge.className = 'sfba-badge sfba-badge-green';
					badge.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#22c55e"/></svg> <?php echo esc_js( __( 'Index Ready', 'super-fast-blog-ai' ) ); ?>';
				}

				// Hide the "build first" info box if visible
				const infoBox = document.querySelector( '.sfba-info-box' );
				if ( infoBox ) { infoBox.style.display = 'none'; }

				// Animate the number update with a brief flash
				[ elIdx, elAcc ].forEach( el => {
					if ( ! el ) return;
					el.style.transition = 'color .15s';
					el.style.color = '#22c55e';
					setTimeout( () => { el.style.color = ''; }, 700 );
				} );

				btn.innerHTML = '🔄 <?php echo esc_js( __( 'Rebuild Index', 'super-fast-blog-ai' ) ); ?>';
				btn.disabled  = false;
				btn.className = btn.className.replace( 'sfba-btn-primary', 'sfba-btn-secondary' );

				notice( '✓ <?php echo esc_js( __( 'Index rebuilt —', 'super-fast-blog-ai' ) ); ?> ' + fmt( d.total_indexed ) + ' <?php echo esc_js( __( 'posts indexed.', 'super-fast-blog-ai' ) ); ?>', 'success' );
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Build failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			btn.disabled = false; btn.innerHTML = orig;
		}
	} );

	// ── Suggestion card builder ────────────────────────────────────────────────
	function buildSuggestionCard( s, i, opts = {} ) {
		const matchScore  = s.match_score || 0;
		const anchorText  = s.anchor || s.anchor_text || s.post_title || '';
		const targetUrl   = s.url    || s.target_url  || opts.targetUrl || '';
		const targetTitle = s.post_title || s.target_title || targetUrl;
		const excerpt     = s.context_excerpt || '';
		const keywords    = ( s.matched_keywords || [] ).slice( 0, 4 );

		const card = document.createElement( 'div' );
		card.style.cssText = 'background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;transition:box-shadow .15s,border-color .15s;';
		card.onmouseenter = () => { card.style.boxShadow = '0 4px 18px rgba(0,0,0,.07)'; card.style.borderColor = '#d1d5db'; };
		card.onmouseleave = () => { card.style.boxShadow = ''; card.style.borderColor = '#e5e7eb'; };

		// action buttons
		const insertDataAttr = opts.sourcePostData
			? 'data-source-post="' + escHtml( opts.sourcePostData ) + '" class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-opp-insert"'
			: 'class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-insert-link"';

		card.innerHTML =
			// ── Header ──
			'<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:#fafafa;border-bottom:1px solid #f3f4f6;">' +
				'<span style="width:26px;height:26px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#2563eb;flex-shrink:0;">' + (i+1) + '</span>' +
				'<div style="flex:1;min-width:0;">' +
					'<div style="font-size:13px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml( targetTitle ) + '</div>' +
					'<a href="' + escHtml( targetUrl ) + '" target="_blank" rel="noopener" style="font-size:11px;color:#9ca3af;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;line-height:1.4;">' + escHtml( targetUrl ) + '</a>' +
				'</div>' +
				scoreChip( matchScore ) +
			'</div>' +
			// ── Body ──
			'<div style="padding:12px 16px;">' +
				'<div style="display:flex;align-items:center;gap:8px;margin-bottom:' + ( excerpt || keywords.length ? '10px' : '0' ) + ';flex-wrap:wrap;">' +
					'<span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;"><?php echo esc_js( __( 'Anchor', 'super-fast-blog-ai' ) ); ?></span>' +
					'<code style="padding:3px 10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:12px;font-weight:700;color:#1d4ed8;">' + escHtml( anchorText ) + '</code>' +
				'</div>' +
				( excerpt
					? '<div style="font-size:12px;color:#6b7280;line-height:1.65;background:#f9fafb;border-left:3px solid #dbeafe;padding:7px 12px;border-radius:0 6px 6px 0;margin-bottom:' + ( keywords.length ? '10px' : '0' ) + ';">…' + escHtml( excerpt ) + '…</div>'
					: ''
				) +
				( keywords.length
					? '<div style="display:flex;gap:5px;flex-wrap:wrap;">' +
						keywords.map( k => '<span style="padding:2px 9px;background:#f3f4f6;border-radius:20px;font-size:11px;color:#374151;">' + escHtml(k) + '</span>' ).join('') +
					'</div>'
					: ''
				) +
			'</div>' +
			// ── Actions ──
			'<div style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-top:1px solid #f3f4f6;background:#fafafa;flex-wrap:wrap;">' +
				'<button type="button" ' + insertDataAttr + ' data-anchor="' + escHtml( anchorText ) + '" data-url="' + escHtml( targetUrl ) + '" data-title="' + escHtml( targetTitle ) + '" style="gap:5px;">' +
					'🔗 <?php echo esc_js( __( 'Insert into Post', 'super-fast-blog-ai' ) ); ?>' +
				'</button>' +
				'<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-copy-link" data-anchor="' + escHtml( anchorText ) + '" data-url="' + escHtml( targetUrl ) + '">' +
					'📋 <?php echo esc_js( __( 'Copy HTML', 'super-fast-blog-ai' ) ); ?>' +
				'</button>' +
				'<a href="' + escHtml( targetUrl ) + '" target="_blank" rel="noopener" class="sfba-btn sfba-btn-secondary sfba-btn-sm" style="text-decoration:none;">' +
					'↗ <?php echo esc_js( __( 'View', 'super-fast-blog-ai' ) ); ?>' +
				'</a>' +
			'</div>';
		return card;
	}

	// ── Suggest links ──────────────────────────────────────────────────────────
	document.getElementById( 'sfba-suggest-links-btn' )?.addEventListener( 'click', async function () {
		const postId = parseInt( document.getElementById( 'sfba-link-post-select' )?.value || '0', 10 );
		if ( ! postId ) { notice( '<?php echo esc_js( __( 'Please select a post.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }
		this.disabled = true;
		const orig = this.innerHTML;
		this.innerHTML = spinner() + '<?php echo esc_js( __( 'Suggesting…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/internal-links/suggest', { post_id: postId } );
			if ( data.success ) {
				const list        = document.getElementById( 'sfba-link-suggestions-list' );
				const countBadge  = document.getElementById( 'sfba-suggestions-count' );
				const suggestions = data.data?.suggestions || [];
				list.innerHTML    = '';

				if ( countBadge ) countBadge.textContent = suggestions.length;

				if ( suggestions.length === 0 ) {
					list.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;border:1.5px dashed #e5e7eb;border-radius:10px;"><?php echo esc_js( __( 'No suggestions found. Try rebuilding the index.', 'super-fast-blog-ai' ) ); ?></div>';
				} else {
					suggestions.forEach( ( s, i ) => list.appendChild( buildSuggestionCard( s, i ) ) );
				}
				document.getElementById( 'sfba-link-suggestions' ).style.display = 'block';
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Failed to get suggestions.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}
		this.disabled = false; this.innerHTML = orig;
	} );

	// ── Insert link (from Suggest panel) ──────────────────────────────────────
	document.addEventListener( 'click', async function ( e ) {
		const btn = e.target.closest( '.sfba-insert-link' );
		if ( ! btn ) return;

		const postId = parseInt( document.getElementById( 'sfba-link-post-select' )?.value || '0', 10 );
		if ( ! postId ) { notice( '<?php echo esc_js( __( 'No post selected.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }

		const anchor = btn.dataset.anchor || '';
		const url    = btn.dataset.url    || '';
		const title  = btn.dataset.title  || '';
		const orig   = btn.innerHTML;

		btn.disabled = true;
		btn.textContent = '<?php echo esc_js( __( 'Inserting…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/internal-links/insert', { post_id: postId, anchor, url, title } );
			if ( data.success && data.data?.inserted === true ) {
				btn.innerHTML = '✓ <?php echo esc_js( __( 'Inserted!', 'super-fast-blog-ai' ) ); ?>';
				btn.classList.replace( 'sfba-btn-primary', 'sfba-btn-secondary' );
				btn.disabled = true;
				notice( '<?php echo esc_js( __( 'Link inserted successfully.', 'super-fast-blog-ai' ) ); ?>', 'success' );
			} else if ( data.success && data.data?.inserted === false ) {
				notice( data.data.message || '<?php echo esc_js( __( 'Anchor not found in post.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Failed to insert link.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			}
		} catch ( err ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			btn.disabled = false; btn.innerHTML = orig;
		}
	} );

	// ── Insert link (from Opportunities panel) ────────────────────────────────
	document.addEventListener( 'click', async function ( e ) {
		const btn = e.target.closest( '.sfba-opp-insert' );
		if ( ! btn ) return;

		const sourcePostId = parseInt( btn.dataset.sourcePost || '0', 10 );
		const anchor       = btn.dataset.anchor || '';
		const url          = btn.dataset.url    || '';
		const title        = btn.dataset.title  || '';
		if ( ! sourcePostId || ! anchor || ! url ) {
			notice( '<?php echo esc_js( __( 'Missing data — cannot insert.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return;
		}

		const orig = btn.innerHTML;
		btn.disabled = true;
		btn.textContent = '<?php echo esc_js( __( 'Inserting…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/internal-links/insert', { post_id: sourcePostId, anchor, url, title } );
			if ( data.success && data.data?.inserted === true ) {
				btn.innerHTML = '✓ <?php echo esc_js( __( 'Inserted!', 'super-fast-blog-ai' ) ); ?>';
				btn.classList.replace( 'sfba-btn-primary', 'sfba-btn-secondary' );
				btn.disabled = true;
				notice( '<?php echo esc_js( __( 'Link inserted successfully.', 'super-fast-blog-ai' ) ); ?>', 'success' );
			} else if ( data.success && data.data?.inserted === false ) {
				notice( data.data.message || '<?php echo esc_js( __( 'Anchor not found in post.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Failed to insert link.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			}
		} catch ( err ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			btn.disabled = false; btn.innerHTML = orig;
		}
	} );

	// ── Copy link HTML ─────────────────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.sfba-copy-link' );
		if ( ! btn ) return;
		const html = '<a href="' + ( btn.dataset.url || '' ) + '">' + ( btn.dataset.anchor || '' ) + '</a>';
		const orig = btn.innerHTML;
		try {
			const ta = document.createElement( 'textarea' );
			ta.value = html;
			ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
			document.body.appendChild( ta );
			ta.focus(); ta.select();
			document.execCommand( 'copy' );
			document.body.removeChild( ta );
			btn.textContent = '✓ <?php echo esc_js( __( 'Copied!', 'super-fast-blog-ai' ) ); ?>';
			setTimeout( () => { btn.innerHTML = orig; }, 2000 );
		} catch ( err ) {
			notice( '<?php echo esc_js( __( 'Copy failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}
	} );

	// ── Reverse lookup ─────────────────────────────────────────────────────────
	document.getElementById( 'sfba-reverse-links-btn' )?.addEventListener( 'click', async function () {
		const postId = parseInt( document.getElementById( 'sfba-reverse-post-select' )?.value || '0', 10 );
		if ( ! postId ) { notice( '<?php echo esc_js( __( 'Please select a post.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }
		this.disabled = true;
		const orig = this.innerHTML;
		this.innerHTML = spinner() + '<?php echo esc_js( __( 'Looking up…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiGet( '/internal-links/reverse/' + postId );
			if ( data.success ) {
				const links = data.data?.links        || [];
				const opps  = data.data?.opportunities || [];
				const targetUrl   = data.data?.target?.url        || '';
				const targetTitle = data.data?.target?.post_title || '';

				// ── Already-inserted links ─────────────────────────────────────
				const linksList   = document.getElementById( 'sfba-reverse-list' );
				const countBadge  = document.getElementById( 'sfba-reverse-count' );
				linksList.innerHTML = '';
				if ( countBadge ) countBadge.textContent = links.length;

				if ( links.length === 0 ) {
					linksList.innerHTML = '<div style="padding:16px;text-align:center;color:#9ca3af;border:1.5px dashed #e5e7eb;border-radius:10px;"><?php echo esc_js( __( 'No links inserted to this post yet via this tool.', 'super-fast-blog-ai' ) ); ?></div>';
				} else {
					links.forEach( l => {
						const row = document.createElement( 'div' );
						row.style.cssText = 'display:flex;align-items:center;gap:14px;padding:12px 16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;';
						row.innerHTML =
							'<span style="width:32px;height:32px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">✅</span>' +
							'<div style="flex:1;min-width:0;">' +
								'<strong style="font-size:13px;color:#111827;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml( l.source_post_title || ( 'Post #' + l.source_post_id ) ) + '</strong>' +
								'<div style="font-size:12px;color:#6b7280;margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">' +
									'<span><?php echo esc_js( __( 'Anchor:', 'super-fast-blog-ai' ) ); ?></span>' +
									'<code style="background:#dcfce7;padding:1px 7px;border-radius:4px;font-size:11px;color:#15803d;">' + escHtml( l.anchor_text || '' ) + '</code>' +
									( l.created_at ? '<span style="color:#d1d5db;">·</span><span style="color:#9ca3af;">' + escHtml( l.created_at ) + '</span>' : '' ) +
								'</div>' +
							'</div>' +
							( l.source_edit_url
								? '<a href="' + escHtml( l.source_edit_url ) + '" target="_blank" rel="noopener" class="sfba-btn sfba-btn-secondary sfba-btn-sm" style="text-decoration:none;flex-shrink:0;">✏️ <?php echo esc_js( __( 'Edit', 'super-fast-blog-ai' ) ); ?></a>'
								: ''
							);
						linksList.appendChild( row );
					} );
				}

				// ── Opportunities ──────────────────────────────────────────────
				const oppsList  = document.getElementById( 'sfba-opportunities-list' );
				const oppsBadge = document.getElementById( 'sfba-opps-count' );
				oppsList.innerHTML = '';
				if ( oppsBadge ) oppsBadge.textContent = opps.length;

				if ( opps.length === 0 ) {
					oppsList.innerHTML = '<div style="padding:16px;text-align:center;color:#9ca3af;border:1.5px dashed #e5e7eb;border-radius:10px;"><?php echo esc_js( __( 'No opportunities found — all relevant posts may already link here.', 'super-fast-blog-ai' ) ); ?></div>';
				} else {
					opps.forEach( ( opp, i ) => {
						oppsList.appendChild( buildSuggestionCard( opp, i, {
							targetUrl,
							targetTitle,
							sourcePostData: String( opp.post_id ),
						} ) );
					} );
				}

				document.getElementById( 'sfba-reverse-results' ).style.display = 'block';
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Lookup failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}
		this.disabled = false; this.innerHTML = orig;
	} );
} )();
</script>
