<?php
/**
 * Brand Voice admin page template.
 *
 * Variables:
 *   $summary  array|null  — from get_voice_summary(): tone, pov, avg_sentence_length,
 *                           vocab_level, vocab_level_num, uses_questions, uses_lists,
 *                           contraction_rate, posts_analyzed, analyzed_at,
 *                           industry_terms[], overrides[]
 *   $enabled  bool        — brand_voice.enabled setting
 *   $api_base string
 *   $nonce    string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$has_profile = ! empty( $summary );
$overrides   = $has_profile ? ( $summary['overrides'] ?? [] ) : [];
$post_count  = (int) $this->core->settings->get( 'brand_voice.post_count', 30 );
?>
<div class="wrap sfba-admin-wrap" style="max-width:1060px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:24px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Brand Voice', 'super-fast-blog-ai' ); ?></h1>
				<p class="sfba-dash-hero-sub"><?php esc_html_e( 'AI learns your writing style and applies it to every generation.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>
		<div style="display:flex;align-items:center;gap:10px;">
			<?php if ( $has_profile ) : ?>
			<span class="sfba-badge sfba-badge-green" style="font-size:12px;">✓ <?php esc_html_e( 'Profile Active', 'super-fast-blog-ai' ); ?></span>
			<?php endif; ?>
			<button type="button" id="sfba-analyze-btn" class="sfba-btn sfba-btn-primary">
				🔍 <?php echo $has_profile ? esc_html__( 'Re-analyze', 'super-fast-blog-ai' ) : esc_html__( 'Analyze Writing Style', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<div id="sfba-bv-notice" class="sfba-notice" style="display:none;margin-bottom:16px;"></div>

	<?php if ( ! $has_profile ) : ?>
	<!-- Empty state -->
	<div class="sfba-info-box" style="margin-bottom:20px;">
		<h4><?php esc_html_e( 'How it works', 'super-fast-blog-ai' ); ?></h4>
		<p><?php esc_html_e( 'Brand Voice scans your published posts locally (no AI cost) to detect your unique writing patterns — sentence length, vocabulary level, tone, point of view, and style markers. This profile is then injected into AI prompts so generated content sounds like you.', 'super-fast-blog-ai' ); ?></p>
	</div>
	<div class="sfba-empty-state">
		<div class="sfba-empty-icon">🎨</div>
		<h3><?php esc_html_e( 'No voice profile yet', 'super-fast-blog-ai' ); ?></h3>
		<p><?php esc_html_e( 'Click "Analyze Writing Style" to scan your published posts and build your profile.', 'super-fast-blog-ai' ); ?></p>
		<div style="margin-top:16px;display:flex;align-items:center;gap:10px;justify-content:center;">
			<label class="sfba-label" style="margin:0;text-transform:none;letter-spacing:0;font-size:13px;"><?php esc_html_e( 'Analyze last', 'super-fast-blog-ai' ); ?></label>
			<input type="number" id="sfba-post-count" class="sfba-input" style="width:80px;"
			       value="<?php echo esc_attr( $post_count ); ?>" min="1" max="200">
			<label class="sfba-muted" style="margin:0;"><?php esc_html_e( 'posts', 'super-fast-blog-ai' ); ?></label>
			<button type="button" id="sfba-analyze-btn-2" class="sfba-btn sfba-btn-primary">
				🔍 <?php esc_html_e( 'Analyze Now', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<?php else : ?>

	<!-- ── Profile Overview ─────────────────────────────────────────────────── -->
	<div class="sfba-card" style="margin-bottom:16px;">
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
			<h3 style="margin:0;font-size:15px;">📊 <?php esc_html_e( 'Detected Writing Profile', 'super-fast-blog-ai' ); ?></h3>
			<span class="sfba-muted" style="font-size:12px;">
				<?php
				printf(
					/* translators: 1: post count, 2: date */
					esc_html__( 'Based on %1$d posts · analyzed %2$s', 'super-fast-blog-ai' ),
					(int) $summary['posts_analyzed'],
					esc_html( wp_date( get_option( 'date_format' ), strtotime( $summary['analyzed_at'] ) ) )
				);
				?>
			</span>
		</div>

		<!-- Stat grid -->
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
			<?php
			$stats = [
				[ 'icon' => '🗣️', 'label' => __( 'Tone', 'super-fast-blog-ai' ),            'value' => $summary['tone'] ],
				[ 'icon' => '👁️', 'label' => __( 'Point of View', 'super-fast-blog-ai' ),   'value' => $summary['pov'] ],
				[ 'icon' => '📏', 'label' => __( 'Avg Sentence', 'super-fast-blog-ai' ),     'value' => $summary['avg_sentence_length'] . ' ' . __( 'words', 'super-fast-blog-ai' ) ],
				[ 'icon' => '📖', 'label' => __( 'Vocabulary', 'super-fast-blog-ai' ),       'value' => $summary['vocab_level'] ],
				[ 'icon' => '❓', 'label' => __( 'Uses Questions', 'super-fast-blog-ai' ),   'value' => $summary['uses_questions'] ? __( 'Yes', 'super-fast-blog-ai' ) : __( 'No', 'super-fast-blog-ai' ) ],
				[ 'icon' => '📋', 'label' => __( 'Uses Lists', 'super-fast-blog-ai' ),       'value' => $summary['uses_lists']     ? __( 'Yes', 'super-fast-blog-ai' ) : __( 'No', 'super-fast-blog-ai' ) ],
				[ 'icon' => '🤝', 'label' => __( 'Contractions', 'super-fast-blog-ai' ),     'value' => $summary['contraction_rate'] . '%' ],
			];
			foreach ( $stats as $stat ) :
			?>
			<div class="sfba-profile-stat" style="display:flex;flex-direction:column;gap:4px;">
				<span class="sfba-profile-stat-label"><?php echo esc_html( $stat['icon'] ); ?> <?php echo esc_html( $stat['label'] ); ?></span>
				<span class="sfba-profile-stat-value"><?php echo esc_html( $stat['value'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>

		<!-- Industry terms -->
		<?php if ( ! empty( $summary['industry_terms'] ) ) : ?>
		<div class="sfba-terms-section">
			<strong style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sfba-grey-400);display:block;margin-bottom:8px;">
				<?php esc_html_e( 'Top Keywords Found', 'super-fast-blog-ai' ); ?>
			</strong>
			<div class="sfba-terms">
				<?php foreach ( $summary['industry_terms'] as $term ) : ?>
				<span class="sfba-tag"><?php echo esc_html( $term ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Re-analyze controls -->
		<div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--sfba-grey-100);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
			<label class="sfba-muted" style="margin:0;"><?php esc_html_e( 'Analyze last', 'super-fast-blog-ai' ); ?></label>
			<input type="number" id="sfba-post-count" class="sfba-input" style="width:80px;"
			       value="<?php echo esc_attr( $post_count ); ?>" min="1" max="200">
			<label class="sfba-muted" style="margin:0;"><?php esc_html_e( 'posts', 'super-fast-blog-ai' ); ?></label>
			<button type="button" id="sfba-analyze-btn-3" class="sfba-btn sfba-btn-secondary">
				🔄 <?php esc_html_e( 'Re-analyze', 'super-fast-blog-ai' ); ?>
			</button>
			<button type="button" id="sfba-delete-profile-btn" class="sfba-btn sfba-btn-danger">
				🗑 <?php esc_html_e( 'Delete Profile', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<!-- ── Voice Customization ──────────────────────────────────────────────── -->
	<div class="sfba-card" style="margin-bottom:16px;">
		<div style="margin-bottom:18px;">
			<h3 style="margin:0 0 4px;font-size:15px;">🎛️ <?php esc_html_e( 'Customize Voice', 'super-fast-blog-ai' ); ?></h3>
			<p class="sfba-muted"><?php esc_html_e( 'Override the auto-detected settings. These apply on top of analysis and survive re-analysis.', 'super-fast-blog-ai' ); ?></p>
		</div>

		<div class="sfba-form-grid sfba-form-grid-3" style="margin-bottom:16px;">
			<div class="sfba-field-group">
				<label class="sfba-label" for="sfba-override-tone"><?php esc_html_e( 'Override Tone', 'super-fast-blog-ai' ); ?></label>
				<select id="sfba-override-tone" class="sfba-input">
					<option value=""><?php esc_html_e( '— Auto-detected —', 'super-fast-blog-ai' ); ?></option>
					<option value="formal"  <?php selected( $overrides['tone'] ?? '', 'formal' ); ?>><?php esc_html_e( 'Formal', 'super-fast-blog-ai' ); ?></option>
					<option value="casual"  <?php selected( $overrides['tone'] ?? '', 'casual' ); ?>><?php esc_html_e( 'Casual', 'super-fast-blog-ai' ); ?></option>
				</select>
				<?php /* translators: %s is a count/number */ ?>
				<span class="sfba-field-hint"><?php printf( esc_html__( 'Detected: %s', 'super-fast-blog-ai' ), esc_html( $summary['tone'] ) ); ?></span>
			</div>
			<div class="sfba-field-group">
				<label class="sfba-label" for="sfba-override-pov"><?php esc_html_e( 'Override Point of View', 'super-fast-blog-ai' ); ?></label>
				<select id="sfba-override-pov" class="sfba-input">
					<option value=""><?php esc_html_e( '— Auto-detected —', 'super-fast-blog-ai' ); ?></option>
					<option value="first"  <?php selected( $overrides['pov'] ?? '', 'first'  ); ?>><?php esc_html_e( 'First person (I/We)', 'super-fast-blog-ai' ); ?></option>
					<option value="second" <?php selected( $overrides['pov'] ?? '', 'second' ); ?>><?php esc_html_e( 'Second person (You)', 'super-fast-blog-ai' ); ?></option>
					<option value="third"  <?php selected( $overrides['pov'] ?? '', 'third'  ); ?>><?php esc_html_e( 'Third person (They)', 'super-fast-blog-ai' ); ?></option>
				</select>
				<?php /* translators: %s is a count/number */ ?>
				<span class="sfba-field-hint"><?php printf( esc_html__( 'Detected: %s', 'super-fast-blog-ai' ), esc_html( $summary['pov'] ) ); ?></span>
			</div>
			<div class="sfba-field-group">
				<label class="sfba-label" for="sfba-override-sentence-length"><?php esc_html_e( 'Target Sentence Length', 'super-fast-blog-ai' ); ?></label>
				<input type="number" id="sfba-override-sentence-length" class="sfba-input"
				       value="<?php echo esc_attr( $overrides['sentence_length'] ?? '' ); ?>"
				       placeholder="<?php echo esc_attr( $summary['avg_sentence_length'] ); ?>"
				       min="5" max="50">
				<?php /* translators: %s is a count/number */ ?>
				<span class="sfba-field-hint"><?php printf( esc_html__( 'Detected avg: %d words', 'super-fast-blog-ai' ), (int) $summary['avg_sentence_length'] ); ?></span>
			</div>
		</div>

		<div class="sfba-field-group" style="margin-bottom:18px;">
			<label class="sfba-label" for="sfba-override-extra"><?php esc_html_e( 'Extra Style Instructions', 'super-fast-blog-ai' ); ?></label>
			<textarea id="sfba-override-extra" class="sfba-input" rows="3"
			          style="resize:vertical;"
			          placeholder="<?php esc_attr_e( 'e.g. Always use numbered lists for step-by-step guides. Avoid passive voice. Start paragraphs with a strong hook.', 'super-fast-blog-ai' ); ?>"><?php echo esc_textarea( $overrides['extra_instructions'] ?? '' ); ?></textarea>
			<span class="sfba-field-hint"><?php esc_html_e( 'Free-text instruction appended to every AI prompt.', 'super-fast-blog-ai' ); ?></span>
		</div>

		<div class="sfba-action-bar">
			<button type="button" id="sfba-save-overrides-btn" class="sfba-btn sfba-btn-primary">
				💾 <?php esc_html_e( 'Save Customizations', 'super-fast-blog-ai' ); ?>
			</button>
			<?php if ( ! empty( $overrides ) ) : ?>
			<button type="button" id="sfba-clear-overrides-btn" class="sfba-btn sfba-btn-secondary">
				↩ <?php esc_html_e( 'Clear Overrides', 'super-fast-blog-ai' ); ?>
			</button>
			<?php endif; ?>
		</div>
	</div>

	<?php endif; ?>

	<!-- ── Enable / Settings ────────────────────────────────────────────────── -->
	<div class="sfba-card">
		<h3 style="margin:0 0 16px;font-size:15px;">⚙️ <?php esc_html_e( 'Settings', 'super-fast-blog-ai' ); ?></h3>
		<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:var(--sfba-grey-50);border:1px solid var(--sfba-grey-200);border-radius:8px;">
			<div>
				<strong style="font-size:13px;display:block;margin-bottom:2px;"><?php esc_html_e( 'Apply Brand Voice to all generations', 'super-fast-blog-ai' ); ?></strong>
				<p class="sfba-muted" style="margin:0;"><?php esc_html_e( 'Injects your voice profile into every AI prompt automatically.', 'super-fast-blog-ai' ); ?></p>
			</div>
			<label class="sfba-toggle">
				<input type="checkbox" id="sfba-bv-enabled" <?php checked( $enabled ); ?>>
				<span class="sfba-toggle-slider"></span>
			</label>
		</div>
		<div style="margin-top:12px;">
			<button type="button" id="sfba-save-settings-btn" class="sfba-btn sfba-btn-primary">
				<?php esc_html_e( 'Save Settings', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

</div>

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-bv-notice' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className = 'sfba-notice sfba-notice--' + type;
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

	// ── Analyze ────────────────────────────────────────────────────────────────
	async function doAnalyze( btn ) {
		const postCount = parseInt( document.getElementById( 'sfba-post-count' )?.value || '30', 10 );
		const orig = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<div class="sfba-spinner" style="width:13px;height:13px;border-width:2px;"></div> <?php echo esc_js( __( 'Analyzing…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/brand-voice/analyze', { post_count: postCount } );
			if ( data.success ) {
				notice( '<?php echo esc_js( __( 'Analysis complete! Reloading…', 'super-fast-blog-ai' ) ); ?>', 'success' );
				setTimeout( () => window.location.reload(), 1200 );
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Analysis failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				btn.disabled = false; btn.innerHTML = orig;
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			btn.disabled = false; btn.innerHTML = orig;
		}
	}

	[ 'sfba-analyze-btn', 'sfba-analyze-btn-2', 'sfba-analyze-btn-3' ].forEach( id => {
		document.getElementById( id )?.addEventListener( 'click', function () { doAnalyze( this ); } );
	} );

	// ── Delete profile ─────────────────────────────────────────────────────────
	document.getElementById( 'sfba-delete-profile-btn' )?.addEventListener( 'click', async function () {
		if ( ! confirm( '<?php echo esc_js( __( 'Delete your brand voice profile? This cannot be undone.', 'super-fast-blog-ai' ) ); ?>' ) ) return;
		this.disabled = true;
		try {
			const data = await apiDelete( '/brand-voice' );
			if ( data.success ) {
				notice( '<?php echo esc_js( __( 'Profile deleted. Reloading…', 'super-fast-blog-ai' ) ); ?>', 'success' );
				setTimeout( () => window.location.reload(), 1200 );
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Failed to delete.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				this.disabled = false;
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			this.disabled = false;
		}
	} );

	// ── Save overrides ─────────────────────────────────────────────────────────
	document.getElementById( 'sfba-save-overrides-btn' )?.addEventListener( 'click', async function () {
		const payload = {
			tone:                document.getElementById( 'sfba-override-tone' )?.value            || null,
			pov:                 document.getElementById( 'sfba-override-pov' )?.value             || null,
			sentence_length:     parseInt( document.getElementById( 'sfba-override-sentence-length' )?.value || '0', 10 ) || null,
			extra_instructions:  document.getElementById( 'sfba-override-extra' )?.value.trim()   || '',
		};
		// Strip nulls
		Object.keys( payload ).forEach( k => { if ( payload[k] === null ) delete payload[k]; } );

		this.disabled = true; this.textContent = '<?php echo esc_js( __( 'Saving…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/brand-voice/adjust', payload );
			if ( data.success ) { notice( '<?php echo esc_js( __( 'Customizations saved.', 'super-fast-blog-ai' ) ); ?>', 'success' ); }
			else { notice( data.message || '<?php echo esc_js( __( 'Save failed.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		} catch ( e ) { notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		this.disabled = false; this.innerHTML = '💾 <?php echo esc_js( __( 'Save Customizations', 'super-fast-blog-ai' ) ); ?>';
	} );

	// ── Clear overrides ────────────────────────────────────────────────────────
	document.getElementById( 'sfba-clear-overrides-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true;
		try {
			const data = await apiPost( '/brand-voice/adjust', {} );
			if ( data.success ) {
				notice( '<?php echo esc_js( __( 'Overrides cleared. Reloading…', 'super-fast-blog-ai' ) ); ?>', 'success' );
				setTimeout( () => window.location.reload(), 1200 );
			} else { notice( data.message || '<?php echo esc_js( __( 'Failed.', 'super-fast-blog-ai' ) ); ?>', 'error' ); this.disabled = false; }
		} catch ( e ) { notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); this.disabled = false; }
	} );

	// ── Save settings (enable toggle) ──────────────────────────────────────────
	document.getElementById( 'sfba-save-settings-btn' )?.addEventListener( 'click', async function () {
		const enabled    = document.getElementById( 'sfba-bv-enabled' )?.checked ?? false;
		const postCount  = parseInt( document.getElementById( 'sfba-post-count' )?.value || '30', 10 );
		this.disabled = true; this.textContent = '<?php echo esc_js( __( 'Saving…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/settings', { brand_voice: { enabled, post_count: postCount } } );
			if ( data.success ) { notice( '<?php echo esc_js( __( 'Settings saved.', 'super-fast-blog-ai' ) ); ?>', 'success' ); }
			else { notice( data.message || '<?php echo esc_js( __( 'Save failed.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		} catch ( e ) { notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		this.disabled = false; this.textContent = '<?php echo esc_js( __( 'Save Settings', 'super-fast-blog-ai' ) ); ?>';
	} );
} )();
</script>
