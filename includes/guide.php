<?php
/**
 * Guide-model: indstillinger, sidetræ, adresser og version.
 *
 * En guide er en side på øverste niveau (uden forælder) af post typen ibos_guide_page.
 * Dens undersider er kapitler, og deres undersider er indholdssider.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standardindstillinger for en guide.
 *
 * @return array
 */
function ibos_guides_defaults() {
	return array(
		'slug'       => '',
		'short_name' => '',
		'color'      => '#00656c',
		'icon_id'    => 0,
		'logo_id'    => 0,
		'publisher'  => 'Udgivet af IBOS – Instituttet for Blinde og Svagsynede',
		'colophon'   => '',
		'menu'       => 'tiles',
	);
}

/**
 * Indstillinger for en guide (rod-siden).
 *
 * @param int|WP_Post $guide Rod-side.
 * @return array
 */
function ibos_guides_settings( $guide ) {
	$guide = get_post( $guide );
	$s     = ibos_guides_defaults();
	if ( ! $guide ) {
		return $s;
	}
	foreach ( array_keys( $s ) as $key ) {
		$v = get_post_meta( $guide->ID, '_ibg_' . $key, true );
		if ( '' !== $v && null !== $v ) {
			$s[ $key ] = $v;
		}
	}
	$s['slug']       = ibos_guides_clean_slug( $s['slug'] ? $s['slug'] : $guide->post_name );
	$s['short_name'] = $s['short_name'] ? $s['short_name'] : $guide->post_title;
	$s['icon_id']    = (int) $s['icon_id'];
	$s['logo_id']    = (int) $s['logo_id'];
	if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $s['color'] ) ) {
		$s['color'] = '#00656c';
	}
	$s['menu'] = 'list' === $s['menu'] ? 'list' : 'tiles';
	return $s;
}

/**
 * Normaliserer en adresse til "segment" eller "segment/segment".
 *
 * @param string $value Rå værdi.
 * @return string
 */
function ibos_guides_clean_slug( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = strtr( $value, array( 'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa' ) );
	$value = preg_replace( '#[^a-z0-9/_-]#', '-', $value );
	$value = preg_replace( '#-+#', '-', $value );
	$value = preg_replace( '#/+#', '/', $value );
	return trim( $value, '/-' );
}

/**
 * Rod-siden (guiden) for en side.
 *
 * @param int|WP_Post $post Side.
 * @return WP_Post|null
 */
function ibos_guides_root( $post ) {
	$post = get_post( $post );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type ) {
		return null;
	}
	$anc = get_post_ancestors( $post );
	return $anc ? get_post( end( $anc ) ) : $post;
}

/**
 * Alle guides (rod-sider), der er udgivet.
 *
 * @return WP_Post[]
 */
function ibos_guides_all() {
	return get_posts(
		array(
			'post_type'      => IBOS_GUIDES_CPT,
			'post_parent'    => 0,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
}

/**
 * Gemte ruter: adresse => guide-id.
 *
 * @return array<string,int>
 */
function ibos_guides_routes() {
	$r = get_option( 'ibos_guides_routes', array() );
	return is_array( $r ) ? $r : array();
}

/**
 * Genberegner ruter. Returnerer true, hvis de har ændret sig.
 *
 * @return bool
 */
function ibos_guides_refresh_routes() {
	$routes = array();
	foreach ( ibos_guides_all() as $g ) {
		$slug = ibos_guides_settings( $g )['slug'];
		if ( '' !== $slug && ! isset( $routes[ $slug ] ) ) {
			$routes[ $slug ] = (int) $g->ID;
		}
	}
	ksort( $routes );
	if ( ibos_guides_routes() === $routes ) {
		return false;
	}
	update_option( 'ibos_guides_routes', $routes, true );
	return true;
}

/**
 * Fuld URL til en guide (med afsluttende skråstreg).
 *
 * @param int|WP_Post $guide Rod-side.
 * @return string
 */
function ibos_guides_base_url( $guide ) {
	return home_url( '/' . ibos_guides_settings( $guide )['slug'] . '/' );
}

/**
 * Sidetræ for en guide: alle udgivne efterkommere, sorteret.
 *
 * @param int $guide_id Rod-side.
 * @return array{children: array<int,int[]>, posts: array<int,WP_Post>}
 */
function ibos_guides_tree( $guide_id ) {
	static $cache = array();
	if ( isset( $cache[ $guide_id ] ) ) {
		return $cache[ $guide_id ];
	}
	$pages = get_pages(
		array(
			'post_type'   => IBOS_GUIDES_CPT,
			'child_of'    => $guide_id,
			'post_status' => 'publish',
			'sort_column' => 'menu_order,post_title',
		)
	);
	$posts    = array( $guide_id => get_post( $guide_id ) );
	$children = array();
	foreach ( $pages as $p ) {
		$posts[ $p->ID ]                    = $p;
		$children[ (int) $p->post_parent ][] = (int) $p->ID;
	}
	foreach ( $children as &$list ) {
		usort(
			$list,
			function ( $a, $b ) use ( $posts ) {
				$d = (int) $posts[ $a ]->menu_order - (int) $posts[ $b ]->menu_order;
				return $d ? $d : strcmp( $posts[ $a ]->post_title, $posts[ $b ]->post_title );
			}
		);
	}
	unset( $list );
	$cache[ $guide_id ] = array(
		'children' => $children,
		'posts'    => $posts,
	);
	return $cache[ $guide_id ];
}

/**
 * Er siden skjult (eller under en skjult side)?
 *
 * @param int $id Side-id.
 * @return bool
 */
function ibos_guides_is_hidden( $id ) {
	foreach ( array_merge( array( $id ), get_post_ancestors( $id ) ) as $pid ) {
		if ( get_post_meta( $pid, '_ibg_hidden', true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Sider i læserækkefølge (dybde-først), uden skjulte.
 *
 * @param int $guide_id Rod-side.
 * @return int[]
 */
function ibos_guides_order( $guide_id ) {
	$tree = ibos_guides_tree( $guide_id );
	$out  = array();
	$walk = function ( $id ) use ( &$walk, &$out, $tree ) {
		if ( ibos_guides_is_hidden( $id ) ) {
			return;
		}
		$out[] = $id;
		foreach ( isset( $tree['children'][ $id ] ) ? $tree['children'][ $id ] : array() as $c ) {
			$walk( $c );
		}
	};
	$walk( $guide_id );
	return $out;
}

/**
 * Sti for en side relativt til guiden, fx "ledsageteknik/grebet/". Roden giver "".
 *
 * @param int $id       Side-id.
 * @param int $guide_id Rod-side.
 * @return string
 */
function ibos_guides_path( $id, $guide_id ) {
	if ( (int) $id === (int) $guide_id ) {
		return '';
	}
	$parts = array();
	for ( $p = get_post( $id ); $p && (int) $p->ID !== (int) $guide_id; $p = get_post( $p->post_parent ) ) {
		array_unshift( $parts, $p->post_name ? $p->post_name : (string) $p->ID );
		if ( ! $p->post_parent ) {
			break;
		}
	}
	return implode( '/', $parts ) . '/';
}

/**
 * Finder side-id ud fra en sti under guiden.
 *
 * @param int    $guide_id Rod-side.
 * @param string $path     Sti uden start-skråstreg.
 * @return int 0 hvis ikke fundet.
 */
function ibos_guides_resolve( $guide_id, $path ) {
	$tree    = ibos_guides_tree( $guide_id );
	$current = (int) $guide_id;
	foreach ( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) as $seg ) {
		$found = 0;
		foreach ( isset( $tree['children'][ $current ] ) ? $tree['children'][ $current ] : array() as $c ) {
			if ( $tree['posts'][ $c ]->post_name === $seg ) {
				$found = $c;
				break;
			}
		}
		if ( ! $found ) {
			return 0;
		}
		$current = $found;
	}
	return $current;
}

/**
 * Version af en guides indhold. Ændres ved redigering, så appen tilbyder en ny version.
 *
 * @param int|WP_Post|null $guide Rod-side (null = kun den fælles del).
 * @return string
 */
function ibos_guides_version( $guide = null ) {
	$v = IBOS_GUIDES_VERSION . '-' . (int) get_option( 'ibos_guides_rev', 1 );
	$g = $guide ? get_post( $guide ) : null;
	return $g ? $v . '.' . (int) get_post_meta( $g->ID, '_ibg_rev', true ) : $v;
}

/**
 * Hæv versionen. Med en side som argument hæves kun dens guide; ellers alle guides
 * (fx når et billedes alt-tekst ændres i mediebiblioteket).
 *
 * @param int|null $post_id Side-id.
 */
function ibos_guides_bump( $post_id = null ) {
	$root = $post_id ? ibos_guides_root( $post_id ) : null;
	if ( $root ) {
		update_post_meta( $root->ID, '_ibg_rev', (int) get_post_meta( $root->ID, '_ibg_rev', true ) + 1 );
		return;
	}
	update_option( 'ibos_guides_rev', (int) get_option( 'ibos_guides_rev', 1 ) + 1, true );
}

/**
 * WCAG-kontrastforhold mellem to hex-farver.
 *
 * @param string $a Farve.
 * @param string $b Farve.
 * @return float
 */
function ibos_guides_contrast( $a, $b ) {
	$lum = function ( $hex ) {
		$hex = ltrim( $hex, '#' );
		$c   = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v   = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};
	$x = $lum( $a );
	$y = $lum( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}

/**
 * Tilgængelighedstjek af en sides indhold.
 *
 * @param WP_Post $post Side.
 * @return string[] Advarsler på dansk.
 */
function ibos_guides_check( $post ) {
	$w    = array();
	$html = (string) $post->post_content;
	// Billeder markeret som dekorative (CSS-klassen "dekorativ" på billedblokken) må have tom alt-tekst.
	$scan = preg_replace( '/<figure\b[^>]*class="[^"]*\bdekorativ\b[^"]*"[^>]*>.*?<\/figure>/is', '', $html );
	if ( preg_match_all( '/<img\b[^>]*>/i', $scan, $m ) ) {
		foreach ( $m[0] as $img ) {
			if ( ! preg_match( '/\balt="([^"]*)"/i', $img, $alt ) || '' === trim( $alt[1] ) ) {
				$w[] = __( 'Et billede mangler alt-tekst. Skriv en beskrivelse i billedblokkens felt "Alternativ tekst" – eller giv blokken CSS-klassen "dekorativ", hvis billedet kun er pynt.', 'ibos-guides' );
			}
		}
	}
	if ( preg_match( '/<h1[\s>]/i', $html ) ) {
		$w[] = __( 'Brug ikke "Overskrift 1" i teksten – sidens titel er allerede overskrift 1. Brug H2 og nedefter.', 'ibos-guides' );
	}
	$last = 1;
	if ( preg_match_all( '/<h([1-6])[\s>]/i', $html, $hm ) ) {
		foreach ( $hm[1] as $lvl ) {
			$lvl = (int) $lvl;
			if ( $lvl > $last + 1 ) {
				/* translators: 1: forrige niveau, 2: nyt niveau */
				$w[] = sprintf( __( 'Overskriftsniveauerne springer fra H%1$d til H%2$d. Brug niveauerne i rækkefølge.', 'ibos-guides' ), $last, $lvl );
				break;
			}
			$last = $lvl;
		}
	}
	if ( preg_match( '/>\s*(klik her|læs mere|her)\s*<\/a>/iu', $html ) ) {
		$w[] = __( 'Et link har teksten "her" eller "klik her". Skriv en linktekst, der giver mening alene.', 'ibos-guides' );
	}
	return array_values( array_unique( $w ) );
}
