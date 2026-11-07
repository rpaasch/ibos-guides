<?php
/**
 * Skrivehjælp i blok-editoren: skabelon til nye sider, IBOS-mønstre, kun relevante blokke og
 * overskrifter fra H2 til H4. Tomme felter fra skabelonen vises ikke i appen.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Byggesten (med grå hjælpetekst i stedet for rigtig tekst, så intet glemt
 * eksempel-indhold kan ende i appen)
 * ------------------------------------------------------------------------- */

function ibos_guides_tpl_paragraph( $hint ) {
	return array( 'core/paragraph', array( 'placeholder' => $hint ) );
}
function ibos_guides_tpl_heading( $hint, $level = 2 ) {
	return array( 'core/heading', array( 'level' => $level, 'placeholder' => $hint ) );
}
function ibos_guides_tpl_list( $hints, $ordered = false ) {
	$items = array();
	foreach ( (array) $hints as $h ) {
		$items[] = array( 'core/list-item', array( 'placeholder' => $h ) );
	}
	return array( 'core/list', $ordered ? array( 'ordered' => true ) : array(), $items );
}

/**
 * Skabelonen til en ny side af en given type.
 *
 * @param string $kind guide, chapter eller page.
 * @return array
 */
function ibos_guides_template( $kind ) {
	if ( 'guide' === $kind ) {
		return array(
			ibos_guides_tpl_paragraph( __( 'Velkomst: Hvad kan appen bruges til, og hvem er den til? Skriv 2–3 korte sætninger. Kapitlerne vises automatisk som menu under teksten.', 'ibos-guides' ) ),
		);
	}
	if ( 'chapter' === $kind ) {
		return array(
			ibos_guides_tpl_paragraph( __( 'Kort indledning til kapitlet (valgfri – lad feltet stå tomt, hvis der ikke er brug for det). Kapitlets sider vises automatisk som menu under teksten.', 'ibos-guides' ) ),
		);
	}
	return array(
		ibos_guides_tpl_paragraph( __( 'Indledning: Hvad handler siden om, og hvornår bruges teknikken? Skriv 1–3 korte sætninger.', 'ibos-guides' ) ),
		array( 'core/image', array() ),
		ibos_guides_tpl_list(
			array(
				__( 'Ét trin eller én pointe pr. punkt. Skriv kort og konkret – tryk Enter for et nyt punkt.', 'ibos-guides' ),
			)
		),
	);
}

/*
 * Ny side: vælg skabelon ud fra, hvor siden oprettes (?ibg_parent= fra Oversigten eller
 * Placering-boksen). Uden forælder = en ny guide.
 */
add_action( 'load-post-new.php', 'ibos_guides_set_template' );
function ibos_guides_set_template() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- vælger kun skabelon.
	$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( IBOS_GUIDES_CPT !== $type ) {
		return;
	}
	$kind   = 'guide';
	$parent = isset( $_GET['ibg_parent'] ) ? get_post( absint( $_GET['ibg_parent'] ) ) : null;
	// phpcs:enable
	if ( $parent && IBOS_GUIDES_CPT === $parent->post_type ) {
		$kind = $parent->post_parent ? 'page' : 'chapter';
	}
	$obj = get_post_type_object( IBOS_GUIDES_CPT );
	if ( $obj ) {
		$obj->template      = ibos_guides_template( $kind );
		$obj->template_lock = false; // Blokkene kan frit slettes, flyttes og suppleres.
	}
}

/* ---------------------------------------------------------------------------
 * Mønstre (Indsæt → Mønstre → IBOS Guide)
 * ------------------------------------------------------------------------- */

// Mærket foran eksempeltekst i mønstrene. Står det der stadig, advarer tjekket og Oversigten.
const IBOS_GUIDES_SAMPLE = '[✎ ';

/**
 * Indeholder teksten eksempeltekst fra et mønster, der ikke er skrevet om?
 *
 * @param string $content Indhold.
 * @return bool
 */
function ibos_guides_has_sample( $content ) {
	return false !== strpos( (string) $content, IBOS_GUIDES_SAMPLE );
}

/**
 * Serialisér en byggesten til blok-markup. Hjælpeteksten bliver synlig eksempeltekst i
 * firkantede parenteser, så mønstrenes forhåndsvisning viser, hvad der skal stå hvor.
 *
 * @param array $b Byggesten: array( navn, attributter, børn ).
 * @return string
 */
function ibos_guides_block_markup( $b ) {
	list( $name, $attrs ) = $b;
	$kids                 = isset( $b[2] ) ? $b[2] : array();
	$inner                = '';
	foreach ( $kids as $k ) {
		$inner .= ibos_guides_block_markup( $k );
	}
	$text = '';
	if ( isset( $attrs['placeholder'] ) ) {
		$text = esc_html( IBOS_GUIDES_SAMPLE . $attrs['placeholder'] . ']' );
		unset( $attrs['placeholder'] );
	}
	$html = array(
		'core/paragraph' => '<p>' . $text . '</p>',
		'core/heading'   => sprintf( '<h%1$d class="wp-block-heading">%2$s</h%1$d>', isset( $attrs['level'] ) ? (int) $attrs['level'] : 2, $text ),
		'core/list-item' => '<li>' . $text . '</li>',
		'core/list'      => sprintf( '<%1$s class="wp-block-list">%2$s</%1$s>', empty( $attrs['ordered'] ) ? 'ul' : 'ol', $inner ),
		'core/image'     => '<figure class="wp-block-image"><img alt=""/></figure>',
		'core/video'     => '<figure class="wp-block-video"></figure>',
	);
	if ( 'core/heading' === $name && isset( $attrs['level'] ) && 2 === (int) $attrs['level'] ) {
		unset( $attrs['level'] ); // Standardniveauet gemmes ikke i markup.
	}
	$short = substr( $name, 5 );
	$json  = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
	return "<!-- wp:{$short}{$json} -->\n" . $html[ $name ] . "\n<!-- /wp:{$short} -->\n\n";
}

add_action( 'init', 'ibos_guides_patterns', 20 );
function ibos_guides_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'ibos-guides', array( 'label' => __( 'IBOS Guide', 'ibos-guides' ) ) );
	$patterns = array(
		'side'        => array(
			__( 'Side med trin og billede', 'ibos-guides' ),
			__( 'Indledning, billede og punkter – som de fleste sider i guiderne.', 'ibos-guides' ),
			ibos_guides_template( 'page' ),
		),
		'billede'     => array(
			__( 'Billede med forklaring', 'ibos-guides' ),
			__( 'Et billede efterfulgt af en kort forklaring af, hvad det viser.', 'ibos-guides' ),
			array(
				array( 'core/image', array() ),
				ibos_guides_tpl_paragraph( __( 'Forklar, hvad billedet viser – fx hvem der gør hvad. Husk også alt-teksten på billedet.', 'ibos-guides' ) ),
			),
		),
		'afsnit'      => array(
			__( 'Mellemoverskrift med punkter', 'ibos-guides' ),
			__( 'Et nyt afsnit på siden, fx "Ekstra støtte".', 'ibos-guides' ),
			array(
				ibos_guides_tpl_heading( __( 'Mellemoverskrift, fx "Ekstra støtte"', 'ibos-guides' ) ),
				ibos_guides_tpl_list( array( __( 'Ét punkt pr. linje – tryk Enter for et nyt punkt.', 'ibos-guides' ) ) ),
			),
		),
		'trin'        => array(
			__( 'Nummererede trin', 'ibos-guides' ),
			__( 'En fremgangsmåde, hvor rækkefølgen betyder noget.', 'ibos-guides' ),
			array(
				ibos_guides_tpl_heading( __( 'Overskrift, fx "Sådan gør du"', 'ibos-guides' ) ),
				ibos_guides_tpl_list(
					array(
						__( 'Første trin', 'ibos-guides' ),
						__( 'Næste trin', 'ibos-guides' ),
						__( 'Næste trin (tryk Enter for flere)', 'ibos-guides' ),
					),
					true
				),
			),
		),
		'video'       => array(
			__( 'Video med forklaring', 'ibos-guides' ),
			__( 'En video (fra Medier) og en kort tekst om, hvad den viser. Husk undertekster.', 'ibos-guides' ),
			array(
				array( 'core/video', array() ),
				ibos_guides_tpl_paragraph( __( 'Fortæl kort, hvad videoen viser – så siden også giver mening uden lyd, uden internet og med skærmlæser.', 'ibos-guides' ) ),
			),
		),
		'godt-at-vide' => array(
			__( 'Godt at vide', 'ibos-guides' ),
			__( 'En lille overskrift med et kort råd eller en advarsel.', 'ibos-guides' ),
			array(
				ibos_guides_tpl_heading( __( 'Godt at vide', 'ibos-guides' ) ),
				ibos_guides_tpl_paragraph( __( 'Et kort råd, en undtagelse eller en advarsel.', 'ibos-guides' ) ),
			),
		),
	);
	foreach ( $patterns as $slug => $p ) {
		$content = '';
		foreach ( $p[2] as $b ) {
			$content .= ibos_guides_block_markup( $b );
		}
		register_block_pattern(
			'ibos-guides/' . $slug,
			array(
				'title'       => $p[0],
				'description' => $p[1],
				'categories'  => array( 'ibos-guides' ),
				'postTypes'   => array( IBOS_GUIDES_CPT ),
				'viewportWidth' => 420, // Smal forhåndsvisning, så teksten kan læses i indsætteren.
				'content'     => trim( $content ),
			)
		);
	}
}

/* ---------------------------------------------------------------------------
 * Kun blokke, der giver mening i en guide (og virker offline)
 * ------------------------------------------------------------------------- */

add_filter( 'allowed_block_types_all', 'ibos_guides_allowed_blocks', 10, 2 );
function ibos_guides_allowed_blocks( $allowed, $context ) {
	if ( empty( $context->post ) || IBOS_GUIDES_CPT !== $context->post->post_type ) {
		return $allowed;
	}
	return apply_filters(
		'ibos_guides_allowed_blocks',
		array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/image', 'core/video', 'core/quote', 'core/details', 'core/table', 'core/separator', 'core/block', 'core/pattern' )
	);
}

/* Overskrifter: kun H2–H4 i vælgeren (titlen er H1). Råd ved videoblokken om brug uden internet. */
add_action( 'enqueue_block_editor_assets', 'ibos_guides_editor_assets' );
function ibos_guides_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || IBOS_GUIDES_CPT !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'ibos-guides-editor', plugins_url( 'assets/editor.js', IBOS_GUIDES_FILE ), array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-core-data', 'wp-plugins', 'wp-editor', 'wp-edit-post' ), IBOS_GUIDES_VERSION, false );
}

/* ---------------------------------------------------------------------------
 * Tomme blokke (fx en ubrugt del af skabelonen) vises ikke i appen
 * ------------------------------------------------------------------------- */

/**
 * Fjern en tom blok. Bruges kun, mens appens indhold bygges.
 *
 * @param string $html  Blokkens HTML.
 * @param array  $block Blokken.
 * @return string
 */
function ibos_guides_drop_empty_block( $html, $block ) {
	switch ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) {
		case 'core/paragraph':
		case 'core/heading':
		case 'core/list-item':
			// Eksempeltekst fra et mønster ("[✎ …]") vises aldrig for borgerne.
			if ( ibos_guides_has_sample( $html ) ) {
				return '';
			}
			return ( '' === trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ), " \t\n\r\0\x0B\xC2\xA0" ) && false === stripos( $html, '<img' ) ) ? '' : $html;
		case 'core/list':
			return false === stripos( $html, '<li' ) ? '' : $html;
		case 'core/image':
			return preg_match( '/<img\b[^>]*\bsrc\s*=\s*["\']?[^"\'\s>]/i', $html ) ? $html : '';
		case 'core/video':
			return preg_match( '/<video\b[^>]*\bsrc\s*=\s*["\']?[^"\'\s>]/i', $html ) ? $html : '';
	}
	return $html;
}

/* ---------------------------------------------------------------------------
 * Video offline: hvilke videoer må borgeren hente til brug uden internet?
 *
 * Listen bygges af den HTML, appen faktisk viser (renderet som anonym besøgende). Så kommer
 * skjulte blokke ("Skjul blok"), blokke fjernet af andre plugins og videoer i ikke-udgivne
 * sider aldrig med – og videoer i synkroniserede mønstre kommer med.
 * ------------------------------------------------------------------------- */

/**
 * Må videoblokken hentes offline? Standard ja; redaktøren kan slå det fra pr. video.
 *
 * @param array $block Blok.
 * @return bool
 */
function ibos_guides_video_offline_allowed( $block ) {
	return ! ( isset( $block['attrs']['ibgOffline'] ) && false === $block['attrs']['ibgOffline'] );
}

// Valget "Kan hentes til brug uden internet" registreres også på serveren, så det ikke
// forsvinder, når en video i et synkroniseret mønster redigeres et andet sted end i en guide.
add_filter( 'register_block_type_args', 'ibos_guides_video_attr', 10, 2 );
function ibos_guides_video_attr( $args, $name ) {
	if ( 'core/video' === $name ) {
		$args['attributes']['ibgOffline'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
	}
	return $args;
}

/**
 * Absolut adresse på en fil fra sitet selv (samme skema, vært og port), ellers ''.
 *
 * @param string $url Adresse fra indholdet (kan være relativ).
 * @return string
 */
function ibos_guides_local_url( $url ) {
	$url = trim( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $url || 0 === strpos( $url, 'data:' ) ) {
		return '';
	}
	$abs  = WP_Http::make_absolute_url( $url, home_url( '/' ) );
	$home = wp_parse_url( home_url( '/' ) );
	$u    = wp_parse_url( $abs );
	if ( ! $u || empty( $u['host'] ) || empty( $u['scheme'] ) ) {
		return '';
	}
	$port = function ( $p ) {
		return isset( $p['port'] ) ? (int) $p['port'] : ( 'https' === strtolower( $p['scheme'] ) ? 443 : 80 );
	};
	$same = strtolower( $u['scheme'] ) === strtolower( $home['scheme'] )
		&& strtolower( $u['host'] ) === strtolower( $home['host'] )
		&& $port( $u ) === $port( $home );
	return $same ? $abs : '';
}

/**
 * Videoblokken i appen: altid afspilningsknapper, og markering af videoer, der kan hentes
 * offline (kun filer fra sitet selv). Bruges kun, mens appens indhold bygges.
 *
 * @param string $html  Blokkens HTML.
 * @param array  $block Blokken.
 * @return string
 */
function ibos_guides_prepare_video( $html, $block ) {
	if ( 'core/video' !== $block['blockName'] || '' === $html ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( ! $p->next_tag( 'figure' ) ) {
		return $html;
	}
	$p->set_bookmark( 'figure' );
	$local = false;
	if ( $p->next_tag( 'video' ) ) {
		// Uden knapper kan borgeren hverken starte eller stoppe videoen.
		$p->set_attribute( 'controls', true );
		$local = '' !== ibos_guides_local_url( $p->get_attribute( 'src' ) );
	}
	if ( $local && ibos_guides_video_offline_allowed( $block ) ) {
		$p->seek( 'figure' );
		$p->set_attribute( 'data-offline', '1' );
	}
	return $p->get_updated_html();
}

/**
 * Videoer i en sides færdige HTML.
 *
 * @param string $html Renderet indhold.
 * @return array{offline: array[], online: bool} offline: array( 'u', 's', 'x' => string[] ).
 */
function ibos_guides_html_videos( $html ) {
	$out = array(
		'offline' => array(),
		'online'  => false,
	);
	if ( false === stripos( $html, '<video' ) ) {
		return $out;
	}
	$p       = new WP_HTML_Tag_Processor( $html );
	$current = null; // Den video, et <track> hører til.
	while ( $p->next_tag() ) {
		$tag = $p->get_tag();
		if ( 'FIGURE' === $tag && ! $p->is_tag_closer() ) {
			$current = null;
			$offline = '1' === $p->get_attribute( 'data-offline' );
			if ( $p->next_tag( 'video' ) ) {
				$src = ibos_guides_local_url( $p->get_attribute( 'src' ) );
				if ( ! $offline || '' === $src ) {
					$out['online'] = true;
					continue;
				}
				$id     = attachment_url_to_postid( $src );
				$file   = $id ? get_attached_file( $id ) : '';
				$extras = array();
				$poster = ibos_guides_local_url( $p->get_attribute( 'poster' ) );
				if ( '' !== $poster ) {
					$extras[] = $poster;
				}
				$out['offline'][ $src ] = array(
					'u' => $src,
					's' => ( $file && is_readable( $file ) ) ? (int) filesize( $file ) : 0,
					'x' => $extras,
				);
				$current = $src;
			}
		} elseif ( 'TRACK' === $tag && $current ) {
			$t = ibos_guides_local_url( $p->get_attribute( 'src' ) );
			if ( '' !== $t ) {
				$out['offline'][ $current ]['x'][] = $t;
			}
		} elseif ( 'VIDEO' === $tag && ! $p->is_tag_closer() ) {
			// En video uden for en videoblok (fx i et HTML-felt) kræver internet.
			$out['online'] = true;
		}
	}
	return $out;
}

/**
 * Megabyte, afrundet til et pænt tal.
 *
 * @param int $bytes Bytes.
 * @return string
 */
function ibos_guides_mb( $bytes ) {
	if ( $bytes <= 0 ) {
		return '?';
	}
	$mb = $bytes / 1048576;
	return number_format_i18n( $mb < 10 ? max( 0.1, round( $mb, 1 ) ) : round( $mb ), $mb < 10 ? 1 : 0 );
}
