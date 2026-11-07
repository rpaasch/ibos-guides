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
	// Adressen skal være gemt eksplicit (og godkendt ved gem). Ingen adresse = ingen app.
	$s['slug']       = ibos_guides_clean_slug( $s['slug'] );
	$s['short_name'] = $s['short_name'] ? $s['short_name'] : $guide->post_title;
	$s['icon_id']    = (int) $s['icon_id'];
	$s['logo_id']    = (int) $s['logo_id'];
	if ( ! preg_match( '/^#[0-9a-fA-F]{6}\z/', $s['color'] ) ) {
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
	return array_values(
		array_filter(
			get_posts(
				array(
					'post_type'      => IBOS_GUIDES_CPT,
					'post_parent'    => 0,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				)
			),
			'ibos_guides_is_guide'
		)
	);
}

/**
 * Er siden en udgivet, offentlig guide (app)?
 *
 * Kun sider på øverste niveau, hvis app-indstillinger er gemt (eller som er importeret), tæller.
 * Det forhindrer, at kapitler, der mister deres forælder (fx når en guide slettes permanent),
 * pludselig bliver offentlige apps på adresser, der kan overskygge sitets egne sider.
 *
 * @param int|WP_Post $post Side.
 * @return bool
 */
function ibos_guides_is_guide( $post ) {
	$post = get_post( $post );
	return $post
		&& IBOS_GUIDES_CPT === $post->post_type
		&& ! $post->post_parent
		&& 'publish' === $post->post_status
		&& '' === (string) $post->post_password
		&& metadata_exists( 'post', $post->ID, '_ibg_color' )
		&& '' !== ibos_guides_settings( $post )['slug'];
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
	// Længste adresse først, så "a/b" matches før "a".
	uksort(
		$routes,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a ) ?: strcmp( $a, $b );
		}
	);
	$old = ibos_guides_routes();
	if ( $old === $routes ) {
		return false;
	}
	// Adresser, der ikke længere er en app, huskes, så enheder, der har appen installeret,
	// får en "afmeldings"-service worker og sletter det gemte indhold (se ibos_guides_kill_sw()).
	$retired = (array) get_option( 'ibos_guides_retired', array() );
	foreach ( array_diff_key( $old, $routes ) as $slug => $id ) {
		$retired[ $slug ] = time();
	}
	$retired = array_diff_key( $retired, $routes );
	update_option( 'ibos_guides_retired', $retired, true );
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
	global $wp_rewrite;
	// Permalinks som /index.php/%postname%/ (servere uden mod_rewrite): adressen skal med index.php.
	$index = ( $wp_rewrite && $wp_rewrite->using_index_permalinks() ) ? $wp_rewrite->index . '/' : '';
	return home_url( '/' . $index . ibos_guides_settings( $guide )['slug'] . '/' );
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
	$by_id    = array();
	$children = array();
	foreach ( $pages as $p ) {
		// Adgangskodebeskyttede sider (og deres undersider) vises aldrig i appen.
		if ( '' !== (string) $p->post_password ) {
			continue;
		}
		$by_id[ $p->ID ]                     = $p;
		$children[ (int) $p->post_parent ][] = (int) $p->ID;
	}
	// Behold kun sider, der kan nås fra roden via udgivne, offentlige forældre.
	$posts = array( $guide_id => get_post( $guide_id ) );
	$queue = array( (int) $guide_id );
	while ( $queue ) {
		$id = array_shift( $queue );
		foreach ( isset( $children[ $id ] ) ? $children[ $id ] : array() as $c ) {
			$posts[ $c ] = $by_id[ $c ];
			$queue[]     = $c;
		}
	}
	$children = array_intersect_key( $children, $posts );
	// Hent al metadata for træet i én forespørgsel (ellers én pr. side ved fx skjult-tjek).
	update_meta_cache( 'post', array_keys( $posts ) );
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
	static $memo = array();
	$id = (int) $id;
	if ( isset( $memo[ $id ] ) ) {
		return $memo[ $id ];
	}
	$memo[ $id ] = false; // Værn mod cirkulære forælderkæder.
	$post        = get_post( $id );
	$memo[ $id ] = (bool) get_post_meta( $id, '_ibg_hidden', true ) || ( $post && $post->post_parent && ibos_guides_is_hidden( $post->post_parent ) );
	return $memo[ $id ];
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
	// Hurtig afvisning: findes første led ikke som udgivet underside, hentes træet slet ikke
	// (ellers kunne vilkårlige 404-adresser tvinge hele træet i hukommelsen).
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
	if ( $segments ) {
		$first = get_posts(
			array(
				'post_type'      => IBOS_GUIDES_CPT,
				'post_parent'    => (int) $guide_id,
				'name'           => sanitize_title( $segments[0] ),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( ! $first ) {
			return 0;
		}
	}
	$tree    = ibos_guides_tree( $guide_id );
	$current = (int) $guide_id;
	foreach ( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) as $seg ) {
		$found = 0;
		foreach ( isset( $tree['children'][ $current ] ) ? $tree['children'][ $current ] : array() as $c ) {
			$name = $tree['posts'][ $c ]->post_name;
			if ( $name === $seg || rawurldecode( $name ) === $seg ) {
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
		// Byg søgeindeks og offline-liste i baggrunden (WordPress samler gentagne kald).
		if ( ! wp_next_scheduled( 'ibos_guides_rebuild', array( (int) $root->ID ) ) ) {
			wp_schedule_single_event( time() + 30, 'ibos_guides_rebuild', array( (int) $root->ID ) );
		}
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
	if ( '' === trim( $html ) ) {
		return $w;
	}
	// Gennemgås med WordPress' HTML-tag-processor: lineær tid, også på uafsluttet eller
	// fjendtlig markup (et regulært udtryk kunne bruge kvadratisk tid).
	$p          = new WP_HTML_Tag_Processor( $html );
	$decorative = 0; // Dybde inde i <figure class="… dekorativ …">.
	$missing    = false;
	$empty_img  = false;
	$videos     = 0;
	$tracks     = 0;
	$empty_vid  = false;
	$h1         = false;
	$last       = 1;
	$skip       = '';
	while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $p->get_tag();
		if ( 'FIGURE' === $tag ) {
			if ( $p->is_tag_closer() ) {
				$decorative = max( 0, $decorative - 1 );
			} elseif ( $decorative || $p->has_class( 'dekorativ' ) ) {
				$decorative++;
			}
			continue;
		}
		if ( $p->is_tag_closer() ) {
			continue;
		}
		if ( 'VIDEO' === $tag ) {
			if ( is_string( $p->get_attribute( 'src' ) ) ) {
				++$videos;
			} else {
				$empty_vid = true;
			}
		} elseif ( 'TRACK' === $tag && in_array( strtolower( (string) $p->get_attribute( 'kind' ) ), array( 'captions', 'subtitles' ), true ) ) {
			++$tracks;
		} elseif ( 'IMG' === $tag && ! is_string( $p->get_attribute( 'src' ) ) ) {
			$empty_img = true; // Tom billedblok (fx fra skabelonen); vises ikke i appen.
		} elseif ( 'IMG' === $tag && ! $decorative ) {
			$alt = $p->get_attribute( 'alt' );
			if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
				$missing = true;
			}
		} elseif ( preg_match( '/^H([1-6])$/', $tag, $m ) ) {
			$lvl = (int) $m[1];
			if ( 1 === $lvl ) {
				$h1 = true;
			}
			if ( '' === $skip && $lvl > $last + 1 ) {
				$skip = sprintf( /* translators: 1: forrige niveau, 2: nyt niveau */ __( 'Overskriftsniveauerne springer fra H%1$d til H%2$d. Brug niveauerne i rækkefølge.', 'ibos-guides' ), $last, $lvl );
			}
			$last = $lvl;
		}
	}
	if ( function_exists( 'ibos_guides_has_sample' ) && ibos_guides_has_sample( $html ) ) {
		$w[] = __( 'Der står stadig eksempeltekst fra et mønster – tekst i firkantede parenteser, der starter med ✎. Skriv den om, eller slet blokken.', 'ibos-guides' );
	}
	if ( $videos > $tracks ) {
		$w[] = __( 'En video mangler undertekster. Vælg videoblokken → "Tekstspor" og upload en undertekstfil (.vtt) med typen "Undertekster" eller "Billedtekster". Beskriv også kort i teksten, hvad videoen viser.', 'ibos-guides' );
	}
	if ( $empty_vid ) {
		$w[] = __( 'Der er en tom videoblok. Vælg en video (Upload eller Mediebibliotek), eller slet blokken.', 'ibos-guides' );
	}
	if ( $empty_img ) {
		$w[] = __( 'Der er en tom billedblok. Vælg et billede (Upload eller Mediebibliotek), eller slet blokken. Tomme blokke vises ikke i appen.', 'ibos-guides' );
	}
	if ( $missing ) {
		$w[] = __( 'Et billede mangler alt-tekst. Skriv en beskrivelse i billedblokkens felt "Alternativ tekst" – eller giv blokken CSS-klassen "dekorativ", hvis billedet kun er pynt.', 'ibos-guides' );
	}
	if ( $h1 ) {
		$w[] = __( 'Brug ikke "Overskrift 1" i teksten – sidens titel er allerede overskrift 1. Brug H2 og nedefter.', 'ibos-guides' );
	}
	if ( $skip ) {
		$w[] = $skip;
	}
	if ( preg_match( '/>\s*(klik her|læs mere|her)\s*<\/a>/iu', $html ) ) {
		$w[] = __( 'Et link har teksten "her" eller "klik her". Skriv en linktekst, der giver mening alene.', 'ibos-guides' );
	}
	return $w;
}
