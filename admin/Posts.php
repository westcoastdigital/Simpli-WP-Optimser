<?php

/**
 * Post Relationships page for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Posts
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
        add_action('wp_ajax_simpli_scan_relationships', array($this, 'ajax_scan_relationships'));
    }

    /**
     * Render the page
     */
    public static function render_page()
    {
        $instance = self::get_instance();
        $instance->relationship_page();
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
}