<?php
/**
 * Post type, redigering og administration.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'ibos_guides_register_post_type' );
function ibos_guides_register_post_type() {
	register_post_type(
		IBOS_GUIDES_CPT,
		array(
			'labels'              => array(
				'name'               => __( 'Guides', 'ibos-guides' ),
				'singular_name'      => __( 'Guide-side', 'ibos-guides' ),
				'menu_name'          => __( 'Guides', 'ibos-guides' ),
				'all_items'          => __( 'Alle guides og sider', 'ibos-guides' ),
				'add_new'            => __( 'Tilføj ny', 'ibos-guides' ),
				'add_new_item'       => __( 'Tilføj ny guide eller side', 'ibos-guides' ),
				'edit_item'          => __( 'Redigér', 'ibos-guides' ),
				'new_item'           => __( 'Ny side', 'ibos-guides' ),
				'view_item'          => __( 'Vis i appen', 'ibos-guides' ),
				'search_items'       => __( 'Søg i guides', 'ibos-guides' ),
				'not_found'          => __( 'Ingen sider fundet', 'ibos-guides' ),
				'parent_item_colon'  => __( 'Forælder:', 'ibos-guides' ),
			),
			'description'         => __( 'Sider i tilgængelige guide-apps.', 'ibos-guides' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'hierarchical'        => true,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-smartphone',
			'supports'            => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions' ),
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		)
	);
}

/* ---------------------------------------------------------------------------
 * Visningslink: peg på appen i stedet for en almindelig WP-side.
 * ------------------------------------------------------------------------- */

add_filter( 'post_type_link', 'ibos_guides_post_link', 10, 2 );
function ibos_guides_post_link( $link, $post ) {
	if ( IBOS_GUIDES_CPT !== $post->post_type ) {
		return $link;
	}
	$root = ibos_guides_root( $post );
	return $root ? ibos_guides_base_url( $root ) . ibos_guides_path( $post->ID, $root->ID ) : $link;
}

/* ---------------------------------------------------------------------------
 * Metabokse
 * ------------------------------------------------------------------------- */

add_action( 'add_meta_boxes_' . IBOS_GUIDES_CPT, 'ibos_guides_meta_boxes' );
function ibos_guides_meta_boxes( $post ) {
	add_meta_box( 'ibg-help', __( 'Sådan virker guides', 'ibos-guides' ), 'ibos_guides_box_help', null, 'side', 'high' );
	add_meta_box( 'ibg-check', __( 'Tilgængelighedstjek', 'ibos-guides' ), 'ibos_guides_box_check', null, 'side', 'default' );
	add_meta_box( 'ibg-page', __( 'Visning', 'ibos-guides' ), 'ibos_guides_box_page', null, 'side', 'default' );
	if ( ! $post->post_parent ) {
		add_meta_box( 'ibg-settings', __( 'App-indstillinger (kun for guidens øverste side)', 'ibos-guides' ), 'ibos_guides_box_settings', null, 'normal', 'high' );
	}
}

function ibos_guides_box_help( $post ) {
	echo '<p>' . esc_html__( 'En side uden forælder er en guide (en app). Dens tekst bliver appens forside.', 'ibos-guides' ) . '</p>';
	echo '<p>' . esc_html__( 'Sider med guiden som forælder er kapitler. Sider under et kapitel er indholdssider.', 'ibos-guides' ) . '</p>';
	echo '<p>' . esc_html__( 'Vælg forælder og rækkefølge under "Sideattributter". Lavt tal vises først.', 'ibos-guides' ) . '</p>';
	if ( 'publish' === $post->post_status ) {
		echo '<p><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Vis siden i appen', 'ibos-guides' ) . '</a></p>';
	}
}

function ibos_guides_box_check( $post ) {
	$w = ibos_guides_check( $post );
	if ( ! $w ) {
		echo '<p>' . esc_html__( 'Ingen problemer fundet ved sidste gem.', 'ibos-guides' ) . '</p>';
		return;
	}
	echo '<ul style="list-style:disc;padding-left:1.2em">';
	foreach ( $w as $msg ) {
		echo '<li>' . esc_html( $msg ) . '</li>';
	}
	echo '</ul><p><em>' . esc_html__( 'Tjekket opdateres, når siden gemmes og genindlæses.', 'ibos-guides' ) . '</em></p>';
}

function ibos_guides_box_page( $post ) {
	wp_nonce_field( 'ibg_save_' . $post->ID, 'ibg_nonce' );
	$hidden    = (bool) get_post_meta( $post->ID, '_ibg_hidden', true );
	$menu_meta = get_post_meta( $post->ID, '_ibg_child_menu', true );
	?>
	<p>
		<label><input type="checkbox" name="ibg_hidden" value="1" <?php checked( $hidden ); ?>>
		<?php esc_html_e( 'Skjul i menu, forrige/næste og søgning', 'ibos-guides' ); ?></label>
	</p>
	<p>
		<label for="ibg-child-menu"><?php esc_html_e( 'Visning af undersider', 'ibos-guides' ); ?></label><br>
		<select name="ibg_child_menu" id="ibg-child-menu">
			<option value="" <?php selected( $menu_meta, '' ); ?>><?php esc_html_e( 'Som guidens standard', 'ibos-guides' ); ?></option>
			<option value="tiles" <?php selected( $menu_meta, 'tiles' ); ?>><?php esc_html_e( 'Felter', 'ibos-guides' ); ?></option>
			<option value="list" <?php selected( $menu_meta, 'list' ); ?>><?php esc_html_e( 'Liste', 'ibos-guides' ); ?></option>
		</select>
	</p>
	<p class="description"><?php esc_html_e( 'Uddraget (excerpt) vises som kort beskrivelse under linket i menuen.', 'ibos-guides' ); ?></p>
	<?php
}

function ibos_guides_box_settings( $post ) {
	$s        = ibos_guides_settings( $post );
	$conflict = $s['slug'] ? get_page_by_path( $s['slug'] ) : null;
	$contrast = ibos_guides_contrast( $s['color'], '#ffffff' );
	wp_enqueue_media();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ibg-slug"><?php esc_html_e( 'Adresse', 'ibos-guides' ); ?></label></th>
			<td>
				<code><?php echo esc_html( home_url( '/' ) ); ?></code>
				<input type="text" id="ibg-slug" name="ibg_slug" class="regular-text" value="<?php echo esc_attr( $s['slug'] ); ?>" aria-describedby="ibg-slug-help">
				<code>/</code>
				<p class="description" id="ibg-slug-help"><?php esc_html_e( 'Små bogstaver, tal og bindestreg. Ændres adressen, skal brugere installere appen igen.', 'ibos-guides' ); ?></p>
				<?php if ( $conflict ) : ?>
					<p class="notice notice-warning inline" style="padding:.5em"><?php esc_html_e( 'Der findes en almindelig side med samme adresse. Appen vises i stedet for den.', 'ibos-guides' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-short"><?php esc_html_e( 'Kort navn', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-short" name="ibg_short_name" maxlength="20" value="<?php echo esc_attr( $s['short_name'] ); ?>" aria-describedby="ibg-short-help">
				<p class="description" id="ibg-short-help"><?php esc_html_e( 'Navnet under ikonet på hjemmeskærmen (højst ca. 12 tegn).', 'ibos-guides' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-color"><?php esc_html_e( 'Temafarve', 'ibos-guides' ); ?></label></th>
			<td><input type="color" id="ibg-color" name="ibg_color" value="<?php echo esc_attr( $s['color'] ); ?>" aria-describedby="ibg-color-help">
				<p class="description" id="ibg-color-help">
				<?php
				/* translators: %s: kontrastforhold */
				echo esc_html( sprintf( __( 'Bruges til topbjælken med hvid tekst. Kontrast mod hvid: %s:1 (mindst 4,5:1 kræves).', 'ibos-guides' ), number_format_i18n( $contrast, 1 ) ) );
				?>
				</p>
				<?php if ( $contrast < 4.5 ) : ?>
					<p class="notice notice-error inline" style="padding:.5em"><?php esc_html_e( 'Farven er for lys til hvid tekst. Vælg en mørkere farve.', 'ibos-guides' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		foreach ( array(
			'icon_id' => array( __( 'App-ikon', 'ibos-guides' ), __( 'Kvadratisk billede, mindst 512×512 px. Hold motivet inden for de midterste 80 %.', 'ibos-guides' ) ),
			'logo_id' => array( __( 'Logo (valgfrit)', 'ibos-guides' ), __( 'Vises i topbjælken. Bør være hvidt eller lyst. Alt-teksten hentes fra mediebiblioteket.', 'ibos-guides' ) ),
		) as $key => $info ) :
			$img = $s[ $key ] ? wp_get_attachment_image_url( $s[ $key ], 'thumbnail' ) : '';
			?>
		<tr>
			<th scope="row"><?php echo esc_html( $info[0] ); ?></th>
			<td>
				<div class="ibg-media" data-target="ibg-<?php echo esc_attr( $key ); ?>">
					<img src="<?php echo esc_url( $img ); ?>" alt="" style="max-width:96px;max-height:96px;display:<?php echo $img ? 'block' : 'none'; ?>;background:<?php echo esc_attr( $s['color'] ); ?>">
					<input type="hidden" id="ibg-<?php echo esc_attr( $key ); ?>" name="ibg_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>">
					<button type="button" class="button ibg-media-choose"><?php esc_html_e( 'Vælg billede', 'ibos-guides' ); ?></button>
					<button type="button" class="button-link ibg-media-remove"><?php esc_html_e( 'Fjern', 'ibos-guides' ); ?></button>
				</div>
				<p class="description"><?php echo esc_html( $info[1] ); ?></p>
			</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="ibg-publisher"><?php esc_html_e( 'Udgiver', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-publisher" name="ibg_publisher" class="large-text" value="<?php echo esc_attr( $s['publisher'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-colophon"><?php esc_html_e( 'Kolofon', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-colophon" name="ibg_colophon" class="large-text" value="<?php echo esc_attr( $s['colophon'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-menu"><?php esc_html_e( 'Menuvisning', 'ibos-guides' ); ?></label></th>
			<td><select id="ibg-menu" name="ibg_menu">
				<option value="tiles" <?php selected( $s['menu'], 'tiles' ); ?>><?php esc_html_e( 'Felter', 'ibos-guides' ); ?></option>
				<option value="list" <?php selected( $s['menu'], 'list' ); ?>><?php esc_html_e( 'Liste', 'ibos-guides' ); ?></option>
			</select></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Guidens beskrivelse (uddrag) bruges i appens manifest og på forsiden i søgemaskiner.', 'ibos-guides' ); ?></p>
	<?php
}

add_action( 'admin_enqueue_scripts', 'ibos_guides_admin_assets' );
function ibos_guides_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || IBOS_GUIDES_CPT !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'ibos-guides-admin', plugins_url( 'assets/admin.js', IBOS_GUIDES_FILE ), array( 'jquery' ), IBOS_GUIDES_VERSION, true );
}

/* ---------------------------------------------------------------------------
 * Gem
 * ------------------------------------------------------------------------- */

add_action( 'save_post_' . IBOS_GUIDES_CPT, 'ibos_guides_save', 10, 2 );
function ibos_guides_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	ibos_guides_bump( $post_id );

	if ( isset( $_POST['ibg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ibg_nonce'] ) ), 'ibg_save_' . $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
		update_post_meta( $post_id, '_ibg_hidden', empty( $_POST['ibg_hidden'] ) ? '' : '1' );
		$cm = isset( $_POST['ibg_child_menu'] ) ? sanitize_key( wp_unslash( $_POST['ibg_child_menu'] ) ) : '';
		update_post_meta( $post_id, '_ibg_child_menu', in_array( $cm, array( 'tiles', 'list' ), true ) ? $cm : '' );

		if ( ! $post->post_parent && isset( $_POST['ibg_slug'] ) ) {
			$slug = ibos_guides_clean_slug( sanitize_text_field( wp_unslash( $_POST['ibg_slug'] ) ) );
			$bad  = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'comments' );
			if ( in_array( strtok( $slug, '/' ), $bad, true ) ) {
				$slug = '';
			}
			update_post_meta( $post_id, '_ibg_slug', $slug );
			update_post_meta( $post_id, '_ibg_short_name', sanitize_text_field( wp_unslash( isset( $_POST['ibg_short_name'] ) ? $_POST['ibg_short_name'] : '' ) ) );
			$color = sanitize_hex_color( wp_unslash( isset( $_POST['ibg_color'] ) ? $_POST['ibg_color'] : '' ) );
			update_post_meta( $post_id, '_ibg_color', $color ? $color : '#00656c' );
			update_post_meta( $post_id, '_ibg_icon_id', absint( isset( $_POST['ibg_icon_id'] ) ? $_POST['ibg_icon_id'] : 0 ) );
			update_post_meta( $post_id, '_ibg_logo_id', absint( isset( $_POST['ibg_logo_id'] ) ? $_POST['ibg_logo_id'] : 0 ) );
			update_post_meta( $post_id, '_ibg_publisher', sanitize_text_field( wp_unslash( isset( $_POST['ibg_publisher'] ) ? $_POST['ibg_publisher'] : '' ) ) );
			update_post_meta( $post_id, '_ibg_colophon', sanitize_text_field( wp_unslash( isset( $_POST['ibg_colophon'] ) ? $_POST['ibg_colophon'] : '' ) ) );
			$menu = sanitize_key( wp_unslash( isset( $_POST['ibg_menu'] ) ? $_POST['ibg_menu'] : 'tiles' ) );
			update_post_meta( $post_id, '_ibg_menu', 'list' === $menu ? 'list' : 'tiles' );
		}
	}
	if ( ibos_guides_refresh_routes() ) {
		update_option( 'ibos_guides_flush', 1 );
	}
}

// Ændringer i status, sletning og billeders alt-tekst skal også give en ny version.
add_action( 'transition_post_status', 'ibos_guides_on_status', 10, 3 );
function ibos_guides_on_status( $new, $old, $post ) {
	if ( IBOS_GUIDES_CPT === $post->post_type && $new !== $old ) {
		ibos_guides_bump( $post->ID );
		if ( ibos_guides_refresh_routes() ) {
			update_option( 'ibos_guides_flush', 1 );
		}
	}
}
add_action( 'deleted_post', 'ibos_guides_on_delete', 10, 2 );
function ibos_guides_on_delete( $id, $post ) {
	if ( $post && IBOS_GUIDES_CPT === $post->post_type ) {
		ibos_guides_bump();
		if ( ibos_guides_refresh_routes() ) {
			update_option( 'ibos_guides_flush', 1 );
		}
	}
}
add_action( 'edit_attachment', 'ibos_guides_bump_all' );
function ibos_guides_bump_all() {
	ibos_guides_bump();
}

/* ---------------------------------------------------------------------------
 * Listevisning
 * ------------------------------------------------------------------------- */

add_filter( 'manage_' . IBOS_GUIDES_CPT . '_posts_columns', 'ibos_guides_columns' );
function ibos_guides_columns( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['ibg_guide'] = __( 'Guide', 'ibos-guides' );
			$new['ibg_order'] = __( 'Rækkefølge', 'ibos-guides' );
			$new['ibg_check'] = __( 'Tjek', 'ibos-guides' );
		}
	}
	return $new;
}

add_action( 'manage_' . IBOS_GUIDES_CPT . '_posts_custom_column', 'ibos_guides_column', 10, 2 );
function ibos_guides_column( $col, $post_id ) {
	$post = get_post( $post_id );
	if ( 'ibg_guide' === $col ) {
		$root = ibos_guides_root( $post );
		if ( $root && (int) $root->ID === (int) $post_id ) {
			$url = ibos_guides_base_url( $root );
			echo '<strong>' . esc_html__( 'App', 'ibos-guides' ) . '</strong><br><a href="' . esc_url( $url ) . '">' . esc_html( wp_parse_url( $url, PHP_URL_PATH ) ) . '</a>';
		} elseif ( $root ) {
			echo esc_html( $root->post_title );
		}
	} elseif ( 'ibg_order' === $col ) {
		echo (int) $post->menu_order;
	} elseif ( 'ibg_check' === $col ) {
		$n = count( ibos_guides_check( $post ) );
		echo $n
			? '<span style="color:#b32d2e">' . esc_html( sprintf( /* translators: %d: antal */ _n( '%d advarsel', '%d advarsler', $n, 'ibos-guides' ), $n ) ) . '</span>'
			: esc_html__( 'OK', 'ibos-guides' );
	}
}

// Vis træet i rækkefølge.
add_action( 'pre_get_posts', 'ibos_guides_admin_order' );
function ibos_guides_admin_order( $q ) {
	if ( is_admin() && $q->is_main_query() && IBOS_GUIDES_CPT === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}

// Hjælpetekst øverst på listen.
add_action( 'admin_notices', 'ibos_guides_list_notice' );
function ibos_guides_list_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-' . IBOS_GUIDES_CPT !== $screen->id ) {
		return;
	}
	echo '<div class="notice notice-info"><p>' . esc_html__( 'Hver guide er en side på øverste niveau. Kapitler og sider ligger under den. Brug "Hurtig redigering" til at ændre rækkefølge og forælder.', 'ibos-guides' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . IBOS_GUIDES_CPT . '&page=ibos-guides-import' ) ) . '">' . esc_html__( 'Importér O&M-teknik eller Ledsageteknik', 'ibos-guides' ) . '</a></p></div>';
}
