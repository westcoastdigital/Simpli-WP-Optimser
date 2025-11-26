<?php

/**
 * Image Size Finder page for WP Optimiser by SimpliWeb
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpliOptimiser_Images
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
        // Add admin post handlers
        add_action('admin_post_simpli_save_image_settings', array($this, 'save_image_settings'));

        // Disable big image scaling if option is set
        if (get_option('simpli_disable_scaled', 0)) {
            add_filter('big_image_size_threshold', '__return_false');
        }

        // AJAX handlers for batch resize
        add_action('wp_ajax_simpli_get_resize_images', array($this, 'ajax_get_resize_images'));
        add_action('wp_ajax_simpli_resize_single_image', array($this, 'ajax_resize_single_image'));

        // Auto-resize on upload
        add_filter('wp_handle_upload', array($this, 'auto_resize_on_upload'));

        // AJAX handlers for regenerate thumbnails
        add_action('wp_ajax_simpli_get_all_images', array($this, 'ajax_get_all_images'));
        add_action('wp_ajax_simpli_regenerate_single_thumbnail', array($this, 'ajax_regenerate_single_thumbnail'));

        // Disallow large image uploads
        add_filter('wp_handle_upload_prefilter', array($this, 'disallow_large_images'), 20);
    }

    /**
     * Render the page
     */
    public static function render_page()
    {
        $instance = self::get_instance();
        $instance->image_page();
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

            <?php
            // Show success message
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'simpliweb') . '</p></div>';

                // Show cleanup results if available
                $cleanup_result = get_transient('simpli_scaled_cleanup_result');
                if ($cleanup_result) {
                    delete_transient('simpli_scaled_cleanup_result');

                    if ($cleanup_result['cleaned'] > 0) {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        printf(
                            __('Cleaned up %d -scaled image(s) and updated database references.', 'simpliweb'),
                            $cleanup_result['cleaned']
                        );
                        echo '</p></div>';
                    }

                    if ($cleanup_result['errors'] > 0) {
                        echo '<div class="notice notice-warning is-dismissible"><p>';
                        printf(
                            __('Warning: %d -scaled file(s) referenced in database but original file not found.', 'simpliweb'),
                            $cleanup_result['errors']
                        );
                        echo '</p></div>';
                    }
                }
            }
            ?>

            <div class="card" style="margin-bottom: 20px;">
                <h2><?php _e('Image Optimization Settings', 'simpliweb'); ?></h2>


                <?php
                // Load current settings
                $opt_max_upload = get_option('simpli_max_upload_bytes', 6 * 1024 * 1024);
                $opt_resize_max  = get_option('simpli_resize_bytes', 1 * 1024 * 1024);
                $opt_min_width   = get_option('simpli_min_width', 1200);
                $opt_min_height  = get_option('simpli_min_height', 1200);
                $opt_disable_scaled = get_option('simpli_disable_scaled', 0);
                ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('simpli_save_image_settings'); ?>
                    <input type="hidden" name="action" value="simpli_save_image_settings">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="simpli_max_upload"><?php _e('Max allowed upload (block if larger)', 'simpliweb'); ?></label></th>
                            <td>
                                <input type="number" name="simpli_max_upload" id="simpli_max_upload" value="<?php echo esc_attr(round($opt_max_upload / (1024 * 1024), 2)); ?>" step="0.1" min="0.1" style="width:110px;">
                                <span>MB</span>
                                <p class="description"><?php _e('If an upload is larger than this it will be rejected.', 'simpliweb'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="simpli_resize_max"><?php _e('Auto-resize threshold', 'simpliweb'); ?></label></th>
                            <td>
                                <input type="number" name="simpli_resize_max" id="simpli_resize_max" value="<?php echo esc_attr(round($opt_resize_max / (1024 * 1024), 2)); ?>" step="0.1" min="0.1" style="width:110px;">
                                <span>MB</span>
                                <p class="description"><?php _e('Images above this (but below the Max upload) will be resized automatically on upload.', 'simpliweb'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Minimum Width & Height', 'simpliweb'); ?></th>
                            <td>
                                <input type="number" name="simpli_min_width" id="simpli_min_width" value="<?php echo esc_attr($opt_min_width); ?>" min="1" style="width:110px;"> px
                                &nbsp;&nbsp;
                                <input type="number" name="simpli_min_height" id="simpli_min_height" value="<?php echo esc_attr($opt_min_height); ?>" min="1" style="width:110px;"> px
                                <p class="description"><?php _e('The plugin will not shrink images below these dimensions when attempting to reduce file size.', 'simpliweb'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Disable WP big-image scaling', 'simpliweb'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="simpli_disable_scaled" value="1" <?php checked(1, $opt_disable_scaled); ?>>
                                    <?php _e('Add filter to disable WordPress `-scaled` behavior (2560px).', 'simpliweb'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary" style="display:block;margin-bottom:5px;"><?php _e('Save Settings', 'simpliweb'); ?></button>
                        <button type="button" id="simpli-batch-resize-btn" class="button">
                            <?php _e('Batch Resize Images', 'simpliweb'); ?>
                            <span id="simpli-resize-status" style="margin-left: 10px;"></span>
                        </button>
                        <button type="button" id="simpli-regenerate-btn" class="button">
                            <?php _e('Regenerate Thumbnails', 'simpliweb'); ?>
                            <span id="simpli-regenerate-status" style="margin-left: 10px;"></span>
                        </button>
                    </p>
                </form>
            </div>

            <div id="simpli-resize-progress" style="display: none; margin: 20px 0;">
                <div class="card">
                    <h3 id="simpli-progress-title"><?php _e('Batch Resize Progress', 'simpliweb'); ?></h3>
                    <div style="background: #f0f0f0; border-radius: 3px; height: 30px; overflow: hidden; margin-bottom: 10px;">
                        <div id="simpli-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="simpli-progress-text">Processing...</p>
                    <div id="simpli-progress-log" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; background: #fff; padding: 10px; border: 1px solid #ddd;"></div>
                </div>
            </div>

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
                                    <?php printf(_n('%s item', '%s items', $total_images, 'simpliweb'), number_format_i18n($total_images)); ?>
                                </span>
                                <span class="pagination-links">
                                    <?php if ($current_page == 1): ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                                    <?php else: ?>
                                        <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('First page'); ?></span>
                                            <span aria-hidden="true">«</span>
                                        </a>
                                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Previous page'); ?></span>
                                            <span aria-hidden="true">‹</span>
                                        </a>
                                    <?php endif; ?>

                                    <span class="paging-input">
                                        <label for="current-page-selector" class="screen-reader-text"><?php _e('Current Page'); ?></label>
                                        <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="<?php echo strlen($total_pages); ?>" aria-describedby="table-paging">
                                        <span class="tablenav-paging-text"> <?php _e('of'); ?> <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span></span>
                                    </span>

                                    <?php if ($current_page == $total_pages): ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                                    <?php else: ?>
                                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Next page'); ?></span>
                                            <span aria-hidden="true">›</span>
                                        </a>
                                        <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Last page'); ?></span>
                                            <span aria-hidden="true">»</span>
                                        </a>
                                    <?php endif; ?>
                                </span>
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
                                $image_thumbnail = wp_get_attachment_image_url($image['id'], 'simpli-thumbbail');
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
                        <div class="tablenav top">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(_n('%s item', '%s items', $total_images, 'simpliweb'), number_format_i18n($total_images)); ?>
                                </span>
                                <span class="pagination-links">
                                    <?php if ($current_page == 1): ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                                    <?php else: ?>
                                        <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('First page'); ?></span>
                                            <span aria-hidden="true">«</span>
                                        </a>
                                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Previous page'); ?></span>
                                            <span aria-hidden="true">‹</span>
                                        </a>
                                    <?php endif; ?>

                                    <span class="paging-input">
                                        <label for="current-page-selector" class="screen-reader-text"><?php _e('Current Page'); ?></label>
                                        <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="<?php echo strlen($total_pages); ?>" aria-describedby="table-paging">
                                        <span class="tablenav-paging-text"> <?php _e('of'); ?> <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span></span>
                                    </span>

                                    <?php if ($current_page == $total_pages): ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                                    <?php else: ?>
                                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Next page'); ?></span>
                                            <span aria-hidden="true">›</span>
                                        </a>
                                        <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>">
                                            <span class="screen-reader-text"><?php _e('Last page'); ?></span>
                                            <span aria-hidden="true">»</span>
                                        </a>
                                    <?php endif; ?>
                                </span>
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

        <script>
            jQuery(document).ready(function($) {
                $('#simpli-batch-resize-btn').on('click', function() {
                    if (!confirm('<?php echo esc_js(__('This will resize large images. This cannot be undone. Make a backup first. Continue?', 'simpliweb')); ?>')) {
                        return;
                    }

                    var $btn = $(this);
                    var $status = $('#simpli-resize-status');
                    var $progress = $('#simpli-resize-progress');
                    var $progressBar = $('#simpli-progress-bar');
                    var $progressText = $('#simpli-progress-text');
                    var $progressLog = $('#simpli-progress-log');

                    $btn.prop('disabled', true);
                    $status.html('<span class="spinner is-active" style="float: none;"></span>');
                    $progress.show();
                    $progressLog.html('');

                    // First, get the list of images to process
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'simpli_get_resize_images',
                            nonce: '<?php echo wp_create_nonce('simpli_batch_resize'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var images = response.data.images;
                                var total = images.length;

                                if (total === 0) {
                                    $progressText.html('<?php _e('No images need resizing.', 'simpliweb'); ?>');
                                    $btn.prop('disabled', false);
                                    $status.html('');
                                    return;
                                }

                                $progressText.html('Found ' + total + ' images to process...');
                                processNextImage(0);

                                function processNextImage(index) {
                                    if (index >= total) {
                                        $progressText.html('<?php _e('Complete! Processed ', 'simpliweb'); ?>' + total + ' <?php _e('images.', 'simpliweb'); ?>');
                                        $btn.prop('disabled', false);
                                        $status.html('<span style="color: green;">✓ <?php _e('Done', 'simpliweb'); ?></span>');
                                        return;
                                    }

                                    var imageId = images[index];
                                    var percent = Math.round((index / total) * 100);

                                    $progressBar.css('width', percent + '%');
                                    $progressText.html('Processing image ' + (index + 1) + ' of ' + total + ' (' + percent + '%)');

                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'simpli_resize_single_image',
                                            nonce: '<?php echo wp_create_nonce('simpli_batch_resize'); ?>',
                                            image_id: imageId
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                $progressLog.append('<div style="color: green;">✓ ' + response.data.message + '</div>');
                                            } else {
                                                $progressLog.append('<div style="color: red;">✗ ' + response.data.message + '</div>');
                                            }
                                            $progressLog.scrollTop($progressLog[0].scrollHeight);
                                            processNextImage(index + 1);
                                        },
                                        error: function() {
                                            $progressLog.append('<div style="color: red;">✗ Error processing image #' + imageId + '</div>');
                                            $progressLog.scrollTop($progressLog[0].scrollHeight);
                                            processNextImage(index + 1);
                                        }
                                    });
                                }
                            } else {
                                alert('Error: ' + response.data.message);
                                $btn.prop('disabled', false);
                                $status.html('');
                                $progress.hide();
                            }
                        },
                        error: function() {
                            alert('<?php _e('An error occurred.', 'simpliweb'); ?>');
                            $btn.prop('disabled', false);
                            $status.html('');
                            $progress.hide();
                        }
                    });
                });

                $('#simpli-regenerate-btn').on('click', function() {
                    if (!confirm('<?php echo esc_js(__('This will regenerate all image thumbnails. Continue?', 'simpliweb')); ?>')) {
                        return;
                    }

                    var $btn = $(this);
                    var $status = $('#simpli-regenerate-status');
                    var $progress = $('#simpli-resize-progress');
                    var $progressTitle = $('#simpli-progress-title');
                    var $progressBar = $('#simpli-progress-bar');
                    var $progressText = $('#simpli-progress-text');
                    var $progressLog = $('#simpli-progress-log');

                    $btn.prop('disabled', true);
                    $status.html('<span class="spinner is-active" style="float: none;"></span>');
                    $progress.show();
                    $progressTitle.text('<?php _e('Regenerate Thumbnails Progress', 'simpliweb'); ?>');
                    $progressLog.html('');
                    $progressBar.css('width', '0%');

                    // First, get the list of images to process
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'simpli_get_all_images',
                            nonce: '<?php echo wp_create_nonce('simpli_regenerate_thumbnails'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var images = response.data.images;
                                var total = images.length;

                                if (total === 0) {
                                    $progressText.html('<?php _e('No images found.', 'simpliweb'); ?>');
                                    $btn.prop('disabled', false);
                                    $status.html('');
                                    return;
                                }

                                $progressText.html('Found ' + total + ' images to process...');
                                processNextImage(0);

                                function processNextImage(index) {
                                    if (index >= total) {
                                        $progressText.html('<?php _e('Complete! Regenerated thumbnails for ', 'simpliweb'); ?>' + total + ' <?php _e('images.', 'simpliweb'); ?>');
                                        $btn.prop('disabled', false);
                                        $status.html('<span style="color: green;">✓ <?php _e('Done', 'simpliweb'); ?></span>');
                                        return;
                                    }

                                    var imageId = images[index];
                                    var percent = Math.round((index / total) * 100);

                                    $progressBar.css('width', percent + '%');
                                    $progressText.html('Processing image ' + (index + 1) + ' of ' + total + ' (' + percent + '%)');

                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'simpli_regenerate_single_thumbnail',
                                            nonce: '<?php echo wp_create_nonce('simpli_regenerate_thumbnails'); ?>',
                                            image_id: imageId
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                $progressLog.append('<div style="color: green;">✓ ' + response.data.message + '</div>');
                                            } else {
                                                $progressLog.append('<div style="color: red;">✗ ' + response.data.message + '</div>');
                                            }
                                            $progressLog.scrollTop($progressLog[0].scrollHeight);
                                            processNextImage(index + 1);
                                        },
                                        error: function() {
                                            $progressLog.append('<div style="color: red;">✗ Error processing image #' + imageId + '</div>');
                                            $progressLog.scrollTop($progressLog[0].scrollHeight);
                                            processNextImage(index + 1);
                                        }
                                    });
                                }
                            } else {
                                alert('Error: ' + response.data.message);
                                $btn.prop('disabled', false);
                                $status.html('');
                                $progress.hide();
                            }
                        },
                        error: function() {
                            alert('<?php _e('An error occurred.', 'simpliweb'); ?>');
                            $btn.prop('disabled', false);
                            $status.html('');
                            $progress.hide();
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Save image optimization settings
     */
    public function save_image_settings()
    {
        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'simpli_save_image_settings')) {
            wp_die(__('Security check failed', 'simpliweb'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'simpliweb'));
        }

        // Convert MB to bytes and save
        if (isset($_POST['simpli_max_upload'])) {
            $max_upload_mb = floatval($_POST['simpli_max_upload']);
            update_option('simpli_max_upload_bytes', $max_upload_mb * 1024 * 1024);
        }

        if (isset($_POST['simpli_resize_max'])) {
            $resize_mb = floatval($_POST['simpli_resize_max']);
            update_option('simpli_resize_bytes', $resize_mb * 1024 * 1024);
        }

        if (isset($_POST['simpli_min_width'])) {
            update_option('simpli_min_width', intval($_POST['simpli_min_width']));
        }

        if (isset($_POST['simpli_min_height'])) {
            update_option('simpli_min_height', intval($_POST['simpli_min_height']));
        }

        // Checkbox - will be absent if unchecked
        $disable_scaled = isset($_POST['simpli_disable_scaled']) ? 1 : 0;
        update_option('simpli_disable_scaled', $disable_scaled);

        // Checkbox - will be absent if unchecked
        $disable_scaled = isset($_POST['simpli_disable_scaled']) ? 1 : 0;
        $was_disabled = get_option('simpli_disable_scaled', 0);
        update_option('simpli_disable_scaled', $disable_scaled);

        // If we're enabling the disable_scaled option, clean up existing -scaled images
        if ($disable_scaled && !$was_disabled) {
            $this->cleanup_scaled_images();
        }

        // Redirect back with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'simpli-optimiser-images',
                'settings-updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Get list of images that need resizing
     */
    public function ajax_get_resize_images()
    {
        check_ajax_referer('simpli_batch_resize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;

        $resize_threshold = get_option('simpli_resize_bytes', 1 * 1024 * 1024);

        // Get all image attachments
        $attachments = $wpdb->get_col("
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'attachment' 
        AND post_mime_type LIKE 'image/%'
        ORDER BY ID ASC
    ");

        $images_to_resize = array();

        foreach ($attachments as $attachment_id) {
            $file_path = get_attached_file($attachment_id);

            // Get original upload, not -scaled version
            $original_path = $this->get_original_image_path($file_path);

            if (!file_exists($original_path)) {
                continue;
            }

            $file_size = filesize($original_path);

            // Only include if larger than threshold
            if ($file_size > $resize_threshold) {
                $images_to_resize[] = $attachment_id;
            }
        }

        wp_send_json_success(array(
            'images' => $images_to_resize,
            'count' => count($images_to_resize)
        ));
    }

    /**
     * Resize a single image via AJAX
     */
    public function ajax_resize_single_image()
    {
        check_ajax_referer('simpli_batch_resize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $image_id = intval($_POST['image_id']);

        if (!$image_id) {
            wp_send_json_error(array('message' => 'Invalid image ID'));
        }

        $result = $this->resize_image($image_id);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Get original image path (not -scaled version)
     */
    private function get_original_image_path($file_path)
    {
        // Check if this is a -scaled image
        if (preg_match('/-scaled\.(jpg|jpeg|png|gif)$/i', $file_path, $matches)) {
            // Try to find the original
            $original = preg_replace('/-scaled\.(jpg|jpeg|png|gif)$/i', '.$1', $file_path);
            if (file_exists($original)) {
                return $original;
            }
        }

        return $file_path;
    }

    /**
     * Resize an image based on settings
     */
    private function resize_image($image_id)
    {
        $file_path = get_attached_file($image_id);
        $original_path = $this->get_original_image_path($file_path);

        if (!file_exists($original_path)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: File not found"
            );
        }

        $mime = mime_content_type($original_path);
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/gif'), true)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: Unsupported format"
            );
        }

        $original_size = filesize($original_path);
        $resize_threshold = get_option('simpli_resize_bytes', 1 * 1024 * 1024);
        $min_width = get_option('simpli_min_width', 1200);
        $min_height = get_option('simpli_min_height', 1200);

        // Check if resize is needed
        if ($original_size <= $resize_threshold) {
            return array(
                'success' => true,
                'message' => "Image #{$image_id}: Already under threshold (" . size_format($original_size) . ")"
            );
        }

        $editor = wp_get_image_editor($original_path);
        if (is_wp_error($editor)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: " . $editor->get_error_message()
            );
        }

        $size = $editor->get_size();
        if (!$size || !isset($size['width'], $size['height'])) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: Could not get dimensions"
            );
        }

        $width = $size['width'];
        $height = $size['height'];

        // Calculate scale factor to get under file size threshold
        // Start with 90% and reduce iteratively
        $scale_factor = 0.9;
        $new_width = round($width * $scale_factor);
        $new_height = round($height * $scale_factor);

        // Make sure we don't go below minimum dimensions
        if ($new_width < $min_width || $new_height < $min_height) {
            // Set to minimum, maintaining aspect ratio
            $aspect_ratio = $width / $height;

            if ($width > $height) {
                // Landscape or square
                $new_width = max($min_width, $width);
                $new_height = round($new_width / $aspect_ratio);
            } else {
                // Portrait
                $new_height = max($min_height, $height);
                $new_width = round($new_height * $aspect_ratio);
            }
        }

        // Resize the image
        $editor->resize($new_width, $new_height, false);
        $editor->set_quality(82);

        $saved = $editor->save($original_path);

        if (is_wp_error($saved)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: " . $saved->get_error_message()
            );
        }

        // Update file size
        clearstatcache(true, $original_path);
        $new_size = filesize($original_path);

        // Regenerate thumbnails
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $original_path));

        return array(
            'success' => true,
            'message' => sprintf(
                "Image #%d: Resized from %s to %s (%dx%d → %dx%d)",
                $image_id,
                size_format($original_size),
                size_format($new_size),
                $width,
                $height,
                $new_width,
                $new_height
            )
        );
    }

    /**
     * Automatically resize images on upload
     */
    public function auto_resize_on_upload($file)
    {
        // Only affect images
        $mime = $file['type'] ?? '';
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/gif'), true)) {
            return $file;
        }

        $file_path = $file['file'];

        if (!file_exists($file_path)) {
            return $file;
        }

        $file_size = filesize($file_path);
        $resize_threshold = get_option('simpli_resize_bytes', 1 * 1024 * 1024);
        $min_width = get_option('simpli_min_width', 1200);
        $min_height = get_option('simpli_min_height', 1200);

        // Only resize if larger than threshold
        if ($file_size <= $resize_threshold) {
            return $file;
        }

        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return $file;
        }

        $size = $editor->get_size();
        if (!$size || !isset($size['width'], $size['height'])) {
            return $file;
        }

        $width = $size['width'];
        $height = $size['height'];

        // Calculate target dimensions
        $scale_factor = 0.9;
        $new_width = round($width * $scale_factor);
        $new_height = round($height * $scale_factor);

        // Respect minimum dimensions
        if ($new_width < $min_width || $new_height < $min_height) {
            $aspect_ratio = $width / $height;

            if ($width > $height) {
                $new_width = max($min_width, $width);
                $new_height = round($new_width / $aspect_ratio);
            } else {
                $new_height = max($min_height, $height);
                $new_width = round($new_height * $aspect_ratio);
            }
        }

        $editor->resize($new_width, $new_height, false);
        $editor->set_quality(82);

        $saved = $editor->save($file_path);

        if (!is_wp_error($saved)) {
            clearstatcache(true, $file_path);
            $file['size'] = filesize($file_path);
        }

        return $file;
    }

    /**
     * Clean up -scaled images and update database references
     */
    private function cleanup_scaled_images()
    {
        global $wpdb;

        // Get all image attachments
        $attachments = $wpdb->get_results("
        SELECT ID, meta_value as file_path
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND pm.meta_key = '_wp_attached_file'
    ");

        $cleaned = 0;
        $errors = 0;

        foreach ($attachments as $attachment) {
            $file_path = $attachment->file_path;

            // Check if this references a -scaled file
            if (preg_match('/-scaled\.(jpg|jpeg|png|gif|webp)$/i', $file_path)) {
                $upload_dir = wp_upload_dir();
                $full_scaled_path = $upload_dir['basedir'] . '/' . $file_path;

                // Get the original filename (without -scaled)
                $original_file_path = preg_replace('/-scaled\.(jpg|jpeg|png|gif|webp)$/i', '.$1', $file_path);
                $full_original_path = $upload_dir['basedir'] . '/' . $original_file_path;

                // Check if original exists
                if (file_exists($full_original_path)) {
                    // Delete the -scaled file
                    if (file_exists($full_scaled_path)) {
                        @unlink($full_scaled_path);
                    }

                    // Update the database to point to the original
                    update_post_meta($attachment->ID, '_wp_attached_file', $original_file_path);

                    // Update attachment metadata
                    $metadata = wp_get_attachment_metadata($attachment->ID);
                    if ($metadata && isset($metadata['file'])) {
                        $metadata['file'] = $original_file_path;

                        // Update width/height from original image
                        $image_size = @getimagesize($full_original_path);
                        if ($image_size) {
                            $metadata['width'] = $image_size[0];
                            $metadata['height'] = $image_size[1];
                        }

                        wp_update_attachment_metadata($attachment->ID, $metadata);
                    }

                    $cleaned++;
                } else {
                    $errors++;
                }
            }
        }

        // Store results to show in admin notice
        set_transient('simpli_scaled_cleanup_result', array(
            'cleaned' => $cleaned,
            'errors' => $errors
        ), 60);
    }

    /**
     * Get list of all images for regeneration
     */
    public function ajax_get_all_images()
    {
        check_ajax_referer('simpli_regenerate_thumbnails', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;

        // Get all image attachments
        $attachments = $wpdb->get_col("
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'attachment' 
        AND post_mime_type LIKE 'image/%'
        ORDER BY ID ASC
    ");

        wp_send_json_success(array(
            'images' => $attachments,
            'count' => count($attachments)
        ));
    }

    /**
     * Regenerate thumbnails for a single image via AJAX
     */
    public function ajax_regenerate_single_thumbnail()
    {
        check_ajax_referer('simpli_regenerate_thumbnails', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $image_id = intval($_POST['image_id']);

        if (!$image_id) {
            wp_send_json_error(array('message' => 'Invalid image ID'));
        }

        $result = $this->regenerate_thumbnail($image_id);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Regenerate thumbnails for a single image
     */
    private function regenerate_thumbnail($image_id)
    {
        $file_path = get_attached_file($image_id);

        if (!$file_path || !file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: File not found"
            );
        }

        $mime = get_post_mime_type($image_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: Not an image"
            );
        }

        // Include required files
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Delete old thumbnails
        $metadata = wp_get_attachment_metadata($image_id);
        if ($metadata && isset($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $file_dir = dirname($file_path);

            foreach ($metadata['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $thumbnail_path = $file_dir . '/' . $size_data['file'];
                    if (file_exists($thumbnail_path)) {
                        @unlink($thumbnail_path);
                    }
                }
            }
        }

        // Regenerate thumbnails
        $new_metadata = wp_generate_attachment_metadata($image_id, $file_path);

        if (is_wp_error($new_metadata)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: " . $new_metadata->get_error_message()
            );
        }

        if (empty($new_metadata)) {
            return array(
                'success' => false,
                'message' => "Image #{$image_id}: Failed to generate metadata"
            );
        }

        // Update metadata
        wp_update_attachment_metadata($image_id, $new_metadata);

        // Count thumbnails generated
        $thumbnail_count = isset($new_metadata['sizes']) ? count($new_metadata['sizes']) : 0;

        return array(
            'success' => true,
            'message' => sprintf(
                "Image #%d: Regenerated %d thumbnail(s) - %s",
                $image_id,
                $thumbnail_count,
                basename($file_path)
            )
        );
    }

    /**
     * Disallow large image uploads
     */
    public function disallow_large_images($file)
    {
        // Check if it's an image type we care about
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowed_types)) {
            return $file;
        }

        // Get max upload size (already in bytes)
        $max_upload_bytes = get_option('simpli_max_upload_bytes', 6 * 1024 * 1024);

        // Check if file exceeds limit
        if ($file['size'] > $max_upload_bytes) {
            // Convert bytes to MB for display
            $max_mb = round($max_upload_bytes / (1024 * 1024), 1);
            $file['error'] = sprintf(
                __('Image is too large. Maximum allowed size is %s MB.', 'simpliweb'),
                $max_mb
            );
        }

        return $file;
    }
}
