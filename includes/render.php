<?php
/**
 * HTML-skabeloner, manifest, søgeindeks og service worker.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL til en fil i pluginets assets-mappe (med version, så cache brydes ved opdatering).
 *
 * @param string $file Filnavn.
 * @return string
 */
function ibos_guides_asset( $file ) {
	return plugins_url( 'assets/' . $file, IBOS_GUIDES_FILE ) . '?ver=' . rawurlencode( IBOS_GUIDES_VERSION );
}

/**
 * Renderet indhold af en side (blokke → HTML).
 *
 * @param WP_Post $post Side.
 * @return string
 */
function ibos_guides_content( $post, $use_cache = true ) {
	static $cache = array();
	$key          = $post->ID . ':' . get_current_user_id();
	if ( $use_cache && isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$prev            = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	add_filter( 'render_block', 'ibos_guides_drop_empty_block', 10, 2 ); // Ubrugte felter fra skabelonen.
	add_filter( 'render_block', 'ibos_guides_prepare_video', 11, 2 );
	$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	remove_filter( 'render_block', 'ibos_guides_drop_empty_block', 10 );
	remove_filter( 'render_block', 'ibos_guides_prepare_video', 11 );
	// Overskrift 1 hører kun til sidens titel.
	$html            = preg_replace( '#<(/?)h1(\s|>)#i', '<$1h2$2', $html );
	$GLOBALS['post'] = $prev; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $prev ) {
		setup_postdata( $prev );
	}
	if ( $use_cache ) {
		$cache[ $key ] = $html;
	}
	return $html;
}

/**
 * Menutitel (kortere titel kan sættes i meta _ibg_menu_title).
 *
 * @param WP_Post $p Side.
 * @return string
 */
function ibos_guides_menu_title( $p ) {
	$m = get_post_meta( $p->ID, '_ibg_menu_title', true );
	return $m ? $m : get_the_title( $p );
}

/**
 * Fælles sideramme.
 *
 * @param WP_Post $guide Rod-side.
 * @param array   $a     title, description, breadcrumbs, body, pager, is_home, body_class.
 * @return string
 */
function ibos_guides_layout( $guide, $a ) {
	$s     = ibos_guides_settings( $guide );
	$base  = ibos_guides_base_url( $guide );
	$name  = get_the_title( $guide );
	$logo  = $s['logo_id'] ? wp_get_attachment_image( $s['logo_id'], 'medium', false, array( 'class' => 'brand-logo', 'alt' => '' ) ) : '';
	$dev   = defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['ibg-nosw'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	ob_start();
	?>
<!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $a['title'] ); ?></title>
<?php if ( ! empty( $a['description'] ) ) : ?>
<meta name="description" content="<?php echo esc_attr( $a['description'] ); ?>">
<?php endif; ?>
<meta name="theme-color" content="<?php echo esc_attr( $s['color'] ); ?>">
<meta name="color-scheme" content="light dark">
<link rel="manifest" href="<?php echo esc_url( $base . 'manifest.webmanifest' ); ?>">
<link rel="icon" href="<?php echo esc_url( $base . 'icons/favicon-32.png' ); ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?php echo esc_url( $base . 'icons/apple-touch-icon.png' ); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $s['short_name'] ); ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<link rel="stylesheet" href="<?php echo esc_url( ibos_guides_asset( 'app.css' ) ); ?>">
<style>:root{--theme:<?php echo esc_html( $s['color'] ); ?>;}</style>
<script src="<?php echo esc_url( ibos_guides_asset( 'qrcode.js' ) ); ?>" defer></script>
<script src="<?php echo esc_url( ibos_guides_asset( 'app.js' ) ); ?>" defer></script>
</head>
<body data-base="<?php echo esc_url( $base ); ?>"<?php echo $dev ? ' data-nosw="1"' : ''; ?> class="<?php echo esc_attr( isset( $a['body_class'] ) ? $a['body_class'] : '' ); ?>">
<a class="skip-link" href="#indhold"><?php esc_html_e( 'Spring til indhold', 'ibos-guides' ); ?></a>
<header class="site-header">
	<div class="wrap header-inner">
		<p class="brand">
		<?php if ( ! empty( $a['is_home'] ) ) : ?>
			<a href="<?php echo esc_url( $base ); ?>" aria-current="page"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $name ); ?></span></a>
		<?php else : ?>
			<a href="<?php echo esc_url( $base ); ?>"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $name ); ?></span><span class="visually-hidden">, <?php esc_html_e( 'forside', 'ibos-guides' ); ?></span></a>
		<?php endif; ?>
		</p>
		<nav class="shortcuts" aria-label="<?php esc_attr_e( 'Genveje', 'ibos-guides' ); ?>">
			<ul>
				<li><a href="<?php echo esc_url( $base . 'soeg/' ); ?>"><?php esc_html_e( 'Søg', 'ibos-guides' ); ?></a></li>
				<li><a href="<?php echo esc_url( $base . 'installer/' ); ?>"><?php esc_html_e( 'Installér appen', 'ibos-guides' ); ?></a></li>
			</ul>
		</nav>
	</div>
</header>
<?php echo isset( $a['breadcrumbs'] ) ? $a['breadcrumbs'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet ved opbygning. ?>
<main id="indhold" tabindex="-1">
	<div class="wrap">
<?php echo $a['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet ved opbygning. ?>
	</div>
</main>
<?php echo isset( $a['pager'] ) ? $a['pager'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet ved opbygning. ?>
<footer class="site-footer">
	<div class="wrap">
		<?php if ( $s['colophon'] ) : ?><p><?php echo esc_html( $s['colophon'] ); ?></p><?php endif; ?>
		<?php if ( $s['publisher'] ) : ?><p><?php echo esc_html( $s['publisher'] ); ?></p><?php endif; ?>
		<?php if ( $s['a11y_url'] ) : ?><p><a href="<?php echo esc_url( $s['a11y_url'] ); ?>"><?php esc_html_e( 'Tilgængelighedserklæring', 'ibos-guides' ); ?></a></p><?php endif; ?>
	</div>
</footer>
<div class="toast" id="app-status" role="status" aria-live="polite"></div>
</body>
</html>
	<?php
	return ob_get_clean();
}

/**
 * Brødkrummer med "Tilbage"-link.
 *
 * @param array $trail [ [title, url], … ] fra forside til aktuel side.
 * @return string
 */
function ibos_guides_breadcrumbs( $trail ) {
	if ( count( $trail ) < 2 ) {
		return '';
	}
	$parent = $trail[ count( $trail ) - 2 ];
	$items  = '';
	foreach ( $trail as $i => $t ) {
		$items .= ( count( $trail ) - 1 === $i )
			? '<li><a href="' . esc_url( $t[1] ) . '" aria-current="page">' . esc_html( $t[0] ) . '</a></li>'
			: '<li><a href="' . esc_url( $t[1] ) . '">' . esc_html( $t[0] ) . '</a></li>';
	}
	return '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Du er her', 'ibos-guides' ) . '"><div class="wrap">'
		. '<a class="back-link" href="' . esc_url( $parent[1] ) . '"><span aria-hidden="true">‹ </span>' . esc_html__( 'Tilbage', 'ibos-guides' ) . '<span class="visually-hidden"> ' . esc_html__( 'til', 'ibos-guides' ) . ' ' . esc_html( $parent[0] ) . '</span></a>'
		. '<ol>' . $items . '</ol></div></nav>';
}

/**
 * Renderer en indholdsside, et kapitel eller forsiden.
 *
 * @param WP_Post $guide Rod-side.
 * @param int     $id    Side-id.
 * @return string
 */
function ibos_guides_render_page( $guide, $id ) {
	$s       = ibos_guides_settings( $guide );
	$base    = ibos_guides_base_url( $guide );
	$tree    = ibos_guides_tree( $guide->ID );
	$post    = $tree['posts'][ $id ];
	$is_home = (int) $id === (int) $guide->ID;
	$url     = function ( $pid ) use ( $base, $guide ) {
		return $base . ibos_guides_path( $pid, $guide->ID );
	};

	// Titel: "Side – Kapitel – App".
	$parts = array();
	if ( ! $is_home ) {
		$parts[] = get_the_title( $post );
		if ( (int) $post->post_parent !== (int) $guide->ID ) {
			$parts[] = get_the_title( $post->post_parent );
		}
	}
	$parts[] = get_the_title( $guide );

	// Brødkrummer.
	$trail = array();
	for ( $p = $post; $p; $p = $p->post_parent ? get_post( $p->post_parent ) : null ) {
		array_unshift( $trail, array( (int) $p->ID === (int) $guide->ID ? __( 'Forside', 'ibos-guides' ) : ibos_guides_menu_title( $p ), $url( $p->ID ) ) );
		if ( (int) $p->ID === (int) $guide->ID ) {
			break;
		}
	}

	// Undermenu.
	$children = array();
	foreach ( isset( $tree['children'][ $id ] ) ? $tree['children'][ $id ] : array() as $c ) {
		if ( ! ibos_guides_is_hidden( $c ) ) {
			$children[] = $tree['posts'][ $c ];
		}
	}
	$menu = '';
	if ( $children ) {
		$style = get_post_meta( $id, '_ibg_child_menu', true );
		$style = $style ? $style : $s['menu'];
		$label = $is_home ? __( 'Indhold', 'ibos-guides' ) : sprintf( /* translators: %s: kapitel */ __( 'Sider i %s', 'ibos-guides' ), get_the_title( $post ) );
		$menu .= '<section class="child-menu" aria-labelledby="menu-overskrift"><h2 id="menu-overskrift">' . esc_html( $label ) . '</h2>';
		$menu .= '<nav aria-labelledby="menu-overskrift"><ul class="menu menu--' . ( 'list' === $style ? 'list' : 'tiles' ) . '">';
		foreach ( $children as $c ) {
			$menu .= '<li><a href="' . esc_url( $url( $c->ID ) ) . '">' . esc_html( ibos_guides_menu_title( $c ) ) . '</a>';
			if ( has_excerpt( $c ) ) {
				$menu .= '<p>' . esc_html( get_the_excerpt( $c ) ) . '</p>';
			}
			$menu .= '</li>';
		}
		$menu .= '</ul></nav></section>';
	}

	// Forrige/næste.
	$order = ibos_guides_order( $guide->ID );
	$i     = array_search( (int) $id, $order, true );
	$pager = '';
	if ( false !== $i ) {
		$prev  = $i > 0 ? $order[ $i - 1 ] : 0;
		$next  = isset( $order[ $i + 1 ] ) ? $order[ $i + 1 ] : 0;
		$pager = '<nav class="pager" aria-label="' . esc_attr__( 'Forrige og næste side', 'ibos-guides' ) . '"><div class="wrap"><ul>';
		if ( $prev ) {
			$pager .= '<li class="pager-prev"><a href="' . esc_url( $url( $prev ) ) . '" rel="prev"><span class="pager-label">' . esc_html__( 'Forrige', 'ibos-guides' ) . '<span class="visually-hidden"> ' . esc_html__( 'side', 'ibos-guides' ) . '</span>:</span> <span class="pager-title">' . esc_html( $prev === (int) $guide->ID ? __( 'Forside', 'ibos-guides' ) : ibos_guides_menu_title( $tree['posts'][ $prev ] ) ) . '</span></a></li>';
		}
		if ( $next ) {
			$pager .= '<li class="pager-next"><a href="' . esc_url( $url( $next ) ) . '" rel="next"><span class="pager-label">' . esc_html__( 'Næste', 'ibos-guides' ) . '<span class="visually-hidden"> ' . esc_html__( 'side', 'ibos-guides' ) . '</span>:</span> <span class="pager-title">' . esc_html( ibos_guides_menu_title( $tree['posts'][ $next ] ) ) . '</span></a></li>';
		}
		$pager .= '</ul></div></nav>';
	}

	$body = '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>' . "\n" . ibos_guides_content( $post ) . "\n" . $menu;
	return ibos_guides_layout(
		$guide,
		array(
			'title'       => implode( ' – ', $parts ),
			'description' => has_excerpt( $post ) ? get_the_excerpt( $post ) : '',
			'breadcrumbs' => ibos_guides_breadcrumbs( $trail ),
			'body'        => $body,
			'pager'       => $pager,
			'is_home'     => $is_home,
		)
	);
}

/**
 * Renderer søgesiden eller installationssiden.
 *
 * @param WP_Post $guide Rod-side.
 * @param string  $which search|install.
 * @return string
 */
function ibos_guides_render_special( $guide, $which ) {
	$base  = ibos_guides_base_url( $guide );
	$s     = ibos_guides_settings( $guide );
	$name  = get_the_title( $guide );
	$title = 'search' === $which ? __( 'Søg', 'ibos-guides' ) : __( 'Installér appen', 'ibos-guides' );
	$url   = $base . ( 'search' === $which ? 'soeg/' : 'installer/' );

	if ( 'search' === $which ) {
		$body = '<h1>' . esc_html( $title ) . '</h1>
<form class="search-form" role="search" action="' . esc_url( $url ) . '" method="get" id="search-form" hidden>
  <label for="q">' . esc_html__( 'Søg i titler og tekst', 'ibos-guides' ) . '</label>
  <div class="search-row">
    <input id="q" name="q" type="search" autocomplete="off" autocapitalize="off" enterkeyhint="search" aria-describedby="search-hint">
    <button type="submit">' . esc_html__( 'Søg', 'ibos-guides' ) . '</button>
  </div>
  <p id="search-hint" class="hint">' . esc_html__( 'Resultaterne vises, mens du skriver. Søgningen virker også uden internet.', 'ibos-guides' ) . '</p>
</form>
<p class="search-status" id="search-status" role="status" aria-live="polite" aria-atomic="true"></p>
<div id="search-results-wrap" hidden>
  <h2 id="search-results-heading">' . esc_html__( 'Resultater', 'ibos-guides' ) . '</h2>
  <ol class="search-results" id="search-results" aria-labelledby="search-results-heading"></ol>
</div>
<noscript><p>' . esc_html__( 'Søgningen kræver JavaScript. Du kan finde alle sider via menuen på', 'ibos-guides' ) . ' <a href="' . esc_url( $base ) . '">' . esc_html__( 'forsiden', 'ibos-guides' ) . '</a>.</p></noscript>';
	} else {
		$n           = esc_html( $name );
		$collected   = ibos_guides_collect( $guide );
		$videos      = isset( $collected['videos'] ) ? $collected['videos'] : array();
		$online_only = ! empty( $collected['online_video'] );
		$body = '<h1>' . esc_html( $title ) . '</h1>
<p>' . sprintf( /* translators: %s: appens navn */ esc_html__( 'Læg %s på hjemmeskærmen, så den åbner som en app. Når appen har været åbnet én gang med internet, virker alt indhold også uden net.', 'ibos-guides' ), $n ) . ( $online_only ? ' ' . esc_html__( 'Undtagelsen er videoer: de kræver internet.', 'ibos-guides' ) : '' ) . ( $videos ? ' ' . esc_html__( 'Videoer hentes for sig – se "Videoer uden internet" herunder.', 'ibos-guides' ) : '' ) . '</p>
<div class="install-box" id="install-box">
  <p id="install-status" role="status" aria-live="polite"></p>
  <button type="button" class="button" id="install-button" hidden>' . sprintf( /* translators: %s: appens navn */ esc_html__( 'Installér %s', 'ibos-guides' ), $n ) . '</button>
</div>' . ibos_guides_videos_section( $videos ) . '

<h2 id="iphone">' . esc_html__( 'iPhone og iPad', 'ibos-guides' ) . '</h2>
<ol class="steps">
  <li>' . wp_kses( __( 'Tryk på <strong>Del</strong> (en firkant med en pil, der peger op). Kan du ikke se den, så tryk først på <strong>Flere</strong> (tre prikker).', 'ibos-guides' ), array( 'strong' => array() ) ) . '</li>
  <li>' . wp_kses( __( 'Vælg <strong>Føj til hjemmeskærm</strong>.', 'ibos-guides' ), array( 'strong' => array() ) ) . '</li>
  <li>' . wp_kses( __( 'Tryk på <strong>Tilføj</strong>.', 'ibos-guides' ), array( 'strong' => array() ) ) . '</li>
</ol>
<p class="hint">' . esc_html__( 'I Safari sidder Del nederst på iPhone og øverst på iPad. I andre browsere, fx Chrome, findes Del i menuen. Ligger Føj til hjemmeskærm ikke i listen, så rul ned, eller vælg Vis flere.', 'ibos-guides' ) . '</p>
<h2 id="android">' . esc_html__( 'Android', 'ibos-guides' ) . '</h2>
<ol class="steps">
  <li>' . sprintf( /* translators: %s: appens navn */ wp_kses( __( 'Tryk på knappen <strong>Installér %s</strong> ovenfor, hvis den vises.', 'ibos-guides' ), array( 'strong' => array() ) ), $n ) . '</li>
  <li>' . wp_kses( __( 'Ellers: åbn Chromes menu (tre prikker), og vælg <strong>Installér app</strong> eller <strong>Føj til startskærm</strong>.', 'ibos-guides' ), array( 'strong' => array() ) ) . '</li>
</ol>
<h2 id="computer">' . esc_html__( 'Computer', 'ibos-guides' ) . '</h2>
<ol class="steps">
  <li>' . wp_kses( __( 'I Chrome eller Edge: brug knappen ovenfor eller installationsikonet i adresselinjen (en skærm med en pil).', 'ibos-guides' ), array() ) . '</li>
  <li>' . wp_kses( __( 'På Mac med Safari: vælg <strong>Arkiv → Føj til Dock</strong>.', 'ibos-guides' ), array( 'strong' => array() ) ) . '</li>
</ol>
<section id="share" class="share" aria-labelledby="share-heading" hidden>
  <h2 id="share-heading">' . esc_html__( 'Vis appen til en anden', 'ibos-guides' ) . '</h2>
  <p>' . esc_html__( 'Vil du vise appen til en anden, fx en borger du hjælper? Den anden scanner QR-koden med kameraet på sin telefon. Så åbnes appen, og vejledningen ovenfor kan følges. Du kan også sende adressen.', 'ibos-guides' ) . '</p>
  <div class="qr" id="qr"></div>
  <p>' . esc_html__( 'Adresse:', 'ibos-guides' ) . ' <span class="app-url" id="app-url"></span></p>
  <button type="button" class="button button--secondary" id="copy-url">' . esc_html__( 'Kopiér adressen', 'ibos-guides' ) . '</button>
</section>';
	}

	return ibos_guides_layout(
		$guide,
		array(
			'title'       => $title . ' – ' . $name,
			'breadcrumbs' => ibos_guides_breadcrumbs( array( array( __( 'Forside', 'ibos-guides' ), $base ), array( $title, $url ) ) ),
			'body'        => $body,
			'body_class'  => 'page-' . $which,
		)
	);
}

/**
 * Afsnittet "Videoer uden internet" på Installér-siden (vises kun med JavaScript og Cache API).
 *
 * @param array[] $videos Fra ibos_guides_collect().
 * @return string
 */
function ibos_guides_videos_section( $videos ) {
	if ( ! $videos ) {
		return '';
	}
	$total = array_sum( wp_list_pluck( $videos, 's' ) );
	$count = count( $videos );
	$size  = $total > 0 ? sprintf( /* translators: %s: megabyte */ __( 'ca. %s MB', 'ibos-guides' ), ibos_guides_mb( $total ) ) : '';
	$intro = 1 === $count
		? __( 'Appens video kræver internet, indtil du henter den.', 'ibos-guides' )
		/* translators: %d: antal videoer */
		: sprintf( __( 'Appens %d videoer kræver internet, indtil du henter dem.', 'ibos-guides' ), $count );
	if ( $size ) {
		$intro .= ' ' . ( 1 === $count
			/* translators: %s: størrelse, fx "ca. 12 MB" */
			? sprintf( __( 'Den fylder %s på telefonen – hent den gerne på wifi.', 'ibos-guides' ), $size )
			/* translators: %s: størrelse, fx "ca. 12 MB" */
			: sprintf( __( 'De fylder %s på telefonen – hent dem gerne på wifi.', 'ibos-guides' ), $size ) );
	}
	$button = ( 1 === $count ? __( 'Hent videoen', 'ibos-guides' ) : __( 'Hent videoer', 'ibos-guides' ) ) . ( $size ? ' (' . $size . ')' : '' );
	return '
<section id="videos" class="videos-offline" aria-labelledby="videos-heading" data-videos="' . esc_attr( wp_json_encode( $videos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '" hidden>
  <h2 id="videos-heading">' . esc_html__( 'Videoer uden internet', 'ibos-guides' ) . '</h2>
  <p>' . esc_html( $intro ) . '</p>
  <p id="videos-status" class="videos-status" role="status" aria-live="polite"></p>
  <progress id="videos-progress" max="100" value="0" aria-label="' . esc_attr__( 'Hentning af videoer', 'ibos-guides' ) . '" hidden></progress>
  <p class="videos-actions">
    <button type="button" class="button" id="videos-get">' . esc_html( $button ) . '</button>
    <button type="button" class="button button--secondary" id="videos-remove" hidden>' . esc_html__( 'Fjern hentede videoer', 'ibos-guides' ) . '</button>
  </p>
</section>';
}

/**
 * Web app-manifest.
 *
 * @param WP_Post $guide Rod-side.
 * @return array
 */
function ibos_guides_manifest( $guide ) {
	$s    = ibos_guides_settings( $guide );
	$base = ibos_guides_base_url( $guide );
	return array(
		'name'             => get_the_title( $guide ),
		'short_name'       => $s['short_name'],
		'description'      => has_excerpt( $guide ) ? get_the_excerpt( $guide ) : '',
		'lang'             => 'da',
		'dir'              => 'ltr',
		'id'               => $base,
		'start_url'        => $base,
		'scope'            => $base,
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => $s['color'],
		'icons'            => array(
			array( 'src' => $base . 'icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => $base . 'icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => $base . 'icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
		),
	);
}

/**
 * Søgeindeks og liste over billeder – caches pr. version.
 *
 * @param WP_Post $guide Rod-side.
 * @return array{v: string, index: array, images: string[], videos: array[], extras: string[], online_video: bool, pages: string[], hash: string}
 */
function ibos_guides_collect( $guide ) {
	$key     = 'ibg_collect_' . $guide->ID;
	$data    = get_transient( $key );
	$current = ibos_guides_version( $guide );
	if ( is_array( $data ) && isset( $data['v'] ) && $current === $data['v'] ) {
		return $data;
	}
	// Lås: kun én genopbygning ad gangen pr. guide (ellers kan mange samtidige anonyme
	// forespørgsler efter en redigering starte hver sin tunge genopbygning). Mens låsen holdes,
	// serveres den forrige version, hvis der er en.
	$lock = 'ibg_collect_lock_' . $guide->ID;
	$own  = add_option( $lock, time(), '', false );
	if ( ! $own ) {
		if ( time() - (int) get_option( $lock ) < 2 * MINUTE_IN_SECONDS && is_array( $data ) && isset( $data['index'], $data['pages'] ) ) {
			return $data;
		}
		update_option( $lock, time(), false );
		$own = true;
	}
	try {
		$data = ibos_guides_build_collect( $guide, $current );
		set_transient( $key, $data, DAY_IN_SECONDS );
	} finally {
		if ( $own ) {
			delete_option( $lock );
		}
	}
	return $data;
}

/**
 * Bygger søgeindeks, sideliste og billedliste for en guide.
 *
 * Bygges altid som en anonym besøgende – ellers kan indhold, som kun vises for indloggede
 * (fx via shortcodes eller blokke med synlighedsregler), havne i det offentlige indeks.
 * Sidernes HTML holdes ikke i hukommelsen, og søgeteksten pr. side er begrænset.
 *
 * @param WP_Post $guide   Rod-side.
 * @param string  $version Version.
 * @return array
 */
function ibos_guides_build_collect( $guide, $version ) {
	$prev_user = get_current_user_id();
	wp_set_current_user( 0 );
	// Ingen udgående HTTP-kald (oEmbed/auto-embed) mens den fælles cache bygges – opbygningen
	// kan udløses af en anonym besøgende, og serveren skal ikke kunne bringes til at kalde
	// vilkårlige adresser på den måde. Allerede cachede embeds bruges stadig.
	$no_fetch = function () {
		return '';
	};
	add_filter( 'pre_oembed_result', $no_fetch, 1 );
	add_filter( 'embed_oembed_discover', '__return_false', 1 );
	$tree   = ibos_guides_tree( $guide->ID );
	$order  = ibos_guides_order( $guide->ID );
	$hash   = hash_init( 'md5' );
	hash_update( $hash, wp_json_encode( ibos_guides_settings( $guide ) ) . get_the_title( $guide ) );
	$index  = array();
	$images = array();
	$videos = array();
	$extras = array();
	$pages  = array();
	$has_online_video = false;
	try {
		foreach ( $order as $id ) {
			$p       = $tree['posts'][ $id ];
			$pages[] = ibos_guides_path( $id, $guide->ID );
			$html    = ibos_guides_content( $p, false );
			hash_update( $hash, $id . '|' . get_the_title( $p ) . '|' . ibos_guides_menu_title( $p ) . '|' . $p->post_excerpt . '|' . $html );
			if ( preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"/i', $html, $m ) ) {
				foreach ( $m[1] as $src ) {
					$src = ibos_guides_local_url( $src );
					if ( '' !== $src ) {
						$images[ $src ] = true;
					}
				}
			}
			$vids = ibos_guides_html_videos( $html );
			$has_online_video = $has_online_video || $vids['online'];
			foreach ( $vids['offline'] as $src => $v ) {
				if ( ! isset( $videos[ $src ] ) ) {
					$videos[ $src ] = array( 'u' => $v['u'], 's' => $v['s'] );
					foreach ( $v['x'] as $x ) {
						$extras[ $x ] = true;
					}
				}
			}
			$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
			unset( $html );
			$index[] = array(
				't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				's' => ( (int) $p->post_parent && (int) $p->post_parent !== (int) $guide->ID ) ? html_entity_decode( get_the_title( $p->post_parent ), ENT_QUOTES, 'UTF-8' ) : '',
				'u' => ibos_guides_path( $id, $guide->ID ),
				'x' => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 6000 ) : substr( $text, 0, 6000 ),
			);
		}
	} finally {
		remove_filter( 'pre_oembed_result', $no_fetch, 1 );
		remove_filter( 'embed_oembed_discover', '__return_false', 1 );
		wp_set_current_user( $prev_user );
	}
	$images = array_keys( $images );
	$videos = array_values( $videos );
	$extras = array_keys( $extras );
	hash_update( $hash, wp_json_encode( array( $guide->ID, $images, $pages, $videos, $extras, $has_online_video ) ) );
	return array(
		'v'      => $version,
		'index'  => $index,
		'images' => $images,
		// Videoer, borgeren kan hente til brug uden internet (+ undertekster og plakatbilleder).
		'videos' => $videos,
		'extras' => $extras,
		// Er der videoer, der kun kan ses med internet? (Til teksten på Installér-siden.)
		'online_video' => $has_online_video,
		'pages'  => $pages,
		// Hash af alt, der vises: bestemmer service workerens version (ny version kun ved reel ændring).
		'hash'   => hash_final( $hash ),
	);
}

// Genopbyg i baggrunden kort efter en redigering, så den første besøgende ikke skal vente.
add_action( 'ibos_guides_rebuild', 'ibos_guides_rebuild_cron' );
function ibos_guides_rebuild_cron( $guide_id ) {
	$guide = get_post( (int) $guide_id );
	if ( ibos_guides_is_guide( $guide ) ) {
		ibos_guides_collect( $guide );
	}
}

function ibos_guides_search_index( $guide ) {
	return ibos_guides_collect( $guide )['index'];
}

/**
 * Service worker med versioneret precache.
 *
 * @param WP_Post $guide Rod-side.
 * @return string
 */
function ibos_guides_service_worker( $guide ) {
	$base     = ibos_guides_base_url( $guide );
	$data     = ibos_guides_collect( $guide );
	$critical = array( $base, $base . 'soeg/', $base . 'installer/', $base . 'manifest.webmanifest', $base . 'search-index.json' );
	foreach ( $data['pages'] as $path ) {
		$critical[] = $base . $path;
	}
	foreach ( array( 'app.css', 'app.js', 'qrcode.js' ) as $f ) {
		$critical[] = ibos_guides_asset( $f );
	}
	$critical = array_values( array_unique( $critical ) );
	$optional = array();
	foreach ( array( 'icon-192', 'icon-512', 'icon-maskable-512', 'apple-touch-icon', 'favicon-32' ) as $i ) {
		$optional[] = $base . 'icons/' . $i . '.png';
	}
	$extras   = isset( $data['extras'] ) ? $data['extras'] : array();
	$videos   = isset( $data['videos'] ) ? wp_list_pluck( $data['videos'], 'u' ) : array();
	$optional = array_values( array_diff( array_unique( array_merge( $optional, $data['images'], $extras ) ), $critical ) );
	// Versionen afhænger af guide, indhold, farve og plugin – så en ny guide på en genbrugt
	// adresse eller ændret indhold altid giver en ny service worker.
	$version = IBOS_GUIDES_VERSION . '-' . substr( md5( $guide->ID . '|' . $data['hash'] . '|' . implode( '|', $critical ) . '|' . implode( '|', $optional ) . '|' . implode( '|', $videos ) ), 0, 12 );
	// Stier uden for appens adresse, som appen bruger (uploads og pluginets filer) – fra
	// WordPress selv, så det også virker i undermapper og med flyttet wp-content.
	$up     = wp_upload_dir( null, false );
	$shared = array_values(
		array_filter(
			array(
				trailingslashit( (string) wp_parse_url( $up['baseurl'], PHP_URL_PATH ) ),
				trailingslashit( (string) wp_parse_url( plugins_url( '/', IBOS_GUIDES_FILE ), PHP_URL_PATH ) ),
			)
		)
	);
	$tpl = file_get_contents( IBOS_GUIDES_DIR . '/assets/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	// strtr erstatter i én omgang, så en pladsholder inde i en indsat værdi ikke erstattes igen.
	return strtr(
		$tpl,
		array(
			'__VERSION__'  => esc_js( $version ),
			'__CRITICAL__' => wp_json_encode( $critical, JSON_UNESCAPED_SLASHES ),
			'__OPTIONAL__' => wp_json_encode( $optional, JSON_UNESCAPED_SLASHES ),
			'__SHARED__'   => wp_json_encode( $shared, JSON_UNESCAPED_SLASHES ),
			'__SCOPE__'    => esc_js( $base ),
			'__VIDEOS__'   => wp_json_encode( array_values( $videos ), JSON_UNESCAPED_SLASHES ),
		)
	);
}
