<?php
/**
 * ACF Blocks file for Wicket Base Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Wicket_Blocks' ) ) {

	/**
	 * Wicket Blocks class
	 */
	class Wicket_Blocks {
		/**
		 * Constructor
		 */
		public function __construct() {

			// Add Wicket block catgories
			add_filter( 'block_categories_all' , array( $this, 'wicket_block_category') );

			// Add ACF blocks and field groups
			add_action( 'acf/init', array( $this, 'wicket_load_blocks'), 5 );
			add_filter( 'acf/settings/load_json', array( $this, 'wicket_load_acf_field_group') );

		}

		/**
		 * Add Wicket block categories
		 */
		public function wicket_block_category( $categories ) {
			$categories[] = array(
				'slug'  => 'wicket',
				'title' => 'Wicket'
			);
			return $categories;
		}

		/**
		 * Load ACF Blocks
		 */
		public function wicket_load_blocks() {
			$blocks = $this->wicket_get_blocks();
			foreach( $blocks as $block ) {
				if ( file_exists( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/block.json' ) ) {
					// Check if Block is already registered
					$registry = WP_Block_Type_Registry::get_instance();
					if ( ! $registry->get_registered( 'wicket/' . $block ) ) {
						register_block_type( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/block.json' );
						if ( file_exists( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/style.css' ) ) {
							wp_register_style( 'block-' . $block, WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/style.css', array(), filemtime( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/style.css' ) );
						}
						if ( file_exists( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/init.php' ) ) {
							include_once WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block . '/init.php';
						}
					}
				}
			}
		}

		/**
		 * Load ACF field groups for blocks
		 */
		public function wicket_load_acf_field_group( $paths ) {
			$blocks = $this->wicket_get_blocks();
			foreach( $blocks as $block ) {
				$paths[] = WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' . $block;
			}
			return $paths;
		}

		/**
		 * Get ACF Blocks from all folders included in the blocks folder
		 */
		public function wicket_get_blocks() {
			$blocks = scandir( WICKET_PLUGIN_DIR . 'includes/blocks/acf-blocks/' );
			$blocks = array_values( array_diff( $blocks, array( '..', '.', '.DS_Store', '_base-block' ) ) );
			return $blocks;
		}

	} // end Class Wicket_Blocks.
	new Wicket_Blocks();
}