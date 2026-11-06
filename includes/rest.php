<?php
/**
 * REST-controller for guide-sider: kun brugere, der må redigere sider, kan læse via REST.
 *
 * Offentligheden ser kun guides via appen, som selv frasorterer kladder, private og
 * adgangskodebeskyttede sider og alt under dem. WordPress' standard-controller tjekker kun
 * den enkelte sides status – ikke forældrenes – og ville derfor fx vise udgivne undersider
 * af en kladde-guide til alle. Tjekket ligger i controlleren, så det gælder uanset hvordan
 * ruten staves (WordPress matcher REST-ruter uden forskel på store og små bogstaver).
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_REST_Posts_Controller' ) && ! class_exists( 'IBOS_Guides_REST_Controller' ) ) {
	/**
	 * Posts-controller med krav om edit_pages for læsning.
	 */
	class IBOS_Guides_REST_Controller extends WP_REST_Posts_Controller {

		/**
		 * Fejl, når brugeren ikke må læse.
		 *
		 * @return WP_Error|true
		 */
		private function ibg_require_editor() {
			if ( current_user_can( 'edit_pages' ) ) {
				return true;
			}
			return new WP_Error( 'rest_forbidden', __( 'Guide-sider er kun tilgængelige for redaktører via REST.', 'ibos-guides' ), array( 'status' => rest_authorization_required_code() ) );
		}

		public function get_items_permissions_check( $request ) {
			$ok = $this->ibg_require_editor();
			return true === $ok ? parent::get_items_permissions_check( $request ) : $ok;
		}

		public function get_item_permissions_check( $request ) {
			$ok = $this->ibg_require_editor();
			return true === $ok ? parent::get_item_permissions_check( $request ) : $ok;
		}

		public function check_read_permission( $post ) {
			return current_user_can( 'edit_pages' ) && parent::check_read_permission( $post );
		}
	}
}
