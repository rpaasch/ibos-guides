<?php
/**
 * Redigering som en indholdsfortegnelse: "Guides → Oversigt" (træ med knapper til at tilføje
 * og flytte sider) og boksen "Placering" i editoren (forklarer WordPress' normale "Overordnet").
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Menu: "Guides" med Oversigt som forside
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'ibos_guides_tree_menu', 9 );
function ibos_guides_tree_menu() {
	add_menu_page( __( 'Guides', 'ibos-guides' ), __( 'Guides', 'ibos-guides' ), 'edit_pages', 'ibos-guides', 'ibos_guides_tree_page', 'dashicons-smartphone', 21 );
	add_submenu_page( 'ibos-guides', __( 'Oversigt over guides', 'ibos-guides' ), __( 'Oversigt', 'ibos-guides' ), 'edit_pages', 'ibos-guides', 'ibos_guides_tree_page' );
	add_submenu_page( 'ibos-guides', __( 'Ny guide', 'ibos-guides' ), __( 'Ny guide', 'ibos-guides' ), 'edit_pages', 'post-new.php?post_type=' . IBOS_GUIDES_CPT );
}

/**
 * Typen af en side i guiden: guide, kapitel eller side.
 *
 * @param WP_Post $post Side.
 * @return string
 */
function ibos_guides_kind( $post ) {
	if ( ! $post->post_parent ) {
		return 'guide';
	}
	$parent = get_post( $post->post_parent );
	return ( $parent && ! $parent->post_parent ) ? 'chapter' : 'page';
}

/**
 * Børn af en side (alle statusser undtagen papirkurv), sorteret.
 *
 * @param int $parent_id Forælder (0 = guides).
 * @return WP_Post[]
 */
function ibos_guides_children_admin( $parent_id ) {
	return get_posts(
		array(
			'post_type'      => IBOS_GUIDES_CPT,
			'post_parent'    => (int) $parent_id,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
}


/**
 * Hele træet hentet med én forespørgsel: sider pr. id og sorterede børn pr. forælder.
 *
 * @return array{posts: array<int, WP_Post>, kids: array<int, int[]>}
 */
function ibos_guides_tree_index() {
	$all   = get_posts(
		array(
			'post_type'      => IBOS_GUIDES_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	$posts = array();
	$kids  = array();
	foreach ( $all as $p ) {
		$posts[ $p->ID ]                 = $p;
		$kids[ (int) $p->post_parent ][] = $p->ID;
	}
	// Dybde og guide for hver side, regnet ud fra roden (sider uden synlig forælder udelades).
	$depth = array();
	$root  = array();
	$walk  = function ( $id, $d, $r ) use ( &$walk, &$depth, &$root, $kids ) {
		if ( $d > 20 || isset( $depth[ $id ] ) ) {
			return;
		}
		$depth[ $id ] = $d;
		$root[ $id ]  = $r;
		foreach ( isset( $kids[ $id ] ) ? $kids[ $id ] : array() as $k ) {
			$walk( $k, $d + 1, $r );
		}
	};
	foreach ( isset( $kids[0] ) ? $kids[0] : array() as $g ) {
		$walk( $g, 0, $g );
	}
	return array( 'posts' => $posts, 'kids' => $kids, 'depth' => $depth, 'root' => $root );
}

/**
 * Dybde i træet: 0 = guide, 1 = kapitel, 2 = side, 3+ = underside.
 *
 * @param int|WP_Post $post Side.
 * @return int
 */
function ibos_guides_depth( $post ) {
	return count( get_post_ancestors( $post ) );
}

/**
 * Alle sider i en guide i læserækkefølge (som i appen).
 *
 * @param int   $root_id Guidens id.
 * @param array $index   Fra ibos_guides_tree_index().
 * @return int[]
 */
function ibos_guides_reading_order( $root_id, $index ) {
	$out  = array();
	$walk = function ( $id, $guard ) use ( &$walk, &$out, $index ) {
		if ( $guard > 20 ) {
			return;
		}
		$out[] = $id;
		foreach ( isset( $index['kids'][ $id ] ) ? $index['kids'][ $id ] : array() as $k ) {
			$walk( $k, $guard + 1 );
		}
	};
	$walk( (int) $root_id, 0 );
	return $out;
}

/* ---------------------------------------------------------------------------
 * Placering: fælles regler for træk-og-slip, "Flyt til", op/ned og kopiering
 * ------------------------------------------------------------------------- */

/**
 * Må siden lægges under denne forælder? Strukturen bevares: et kapitel kan kun ligge i en guide,
 * en side kun i et kapitel osv. Derfor kan der heller ikke opstå cirkler.
 *
 * @param WP_Post $item   Siden, der flyttes.
 * @param WP_Post $parent Ny forælder.
 * @return string Fejltekst eller ''.
 */
function ibos_guides_place_problem( $item, $parent ) {
	if ( ! $item || IBOS_GUIDES_CPT !== $item->post_type || 'trash' === $item->post_status || ! current_user_can( 'edit_post', $item->ID ) ) {
		return __( 'Du har ikke adgang til at flytte denne side.', 'ibos-guides' );
	}
	if ( ! $parent || IBOS_GUIDES_CPT !== $parent->post_type || 'trash' === $parent->post_status || ! current_user_can( 'edit_post', $parent->ID ) ) {
		return __( 'Siden kan ikke flyttes dertil.', 'ibos-guides' );
	}
	$depth = ibos_guides_depth( $item );
	if ( 0 === $depth || ibos_guides_depth( $parent ) !== $depth - 1 ) {
		return __( 'Et kapitel kan kun ligge i en guide, og en side kun i et kapitel.', 'ibos-guides' );
	}
	return '';
}

/**
 * Sæt rækkefølgen af en forælders børn (10, 20, 30 …). Sider i listen, der ligger et andet
 * sted, flyttes hertil. Kun ændrede sider gemmes.
 *
 * @param int   $parent_id Forælder.
 * @param int[] $ids       Børn i den ønskede rækkefølge.
 */
function ibos_guides_set_order( $parent_id, $ids ) {
	$GLOBALS['ibos_guides_placing'] = true; // Rækkefølgen sættes her; ikke "læg sidst".
	foreach ( array_values( $ids ) as $n => $id ) {
		$p = get_post( $id );
		if ( $p && ( (int) $p->post_parent !== (int) $parent_id || (int) $p->menu_order !== ( $n + 1 ) * 10 ) ) {
			wp_update_post( array( 'ID' => $p->ID, 'post_parent' => (int) $parent_id, 'menu_order' => ( $n + 1 ) * 10 ) );
		}
	}
	$GLOBALS['ibos_guides_placing'] = false;
}

/**
 * Børnenes id'er (uden papirkurv) i nuværende rækkefølge.
 *
 * @param int $parent_id Forælder.
 * @return int[]
 */
function ibos_guides_child_ids( $parent_id ) {
	return array_map( 'intval', wp_list_pluck( ibos_guides_children_admin( $parent_id ), 'ID' ) );
}

/**
 * Tilbage til oversigten med en besked.
 *
 * @param string $done Hvad der skete (moved, copied, trashed, restored).
 * @param int    $id   Siden, det drejer sig om.
 * @param string $dir  Ved op/ned: retningen (så fokus kan sættes på samme knap igen).
 */
function ibos_guides_tree_redirect( $done, $id, $dir = '' ) {
	$dir = in_array( $dir, array( 'up', 'down' ), true ) ? '&ibg_dir=' . $dir : '';
	wp_safe_redirect( admin_url( 'admin.php?page=ibos-guides&ibg_done=' . rawurlencode( $done ) . '&ibg_id=' . (int) $id . $dir . '#ibg-item-' . (int) $id ) );
	exit;
}

/**
 * Læs et heltal fra $_POST.
 *
 * @param string $key Navn.
 * @return int
 */
function ibos_guides_post_int( $key ) {
	return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce tjekkes af kalderen.
}

/* Træk og slip (AJAX): ny rækkefølge for én liste. */
add_action( 'wp_ajax_ibos_guides_reorder', 'ibos_guides_ajax_reorder' );
function ibos_guides_ajax_reorder() {
	check_ajax_referer( 'ibos_guides_reorder' );
	$parent = get_post( ibos_guides_post_int( 'parent' ) );
	$raw    = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_slice( wp_unslash( $_POST['order'] ), 0, 500 ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- renses med absint herunder.
	$ids    = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	if ( ! $parent || ! $ids ) {
		wp_send_json_error( array( 'message' => __( 'Rækkefølgen kunne ikke gemmes. Siden genindlæses.', 'ibos-guides' ) ) );
	}
	$moved = null;
	foreach ( $ids as $id ) {
		$item    = get_post( $id );
		$problem = ibos_guides_place_problem( $item, $parent );
		if ( $problem ) {
			wp_send_json_error( array( 'message' => $problem ) );
		}
		if ( (int) $item->post_parent !== (int) $parent->ID ) {
			$moved = $item;
		}
	}
	// Børn, der ikke var med i listen (fx oprettet imens i en anden fane), beholdes sidst.
	$ids = array_merge( $ids, array_diff( ibos_guides_child_ids( $parent->ID ), $ids ) );
	ibos_guides_set_order( $parent->ID, $ids );
	$message = $moved
		/* translators: 1: side, 2: kapitel/guide */
		? sprintf( __( '"%1$s" er flyttet til "%2$s".', 'ibos-guides' ), get_the_title( $moved ), get_the_title( $parent ) )
		: __( 'Rækkefølgen er gemt.', 'ibos-guides' );
	wp_send_json_success( array( 'message' => $message ) );
}

/* Flyt op/ned (virker uden mus og uden JavaScript). */
add_action( 'admin_post_ibos_guides_move', 'ibos_guides_move' );
function ibos_guides_move() {
	$id = ibos_guides_post_int( 'id' );
	check_admin_referer( 'ibos_guides_move_' . $id );
	$post = get_post( $id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til at flytte denne side.', 'ibos-guides' ) );
	}
	$dir  = isset( $_POST['dir'] ) && 'up' === $_POST['dir'] ? 'up' : 'down';
	$sibs = ibos_guides_child_ids( $post->post_parent );
	$i    = array_search( $id, $sibs, true );
	$j    = 'up' === $dir ? $i - 1 : $i + 1;
	if ( false !== $i && isset( $sibs[ $j ] ) ) {
		list( $sibs[ $i ], $sibs[ $j ] ) = array( $sibs[ $j ], $sibs[ $i ] );
		ibos_guides_set_order( $post->post_parent, $sibs );
	}
	ibos_guides_tree_redirect( 'moved', $id, $dir );
}

/* Flyt til et andet kapitel (eller en anden guide); lægges sidst dér. */
add_action( 'admin_post_ibos_guides_reparent', 'ibos_guides_reparent' );
function ibos_guides_reparent() {
	$id = ibos_guides_post_int( 'id' );
	check_admin_referer( 'ibos_guides_reparent_' . $id );
	$item    = get_post( $id );
	$parent  = get_post( ibos_guides_post_int( 'parent' ) );
	$problem = ibos_guides_place_problem( $item, $parent );
	if ( $problem ) {
		wp_die( esc_html( $problem ) );
	}
	if ( (int) $item->post_parent !== (int) $parent->ID ) {
		$ids   = array_diff( ibos_guides_child_ids( $parent->ID ), array( $id ) );
		$ids[] = $id;
		ibos_guides_set_order( $parent->ID, $ids );
	}
	ibos_guides_tree_redirect( 'moved', $id );
}

/* Kopiér en side; kopien er en kladde lige under originalen. */
add_action( 'admin_post_ibos_guides_copy', 'ibos_guides_copy' );
function ibos_guides_copy() {
	$id = ibos_guides_post_int( 'id' );
	check_admin_referer( 'ibos_guides_copy_' . $id );
	$src = get_post( $id );
	if ( ! $src || ! current_user_can( 'edit_pages' ) || ibos_guides_place_problem( $src, get_post( $src->post_parent ) ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til at kopiere denne side.', 'ibos-guides' ) );
	}
	$new = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => IBOS_GUIDES_CPT,
				'post_status'  => 'draft',
				/* translators: %s: titel */
				'post_title'   => sprintf( __( '%s (kopi)', 'ibos-guides' ), $src->post_title ),
				'post_content' => $src->post_content,
				'post_excerpt' => $src->post_excerpt,
				'post_parent'  => (int) $src->post_parent,
				'menu_order'   => (int) $src->menu_order,
			)
		),
		true
	);
	if ( is_wp_error( $new ) ) {
		wp_die( esc_html( $new->get_error_message() ) );
	}
	foreach ( array( '_ibg_hidden', '_ibg_child_menu' ) as $key ) {
		$v = get_post_meta( $id, $key, true );
		if ( '' !== $v ) {
			update_post_meta( $new, $key, $v );
		}
	}
	$ids = array();
	foreach ( ibos_guides_child_ids( $src->post_parent ) as $sid ) {
		if ( $sid !== $new ) {
			$ids[] = $sid;
		}
		if ( $sid === $id ) {
			$ids[] = $new;
		}
	}
	ibos_guides_set_order( $src->post_parent, $ids );
	ibos_guides_tree_redirect( 'copied', $new );
}

/* Læg i papirkurven (undersider følger med) – og fortryd. */
add_action( 'admin_post_ibos_guides_trash', 'ibos_guides_trash' );
function ibos_guides_trash() {
	$id = ibos_guides_post_int( 'id' );
	check_admin_referer( 'ibos_guides_trash_' . $id );
	$post = get_post( $id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type || ! $post->post_parent || ! current_user_can( 'delete_post', $id ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til at slette denne side.', 'ibos-guides' ) );
	}
	wp_trash_post( $id );
	ibos_guides_tree_redirect( 'trashed', $id );
}
add_action( 'admin_post_ibos_guides_untrash', 'ibos_guides_untrash' );
function ibos_guides_untrash() {
	$id = ibos_guides_post_int( 'id' );
	check_admin_referer( 'ibos_guides_untrash_' . $id );
	$post = get_post( $id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type || 'trash' !== $post->post_status || ! current_user_can( 'delete_post', $id ) ) {
		wp_die( esc_html__( 'Siden kan ikke gendannes.', 'ibos-guides' ) );
	}
	wp_untrash_post( $id );
	ibos_guides_tree_redirect( 'restored', $id );
}

/* ---------------------------------------------------------------------------
 * Siden "Oversigt"
 * ------------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', 'ibos_guides_tree_assets' );
function ibos_guides_tree_assets( $hook ) {
	if ( 'toplevel_page_ibos-guides' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'ibos-guides-tree', plugins_url( 'assets/admin-tree.js', IBOS_GUIDES_FILE ), array( 'jquery', 'jquery-ui-sortable' ), IBOS_GUIDES_VERSION, true );
	wp_localize_script(
		'ibos-guides-tree',
		'ibgTree',
		array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ibos_guides_reorder' ),
			'saving'  => __( 'Gemmer …', 'ibos-guides' ),
			'error'   => __( 'Ændringen kunne ikke gemmes. Siden genindlæses.', 'ibos-guides' ),
			'confirm' => __( 'Læg "%s" i papirkurven? Eventuelle undersider følger med. Du kan fortryde bagefter.', 'ibos-guides' ),
		)
	);
}

function ibos_guides_tree_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$index = ibos_guides_tree_index();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- kun visning af besked.
	$done = isset( $_GET['ibg_done'] ) ? sanitize_key( wp_unslash( $_GET['ibg_done'] ) ) : '';
	$did  = isset( $_GET['ibg_id'] ) ? absint( $_GET['ibg_id'] ) : 0;
	// phpcs:enable
	$msgs = array(
		/* translators: %s: titel */
		'moved'    => __( '"%s" er flyttet.', 'ibos-guides' ),
		/* translators: %s: titel */
		'copied'   => __( 'Kopien "%s" er oprettet som kladde lige under originalen.', 'ibos-guides' ),
		/* translators: %s: titel */
		'trashed'  => __( '"%s" er lagt i papirkurven.', 'ibos-guides' ),
		/* translators: %s: titel */
		'restored' => __( '"%s" er gendannet.', 'ibos-guides' ),
	);
	?>
	<div class="wrap ibg-tree-wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Guides', 'ibos-guides' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . IBOS_GUIDES_CPT ) ); ?>"><?php esc_html_e( 'Ny guide', 'ibos-guides' ); ?></a>
		<?php if ( current_user_can( 'publish_pages' ) ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=ibos-guides-import' ) ); ?>"><?php esc_html_e( 'Importér guide', 'ibos-guides' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php if ( $did && isset( $msgs[ $done ] ) && get_post( $did ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( sprintf( $msgs[ $done ], get_the_title( $did ) ) ); ?>
				<?php if ( 'trashed' === $done && current_user_can( 'delete_post', $did ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="ibos_guides_untrash">
						<input type="hidden" name="id" value="<?php echo (int) $did; ?>">
						<?php wp_nonce_field( 'ibos_guides_untrash_' . $did ); ?>
						<button type="submit" class="button-link" id="ibg-undo"><?php esc_html_e( 'Fortryd', 'ibos-guides' ); ?></button>
					</form>
				<?php elseif ( 'copied' === $done ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $did ) ); ?>"><?php esc_html_e( 'Redigér kopien', 'ibos-guides' ); ?></a>
				<?php endif; ?>
			</p></div>
		<?php endif; ?>
		<div class="ibg-intro">
			<p><?php esc_html_e( 'Her ser du hver guide som en indholdsfortegnelse – i samme rækkefølge som i appen. En guide består af kapitler, og hvert kapitel har sine sider.', 'ibos-guides' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Flyt: træk i håndtaget ⠿ – også over i et andet kapitel. Eller brug knapperne ↑ og ↓ eller "Flyt til" under "Mere".', 'ibos-guides' ); ?></li>
				<li><?php esc_html_e( 'Ny side: brug "+ Ny side i kapitlet". Siden lægges sidst i kapitlet.', 'ibos-guides' ); ?></li>
				<li><?php esc_html_e( 'Ændringer gemmes med det samme, og appen får en ny version.', 'ibos-guides' ); ?></li>
			</ul>
		</div>
		<p id="ibg-tree-status" class="ibg-tree-status" role="status" aria-live="polite"<?php echo ( $did && isset( $msgs[ $done ] ) && get_post( $did ) ) ? ' data-announce="' . esc_attr( sprintf( $msgs[ $done ], get_the_title( $did ) ) ) . '" data-focus="' . esc_attr( $done . ':' . $did ) . '"' : ''; ?>></p>
		<?php if ( empty( $index['kids'][0] ) ) : ?>
			<p><?php esc_html_e( 'Der er ingen guides endnu. Opret en ny guide, eller importér O&M-teknik eller Ledsageteknik.', 'ibos-guides' ); ?></p>
		<?php endif; ?>
		<?php foreach ( isset( $index['kids'][0] ) ? $index['kids'][0] : array() as $gid ) : ?>
			<section class="ibg-tree-guide" aria-labelledby="ibg-guide-<?php echo (int) $gid; ?>">
				<h2 id="ibg-guide-<?php echo (int) $gid; ?>"><?php echo esc_html( get_the_title( $gid ) ); ?></h2>
				<ul class="ibg-tree">
					<?php ibos_guides_tree_item( $index['posts'][ $gid ], 0, $gid, $index ); ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
	<style>
		.ibg-intro { max-width:52rem; }
		.ibg-intro ul { list-style:disc; margin-left:1.5rem; }
		.ibg-tree-status { min-height:1.5em; font-weight:600; }
		.ibg-tree-guide { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:0 1rem 1rem; margin:1rem 0; max-width:64rem; }
		.ibg-tree, .ibg-tree ul { list-style:none; margin:0; padding:0; }
		.ibg-tree ul { margin-left:1.75rem; border-left:2px solid #dcdcde; padding-left:.75rem; min-height:.5rem; }
		.ibg-row { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .6rem; padding:.4rem .25rem; border-bottom:1px solid #f0f0f1; background:#fff; }
		.ibg-row .ibg-title { font-size:14px; font-weight:600; flex:1 1 14rem; }
		.ibg-row .ibg-kind { color:#50575e; font-weight:400; font-size:12px; margin-left:.35rem; }
		.ibg-row form { display:inline; margin:0; }
		.ibg-handle { cursor:grab; font-size:18px; line-height:1; color:#50575e; padding:4px 2px; user-select:none; }
		.ibg-handle:hover { color:#2271b1; }
		.ibg-badge-warn { background:#fcf0f1 !important; color:#8a2424 !important; }
		.ibg-badge { display:inline-block; font-size:11px; padding:1px 6px; border-radius:3px; background:#f0f0f1; color:#1d2327; margin-left:.35rem; }
		.ibg-row .button { min-height:32px; }
		.ibg-list > li:first-child > .ibg-row .ibg-up, .ibg-list > li:last-child > .ibg-row .ibg-down { visibility:hidden; }
		.ibg-more[open] { flex-basis:100%; order:10; }
		.ibg-more summary { cursor:pointer; list-style:none; }
		.ibg-more summary::-webkit-details-marker { display:none; }
		.ibg-more[open] summary { margin-bottom:.5rem; }
		.ibg-more-panel { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem 1.5rem; background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; padding:.6rem .75rem; }
		.ibg-more-panel label { margin-right:.35rem; }
		.ibg-more-panel select { max-width:20rem; }
		.ibg-more-panel .ibg-danger { color:#b32d2e; border-color:#b32d2e; }
		.ibg-add { margin:.4rem 0 .6rem; }
		.ibg-placeholder { height:2.5rem; border:2px dashed #2271b1; border-radius:4px; background:#f0f6fc; margin:.2rem 0; }
		.ibg-dragging .ibg-list { min-height:2rem; outline:1px dashed #c3c4c7; outline-offset:2px; }
		.ui-sortable-helper > .ibg-row { box-shadow:0 6px 18px rgba(0,0,0,.18); }
		.ibg-flash > .ibg-row { background:#edfaef; transition:background 1s; }
		@media (prefers-reduced-motion: reduce) { .ibg-flash > .ibg-row { transition:none; } }
		.ibg-tree-wrap.is-saving .ibg-tree { opacity:.7; pointer-events:none; }
		li:target > .ibg-row { background:#fcf9e8; }
	</style>
	<?php
}

/**
 * Ét punkt i træet (rekursivt).
 *
 * @param WP_Post $p     Side.
 * @param int     $depth 0 = guide, 1 = kapitel, 2+ = side.
 * @param int     $gid   Guidens id.
 * @param array   $index Fra ibos_guides_tree_index().
 */
function ibos_guides_tree_item( $p, $depth, $gid, $index ) {
	if ( $depth > 20 ) {
		return;
	}
	$kids   = isset( $index['kids'][ $p->ID ] ) ? $index['kids'][ $p->ID ] : array();
	$title  = get_the_title( $p );
	$title  = '' === $title ? __( '(uden titel)', 'ibos-guides' ) : $title;
	$kinds  = array( __( 'forside', 'ibos-guides' ), __( 'kapitel', 'ibos-guides' ), __( 'side', 'ibos-guides' ), __( 'underside', 'ibos-guides' ) );
	$status = array(
		'draft'   => __( 'Kladde', 'ibos-guides' ),
		'pending' => __( 'Afventer', 'ibos-guides' ),
		'private' => __( 'Privat', 'ibos-guides' ),
		'future'  => __( 'Planlagt', 'ibos-guides' ),
	);
	$add    = array( __( 'Nyt kapitel', 'ibos-guides' ), __( 'Ny side i kapitlet', 'ibos-guides' ), __( 'Ny underside', 'ibos-guides' ) );
	$post_a = admin_url( 'admin-post.php' );
	?>
	<li id="ibg-item-<?php echo (int) $p->ID; ?>" data-id="<?php echo (int) $p->ID; ?>">
		<div class="ibg-row">
			<?php if ( $depth > 0 ) : ?>
				<span class="ibg-handle ibg-handle-d<?php echo (int) $depth; ?>" title="<?php esc_attr_e( 'Træk for at flytte', 'ibos-guides' ); ?>" aria-hidden="true">⠿</span>
			<?php endif; ?>
			<span class="ibg-title">
				<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $title ); ?></a><span class="ibg-kind">(<?php echo esc_html( $kinds[ min( $depth, 3 ) ] ); ?>)</span>
				<?php if ( isset( $status[ $p->post_status ] ) ) : ?><span class="ibg-badge"><?php echo esc_html( $status[ $p->post_status ] ); ?></span><?php endif; ?>
				<?php if ( '' !== (string) $p->post_password ) : ?><span class="ibg-badge"><?php esc_html_e( 'Adgangskode', 'ibos-guides' ); ?></span><?php endif; ?>
				<?php if ( get_post_meta( $p->ID, '_ibg_hidden', true ) ) : ?><span class="ibg-badge"><?php esc_html_e( 'Skjult i menu', 'ibos-guides' ); ?></span><?php endif; ?>
				<?php if ( ibos_guides_has_sample( $p->post_content ) ) : ?><span class="ibg-badge ibg-badge-warn"><?php esc_html_e( 'Eksempeltekst mangler at blive skrevet om', 'ibos-guides' ); ?></span><?php endif; ?>
			</span>
			<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titel */ __( 'Redigér "%s"', 'ibos-guides' ), $title ) ); ?>"><?php esc_html_e( 'Redigér', 'ibos-guides' ); ?></a>
			<?php if ( 'publish' === $p->post_status ) : ?>
				<a class="button button-small" href="<?php echo esc_url( get_permalink( $p ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titel */ __( 'Vis "%s" i appen', 'ibos-guides' ), $title ) ); ?>"><?php esc_html_e( 'Vis', 'ibos-guides' ); ?></a>
			<?php endif; ?>
			<?php if ( $depth > 0 ) : ?>
				<?php foreach ( array( 'up' => __( 'Flyt op', 'ibos-guides' ), 'down' => __( 'Flyt ned', 'ibos-guides' ) ) as $dir => $label ) : ?>
					<form method="post" action="<?php echo esc_url( $post_a ); ?>" class="ibg-<?php echo esc_attr( $dir ); ?>">
						<input type="hidden" name="action" value="ibos_guides_move">
						<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
						<input type="hidden" name="dir" value="<?php echo esc_attr( $dir ); ?>">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ibos_guides_move_' . $p->ID ) ); ?>">
						<button type="submit" class="button button-small" aria-label="<?php echo esc_attr( $label . ': ' . $title ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo 'up' === $dir ? '↑' : '↓'; ?></button>
					</form>
				<?php endforeach; ?>
				<details class="ibg-more">
					<summary class="button button-small"><?php esc_html_e( 'Mere', 'ibos-guides' ); ?><span class="screen-reader-text"> <?php echo esc_html( sprintf( /* translators: %s: titel */ __( 'for "%s"', 'ibos-guides' ), $title ) ); ?></span></summary>
					<div class="ibg-more-panel">
						<?php
						$targets = array();
						foreach ( $index['depth'] as $cid => $cd ) {
							if ( $cid !== (int) $p->post_parent && $cd === $depth - 1 ) {
								$targets[ $index['root'][ $cid ] ][] = $index['posts'][ $cid ];
							}
						}
						?>
						<?php if ( $targets ) : ?>
							<form method="post" action="<?php echo esc_url( $post_a ); ?>">
								<input type="hidden" name="action" value="ibos_guides_reparent">
								<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ibos_guides_reparent_' . $p->ID ) ); ?>">
								<label for="ibg-to-<?php echo (int) $p->ID; ?>"><?php echo esc_html( 1 === $depth ? __( 'Flyt kapitlet til guiden', 'ibos-guides' ) : __( 'Flyt til kapitlet', 'ibos-guides' ) ); ?></label>
								<select id="ibg-to-<?php echo (int) $p->ID; ?>" name="parent">
									<?php foreach ( $targets as $rid => $cands ) : ?>
										<?php if ( $depth > 1 ) : ?><optgroup label="<?php echo esc_attr( get_the_title( $rid ) ); ?>"><?php endif; ?>
										<?php foreach ( $cands as $cand ) : ?>
											<option value="<?php echo (int) $cand->ID; ?>"><?php echo esc_html( get_the_title( $cand ) ); ?></option>
										<?php endforeach; ?>
										<?php if ( $depth > 1 ) : ?></optgroup><?php endif; ?>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Flyt', 'ibos-guides' ); ?></button>
							</form>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( $post_a ); ?>">
							<input type="hidden" name="action" value="ibos_guides_copy">
							<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ibos_guides_copy_' . $p->ID ) ); ?>">
							<button type="submit" class="button button-small"><?php echo esc_html( $kids ? __( 'Kopiér (uden undersider)', 'ibos-guides' ) : __( 'Kopiér', 'ibos-guides' ) ); ?></button>
						</form>
						<?php if ( current_user_can( 'delete_post', $p->ID ) ) : ?>
							<form method="post" action="<?php echo esc_url( $post_a ); ?>" class="ibg-trash" data-title="<?php echo esc_attr( $title ); ?>">
								<input type="hidden" name="action" value="ibos_guides_trash">
								<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ibos_guides_trash_' . $p->ID ) ); ?>">
								<button type="submit" class="button button-small ibg-danger"><?php echo esc_html( $kids ? __( 'Læg i papirkurven (med undersider)', 'ibos-guides' ) : __( 'Læg i papirkurven', 'ibos-guides' ) ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
		<?php if ( $kids || $depth < 2 ) : ?>
			<ul class="ibg-list" data-parent="<?php echo (int) $p->ID; ?>" data-depth="<?php echo (int) $depth + 1; ?>" data-guide="<?php echo (int) $gid; ?>"><?php
			foreach ( $kids as $k ) {
				ibos_guides_tree_item( $index['posts'][ $k ], $depth + 1, $gid, $index );
			}
			?></ul>
		<?php endif; ?>
		<?php if ( $depth < 2 || $kids ) : ?>
			<div class="ibg-add" style="margin-left:<?php echo $depth < 2 || $kids ? '1.75rem' : '0'; ?>">
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . IBOS_GUIDES_CPT . '&ibg_parent=' . (int) $p->ID ) ); ?>">+ <?php echo esc_html( $add[ min( $depth, 2 ) ] ); ?><span class="screen-reader-text"> <?php echo esc_html( sprintf( /* translators: %s: titel */ __( 'i "%s"', 'ibos-guides' ), $title ) ); ?></span></a>
			</div>
		<?php endif; ?>
	</li>
	<?php
}

/* ---------------------------------------------------------------------------
 * Ny side fra oversigten eller editoren: placeres under den valgte forælder, sidst.
 * ------------------------------------------------------------------------- */

add_filter( 'wp_insert_post_data', 'ibos_guides_new_with_parent', 10, 2 );
function ibos_guides_new_with_parent( $data, $postarr ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- kun forudfyldning af en ny kladde; ingen ændring af eksisterende data.
	if ( IBOS_GUIDES_CPT !== $data['post_type'] || 'auto-draft' !== $data['post_status'] || empty( $_GET['ibg_parent'] ) || ! empty( $postarr['ID'] ) ) {
		return $data;
	}
	$parent = get_post( absint( $_GET['ibg_parent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $parent && IBOS_GUIDES_CPT === $parent->post_type && current_user_can( 'edit_post', $parent->ID ) ) {
		$data['post_parent'] = (int) $parent->ID;
		$last                = ibos_guides_children_admin( $parent->ID );
		$data['menu_order']  = $last ? (int) end( $last )->menu_order + 10 : 10;
	}
	return $data;
}

/* ---------------------------------------------------------------------------
 * Boksen "Placering" i editoren: hvor er jeg, forrige/næste, ny side her
 * ------------------------------------------------------------------------- */

function ibos_guides_box_place( $post ) {
	$kind  = ibos_guides_kind( $post );
	$trail = array();
	foreach ( array_reverse( get_post_ancestors( $post ) ) as $a ) {
		$trail[] = get_the_title( $a );
	}
	$trail[] = '' !== $post->post_title ? $post->post_title : __( '(denne side)', 'ibos-guides' );
	$texts   = array(
		'guide'   => __( 'Dette er en guide – siden er appens forside. Kapitlerne lægges under den.', 'ibos-guides' ),
		'chapter' => __( 'Dette er et kapitel. Det vises som et punkt i appens menu, og dets sider vises under det. Teksten i editoren er valgfri – skriv en kort indledning, eller lad den være tom.', 'ibos-guides' ),
		'page'    => __( 'Dette er en side i et kapitel.', 'ibos-guides' ),
	);
	$prev = 0;
	$next = 0;
	$root = ibos_guides_root( $post );
	if ( $root && 'auto-draft' !== $post->post_status ) {
		$order = ibos_guides_reading_order( $root->ID, ibos_guides_tree_index() );
		$i     = array_search( (int) $post->ID, $order, true );
		if ( false !== $i ) {
			$prev = $i > 0 ? $order[ $i - 1 ] : 0;
			$next = isset( $order[ $i + 1 ] ) ? $order[ $i + 1 ] : 0;
		}
	}
	// Ny side ved siden af denne (samme kapitel) – eller i kapitlet, hvis dette er et kapitel.
	$new_parent = 'page' === $kind ? (int) $post->post_parent : (int) $post->ID;
	$new_label  = array(
		'guide'   => __( '+ Nyt kapitel i guiden', 'ibos-guides' ),
		'chapter' => __( '+ Ny side i kapitlet', 'ibos-guides' ),
		'page'    => __( '+ Ny side i samme kapitel', 'ibos-guides' ),
	);
	?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ibos-guides#ibg-item-' . (int) $post->ID ) ); ?>">← <?php esc_html_e( 'Tilbage til Oversigt', 'ibos-guides' ); ?></a></p>
	<p><strong><?php echo esc_html( implode( ' › ', $trail ) ); ?></strong></p>
	<p><?php echo esc_html( $texts[ $kind ] ); ?></p>
	<?php if ( $prev || $next ) : ?>
		<p style="display:flex;gap:.5rem;flex-wrap:wrap">
			<?php if ( $prev ) : ?>
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $prev ) ); ?>" title="<?php echo esc_attr( get_the_title( $prev ) ); ?>">‹ <?php esc_html_e( 'Forrige', 'ibos-guides' ); ?><span class="screen-reader-text">: <?php echo esc_html( get_the_title( $prev ) ); ?></span></a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $next ) ); ?>" title="<?php echo esc_attr( get_the_title( $next ) ); ?>"><?php esc_html_e( 'Næste', 'ibos-guides' ); ?><span class="screen-reader-text">: <?php echo esc_html( get_the_title( $next ) ); ?></span> ›</a>
			<?php endif; ?>
		</p>
		<p class="description"><?php esc_html_e( 'Husk at gemme, før du går videre.', 'ibos-guides' ); ?></p>
	<?php endif; ?>
	<?php if ( 'auto-draft' !== $post->post_status && ( $new_parent || 'guide' === $kind ) ) : ?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . IBOS_GUIDES_CPT . '&ibg_parent=' . $new_parent ) ); ?>"><?php echo esc_html( $new_label[ $kind ] ); ?></a></p>
	<?php endif; ?>
	<?php if ( 'publish' === $post->post_status ) : ?>
		<p><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'Vis siden i appen', 'ibos-guides' ); ?></a></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Flyt siden: klik på "Overordnet" øverst i panelet og vælg kapitlet (for en side) eller guiden (for et kapitel). Ingen overordnet = siden er en guide. Rækkefølgen ændrer du nemmest ved at trække siden i Guides → Oversigt.', 'ibos-guides' ); ?></p>
	<?php
}
