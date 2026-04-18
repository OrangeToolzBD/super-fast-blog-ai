<?php
/**
 * Repurpose Content admin page template.
 *
 * Variables: $api_base string, $nonce string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$platforms = [
	'twitter'   => [ 'icon' => '🐦', 'label' => 'Twitter / X',  'color' => '#1da1f2' ],
	'linkedin'  => [ 'icon' => '💼', 'label' => 'LinkedIn',      'color' => '#0077b5' ],
	'email'     => [ 'icon' => '✉️',  'label' => 'Email',         'color' => '#059669' ],
	'facebook'  => [ 'icon' => '👥', 'label' => 'Facebook',      'color' => '#1877f2' ],
	'instagram' => [ 'icon' => '📸', 'label' => 'Instagram',     'color' => '#e1306c' ],
	'youtube'   => [ 'icon' => '🎥', 'label' => 'YouTube',       'color' => '#ff0000' ],
];
?>
<div class="wrap sfba-admin-wrap">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Repurpose Content', 'super-fast-blog-ai' ); ?></h1>
				<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
			</div>
		</div>
	</div>

	<div id="sfba-rep-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<!-- Post selector -->
	<div class="sfba-card" style="margin-bottom:16px;">
		<h3 style="margin:0 0 16px;"><?php esc_html_e( 'Select a Post to Repurpose', 'super-fast-blog-ai' ); ?></h3>
		<div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
			<div style="flex:1;min-width:220px;">
				<label class="sfba-label" for="sfba-rep-post-select"><?php esc_html_e( 'Choose a published post', 'super-fast-blog-ai' ); ?></label>
				<select id="sfba-rep-post-select" class="sfba-input">
					<option value=""><?php esc_html_e( '— Select a post —', 'super-fast-blog-ai' ); ?></option>
					<?php foreach ( $recent_posts as $post ) : ?>
					<option value="<?php echo esc_attr( $post->ID ); ?>">
						<?php echo esc_html( $post->post_title ); ?> <span style="color:#9ca3af;">(ID: <?php echo esc_html( $post->ID ); ?>)</span>
					</option>
					<?php endforeach; ?>
				</select>
				<span class="sfba-field-hint"><?php esc_html_e( 'Shows last 50 published posts.', 'super-fast-blog-ai' ); ?></span>
			</div>
			<div style="flex:2;min-width:220px;">
				<label class="sfba-label" for="sfba-rep-content"><?php esc_html_e( 'Or paste content directly', 'super-fast-blog-ai' ); ?></label>
				<textarea id="sfba-rep-content" class="sfba-textarea" rows="5"
				          placeholder="<?php esc_attr_e( 'Paste the blog post content here…', 'super-fast-blog-ai' ); ?>"></textarea>
				<span class="sfba-field-hint"><?php esc_html_e( 'If a post is selected above, its content will be used automatically.', 'super-fast-blog-ai' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Hashtag Language — single line -->
	<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 16px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
		<span style="font-size:12px;font-weight:600;color:#6b7280;white-space:nowrap;flex-shrink:0;">
			# <?php esc_html_e( 'Hashtag Language', 'super-fast-blog-ai' ); ?>
		</span>
		<div style="display:flex;gap:8px;flex-wrap:nowrap;">
			<label id="sfba-hl-english-label"
			       style="display:flex;align-items:center;gap:6px;padding:5px 12px;border:1.5px solid #2563eb;border-radius:50px;background:#eff6ff;cursor:pointer;font-size:12px;font-weight:600;color:#1d4ed8;user-select:none;white-space:nowrap;transition:all .15s;">
				<input type="checkbox" id="sfba-hl-english" checked style="display:none;">
				<span id="sfba-hl-english-dot" style="width:7px;height:7px;border-radius:50%;background:#2563eb;flex-shrink:0;"></span>
				<?php esc_html_e( 'English', 'super-fast-blog-ai' ); ?>
			</label>
			<label id="sfba-hl-source-label"
			       style="display:flex;align-items:center;gap:6px;padding:5px 12px;border:1.5px solid #e5e7eb;border-radius:50px;background:#f9fafb;cursor:pointer;font-size:12px;font-weight:600;color:#6b7280;user-select:none;white-space:nowrap;transition:all .15s;">
				<input type="checkbox" id="sfba-hl-source" style="display:none;">
				<span id="sfba-hl-source-dot" style="width:7px;height:7px;border-radius:50%;background:#d1d5db;flex-shrink:0;"></span>
				<?php esc_html_e( 'Source Language', 'super-fast-blog-ai' ); ?>
			</label>
		</div>
		<span style="font-size:11px;color:#d1d5db;flex-shrink:0;"><?php esc_html_e( 'Select one or both', 'super-fast-blog-ai' ); ?></span>
	</div>

	<!-- Platform grid -->
	<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
		<?php foreach ( $platforms as $slug => $p ) : ?>
		<div class="sfba-card" style="border-top:3px solid <?php echo esc_attr( $p['color'] ); ?>;">
			<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
				<span style="font-size:22px;"><?php echo $p['icon']; ?></span>
				<strong style="font-size:14px;"><?php echo esc_html( $p['label'] ); ?></strong>
			</div>
			<button type="button"
			        class="sfba-btn sfba-btn-primary sfba-rep-platform-btn"
			        data-platform="<?php echo esc_attr( $slug ); ?>"
			        style="width:100%;justify-content:center;background:<?php echo esc_attr( $p['color'] ); ?>;border-color:<?php echo esc_attr( $p['color'] ); ?>;">
				<?php echo esc_html( sprintf(
					/* translators: %s: platform name */
					__( 'Repurpose for %s', 'super-fast-blog-ai' ),
					$p['label']
				) ); ?>
			</button>
			<div class="sfba-rep-result" data-platform="<?php echo esc_attr( $slug ); ?>"
			     style="display:none;margin-top:12px;">
				<pre class="sfba-rep-text" style="white-space:pre-wrap;font-family:inherit;font-size:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px;max-height:200px;overflow-y:auto;"></pre>
				<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-rep-copy" style="margin-top:6px;">
					📋 <?php esc_html_e( 'Copy', 'super-fast-blog-ai' ); ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Repurpose all -->
	<div class="sfba-card" style="margin-top:20px;text-align:center;">
		<button type="button" id="sfba-rep-all-btn" class="sfba-btn sfba-btn-primary sfba-btn-lg">
			✨ <?php esc_html_e( 'Repurpose for All Platforms', 'super-fast-blog-ai' ); ?>
		</button>
		<p style="margin:10px 0 0;font-size:12px;color:#9ca3af;">
			<?php esc_html_e( 'Generates content for all 6 platforms at once.', 'super-fast-blog-ai' ); ?>
		</p>
	</div>

</div>

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	// ── Hashtag language pill toggle ─────────────────────────────────────────
	function syncPill( checkboxId, labelId, dotId, activeColor ) {
		const cb    = document.getElementById( checkboxId );
		const label = document.getElementById( labelId );
		const dot   = document.getElementById( dotId );
		if ( ! cb || ! label ) return;
		function update() {
			if ( cb.checked ) {
				label.style.borderColor = activeColor;
				label.style.background  = activeColor === '#2563eb' ? '#eff6ff' : '#f0fdf4';
				label.style.color       = activeColor === '#2563eb' ? '#1d4ed8' : '#15803d';
				if ( dot ) dot.style.background = activeColor;
			} else {
				label.style.borderColor = '#e5e7eb';
				label.style.background  = '#f9fafb';
				label.style.color       = '#6b7280';
				if ( dot ) dot.style.background = '#d1d5db';
			}
		}
		label.addEventListener( 'click', () => { cb.checked = ! cb.checked; update(); } );
		update();
	}
	syncPill( 'sfba-hl-english', 'sfba-hl-english-label', 'sfba-hl-english-dot', '#2563eb' );
	syncPill( 'sfba-hl-source',  'sfba-hl-source-label',  'sfba-hl-source-dot',  '#16a34a' );

	// ── Helpers ───────────────────────────────────────────────────────────────
	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-rep-notice' );
		if ( ! el ) return;
		el.textContent = msg; el.className = 'sfba-notice sfba-notice--' + type; el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 6000 );
	}
	function getHashtagLang() {
		const en  = document.getElementById( 'sfba-hl-english' )?.checked;
		const src = document.getElementById( 'sfba-hl-source' )?.checked;
		if ( en && src ) return 'both';
		if ( src )       return 'source';
		return 'english';
	}
	function getInput() {
		return {
			post_id:      parseInt( document.getElementById( 'sfba-rep-post-select' )?.value || '0', 10 ),
			content:      document.getElementById( 'sfba-rep-content' )?.value.trim() || '',
			hashtag_lang: getHashtagLang(),
		};
	}

	// ── Format result ─────────────────────────────────────────────────────────
	function formatResult( platform, result ) {
		if ( ! result || typeof result !== 'object' ) return String( result );
		switch ( platform ) {
			case 'twitter':
				return Array.isArray( result.thread )
					? result.thread.map( ( t, i ) => ( i + 1 ) + '. ' + t ).join( '\n\n' )
					: ( result.single || '' );
			case 'linkedin': {
				let lk = result.post || '';
				if ( result.hashtags?.length ) lk += '\n\n' + result.hashtags.map( h => '#' + h ).join( ' ' );
				return lk;
			}
			case 'facebook':
				return result.post || '';
			case 'email': {
				let em = '';
				if ( result.subject )      em += 'Subject: '  + result.subject      + '\n';
				if ( result.preview_text ) em += 'Preview: '  + result.preview_text + '\n';
				if ( result.body_html )    em += '\n' + result.body_html.replace( /<[^>]+>/g, '' ).replace( /\n{3,}/g, '\n\n' ).trim();
				if ( result.cta_text )     em += '\n\n' + result.cta_text + ( result.cta_url ? ' → ' + result.cta_url : '' );
				return em.trim();
			}
			case 'instagram': {
				let ig = result.caption || '';
				if ( result.hashtags?.length ) ig += '\n\n' + result.hashtags.map( h => '#' + h ).join( ' ' );
				return ig;
			}
			case 'youtube': {
				let yt = '';
				if ( result.title )       yt += '📺 ' + result.title + '\n\n';
				if ( result.description ) yt += result.description + '\n\n';
				if ( Array.isArray( result.script_outline ) ) {
					yt += '── Script Outline ──\n';
					result.script_outline.forEach( s => {
						yt += '\n[' + ( s.timestamp || '' ) + '] ' + ( s.section || '' ) + '\n';
						( s.talking_points || [] ).forEach( pt => { yt += '  • ' + pt + '\n'; } );
					} );
				}
				return yt.trim();
			}
			default:
				return JSON.stringify( result, null, 2 );
		}
	}
	function showResult( platform, result ) {
		const wrap = document.querySelector( '.sfba-rep-result[data-platform="' + platform + '"]' );
		const pre  = wrap?.querySelector( '.sfba-rep-text' );
		if ( wrap && pre ) { pre.textContent = formatResult( platform, result ); wrap.style.display = 'block'; }
	}
	async function repurpose( platform, body ) {
		const r = await fetch( apiBase + '/repurpose/' + platform, {
			method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( body ),
		} );
		return r.json();
	}

	// ── Per-platform buttons ──────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-rep-platform-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const platform = this.dataset.platform;
			const input    = getInput();
			if ( ! input.post_id && ! input.content ) { notice( 'Please select a post or paste content.', 'error' ); return; }
			this.disabled = true; const orig = this.textContent; this.textContent = 'Generating…';
			try {
				const data = await repurpose( platform, input );
				if ( data.success ) { showResult( platform, data.data?.result ?? data.data ); }
				else { notice( data.message || 'Generation failed.', 'error' ); }
			} catch ( e ) { notice( 'Network error.', 'error' ); }
			this.disabled = false; this.textContent = orig;
		} );
	} );

	// ── Generate All button ───────────────────────────────────────────────────
	document.getElementById( 'sfba-rep-all-btn' )?.addEventListener( 'click', async function () {
		const input = getInput();
		if ( ! input.post_id && ! input.content ) { notice( 'Please select a post or paste content.', 'error' ); return; }
		this.disabled = true; this.textContent = '✨ Generating…';
		try {
			const r    = await fetch( apiBase + '/repurpose', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( input ) } );
			const data = await r.json();
			if ( data.success ) {
				const results = data.data?.results || {};
				Object.keys( results ).forEach( pl => showResult( pl, results[ pl ] ) );
				notice( 'All platforms generated.', 'success' );
			} else { notice( data.message || 'Generation failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = '✨ Repurpose for All Platforms';
	} );

	// ── Copy buttons ──────────────────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.sfba-rep-copy' );
		if ( ! btn ) return;
		const pre = btn.previousElementSibling;
		if ( ! pre ) return;
		const text = pre.textContent;
		const orig = btn.innerHTML;
		function markCopied() { btn.textContent = '✓ Copied!'; setTimeout( () => { btn.innerHTML = orig; }, 2000 ); }
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( markCopied ).catch( () => notice( 'Copy failed.', 'error' ) );
		} else {
			try {
				const ta = document.createElement( 'textarea' );
				ta.value = text; ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
				document.body.appendChild( ta ); ta.focus(); ta.select();
				document.execCommand( 'copy' ); document.body.removeChild( ta ); markCopied();
			} catch ( err ) { notice( 'Copy failed — please select and copy manually.', 'error' ); }
		}
	} );
} )();
</script>
