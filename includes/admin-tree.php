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

function ibos_guides_tree_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$guides = ibos_guides_children_admin( 0 );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- kun visning af besked.
	$moved = isset( $_GET['ibg_moved'] ) ? absint( $_GET['ibg_moved'] ) : 0;
	?>
	<div class="wrap ibg-tree-wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Guides', 'ibos-guides' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . IBOS_GUIDES_CPT ) ); ?>"><?php esc_html_e( 'Ny guide', 'ibos-guides' ); ?></a>
		<?php if ( current_user_can( 'publish_pages' ) ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=ibos-guides-import' ) ); ?>"><?php esc_html_e( 'Importér guide', 'ibos-guides' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php if ( $moved ) : ?>
			<div class="notice notice-success is-dismissible" role="status"><p>
				<?php
				/* translators: %s: sidens titel */
				echo esc_html( sprintf( __( '"%s" er flyttet.', 'ibos-guides' ), get_the_title( $moved ) ) );
				?>
			</p></div>
		<?php endif; ?>
		<p style="max-width:48rem"><?php esc_html_e( 'Her ser du hver guide som en indholdsfortegnelse – i samme rækkefølge som i appen. En guide består af kapitler, og hvert kapitel har sine sider. Brug knapperne til at tilføje sider det rigtige sted og til at flytte dem op og ned.', 'ibos-guides' ); ?></p>
		<?php if ( ! $guides ) : ?>
			<p><?php esc_html_e( 'Der er ingen guides endnu. Opret en ny guide, eller importér O&M-teknik eller Ledsageteknik.', 'ibos-guides' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $guides as $g ) : ?>
			<section class="ibg-tree-guide" aria-labelledby="ibg-guide-<?php echo (int) $g->ID; ?>">
				<h2 id="ibg-guide-<?php echo (int) $g->ID; ?>"><?php echo esc_html( get_the_title( $g ) ); ?></h2>
				<ul class="ibg-tree">
					<?php ibos_guides_tree_item( $g, 0, 1, 1 ); ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
	<style>
		.ibg-tree-guide { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:0 1rem 1rem; margin:1rem 0; max-width:60rem; }
		.ibg-tree, .ibg-tree ul { list-style:none; margin:0; padding:0; }
		.ibg-tree ul { margin-left:1.75rem; border-left:2px solid #dcdcde; padding-left:.75rem; }
		.ibg-row { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .75rem; padding:.45rem 0; border-bottom:1px solid #f0f0f1; }
		.ibg-row .ibg-title { font-size:14px; font-weight:600; flex:1 1 14rem; }
		.ibg-row .ibg-kind { color:#50575e; font-weight:400; font-size:12px; margin-left:.35rem; }
		.ibg-row form { display:inline; margin:0; }
		.ibg-badge { display:inline-block; font-size:11px; padding:1px 6px; border-radius:3px; background:#f0f0f1; color:#1d2327; margin-left:.35rem; }
		.ibg-row .button { min-height:32px; }
		.ibg-add { margin:.4rem 0 .6rem; }
	</style>
	<?php
}

/**
 * Ét punkt i træet (rekursivt).
 *
 * @param WP_Post $p     Side.
 * @param int     $depth 0 = guide, 1 = kapitel, 2+ = side.
 * @param int     $pos   Position blandt søskende (1-baseret).
 * @param int     $count Antal søskende.
 */
function ibos_guides_tree_item( $p, $depth, $pos, $count ) {
	$kids   = ibos_guides_children_admin( $p->ID );
	$title  = get_the_title( $p );
	$title  = '' === $title ? __( '(uden titel)', 'ibos-guides' ) : $title;
	$kinds  = array( __( 'forside', 'ibos-guides' ), __( 'kapitel', 'ibos-guides' ), __( 'side', 'ibos-guides' ) );
	$kind   = $kinds[ min( $depth, 2 ) ];
	$status = array(
		'draft'   => __( 'Kladde', 'ibos-guides' ),
		'pending' => __( 'Afventer', 'ibos-guides' ),
		'private' => __( 'Privat', 'ibos-guides' ),
		'future'  => __( 'Planlagt', 'ibos-guides' ),
	);
	$add = array( __( 'Nyt kapitel', 'ibos-guides' ), __( 'Ny side i kapitlet', 'ibos-guides' ), __( 'Ny underside', 'ibos-guides' ) );
	?>
	<li id="ibg-item-<?php echo (int) $p->ID; ?>">
		<div class="ibg-row">
			<span class="ibg-title">
				<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $title ); ?></a><span class="ibg-kind">(<?php echo esc_html( $kind ); ?>)</span>
				<?php if ( isset( $status[ $p->post_status ] ) ) : ?><span class="ibg-badge"><?php echo esc_html( $status[ $p->post_status ] ); ?></span><?php endif; ?>
				<?php if ( '' !== (string) $p->post_password ) : ?><span class="ibg-badge"><?php esc_html_e( 'Adgangskode', 'ibos-guides' ); ?></span><?php endif; ?>
				<?php if ( get_post_meta( $p->ID, '_ibg_hidden', true ) ) : ?><span class="ibg-badge"><?php esc_html_e( 'Skjult i menu', 'ibos-guides' ); ?></span><?php endif; ?>
			</span>
			<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titel */ __( 'Redigér "%s"', 'ibos-guides' ), $title ) ); ?>"><?php esc_html_e( 'Redigér', 'ibos-guides' ); ?></a>
			<?php if ( 'publish' === $p->post_status ) : ?>
				<a class="button button-small" href="<?php echo esc_url( get_permalink( $p ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titel */ __( 'Vis "%s" i appen', 'ibos-guides' ), $title ) ); ?>"><?php esc_html_e( 'Vis', 'ibos-guides' ); ?></a>
			<?php endif; ?>
			<?php if ( $depth > 0 ) : ?>
				<?php foreach ( array( 'up' => array( __( 'Flyt op', 'ibos-guides' ), $pos > 1 ), 'down' => array( __( 'Flyt ned', 'ibos-guides' ), $pos < $count ) ) as $dir => $info ) : ?>
					<?php if ( $info[1] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ibos_guides_move">
							<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
							<input type="hidden" name="dir" value="<?php echo esc_attr( $dir ); ?>">
							<?php wp_nonce_field( 'ibos_guides_move_' . $p->ID ); ?>
							<button type="submit" class="button button-small" aria-label="<?php echo esc_attr( $info[0] . ': ' . $title ); ?>"><?php echo 'up' === $dir ? '↑' : '↓'; ?> <?php echo esc_html( $info[0] ); ?></button>
						</form>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php if ( $kids ) : ?>
			<ul>
				<?php
				$i = 0;
				foreach ( $kids as $k ) {
					ibos_guides_tree_item( $k, $depth + 1, ++$i, count( $kids ) );
				}
				?>
			</ul>
		<?php endif; ?>
		<?php if ( $depth < 2 || $kids ) : ?>
			<div class="ibg-add" style="margin-left:<?php echo $kids ? '1.75rem' : '0'; ?>">
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . IBOS_GUIDES_CPT . '&ibg_parent=' . (int) $p->ID ) ); ?>">+ <?php echo esc_html( $add[ min( $depth, 2 ) ] ); ?><span class="screen-reader-text"> <?php echo esc_html( sprintf( /* translators: %s: titel */ __( 'i "%s"', 'ibos-guides' ), $title ) ); ?></span></a>
			</div>
		<?php endif; ?>
	</li>
	<?php
}

/* ---------------------------------------------------------------------------
 * Flyt op/ned
 * ------------------------------------------------------------------------- */

add_action( 'admin_post_ibos_guides_move', 'ibos_guides_move' );
function ibos_guides_move() {
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$dir = isset( $_POST['dir'] ) && 'up' === $_POST['dir'] ? 'up' : 'down';
	check_admin_referer( 'ibos_guides_move_' . $id );
	$post = get_post( $id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til at flytte denne side.', 'ibos-guides' ) );
	}
	$sibs = wp_list_pluck( ibos_guides_children_admin( $post->post_parent ), 'ID' );
	$i    = array_search( $id, $sibs, true );
	$j    = 'up' === $dir ? $i - 1 : $i + 1;
	if ( false !== $i && isset( $sibs[ $j ] ) ) {
		$tmp          = $sibs[ $j ];
		$sibs[ $j ]   = $sibs[ $i ];
		$sibs[ $i ]   = $tmp;
		foreach ( $sibs as $n => $sid ) {
			if ( (int) get_post_field( 'menu_order', $sid ) !== ( $n + 1 ) * 10 && current_user_can( 'edit_post', $sid ) ) {
				wp_update_post( array( 'ID' => $sid, 'menu_order' => ( $n + 1 ) * 10 ) );
			}
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=ibos-guides&ibg_moved=' . $id . '#ibg-item-' . $id ) );
	exit;
}

/* ---------------------------------------------------------------------------
 * Ny side fra oversigten: placeres under den valgte forælder, sidst.
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
 * Boksen "Placering" i editoren
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
	?>
	<p><strong><?php echo esc_html( implode( ' › ', $trail ) ); ?></strong></p>
	<p><?php echo esc_html( $texts[ $kind ] ); ?></p>
	<p class="description"><?php esc_html_e( 'Placeringen styres som ved almindelige WordPress-sider: klik på "Overordnet" øverst i panelet og vælg guiden (for et kapitel) eller kapitlet (for en side). Ingen overordnet = siden er en guide. Rækkefølgen ændrer du nemmest med "Flyt op/ned" i Guides → Oversigt.', 'ibos-guides' ); ?></p>
	<?php if ( 'publish' === $post->post_status ) : ?>
		<p><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'Vis siden i appen', 'ibos-guides' ); ?></a></p>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ibos-guides#ibg-item-' . (int) $post->ID ) ); ?>"><?php esc_html_e( 'Se hele guiden i Guides → Oversigt', 'ibos-guides' ); ?></a></p>
	<?php
}
