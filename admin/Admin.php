<?php

/**
 * Admin interface for WP Optimiser by SimpliWeb
 */

if (!defined("ABSPATH")) {
	exit();
}

class SimpliOptimiser_Admin
{
	private static $instance = null;
	private $gravity_active = false;

	/**
	 * Get singleton instance
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		add_action("plugins_loaded", [$this, "detect_gravity"]);
		add_action("admin_menu", [$this, "add_admin_menu"]);
		add_action("admin_enqueue_scripts", [$this, "enqueue_admin_assets"]);

		// Include and initialize sub-classes
		$this->include_admin_classes();
		if (class_exists("GFForms")) {
			$this->gravity_active = true;
		}
	}

	public function detect_gravity()
	{
		if (class_exists("GFForms")) {
			$this->gravity_active = true;

			// Load Gravity class since plugin is active
			require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Gravity.php";
			SimpliOptimiser_Gravity::get_instance();
		}
	}

	/**
	 * Include admin class files
	 */
	private function include_admin_classes()
	{
		require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Posts.php";
		require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Transients.php";
		require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Shortcodes.php";
		require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Media.php";
		require_once SIMPLI_OPTIMISER_PLUGIN_DIR . "admin/Images.php";
		// Initialize each admin page
		SimpliOptimiser_Posts::get_instance();
		SimpliOptimiser_Transients::get_instance();
		SimpliOptimiser_Shortcodes::get_instance();
		SimpliOptimiser_Media::get_instance();
		SimpliOptimiser_Images::get_instance();
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu()
	{
		// Add parent menu (won't show as submenu)
		add_menu_page(
			__("Optimiser", "simpliweb"),
			__("Optimiser", "simpliweb"),
			"manage_options",
			"simpli-optimiser",
			"__return_null", // No callback needed
			"dashicons-hammer",
			100,
		);

		// Remove the automatic duplicate submenu that WordPress creates
		remove_submenu_page("simpli-optimiser", "simpli-optimiser");

		// Add submenu pages (these will call the respective class methods)
		add_submenu_page(
			"simpli-optimiser",
			__("Post Relationships", "simpliweb"),
			__("Post Relationships", "simpliweb"),
			"manage_options",
			"simpli-optimiser-posts",
			["SimpliOptimiser_Posts", "render_page"],
		);

		add_submenu_page(
			"simpli-optimiser",
			__("Transient Manager", "simpliweb"),
			__("Transient Manager", "simpliweb"),
			"manage_options",
			"simpli-optimiser-transients",
			["SimpliOptimiser_Transients", "render_page"],
		);

		add_submenu_page(
			"simpli-optimiser",
			__("Shortcode Finder", "simpliweb"),
			__("Shortcode Finder", "simpliweb"),
			"manage_options",
			"simpli-optimiser-shortcodes",
			["SimpliOptimiser_Shortcodes", "render_page"],
		);

		add_submenu_page(
			"simpli-optimiser",
			__("Media Source", "simpliweb"),
			__("Media Source", "simpliweb"),
			"manage_options",
			"simpli-optimiser-media",
			["SimpliOptimiser_Media", "render_page"],
		);

		add_submenu_page(
			"simpli-optimiser",
			__("Image Sizes", "simpliweb"),
			__("Image Sizes", "simpliweb"),
			"manage_options",
			"simpli-optimiser-images",
			["SimpliOptimiser_Images", "render_page"],
		);
		if ($this->gravity_active == true) {
			add_submenu_page(
				"simpli-optimiser",
				__("Gravity Forms", "simpliweb"),
				__("Gravity Forms", "simpliweb"),
				"manage_options",
				"simpli-optimiser-gravity",
				["SimpliOptimiser_Gravity", "render_page"],
			);
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets($hook)
	{
		if (strpos($hook, "simpli-optimiser") === false) {
			return;
		}

		wp_enqueue_style(
			"simpli-optimiser-admin",
			SIMPLI_OPTIMISER_PLUGIN_URL . "admin/css/admin.css",
			[],
			SIMPLI_OPTIMISER_VERSION,
		);
	}
}
