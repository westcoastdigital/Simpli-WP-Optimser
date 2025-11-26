<?php

/**
 * Shortcode Finder page for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Shortcodes
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
        // No AJAX handlers needed for this page
    }

    /**
     * Render the page
     */
    public static function render_page()
    {
        $instance = self::get_instance();
        $instance->shortcode_page();
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
}