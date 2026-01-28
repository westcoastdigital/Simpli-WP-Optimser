<?php
/*
Plugin Name:  WP Optimiser by SimpliWeb
Plugin URI:   https://github.com/westcoastdigital/Simpli-WP-Optimser
Description:  Comprehensive WordPress optimization toolkit with Post Relationship Visualiser, Transient Manager, Shortcode Finder, and Media Library Source Tracker.
Version:      1.0.0
Author:       Jon Mather
Author URI:   https://jonmather.au
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  simpliweb
Domain Path:  /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIMPLI_OPTIMISER_VERSION', '1.0.0');
define('SIMPLI_OPTIMISER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIMPLI_OPTIMISER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once SIMPLI_OPTIMISER_PLUGIN_DIR . 'admin/Admin.php';

// Include the updater class
require_once plugin_dir_path(__FILE__) . 'github-updater.php';

// For private repos, uncomment and add your token:
// define('SW_GITHUB_ACCESS_TOKEN', 'your_token_here');

if (class_exists('SimpliWeb_GitHub_Updater')) {
    $updater = new SimpliWeb_GitHub_Updater(__FILE__);
    $updater->set_username('westcoastdigital'); // Update Username
    $updater->set_repository('Simpli-WP-Optimser'); // Update plugin slug
    
    if (defined('GITHUB_ACCESS_TOKEN')) {
      $updater->authorize(SW_GITHUB_ACCESS_TOKEN);
    }
    
    $updater->initialize();
}
// ============================================

/**
 * Main plugin class
 */
class SimpliOptimiser {
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize admin
        if (is_admin()) {
            SimpliOptimiser_Admin::get_instance();
        }
        add_image_size( 'simpli-thumbbail', 50, 50, true );
        // Initialize media tracker
        add_action('add_attachment', array($this, 'track_media_source'));
        add_filter('manage_media_columns', array($this, 'add_media_column'));
        add_action('manage_media_custom_column', array($this, 'display_media_column'), 10, 2);
    }
    
    /**
     * Track where media was uploaded from
     */
    public function track_media_source($attachment_id) {
        $referer = wp_get_referer();
        
        if ($referer) {
            // Try to extract post ID from referer
            $post_id = url_to_postid($referer);
            
            if (!$post_id && preg_match('/post=(\d+)/', $referer, $matches)) {
                $post_id = intval($matches[1]);
            }
            
            if ($post_id) {
                update_post_meta($attachment_id, '_upload_source_post', $post_id);
                update_post_meta($attachment_id, '_upload_source_url', $referer);
            }
        }
        
        update_post_meta($attachment_id, '_upload_date', current_time('mysql'));
    }
    
    /**
     * Add source column to media library
     */
    public function add_media_column($columns) {
        $columns['upload_source'] = __('Upload Source', 'simpliweb');
        return $columns;
    }
    
    /**
     * Display source column content
     */
    public function display_media_column($column_name, $attachment_id) {
        if ($column_name === 'upload_source') {
            $source_post = get_post_meta($attachment_id, '_upload_source_post', true);
            
            if ($source_post) {
                $post = get_post($source_post);
                if ($post) {
                    echo '<a href="' . get_edit_post_link($source_post) . '">' . 
                         esc_html($post->post_title) . '</a><br>';
                    echo '<small>' . esc_html($post->post_type) . '</small>';
                } else {
                    echo '<span style="color: #999;">Post deleted</span>';
                }
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        }
    }
}

// Initialize the plugin
SimpliOptimiser::get_instance();

add_action('admin_menu', function () {
    remove_submenu_page('simpli-optimiser', 'simpli-optimiser');
}, 999);