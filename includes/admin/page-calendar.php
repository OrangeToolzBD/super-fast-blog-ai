<?php
/**
 * Content Calendar admin page template.
 *
 * Variables: $entries array, $month int, $year int, $api_base string, $nonce string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$month_name = gmdate( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
$prev_month = $month === 1 ? 12 : $month - 1;
$prev_year  = $month === 1 ? $year - 1 : $year;
$next_month = $month === 12 ? 1 : $month + 1;
$next_year  = $month === 12 ? $year + 1 : $year;

$generate_base = admin_url( 'admin.php?page=sfba-generate&tab=article' );

$status_cfg = [
	'idea'        => [ 'label' => 'Idea',        'bg' => '#f3f4f6', 'color' => '#6b7280',  'dot' => '#9ca3af'  ],
	'planned'     => [ 'label' => 'Planned',      'bg' => '#fefce8', 'color' => '#854d0e',  'dot' => '#eab308'  ],
	'in_progress' => [ 'label' => 'In Progress',  'bg' => '#fff7ed', 'color' => '#c2410c',  'dot' => '#f97316'  ],
	'published'   => [ 'label' => 'Published',    'bg' => '#f0fdf4', 'color' => '#166534',  'dot' => '#22c55e'  ],
];
?>
<div class="wrap sfba-admin-wrap" style="max-width:1100px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Content Calendar', 'super-fast-blog-ai' ); ?></h1>
				<p class="sfba-dash-hero-sub"><?php esc_html_e( 'Plan, schedule and generate your content pipeline.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>
		<div style="display:flex;gap:8px;flex-wrap:wrap;">
			<button type="button" id="sfba-suggest-btn" class="sfba-btn sfba-btn-primary">
				✨ <?php esc_html_e( 'AI Suggest Topics', 'super-fast-blog-ai' ); ?>
			</button>
			<button type="button" id="sfba-add-entry-btn" class="sfba-btn sfba-btn-secondary">
				+ <?php esc_html_e( 'Add Entry', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<div id="sfba-cal-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<!-- Month nav -->
	<div class="sfba-card" style="margin-bottom:20px;padding:14px 20px;">
		<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
			<div style="display:flex;align-items:center;gap:10px;">
				<a href="?page=sfba-content-calendar&month=<?php echo esc_attr( $prev_month ); ?>&year=<?php echo esc_attr( $prev_year ); ?>"
				   class="sfba-btn sfba-btn-secondary sfba-btn-sm">← <?php esc_html_e( 'Prev', 'super-fast-blog-ai' ); ?></a>
				<h2 style="margin:0;font-size:17px;font-weight:700;min-width:140px;text-align:center;"><?php echo esc_html( $month_name ); ?></h2>
				<a href="?page=sfba-content-calendar&month=<?php echo esc_attr( $next_month ); ?>&year=<?php echo esc_attr( $next_year ); ?>"
				   class="sfba-btn sfba-btn-secondary sfba-btn-sm"><?php esc_html_e( 'Next', 'super-fast-blog-ai' ); ?> →</a>
			</div>
			<!-- Status legend -->
			<div style="display:flex;gap:12px;flex-wrap:wrap;">
				<?php foreach ( $status_cfg as $key => $cfg ) : ?>
				<span style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:500;color:#6b7280;">
					<span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $cfg['dot'] ); ?>;flex-shrink:0;"></span>
					<?php echo esc_html( $cfg['label'] ); ?>
				</span>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Entries list -->
	<?php if ( empty( $entries ) ) : ?>
	<div class="sfba-empty-state">
		<div class="sfba-empty-icon">📅</div>
		<h3><?php esc_html_e( 'No entries this month', 'super-fast-blog-ai' ); ?></h3>
		<p><?php esc_html_e( 'Click "AI Suggest Topics" to generate content ideas or "Add Entry" to create one manually.', 'super-fast-blog-ai' ); ?></p>
	</div>
	<?php else : ?>
	<div style="display:flex;flex-direction:column;gap:10px;">
		<?php foreach ( $entries as $entry ) :
			$sc  = $status_cfg[ $entry['status'] ] ?? [ 'label' => ucfirst( $entry['status'] ), 'bg' => '#f3f4f6', 'color' => '#6b7280', 'dot' => '#9ca3af' ];
			$kd  = (int) ( $entry['keyword_difficulty'] ?? 0 );
			$kv  = (int) ( $entry['keyword_volume']     ?? 0 );
			$gen_url = add_query_arg( [
				'tab'     => 'article',
				'title'   => rawurlencode( $entry['title'] ),
				'keyword' => rawurlencode( $entry['target_keyword'] ?? '' ),
			], admin_url( 'admin.php?page=sfba-generate' ) );
		?>
		<div class="sfba-card" style="padding:0;overflow:hidden;border:1.5px solid #e5e7eb;border-radius:12px;">

			<!-- Top bar: status dot + date -->
			<div style="display:flex;align-items:center;gap:0;border-bottom:1px solid #f3f4f6;">
				<span style="display:flex;align-items:center;gap:6px;padding:8px 14px;font-size:11px;font-weight:600;color:<?php echo esc_attr( $sc['color'] ); ?>;background:<?php echo esc_attr( $sc['bg'] ); ?>;flex-shrink:0;">
					<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $sc['dot'] ); ?>;"></span>
					<?php echo esc_html( $sc['label'] ); ?>
				</span>
				<?php if ( ! empty( $entry['suggested_date'] ) && $entry['suggested_date'] !== '0000-00-00' ) : ?>
				<span style="padding:8px 12px;font-size:11px;color:#9ca3af;border-left:1px solid #f3f4f6;">
					📅 <?php echo esc_html( gmdate( 'M j, Y', strtotime( $entry['suggested_date'] ) ) ); ?>
				</span>
				<?php endif; ?>
				<?php if ( $entry['source'] === 'ai_suggested' ) : ?>
				<span style="padding:8px 12px;font-size:11px;color:#7c3aed;border-left:1px solid #f3f4f6;">✨ AI</span>
				<?php endif; ?>
			</div>

			<!-- Main content -->
			<div style="padding:14px 16px;">
				<div style="display:flex;align-items:flex-start;gap:12px;">
					<div style="flex:1;min-width:0;">
						<strong style="font-size:14px;color:#111827;display:block;margin-bottom:6px;line-height:1.4;">
							<?php echo esc_html( $entry['title'] ); ?>
						</strong>
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
							<?php if ( ! empty( $entry['target_keyword'] ) ) : ?>
							<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;font-size:11px;font-weight:600;color:#1d4ed8;">
								🔑 <?php echo esc_html( $entry['target_keyword'] ); ?>
							</span>
							<?php endif; ?>
							<?php if ( $kv > 0 ) : ?>
							<span style="font-size:11px;color:#6b7280;">
								<?php echo esc_html( number_format( $kv ) ); ?>/mo
							</span>
							<?php endif; ?>
							<?php if ( $kd > 0 ) : ?>
							<span style="font-size:11px;color:<?php echo $kd >= 60 ? '#dc2626' : ( $kd >= 35 ? '#d97706' : '#16a34a' ); ?>;">
								KD <?php echo esc_html( $kd ); ?>
							</span>
							<?php endif; ?>
						</div>
					</div>

					<!-- Action buttons -->
					<div style="display:flex;align-items:center;gap:6px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end;">

						<!-- Generate Article button — always visible -->
						<a href="<?php echo esc_url( $gen_url ); ?>"
						   class="sfba-btn sfba-btn-primary sfba-btn-sm"
						   style="gap:4px;white-space:nowrap;">
							⚡ <?php esc_html_e( 'Generate Article', 'super-fast-blog-ai' ); ?>
						</a>

						<?php if ( $entry['status'] === 'idea' || $entry['status'] === 'planned' ) : ?>
						<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-start-writing"
						        data-id="<?php echo esc_attr( $entry['id'] ); ?>"
						        style="white-space:nowrap;">
							✏️ <?php esc_html_e( 'Start Writing', 'super-fast-blog-ai' ); ?>
						</button>
						<?php elseif ( $entry['post_id'] > 0 ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'] ) ); ?>"
						   class="sfba-btn sfba-btn-secondary sfba-btn-sm"
						   style="white-space:nowrap;">
							✏️ <?php esc_html_e( 'Edit Post', 'super-fast-blog-ai' ); ?>
						</a>
						<?php endif; ?>

						<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-delete-entry"
						        data-id="<?php echo esc_attr( $entry['id'] ); ?>"
						        title="<?php esc_attr_e( 'Delete entry', 'super-fast-blog-ai' ); ?>">✕</button>
					</div>
				</div>
			</div>

		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

</div><!-- .sfba-admin-wrap -->

<!-- Suggest modal -->
<div id="sfba-suggest-modal" class="sfba-modal" style="display:none;">
	<div class="sfba-modal-inner" style="width:540px;">
		<h3 style="margin:0 0 6px;">✨ <?php esc_html_e( 'AI Topic Suggestions', 'super-fast-blog-ai' ); ?></h3>
		<p style="margin:0 0 16px;font-size:13px;color:#6b7280;">
			<?php esc_html_e( 'The AI will analyze your existing content and suggest topics that fill coverage gaps.', 'super-fast-blog-ai' ); ?>
		</p>
		<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
			<label class="sfba-label" style="margin:0;"><?php esc_html_e( 'Number of suggestions', 'super-fast-blog-ai' ); ?></label>
			<input type="number" id="sfba-suggest-count" class="sfba-input" value="5" min="1" max="20" style="width:80px;">
		</div>
		<div id="sfba-suggestions-list" style="display:none;max-height:320px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:16px;"></div>
		<div class="sfba-modal-actions">
			<button type="button" id="sfba-do-suggest-btn" class="sfba-btn sfba-btn-primary">
				✨ <?php esc_html_e( 'Generate Ideas', 'super-fast-blog-ai' ); ?>
			</button>
			<button type="button" class="sfba-btn sfba-btn-secondary sfba-modal-close">
				<?php esc_html_e( 'Close', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	function calNotice( msg, type ) {
		const el = document.getElementById( 'sfba-cal-notice' );
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
	async function apiDelete( path ) {
		const r = await fetch( apiBase + path, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } } );
		return r.json();
	}
	function escHtml( s ) {
		return String( s ).replace( /&/g,'&amp;' ).replace( /</g,'&lt;' ).replace( />/g,'&gt;' ).replace( /"/g,'&quot;' );
	}

	// ── Suggest modal ──────────────────────────────────────────────────────────
	document.getElementById( 'sfba-suggest-btn' )?.addEventListener( 'click', () => {
		document.getElementById( 'sfba-suggest-modal' ).style.display = 'flex';
	} );
	document.querySelectorAll( '.sfba-modal-close' ).forEach( b => b.addEventListener( 'click', () => {
		document.getElementById( 'sfba-suggest-modal' ).style.display = 'none';
	} ) );

	document.getElementById( 'sfba-do-suggest-btn' )?.addEventListener( 'click', async function () {
		const count  = parseInt( document.getElementById( 'sfba-suggest-count' )?.value, 10 ) || 5;
		const listEl = document.getElementById( 'sfba-suggestions-list' );
		this.disabled = true;
		this.innerHTML = '<div class="sfba-spinner" style="width:13px;height:13px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px;"></div> <?php echo esc_js( __( 'Generating…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/calendar/suggest', { count } );
			if ( data.success ) {
				listEl.innerHTML = '';
				const inserted = data.data?.inserted ?? 0;
				calNotice( inserted + ' <?php echo esc_js( __( 'ideas added to calendar. Reloading…', 'super-fast-blog-ai' ) ); ?>', 'success' );
				setTimeout( () => window.location.reload(), 1800 );
			} else {
				calNotice( data.message || '<?php echo esc_js( __( 'Failed to generate suggestions.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			}
		} catch ( e ) {
			calNotice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}
		this.disabled = false;
		this.innerHTML = '✨ <?php echo esc_js( __( 'Generate Ideas', 'super-fast-blog-ai' ) ); ?>';
	} );

	// ── Delete entry ───────────────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-delete-entry' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			if ( ! confirm( '<?php echo esc_js( __( 'Delete this calendar entry?', 'super-fast-blog-ai' ) ); ?>' ) ) return;
			this.disabled = true;
			try {
				const data = await apiDelete( '/calendar/entry/' + this.dataset.id );
				if ( data.success ) {
					this.closest( '.sfba-card' )?.remove();
					calNotice( '<?php echo esc_js( __( 'Entry deleted.', 'super-fast-blog-ai' ) ); ?>', 'success' );
				} else {
					calNotice( data.message || '<?php echo esc_js( __( 'Failed to delete.', 'super-fast-blog-ai' ) ); ?>', 'error' );
					this.disabled = false;
				}
			} catch ( e ) {
				calNotice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				this.disabled = false;
			}
		} );
	} );

	// ── Start Writing (creates WP draft) ───────────────────────────────────────
	document.querySelectorAll( '.sfba-start-writing' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			this.disabled = true;
			const orig = this.innerHTML;
			this.innerHTML = '<div class="sfba-spinner" style="width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:5px;"></div> <?php echo esc_js( __( 'Creating…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/calendar/start-writing', { calendar_id: parseInt( this.dataset.id, 10 ) } );
				if ( data.success && data.data?.edit_url ) {
					window.location.href = data.data.edit_url;
				} else {
					calNotice( data.message || '<?php echo esc_js( __( 'Failed to create draft.', 'super-fast-blog-ai' ) ); ?>', 'error' );
					this.disabled = false;
					this.innerHTML = orig;
				}
			} catch ( e ) {
				calNotice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				this.disabled = false;
				this.innerHTML = orig;
			}
		} );
	} );
} )();
</script>
