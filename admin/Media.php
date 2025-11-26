<?php

/**
 * Media Source Tracker page for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Media
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
        $instance->media_page();
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
}