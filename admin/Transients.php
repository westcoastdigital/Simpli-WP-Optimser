<?php

/**
 * Transient Manager page for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Transients
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
        add_action('wp_ajax_simpli_delete_transients', array($this, 'ajax_delete_transients'));
    }

    /**
     * Render the page
     */
    public static function render_page()
    {
        $instance = self::get_instance();
        $instance->transient_page();
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

                <div id="transient-forms">
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