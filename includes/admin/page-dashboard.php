<?php
/**
 * Dashboard admin page template.
 *
 * Variables:
 *   $summary      array  — total_cost_usd, total_generations, this_month_cost, ...
 *   $voice_status bool   — whether brand voice profile exists
 *   $providers    array  — all provider info arrays
 *   $connected    int    — count of providers with keys
 *   $api_base     string
 *   $nonce        string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$modules = [
	[
		'slug'  => 'sfba-generate',
		'query' => '&tab=titles',
		'icon'  => '🔤',
		'color' => '#3b82f6',
		'bg'    => '#eff6ff',
		'title' => __( 'Generate Titles', 'super-fast-blog-ai' ),
		'desc'  => __( 'AI title ideas from a topic in seconds.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-generate',
		'query' => '&tab=article',
		'icon'  => '📝',
		'color' => '#8b5cf6',
		'bg'    => '#f5f3ff',
		'title' => __( 'Generate Article (AI)', 'super-fast-blog-ai' ),
		'desc'  => __( 'Full SEO blog post saved as WP draft.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-brand-voice',
		'query' => '',
		'icon'  => '🎨',
		'color' => '#7c3aed',
		'bg'    => '#f5f3ff',
		'title' => __( 'Brand Voice', 'super-fast-blog-ai' ),
		'desc'  => __( 'Apply your writing style to every generation.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-content-calendar',
		'query' => '',
		'icon'  => '📅',
		'color' => '#16a34a',
		'bg'    => '#f0fdf4',
		'title' => __( 'Content Calendar', 'super-fast-blog-ai' ),
		'desc'  => __( 'AI-generated topic plan for upcoming posts.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-repurpose',
		'query' => '',
		'icon'  => '♻️',
		'color' => '#f59e0b',
		'bg'    => '#fff7ed',
		'title' => __( 'Repurpose Content', 'super-fast-blog-ai' ),
		'desc'  => __( 'Turn posts into tweets, LinkedIn, email & more.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-internal-links',
		'query' => '',
		'icon'  => '🔗',
		'color' => '#0ea5e9',
		'bg'    => '#f0f9ff',
		'title' => __( 'Internal Links', 'super-fast-blog-ai' ),
		'desc'  => __( 'Smart linking suggestions from your keyword index.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-performance',
		'query' => '',
		'icon'  => '📈',
		'color' => '#10b981',
		'bg'    => '#f0fdf4',
		'title' => __( 'Performance', 'super-fast-blog-ai' ),
		'desc'  => __( 'Track Search Console impressions & clicks.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-cost-tracker',
		'query' => '',
		'icon'  => '💰',
		'color' => '#9333ea',
		'bg'    => '#faf5ff',
		'title' => __( 'Cost Tracker', 'super-fast-blog-ai' ),
		'desc'  => __( 'Monitor AI spend across all providers.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-model-routing',
		'query' => '',
		'icon'  => '🔀',
		'color' => '#f97316',
		'bg'    => '#fff7ed',
		'title' => __( 'Model Routing', 'super-fast-blog-ai' ),
		'desc'  => __( 'Route content types to the best model.', 'super-fast-blog-ai' ),
	],
	[
		'slug'  => 'sfba-settings',
		'query' => '',
		'icon'  => '⚙️',
		'color' => '#6b7280',
		'bg'    => '#f9fafb',
		'title' => __( 'Settings', 'super-fast-blog-ai' ),
		'desc'  => __( 'API keys, providers, and plugin options.', 'super-fast-blog-ai' ),
	],
];

$stats = [
	[
		'label'    => __( 'Total Generations', 'super-fast-blog-ai' ),
		'value'    => number_format( (int) ( $summary['total_generations'] ?? 0 ) ),
		'sub'      => __( 'AI-powered articles created', 'super-fast-blog-ai' ),
		'icon'     => '🤖',
		'accent'   => '#2563eb',
		'icon_bg'  => '#dbeafe',
		'val_color'=> '#1e40af',
	],
	[
		'label'    => __( 'Cost This Month', 'super-fast-blog-ai' ),
		'value'    => '$' . number_format( (float) ( $summary['this_month_cost'] ?? 0 ), 4 ),
		'sub'      => __( 'Current month API spend', 'super-fast-blog-ai' ),
		'icon'     => '💵',
		'accent'   => '#16a34a',
		'icon_bg'  => '#dcfce7',
		'val_color'=> '#15803d',
	],
	[
		'label'    => __( 'Providers Connected', 'super-fast-blog-ai' ),
		'value'    => $connected . '<span style="font-size:15px;font-weight:500;color:#a5b4fc;">&thinsp;/&thinsp;7</span>',
		'sub'      => __( 'AI providers active', 'super-fast-blog-ai' ),
		'icon'     => '🔌',
		'accent'   => '#7c3aed',
		'icon_bg'  => '#ede9fe',
		'val_color'=> '#6d28d9',
	],
	[
		'label'    => __( 'Brand Voice', 'super-fast-blog-ai' ),
		'value'    => $voice_status ? '✓ ' . __( 'Active', 'super-fast-blog-ai' ) : __( 'Not Set', 'super-fast-blog-ai' ),
		'sub'      => $voice_status ? __( 'Applied to every generation', 'super-fast-blog-ai' ) : __( 'Configure brand voice', 'super-fast-blog-ai' ),
		'icon'     => '🎨',
		'accent'   => $voice_status ? '#0891b2' : '#d97706',
		'icon_bg'  => $voice_status ? '#cffafe' : '#fef3c7',
		'val_color'=> $voice_status ? '#0e7490' : '#b45309',
	],
];
?>
<div class="wrap sfba-dash">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Super Fast Blog AI', 'super-fast-blog-ai' ); ?></h1>
				<p class="sfba-dash-hero-sub"><?php esc_html_e( 'AI-powered content generation for WordPress', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>
		<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
	</div>

	<?php if ( $connected === 0 ) : ?>
	<div class="sfba-info-box" style="margin-bottom:20px;">
		<h4><?php esc_html_e( 'No providers connected', 'super-fast-blog-ai' ); ?></h4>
		<p><?php esc_html_e( 'Add at least one API key in Settings to start generating content.', 'super-fast-blog-ai' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings' ) ); ?>"
		   class="sfba-btn sfba-btn-primary sfba-btn-sm" style="margin-top:8px;">
			⚙️ <?php esc_html_e( 'Go to Settings', 'super-fast-blog-ai' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- ── Stats Grid ───────────────────────────────────────────── -->
	<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px;">
		<?php foreach ( $stats as $st ) : ?>
		<div style="position:relative;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 18px 16px;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden;">

			<!-- Accent bar -->
			<div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?php echo esc_attr( $st['accent'] ); ?>;border-radius:12px 12px 0 0;"></div>

			<!-- Top row: icon tile + label -->
			<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
				<div style="width:36px;height:36px;flex-shrink:0;background:<?php echo esc_attr( $st['icon_bg'] ); ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;">
					<?php echo $st['icon']; ?>
				</div>
				<span style="font-size:11.5px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;line-height:1.2;">
					<?php echo esc_html( $st['label'] ); ?>
				</span>
			</div>

			<!-- Metric value -->
			<div style="font-size:26px;font-weight:800;color:<?php echo esc_attr( $st['val_color'] ); ?>;letter-spacing:-.5px;line-height:1;">
				<?php echo $st['value']; ?>
			</div>

		</div>
		<?php endforeach; ?>
	</div>

	<!-- ── Features Grid ────────────────────────────────────────── -->
	<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9ca3af;margin:0 0 12px;">
		<?php esc_html_e( 'Features', 'super-fast-blog-ai' ); ?>
	</p>
	<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
		<?php foreach ( $modules as $m ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $m['slug'] . $m['query'] ) ); ?>"
		   style="display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;text-decoration:none;color:inherit;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:box-shadow .15s,border-color .15s,transform .12s;"
		   onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.09)';this.style.borderColor='<?php echo esc_attr( $m['color'] ); ?>';this.style.transform='translateY(-2px)';"
		   onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,.04)';this.style.borderColor='#e5e7eb';this.style.transform='translateY(0)';">
			<!-- Icon tile -->
			<div style="width:42px;height:42px;flex-shrink:0;border-radius:11px;background:<?php echo esc_attr( $m['bg'] ); ?>;display:flex;align-items:center;justify-content:center;font-size:20px;">
				<?php echo $m['icon']; ?>
			</div>
			<!-- Text -->
			<div style="flex:1;min-width:0;">
				<strong style="display:block;font-size:13px;font-weight:700;color:#111827;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
					<?php echo esc_html( $m['title'] ); ?>
				</strong>
				<span style="font-size:11.5px;color:#6b7280;line-height:1.4;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
					<?php echo esc_html( $m['desc'] ); ?>
				</span>
			</div>
			<!-- Arrow -->
			<svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;color:#d1d5db;">
				<path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</a>
		<?php endforeach; ?>
	</div>

</div>
