<?php
/**
 * Admin interface for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Admin {
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Optimiser', 'simpliweb'),
            __('Optimiser', 'simpliweb'),
            'manage_options',
            'simpli-optimiser',
            array($this, 'relationship_page'),
            'dashicons-hammer',
            30
        );
        
        add_submenu_page(
            'simpli-optimiser',
            __('Post Relationships', 'simpliweb'),
            __('Post Relationships', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-posts',
            array($this, 'relationship_page')
        );
        
        add_submenu_page(
            'simpli-optimiser',
            __('Transient Manager', 'simpliweb'),
            __('Transient Manager', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-transients',
            array($this, 'transient_page')
        );

        add_submenu_page(
            'simpli-optimiser',
            __('Shortcode Finder', 'simpliweb'),
            __('Shortcode Finder', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-shortcodes',
            array($this, 'shortcode_page')
        );

        add_submenu_page(
            'simpli-optimiser',
            __('Media Library Source Tracker', 'simpliweb'),
            __('Media Library Source Tracker', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-media',
            array($this, 'media_page')
        );
    }
    
    /**
     * Handle admin actions
     */
    public function handle_actions() {
        // Register AJAX handlers
        add_action('wp_ajax_simpli_delete_transients', array($this, 'ajax_delete_transients'));
        add_action('wp_ajax_simpli_scan_relationships', array($this, 'ajax_scan_relationships'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'simpli-optimiser') === false) {
            return;
        }
        
        wp_enqueue_style('simpli-optimiser-admin', SIMPLI_OPTIMISER_PLUGIN_URL . 'admin/css/admin.css', array(), SIMPLI_OPTIMISER_VERSION);
    }
    
    /**
     * Post Relationships page
     */
    public function relationship_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Post Relationships', 'simpliweb'); ?></h1>
            
            <div class="card full-width">
                <h2><?php _e('Post Link Analysis', 'simpliweb'); ?></h2>
                <p><?php _e('Analyze internal links between posts, pages, and custom post types.', 'simpliweb'); ?></p>
                
                <button type="button" class="button button-primary" id="scan-relationships">
                    <?php _e('Scan Relationships', 'simpliweb'); ?>
                </button>
                
                <div id="relationship-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#scan-relationships').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Scanning...');
                $('#relationship-results').html('<p>Scanning posts...</p>');
                
                $.post(ajaxurl, {
                    action: 'simpli_scan_relationships',
                    nonce: '<?php echo wp_create_nonce('simpli_scan_relationships'); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('Scan Relationships');
                    
                    if (response.success) {
                        var html = '<h3>Results</h3>';
                        html += '<p>Total posts scanned: ' + response.data.total + '</p>';
                        html += '<p>Posts with links: ' + response.data.with_links + '</p>';
                        html += '<p>Orphaned posts (no incoming links): ' + response.data.orphaned + '</p>';
                        
                        if (response.data.orphaned_list.length > 0) {
                            html += '<h4>Orphaned Posts:</h4>';
                            html += '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                            response.data.orphaned_list.forEach(function(post) {
                                if (post.title && post.title.trim() !== '') {
                                    html += '<tr>';
                                    html += '<td>' + post.title + '</td>';
                                    html += '<td>' + post.type + '</td>';
                                    html += '<td>' + post.status + '</td>';
                                    html += '<td><a href="' + post.edit_link + '" class="button button-small">Edit</a></td>';
                                    html += '</tr>';
                                }
                            });
                            html += '</tbody></table>';
                        }
                        
                        if (response.data.link_map.length > 0) {
                            html += '<h4>Link Map (Top 20):</h4>';
                            html += '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr><th>Post</th><th>Links To</th><th>Linked By</th></tr></thead><tbody>';
                            response.data.link_map.forEach(function(post) {
                                html += '<tr>';
                                html += '<td><a href="' + post.edit_link + '">' + post.title + '</a></td>';
                                html += '<td>' + post.links_out + '</td>';
                                html += '<td>' + post.links_in + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }
                        
                        $('#relationship-results').html(html);
                    } else {
                        $('#relationship-results').html('<p style="color: red;">Error: ' + response.data + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Transient Manager page
     */
    public function transient_page() {
        global $wpdb;
        
        // Handle bulk delete
        if (isset($_POST['delete_transients']) && check_admin_referer('simpli_delete_transients')) {
            $deleted = 0;
            
            if (isset($_POST['delete_all'])) {
                $deleted = $wpdb->query(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_%' 
                     OR option_name LIKE '_site_transient_%'"
                );
            } elseif (isset($_POST['delete_expired'])) {
                $time = time();
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} 
                         WHERE option_name LIKE '_transient_timeout_%' 
                         AND option_value < %d",
                        $time
                    )
                );
                
                // Delete the corresponding transient values
                $wpdb->query(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_%' 
                     AND option_name NOT LIKE '_transient_timeout_%'
                     AND option_name NOT IN (
                         SELECT REPLACE(option_name, '_timeout', '') 
                         FROM {$wpdb->options} 
                         WHERE option_name LIKE '_transient_timeout_%'
                     )"
                );
            }
            
            echo '<div class="notice notice-success"><p>' . sprintf(__('Deleted %d transient(s)', 'simpliweb'), $deleted) . '</p></div>';
        }
        
        // Get all transients
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value, LENGTH(option_value) as size
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_%' 
             ORDER BY option_name 
             LIMIT 500"
        );
        
        $time = time();
        $total_size = 0;
        $expired_count = 0;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Transient Manager', 'simpliweb'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Transient Statistics', 'simpliweb'); ?></h2>
                <p><?php printf(__('Total transients: %d', 'simpliweb'), count($transients)); ?></p>
                
                <?php foreach ($transients as $transient): 
                    $expires = intval($transient->option_value);
                    if ($expires < $time) {
                        $expired_count++;
                    }
                endforeach; ?>
                
                <p><?php printf(__('Expired transients: %d', 'simpliweb'), $expired_count); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field('simpli_delete_transients'); ?>
                    <button type="submit" name="delete_transients" value="1" onclick="return confirm('Delete all expired transients?');" class="button">
                        <?php _e('Delete Expired Transients', 'simpliweb'); ?>
                    </button>
                    <input type="hidden" name="delete_expired" value="1">
                </form>
                
                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <?php wp_nonce_field('simpli_delete_transients'); ?>
                    <button type="submit" name="delete_transients" value="1" onclick="return confirm('Delete ALL transients? This may temporarily slow down your site.');" class="button button-secondary">
                        <?php _e('Delete All Transients', 'simpliweb'); ?>
                    </button>
                    <input type="hidden" name="delete_all" value="1">
                </form>
            </div>
            
            <h2><?php _e('Transient List (First 500)', 'simpliweb'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Transient Name', 'simpliweb'); ?></th>
                        <th><?php _e('Expires', 'simpliweb'); ?></th>
                        <th><?php _e('Status', 'simpliweb'); ?></th>
                        <th><?php _e('Size', 'simpliweb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transients as $transient): 
                        $name = str_replace('_transient_timeout_', '', $transient->option_name);
                        $expires = intval($transient->option_value);
                        $is_expired = $expires < $time;
                        
                        // Get the actual transient size
                        $actual_transient = $wpdb->get_var($wpdb->prepare(
                            "SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name = %s",
                            '_transient_' . $name
                        ));
                        $size = $actual_transient ? $actual_transient : 0;
                        $total_size += $size;
                    ?>
                        <tr>
                            <td><code><?php echo esc_html($name); ?></code></td>
                            <td>
                                <?php 
                                if ($expires == 0) {
                                    echo __('Never', 'simpliweb');
                                } else {
                                    echo date('Y-m-d H:i:s', $expires);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($is_expired && $expires != 0): ?>
                                    <span style="color: red;"><?php _e('Expired', 'simpliweb'); ?></span>
                                <?php else: ?>
                                    <span style="color: green;"><?php _e('Active', 'simpliweb'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo size_format($size, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3"><?php _e('Total Size', 'simpliweb'); ?></th>
                        <th><?php echo size_format($total_size, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }
    
    /**
     * Shortcode Finder page
     */
    public function shortcode_page() {
        global $wpdb, $shortcode_tags;
        
        // Get all post types
        $post_types = get_post_types(array('public' => true), 'objects');
        
        // Find all shortcodes in content
        $results = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_content, post_status
            FROM {$wpdb->posts}
            WHERE post_content LIKE '%[%]%'
            AND post_status IN ('publish', 'draft', 'pending', 'private')
            ORDER BY post_type, post_title
        ");
        
        $shortcode_usage = array();
        $orphaned_shortcodes = array();
        
        foreach ($results as $post) {
            // Find all shortcodes in the content
            preg_match_all('/\[([a-zA-Z0-9_-]+)/', $post->post_content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $shortcode) {
                    if (!isset($shortcode_usage[$shortcode])) {
                        $shortcode_usage[$shortcode] = array(
                            'count' => 0,
                            'posts' => array(),
                            'registered' => isset($shortcode_tags[$shortcode])
                        );
                    }
                    
                    $shortcode_usage[$shortcode]['count']++;
                    $shortcode_usage[$shortcode]['posts'][] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'type' => $post->post_type,
                        'status' => $post->post_status,
                        'edit_link' => get_edit_post_link($post->ID)
                    );
                    
                    if (!isset($shortcode_tags[$shortcode])) {
                        $orphaned_shortcodes[$shortcode] = true;
                    }
                }
            }
        }
        
        // Sort by count
        uasort($shortcode_usage, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        ?>
        <div class="wrap">
            <h1><?php _e('Shortcode Finder', 'simpliweb'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Statistics', 'simpliweb'); ?></h2>
                <p><?php printf(__('Total unique shortcodes found: %d', 'simpliweb'), count($shortcode_usage)); ?></p>
                <p><?php printf(__('Orphaned shortcodes (not registered): %d', 'simpliweb'), count($orphaned_shortcodes)); ?></p>
                <p><?php printf(__('Posts with shortcodes: %d', 'simpliweb'), count($results)); ?></p>
            </div>
            
            <?php if (!empty($orphaned_shortcodes)): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Warning:', 'simpliweb'); ?></strong>
                        <?php _e('The following shortcodes are used but not registered. They may not display correctly:', 'simpliweb'); ?>
                    </p>
                    <p><?php echo implode(', ', array_map('esc_html', array_keys($orphaned_shortcodes))); ?></p>
                </div>
            <?php endif; ?>
            
            <h2><?php _e('Shortcode Usage', 'simpliweb'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?php _e('Shortcode', 'simpliweb'); ?></th>
                        <th style="width: 100px;"><?php _e('Status', 'simpliweb'); ?></th>
                        <th style="width: 80px;"><?php _e('Count', 'simpliweb'); ?></th>
                        <th><?php _e('Used In', 'simpliweb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shortcode_usage as $shortcode => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($shortcode); ?></code></td>
                            <td>
                                <?php if ($data['registered']): ?>
                                    <span style="color: green;">✓ <?php _e('Registered', 'simpliweb'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Not Registered', 'simpliweb'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $data['count']; ?></td>
                            <td>
                                <details>
                                    <summary><?php printf(__('View %d post(s)', 'simpliweb'), count($data['posts'])); ?></summary>
                                    <ul style="margin-top: 10px;">
                                        <?php foreach ($data['posts'] as $post): ?>
                                            <li>
                                                <a href="<?php echo esc_url($post['edit_link']); ?>">
                                                    <?php echo esc_html($post['title'] ?: '(no title)'); ?>
                                                </a>
                                                <small>(<?php echo esc_html($post['type']); ?> - <?php echo esc_html($post['status']); ?>)</small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="card" style="margin-top: 20px;">
                <h3><?php _e('Registered Shortcodes', 'simpliweb'); ?></h3>
                <p><?php _e('All shortcodes currently registered in WordPress:', 'simpliweb'); ?></p>
                <p><?php echo implode(', ', array_map(function($tag) {
                    return '<code>' . esc_html($tag) . '</code>';
                }, array_keys($shortcode_tags))); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Media Library Source Tracker page
     */
    public function media_page() {
        global $wpdb;
        
        // Get media with source tracking
        $media = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_date, pm.meta_value as source_post_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_upload_source_post'
            WHERE p.post_type = 'attachment'
            ORDER BY p.post_date DESC
            LIMIT 200
        ");
        
        $with_source = 0;
        $without_source = 0;
        
        foreach ($media as $item) {
            if ($item->source_post_id) {
                $with_source++;
            } else {
                $without_source++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Media Library Source Tracker', 'simpliweb'); ?></h1>
            
            <div class="card">
                <h2><?php _e('About Source Tracking', 'simpliweb'); ?></h2>
                <p><?php _e('This feature automatically tracks where media files are uploaded from. When you upload an image while editing a post or page, that information is saved.', 'simpliweb'); ?></p>
                <p><?php _e('A new "Upload Source" column has been added to your Media Library showing this information.', 'simpliweb'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Statistics (Last 200 uploads)', 'simpliweb'); ?></h2>
                <p><?php printf(__('Media with tracked source: %d', 'simpliweb'), $with_source); ?></p>
                <p><?php printf(__('Media without source: %d', 'simpliweb'), $without_source); ?></p>
            </div>
            
            <h2><?php _e('Recent Uploads with Source', 'simpliweb'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php _e('ID', 'simpliweb'); ?></th>
                        <th><?php _e('File Name', 'simpliweb'); ?></th>
                        <th><?php _e('Upload Date', 'simpliweb'); ?></th>
                        <th><?php _e('Uploaded From', 'simpliweb'); ?></th>
                        <th><?php _e('Actions', 'simpliweb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($media as $item): 
                        $source_post = null;
                        if ($item->source_post_id) {
                            $source_post = get_post($item->source_post_id);
                        }
                    ?>
                        <tr>
                            <td><?php echo $item->ID; ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($item->ID); ?>">
                                    <?php echo esc_html($item->post_title ?: '(no title)'); ?>
                                </a>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($item->post_date)); ?></td>
                            <td>
                                <?php if ($source_post): ?>
                                    <a href="<?php echo get_edit_post_link($source_post->ID); ?>">
                                        <?php echo esc_html($source_post->post_title); ?>
                                    </a>
                                    <br><small><?php echo esc_html($source_post->post_type); ?></small>
                                <?php elseif ($item->source_post_id): ?>
                                    <span style="color: #999;"><?php _e('Source deleted', 'simpliweb'); ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($item->ID); ?>" class="button button-small">
                                    <?php _e('Edit', 'simpliweb'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('upload.php'); ?>" class="button">
                    <?php _e('View Full Media Library', 'simpliweb'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for scanning post relationships
     */
    public function ajax_scan_relationships() {
        check_ajax_referer('simpli_scan_relationships', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Get all posts
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_content, post_status
            FROM {$wpdb->posts}
            WHERE post_status IN ('publish', 'draft', 'pending', 'private')
            AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
            ORDER BY post_date DESC
        ");
        
        $link_map = array();
        $links_in = array();
        
        foreach ($posts as $post) {
            // Find internal links
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);
            
            $links_out = 0;
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    // Check if it's an internal link
                    if (strpos($url, home_url()) !== false) {
                        $linked_id = url_to_postid($url);
                        if ($linked_id) {
                            $links_out++;
                            if (!isset($links_in[$linked_id])) {
                                $links_in[$linked_id] = 0;
                            }
                            $links_in[$linked_id]++;
                        }
                    }
                }
            }
            
            $link_map[$post->ID] = array(
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'links_out' => $links_out,
                'links_in' => isset($links_in[$post->ID]) ? $links_in[$post->ID] : 0,
                'edit_link' => get_edit_post_link($post->ID)
            );
        }
        
        // Find orphaned posts
        $orphaned = array();
        foreach ($link_map as $post_id => $data) {
            if ($data['links_in'] == 0 
                && $data['status'] == 'publish' 
                && !empty(trim($data['title'])) 
                && !empty($data['edit_link'])) {
                $orphaned[] = array(
                    'id' => $post_id,
                    'title' => $data['title'],
                    'type' => $data['type'],
                    'status' => $data['status'],
                    'edit_link' => $data['edit_link']
                );
            }
        }
        
        // Filter out posts with empty titles or no edit links
        $link_map = array_filter($link_map, function($data) {
            return !empty(trim($data['title'])) && !empty($data['edit_link']);
        });
        
        // Sort link map by total links
        uasort($link_map, function($a, $b) {
            return ($b['links_in'] + $b['links_out']) - ($a['links_in'] + $a['links_out']);
        });
        
        // Get top 20
        $link_map = array_slice($link_map, 0, 20, true);
        
        wp_send_json_success(array(
            'total' => count($posts),
            'with_links' => count(array_filter($link_map, function($data) {
                return $data['links_out'] > 0;
            })),
            'orphaned' => count($orphaned),
            'orphaned_list' => array_slice($orphaned, 0, 50),
            'link_map' => array_values($link_map)
        ));
    }
    
    /**
     * AJAX handler for deleting transients
     */
    public function ajax_delete_transients() {
        check_ajax_referer('simpli_delete_transients', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $type = isset($_POST['type']) ? $_POST['type'] : 'expired';
        $deleted = 0;
        
        if ($type === 'all') {
            $deleted = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_%' 
                 OR option_name LIKE '_site_transient_%'"
            );
        } else {
            $time = time();
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_timeout_%' 
                     AND option_value < %d",
                    $time
                )
            );
        }
        
        wp_send_json_success(array('deleted' => $deleted));
    }
}