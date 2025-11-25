<?php

/**
 * Admin interface for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Admin
{
    private static $instance = null;

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Optimiser', 'simpliweb'),
            __('Optimiser', 'simpliweb'),
            'manage_options',
            'simpli-optimiser',
            array($this, 'relationship_page'),
            'dashicons-hammer',
            100
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
            __('Media Source', 'simpliweb'),
            __('Media Source', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-media',
            array($this, 'media_page')
        );

        add_submenu_page(
            'simpli-optimiser',
            __('Image Sizes', 'simpliweb'),
            __('Image Sizes', 'simpliweb'),
            'manage_options',
            'simpli-optimiser-images',
            array($this, 'image_page')
        );
    }

    /**
     * Handle admin actions
     */
    public function handle_actions()
    {
        // Register AJAX handlers
        add_action('wp_ajax_simpli_delete_transients', array($this, 'ajax_delete_transients'));
        add_action('wp_ajax_simpli_scan_relationships', array($this, 'ajax_scan_relationships'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'simpli-optimiser') === false) {
            return;
        }

        wp_enqueue_style('simpli-optimiser-admin', SIMPLI_OPTIMISER_PLUGIN_URL . 'admin/css/admin.css', array(), SIMPLI_OPTIMISER_VERSION);
    }

    /**
     * Post Relationships page
     */
    public function relationship_page()
    {
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
    public function transient_page()
    {
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
    public function shortcode_page()
    {
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
        uasort($shortcode_usage, function ($a, $b) {
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
                <p><?php echo implode(', ', array_map(function ($tag) {
                        return '<code>' . esc_html($tag) . '</code>';
                    }, array_keys($shortcode_tags))); ?></p>
            </div>
        </div>
    <?php
    }

    /**
     * Media Source Tracker page
     */
    public function media_page()
    {
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
            <h1><?php _e('Media Source Tracker', 'simpliweb'); ?></h1>

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
     * Image Size Finder page
     */
    public function image_page()
    {
        global $wpdb;

        // Default to 1MB (in bytes)
        $min_size = isset($_POST['min_size']) ? floatval($_POST['min_size']) : (isset($_GET['min_size']) ? floatval($_GET['min_size']) : 1);
        $size_unit = isset($_POST['size_unit']) ? $_POST['size_unit'] : (isset($_GET['size_unit']) ? $_GET['size_unit'] : 'MB');

        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Convert to bytes
        $min_size_bytes = $min_size;
        switch ($size_unit) {
            case 'KB':
                $min_size_bytes = $min_size * 1024;
                break;
            case 'MB':
                $min_size_bytes = $min_size * 1024 * 1024;
                break;
            case 'GB':
                $min_size_bytes = $min_size * 1024 * 1024 * 1024;
                break;
        }

        $large_images = array();
        $total_size = 0;

        // Check if form submitted or if we're paginating with existing search
        $search_performed = (isset($_POST['search_images']) && check_admin_referer('simpli_search_images')) ||
            (isset($_GET['searched']) && $_GET['searched'] === '1');

        if ($search_performed) {
            // Get all image attachments
            $attachments = $wpdb->get_results("
            SELECT ID, post_title, post_date, post_parent, post_content as description
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
            ORDER BY post_date DESC
        ");

            foreach ($attachments as $attachment) {
                $file_path = get_attached_file($attachment->ID);

                if (!$file_path || !file_exists($file_path)) {
                    continue;
                }

                $file_size = filesize($file_path);

                // Only include if larger than minimum
                if ($file_size >= $min_size_bytes) {
                    $metadata = wp_get_attachment_metadata($attachment->ID);
                    $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

                    $large_images[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'file_name' => basename($file_path),
                        'alt' => $alt_text,
                        'description' => $attachment->description,
                        'width' => isset($metadata['width']) ? $metadata['width'] : 'N/A',
                        'height' => isset($metadata['height']) ? $metadata['height'] : 'N/A',
                        'file_size' => $file_size,
                        'post_parent' => $attachment->post_parent,
                        'upload_date' => $attachment->post_date,
                        'url' => wp_get_attachment_url($attachment->ID),
                        'edit_link' => get_edit_post_link($attachment->ID)
                    );

                    $total_size += $file_size;
                }
            }

            // Sort by file size (largest first)
            usort($large_images, function ($a, $b) {
                return $b['file_size'] - $a['file_size'];
            });
        }

        // Calculate pagination
        $total_images = count($large_images);
        $total_pages = ceil($total_images / $per_page);
        $offset = ($current_page - 1) * $per_page;
        $paginated_images = array_slice($large_images, $offset, $per_page);

        // Build pagination URL
        $base_url = add_query_arg(array(
            'page' => 'simpli-optimiser-images',
            'searched' => '1',
            'min_size' => $min_size,
            'size_unit' => $size_unit
        ), admin_url('admin.php'));

    ?>
        <div class="wrap">
            <h1><?php _e('Large Image Finder', 'simpliweb'); ?></h1>

            <div class="card">
                <h2><?php _e('Search for Large Images', 'simpliweb'); ?></h2>
                <p><?php _e('Find images larger than a specified file size to help identify files that may need optimization.', 'simpliweb'); ?></p>

                <form method="post" id="simpli-image-search">
                    <?php wp_nonce_field('simpli_search_images'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="min_size"><?php _e('Minimum File Size', 'simpliweb'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                    name="min_size"
                                    id="min_size"
                                    value="<?php echo esc_attr($min_size); ?>"
                                    step="0.1"
                                    min="0.1"
                                    style="width: 100px;">

                                <select name="size_unit" id="size_unit">
                                    <option value="KB" <?php selected($size_unit, 'KB'); ?>>KB</option>
                                    <option value="MB" <?php selected($size_unit, 'MB'); ?>>MB</option>
                                    <option value="GB" <?php selected($size_unit, 'GB'); ?>>GB</option>
                                </select>

                                <p class="description">
                                    <?php _e('Images larger than this size will be displayed in the results.', 'simpliweb'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="search_images" class="button button-primary">
                            <?php _e('Find Large Images', 'simpliweb'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <?php if ($search_performed): ?>
                <div class="card">
                    <h2><?php _e('Results', 'simpliweb'); ?></h2>
                    <p>
                        <?php printf(
                            __('Found %d image(s) larger than %s %s', 'simpliweb'),
                            $total_images,
                            number_format($min_size, 1),
                            $size_unit
                        ); ?>
                    </p>
                    <?php if ($total_images > 0): ?>
                        <p>
                            <strong><?php _e('Total Size:', 'simpliweb'); ?></strong>
                            <?php echo size_format($total_size, 2); ?>
                        </p>
                        <p>
                            <?php printf(
                                __('Showing %d - %d of %d images', 'simpliweb'),
                                $offset + 1,
                                min($offset + $per_page, $total_images),
                                $total_images
                            ); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($total_images > 0): ?>
                    <h2><?php _e('Large Images', 'simpliweb'); ?></h2>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav top">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(__('%s items', 'simpliweb'), number_format_i18n($total_images)); ?>
                                </span>
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%', $base_url),
                                    'format' => '',
                                    'prev_text' => __('&laquo; Previous'),
                                    'next_text' => __('Next &raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'type' => 'plain'
                                ));
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php _e('ID', 'simpliweb'); ?></th>
                                <th style="width: 80px;"><?php _e('Preview', 'simpliweb'); ?></th>
                                <th><?php _e('File Name', 'simpliweb'); ?></th>
                                <th><?php _e('Alt Tag', 'simpliweb'); ?></th>
                                <th style="width: 200px;"><?php _e('Description', 'simpliweb'); ?></th>
                                <th style="width: 80px;"><?php _e('Dimensions', 'simpliweb'); ?></th>
                                <th style="width: 100px;"><?php _e('File Size', 'simpliweb'); ?></th>
                                <th style="width: 120px;"><?php _e('Attached To', 'simpliweb'); ?></th>
                                <th style="width: 110px;"><?php _e('Upload Date', 'simpliweb'); ?></th>
                                <th style="width: 80px;"><?php _e('Actions', 'simpliweb'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_images as $image): ?>
                                <?php
                                $image_thumbnail = wp_get_attachment_image_url($image['id'], 'simpli-thumbbail' );
                                ?>
                                <tr>
                                    <td><?php echo esc_html($image['id']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($image['url']); ?>" target="_blank">
                                            <img src="<?php echo $image_thumbnail; ?>" style="height: auto; width: 50px;" />
                                        </a>
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($image['edit_link']); ?>">
                                                <?php echo esc_html($image['file_name']); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($image['alt']): ?>
                                            <?php echo esc_html($image['alt']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;"><?php _e('No alt text', 'simpliweb'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($image['description']): ?>
                                            <div style="max-height: 50px; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo esc_html(wp_trim_words($image['description'], 10)); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($image['width'] !== 'N/A' && $image['height'] !== 'N/A') {
                                            echo esc_html($image['width'] . ' × ' . $image['height']);
                                        } else {
                                            echo '<span style="color: #999;">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <strong style="color: #d63638;">
                                            <?php echo size_format($image['file_size'], 2); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($image['post_parent']): ?>
                                            <?php $parent = get_post($image['post_parent']); ?>
                                            <?php if ($parent): ?>
                                                <a href="<?php echo get_edit_post_link($image['post_parent']); ?>">
                                                    <?php echo esc_html(wp_trim_words($parent->post_title, 5)); ?>
                                                </a>
                                                <br><small><?php echo esc_html($parent->post_type); ?></small>
                                            <?php else: ?>
                                                <span style="color: #999;"><?php _e('Deleted', 'simpliweb'); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;"><?php _e('Unattached', 'simpliweb'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($image['upload_date'])); ?>
                                        <br>
                                        <small><?php echo date('H:i', strtotime($image['upload_date'])); ?></small>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($image['edit_link']); ?>" class="button button-small">
                                            <?php _e('Edit', 'simpliweb'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(__('%s items', 'simpliweb'), number_format_i18n($total_images)); ?>
                                </span>
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%', $base_url),
                                    'format' => '',
                                    'prev_text' => __('&laquo; Previous'),
                                    'next_text' => __('Next &raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'type' => 'plain'
                                ));
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card" style="margin-top: 20px;">
                        <h3><?php _e('Optimization Tips', 'simpliweb'); ?></h3>
                        <ul>
                            <li><?php _e('Consider using image optimization plugins like ShortPixel or Imagify', 'simpliweb'); ?></li>
                            <li><?php _e('Check if images are being uploaded at unnecessarily high resolutions', 'simpliweb'); ?></li>
                            <li><?php _e('Images missing alt text hurt SEO and accessibility', 'simpliweb'); ?></li>
                            <li><?php _e('Unattached images may be unused and safe to delete', 'simpliweb'); ?></li>
                            <li><?php _e('Convert large PNG files to JPG for better compression (where appropriate)', 'simpliweb'); ?></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success">
                        <p><?php _e('No images found larger than the specified size. Great job keeping your media library optimized!', 'simpliweb'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php
    }

    /**
     * AJAX handler for scanning post relationships
     */
    public function ajax_scan_relationships()
    {
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
            if (
                $data['links_in'] == 0
                && $data['status'] == 'publish'
                && !empty(trim($data['title']))
                && !empty($data['edit_link'])
            ) {
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
        $link_map = array_filter($link_map, function ($data) {
            return !empty(trim($data['title'])) && !empty($data['edit_link']);
        });

        // Sort link map by total links
        uasort($link_map, function ($a, $b) {
            return ($b['links_in'] + $b['links_out']) - ($a['links_in'] + $a['links_out']);
        });

        // Get top 20
        $link_map = array_slice($link_map, 0, 20, true);

        wp_send_json_success(array(
            'total' => count($posts),
            'with_links' => count(array_filter($link_map, function ($data) {
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
    public function ajax_delete_transients()
    {
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
