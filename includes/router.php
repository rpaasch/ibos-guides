<?php
/**
 * Rewrite-regler og servering af apps.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * Regex til en guides rewrite-regel.
 *
 * @param string $slug Adresse.
 * @return string
 */
function ibos_guides_rule_regex( $slug ) {
	return '^' . preg_quote( $slug, '#' ) . '(?:/(.*))?/?$';
}

add_action( 'init', 'ibos_guides_register_rewrites', 20 );
function ibos_guides_register_rewrites() {
	// Tidligere adresser først (ellers ville fx en aktiv guide "a" fange "a/b/sw.js"):
	// kun sw.js, som afregistrerer appen på enheder, der har den.
	foreach ( array_keys( ibos_guides_retired() ) as $slug ) {
		add_rewrite_rule( ibos_guides_retired_regex( $slug ), 'index.php?ibos_guide=retired', 'top' );
	}
	foreach ( ibos_guides_routes() as $slug => $id ) {
		add_rewrite_rule( ibos_guides_rule_regex( $slug ), 'index.php?ibos_guide=' . (int) $id . '&ibos_guide_path=$matches[1]', 'top' );
	}
}

/**
 * Tidligere guide-adresser (adresse => tidspunkt), uden udløbne (over 180 dage).
 *
 * @return array<string,int>
 */
function ibos_guides_retired() {
	$retired = (array) get_option( 'ibos_guides_retired', array() );
	$fresh   = array_filter(
		$retired,
		function ( $t ) {
			return (int) $t > time() - 180 * DAY_IN_SECONDS;
		}
	);
	if ( count( $fresh ) !== count( $retired ) ) {
		update_option( 'ibos_guides_retired', $fresh, true );
		update_option( 'ibos_guides_flush', 1 );
	}
	return $fresh;
}

/**
 * Regex for en tidligere guide-adresses sw.js.
 *
 * @param string $slug Adresse.
 * @return string
 */
function ibos_guides_retired_regex( $slug ) {
	return '^' . preg_quote( $slug, '#' ) . '/sw\.js$';
}

/**
 * Service worker, der sletter appens gemte indhold og afregistrerer sig selv.
 * Serveres på adresser, der ikke længere er en app (gjort privat, slettet eller flyttet).
 */
function ibos_guides_kill_sw() {
	ibos_guides_headers( 'text/javascript; charset=utf-8', 'no-cache, no-store, must-revalidate' );
	echo "/* IBOS Guides: appen findes ikke længere på denne adresse. */\n"
		. "self.addEventListener('install', () => self.skipWaiting());\n"
		. "self.addEventListener('activate', (event) => event.waitUntil((async () => {\n"
		. "  const prefix = 'ibos-guides:' + new URL(self.registration.scope).pathname + ':';\n"
		. "  for (const k of await caches.keys()) if (k.startsWith(prefix)) await caches.delete(k);\n"
		. "  await self.registration.unregister();\n"
		. "  for (const c of await self.clients.matchAll({ type: 'window' })) c.navigate(c.url);\n"
		. "})()));\n";
	exit;
}

add_action( 'init', 'ibos_guides_maybe_flush', 99 );
function ibos_guides_maybe_flush() {
	if ( get_option( 'ibos_guides_flush' ) ) {
		delete_option( 'ibos_guides_flush' );
		flush_rewrite_rules( false );
	}
}

add_filter( 'query_vars', 'ibos_guides_query_vars' );
function ibos_guides_query_vars( $vars ) {
	$vars[] = 'ibos_guide';
	$vars[] = 'ibos_guide_path';
	return $vars;
}

add_action( 'parse_request', 'ibos_guides_parse_request', 1 );
function ibos_guides_parse_request( $wp ) {
	// Stol ikke på query-variablerne: WordPress lader ?ibos_guide=…&ibos_guide_path=… i
	// URL'en overskrive rewrite-reglen. Guide og sti udledes derfor kun af den regel,
	// der faktisk matchede adressen, og af selve stien.
	if ( empty( $wp->matched_rule ) ) {
		if ( isset( $wp->query_vars['ibos_guide'] ) || isset( $wp->query_vars['ibos_guide_path'] ) ) {
			unset( $wp->query_vars['ibos_guide'], $wp->query_vars['ibos_guide_path'] );
			$wp->query_vars['error'] = '404';
		}
		return;
	}
	foreach ( array_keys( ibos_guides_retired() ) as $slug ) {
		if ( ibos_guides_retired_regex( $slug ) !== $wp->matched_rule ) {
			continue;
		}
		// Kun på præcis den tidligere adresse, og ikke hvis en side eller et indlæg nu bruger den
		// (så kan et andet system have sin egen sw.js der – og appens egen baggrundskontrol
		// fjerner den gamle app, når manifestet ikke længere findes).
		$req = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kun sammenlignet.
		$own = (string) wp_parse_url( home_url( '/' . $slug . '/sw.js' ), PHP_URL_PATH );
		if ( ! isset( ibos_guides_routes()[ $slug ] ) && $req === $own && ! get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) ) {
			ibos_guides_kill_sw();
		}
		unset( $wp->query_vars['ibos_guide'], $wp->query_vars['ibos_guide_path'] );
		$wp->query_vars['error'] = '404';
		return;
	}
	$guide_id = 0;
	foreach ( ibos_guides_routes() as $slug => $id ) {
		if ( ibos_guides_rule_regex( $slug ) === $wp->matched_rule ) {
			$guide_id = (int) $id;
			$path     = preg_match( '#' . $wp->matched_rule . '#', (string) $wp->request, $m ) && isset( $m[1] ) ? $m[1] : '';
			break;
		}
	}
	if ( ! $guide_id ) {
		return; // En anden regel matchede – ikke vores forespørgsel.
	}
	$guide = get_post( $guide_id );
	if ( ! ibos_guides_is_guide( $guide ) || ! ibos_guides_request_in_base( $guide ) || ! ibos_guides_dispatch( $guide, $path ) ) {
		unset( $wp->query_vars['ibos_guide'], $wp->query_vars['ibos_guide_path'] );
		$wp->query_vars['error'] = '404';
	}
}

/**
 * Kommer forespørgslen faktisk fra guidens egen adresse?
 *
 * Sammenligner den rå sti (ikke URL-afkodet), præcis som browseren ser den. Kodede
 * skråstreger og ".." afvises, så en service worker aldrig kan serveres fra en sti uden for guiden.
 *
 * @param WP_Post $guide Rod-side.
 * @return bool
 */
function ibos_guides_request_in_base( $guide ) {
	$base = (string) wp_parse_url( ibos_guides_base_url( $guide ), PHP_URL_PATH );
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kun sammenlignet.
	if ( '' === $base || preg_match( '#%2f|%5c|\\\\|(^|/)\.\.?(/|$)#i', $req ) ) {
		return false;
	}
	return 0 === strpos( $req, $base ) || rtrim( $base, '/' ) === $req;
}

/**
 * Sender standard-headers for app-svar.
 *
 * @param string $type  Content-Type.
 * @param string $cache Cache-Control.
 */
function ibos_guides_headers( $type, $cache = 'no-cache' ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // Cache-plugins må ikke gemme app-svar.
	}
	// Tøm output-buffere (fx fra cache-plugins) – men stop, hvis en buffer ikke kan fjernes.
	while ( ob_get_level() > 0 && @ob_end_clean() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		continue;
	}
	// HTML til en logget ind bruger må ikke gemmes i fælles caches (CDN/proxy).
	if ( 0 === strpos( $type, 'text/html' ) && is_user_logged_in() ) {
		$cache = 'private, ' . $cache;
	}
	status_header( 200 );
	header( 'Content-Type: ' . $type );
	header( 'Cache-Control: ' . $cache );
	header( 'X-Content-Type-Options: nosniff' );
	// Overskriv ikke strengere headers, som sitet eller et sikkerhedsplugin allerede har sat.
	$sent = strtolower( implode( "\n", headers_list() ) );
	if ( false === strpos( $sent, 'x-frame-options:' ) ) {
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
	header( "Content-Security-Policy: frame-ancestors 'self'", false );
	if ( false === strpos( $sent, 'referrer-policy:' ) ) {
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
}

/**
 * Behandler en forespørgsel under en guide. Returnerer false ved 404.
 *
 * @param WP_Post $guide Rod-side.
 * @param string  $path  Sti efter guidens adresse.
 * @return bool
 */
function ibos_guides_dispatch( $guide, $path ) {
	$path = ltrim( str_replace( '\\', '/', rawurldecode( $path ) ), '/' );
	if ( false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.#', $path ) ) {
		return false;
	}
	$s = ibos_guides_settings( $guide );

	switch ( $path ) {
		case 'sw.js':
			ibos_guides_headers( 'text/javascript; charset=utf-8', 'no-cache, no-store, must-revalidate' );
			header( 'Service-Worker-Allowed: ' . wp_parse_url( ibos_guides_base_url( $guide ), PHP_URL_PATH ) );
			echo ibos_guides_service_worker( $guide ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- genereret JavaScript.
			exit;
		case 'manifest.webmanifest':
			ibos_guides_headers( 'application/manifest+json; charset=utf-8' );
			echo wp_json_encode( ibos_guides_manifest( $guide ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			exit;
		case 'search-index.json':
			ibos_guides_headers( 'application/json; charset=utf-8' );
			echo wp_json_encode( ibos_guides_search_index( $guide ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
	}
	if ( preg_match( '#^icons/(icon-192|icon-512|icon-maskable-512|apple-touch-icon|favicon-32)\.png$#', $path, $m ) ) {
		$file = ibos_guides_icon_file( $guide, $m[1] );
		if ( ! $file ) {
			return false;
		}
		ibos_guides_headers( 'image/png', 'public, max-age=604800' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	// Sider skal have afsluttende skråstreg, så relative stier og service worker-cache passer.
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kun sammenlignet.
	$base         = ibos_guides_base_url( $guide );

	$special = array( 'soeg' => 'search', 'installer' => 'install' );
	$first   = trim( $path, '/' );
	if ( isset( $special[ $first ] ) && ! ibos_guides_resolve( $guide->ID, $first ) ) {
		if ( '/' !== substr( $request_path, -1 ) ) {
			wp_safe_redirect( $base . $first . '/', 301 );
			exit;
		}
		ibos_guides_headers( 'text/html; charset=utf-8' );
		echo ibos_guides_render_special( $guide, $special[ $first ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet i skabelonen.
		exit;
	}

	$id = ibos_guides_resolve( $guide->ID, $path );
	if ( ! $id ) {
		return false;
	}
	if ( '/' !== substr( $request_path, -1 ) ) {
		wp_safe_redirect( $base . ibos_guides_path( $id, $guide->ID ), 301 );
		exit;
	}
	ibos_guides_headers( 'text/html; charset=utf-8' );
	echo ibos_guides_render_page( $guide, $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet i skabelonen.
	exit;
}
