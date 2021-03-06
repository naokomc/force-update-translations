<?php
/*
Plugin Name: Force update translations
Description: Download WordPress theme/plugin translations and apply them to your site manually even their language pack haven't been released or reviewed on translate.wordpress.org
Author:      Mayo Moriyama
Version:     0.1
*/

class Force_Update_Translations {

	private $admin_notices = [];

  /**
   * Constructor.
   */
  function __construct() {

		add_action( 'plugin_action_links',        array( $this, 'plugin_action_links'        ), 10, 2 );
		add_action( 'pre_current_active_plugins', array( $this, 'pre_current_active_plugins' ) );

  }
	/**
	 * Add plugin action link.
	 *
	 * @param string $actions
	 * @param string $plugin_file
	 * @return array $actions    File path to get source.
	 */
	function plugin_action_links( $actions, $plugin_file ) {
		$url          = admin_url( 'plugins.php?force_translate=' . $plugin_file );
		$new_acctions = array (
			'force_translate' => sprintf(
				'<a href="%1$s">%2$s</a>',
				$url,
				esc_html__( 'Update translation', 'force-update-translations' )
			)
		);
		$actions  = array_merge( $actions, $new_acctions );
		return $actions;

	}
	/**
	 * Main plugin action.
	 *
	 * @return null
	 */
	function pre_current_active_plugins() {
		if ( !isset( $_GET['force_translate'] ) ) {
			return;
		}
		else {
			$plugin_file = $_GET['force_translate'];
			$plugin_slug = substr($plugin_file, 0, strpos($plugin_file, '/') );
			$types       = array( 'po', 'mo' );

			foreach ( $types as $type ){
				$import = $this->import( 'wp-plugins/'. $plugin_slug , 'ja', $type );
				if( is_wp_error( $import ) ) {
					$this->admin_notices[] = array(
						'status'  => 'error',
						'content' => $import->get_error_message()
					);
				}
			} // endforeach;

			if ( empty( $this->admin_notices ) ) {
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
				$this->admin_notices[] = array(
					'status'  => 'success',
					'content' => sprintf(
						__( 'Translation files have been exported: %s', 'force-update-translations' ),
						'<b>' . $plugin_data['Title'] . '</b>' )
				);
			}
			self::admin_notices();

		}
	}
	/**
	 * Import translation file.
	 *
	 * @param string $project   File project
	 * @param string $locale    File locale
	 * @param string $format    File format
	 * @return null|WP_Error    File path to get source.
	 */
	function import( $project_slug, $locale = 'ja', $format = 'mo' ) {

		preg_match("/wp-(.*)/", $project_slug, $project_path);

		$source = $this->get_source_path( $project_slug, $locale, $format );
		$target = sprintf(
			'%s-%s.%s',
			$project_path[1],
			$locale,
			$format
		);
		$response = wp_remote_get( $source );
		if ( !is_array( $response ) ) {
			return new WP_Error( 'fdt-source-not-found', sprintf(
				__( 'Cannot get source file: %s', 'force-update-translations' ), $target
			) );
		}
		else {
			file_put_contents( WP_LANG_DIR . '/' . $target, $response['body'] );
			return;
		}
	}
	/**
	 * Generate a file path to get translation file.
	 *
	 * @param string $project   File project
	 * @param string $locale    File locale
	 * @param string $type      File type
	 * @param string $format    File format
	 * @return $path            File path to get source.
	 */
	function get_source_path( $project, $locale, $format = 'mo', $type = 'dev' ) {
		$path = sprintf( 'https://translate.wordpress.org/projects/%1$s/%2$s/%3$s/default/export-translations?filters[status]=all',
			$project,
			$type,
			$locale
		);
		$path = ( $format == 'po' ) ? $path : $path . '&format=' . $format;
		return $path;
	}

	/**
	 * Prints admin screen notices.
	 *
	 */
	function admin_notices() {
		if ( empty( $this->admin_notices ) ) {
			return;
		}
		foreach ( $this->admin_notices as $notice ) {
			?>
			<div class="notice notice-<?php echo $notice['status']; ?>">
					<p><?php echo $notice['content']; ?></p>
			</div>
			<?php
		}
	}
}

new Force_Update_Translations;
