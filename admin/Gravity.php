<?php

/**
 * Gravity Forms page for WP Optimiser by SimpliWeb
 */

if (!defined("ABSPATH")) {
	exit();
}

class SimpliOptimiser_Gravity
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
		add_action("wp_ajax_so_delete_form", [$this, "ajax_delete_form"]);
		add_action("wp_ajax_so_trash_entries", [$this, "ajax_trash_entries"]);
	}

	/**
	 * Render the page
	 */
	public static function render_page()
	{
		$instance = self::get_instance();
		$instance->gravity_page();
	}

	/**
	 * Gravity Forms page
	 */
	public function gravity_page()
	{
		?>
        <div class="wrap">
            <h1><?php _e("Gravity Forms", "simpliweb"); ?></h1>

            <div class="card full-width">
                <h2><?php _e("Last Known Entries", "simpliweb"); ?></h2>

                <?php
                // Get form data
                $entries = $this->form_entries() ?? [];
                if (is_array($entries) && count($entries) > 0): ?>
               	<?php
                // Sorting logic
                $sort = $_GET["sort"] ?? "id";
                $order = strtolower($_GET["order"] ?? "asc");
                $order = $order === "desc" ? "desc" : "asc";

                // Toggle function for header links
                function so_sort_link(
                	$field,
                	$label,
                	$current_sort,
                	$current_order,
                ) {
                	$new_order =
                		$current_sort === $field && $current_order === "asc"
                			? "desc"
                			: "asc";
                	$url = add_query_arg([
                		"page" => "simpli-optimiser-gravity",
                		"sort" => $field,
                		"order" => $new_order,
                	]);
                	return '<a href="' .
                		esc_url($url) .
                		'" style="display: flex;align-items:center;gap:2px;">' .
                		$label .
                		'<span style="font-size:100%;height:auto;"class="dashicons dashicons-sort"></span></a>';
                }

                // Apply sorting
                usort($entries, function ($a, $b) use ($sort, $order) {
                	switch ($sort) {
                		case "title":
                			$cmp = strcasecmp($a["title"], $b["title"]);
                			break;

                		case "total":
                			$cmp = $a["total_entries"] <=> $b["total_entries"];
                			break;

                		case "views":
                			$cmp = $a["total_views"] <=> $b["total_views"];
                			break;

                		case "last_view":
                			$timeA = $a["last_view"]
                				? strtotime($a["last_view"])
                				: 0;
                			$timeB = $b["last_view"]
                				? strtotime($b["last_view"])
                				: 0;
                			$cmp = $timeA <=> $timeB;
                			break;

                		case "last":
                			$timeA = $a["last_entry"]
                				? strtotime($a["last_entry"])
                				: 0;
                			$timeB = $b["last_entry"]
                				? strtotime($b["last_entry"])
                				: 0;
                			$cmp = $timeA <=> $timeB;
                			break;

                		case "id":
                		default:
                			$cmp = $a["id"] <=> $b["id"];
                			break;
                	}

                	return $order === "asc" ? $cmp : -$cmp;
                });
                ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:60px;">
                                <?= so_sort_link(
                                	"id",
                                	__("ID", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th>
                                <?= so_sort_link(
                                	"title",
                                	__("Form Title", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th>
                                <?= so_sort_link(
                                	"views",
                                	__("Total Views", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th>
                                <?= so_sort_link(
                                	"last_view",
                                	__("Last View", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th>
                                <?= so_sort_link(
                                	"total",
                                	__("Total Entries", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th>
                                <?= so_sort_link(
                                	"last",
                                	__("Last Entry", "simpliweb"),
                                	$sort,
                                	$order,
                                ) ?>
                            </th>
                            <th><?php _e("Actions", "simpliweb"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?= esc_html($entry["id"]) ?></td>
                                <td><?= esc_html($entry["title"]) ?></td>
                                <td><?= esc_html($entry["total_views"]) ?></td>
                                <td><?= $entry["last_view"]
                                	? esc_html($entry["formatted_last_view"])
                                	: "" ?></td>
                                <td><?= esc_html(
                                	$entry["total_entries_formatted"],
                                ) ?></td>
                                <td><?= $entry["last_entry"]
                                	? esc_html($entry["formatted_last_entry"])
                                	: "" ?></td>
                                <td>
                                    <a href="<?= esc_url(
                                    	admin_url(
                                    		"admin.php?page=gf_entries&id=" .
                                    			$entry["id"],
                                    	),
                                    ) ?>" class="button button-primary"><?= __(
	"View Entries",
	"simpliweb",
) ?></a>

									<button class="button button-secondary so-trash-entries" data-form-id="<?= esc_attr(
                                    	$entry["id"],
                                    ) ?>">
                                        <?= __("Trash Entries", "simpliweb") ?>
                                    </button>
                                    <button class="button button-secondary so-delete-form" data-form-id="<?= esc_attr(
                                    	$entry["id"],
                                    ) ?>">
                                        <?= __("Delete", "simpliweb") ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script type="text/javascript">
                jQuery(document).ready(function($){
                    // Trash Entries Handler
                    $('.so-trash-entries').on('click', function(e){
                        e.preventDefault();

                        var formId = $(this).data('form-id');
                        var $button = $(this);

                        if(!confirm('Are you sure you want to trash all entries for this form? This can be undone from the Gravity Forms trash.')) return;

                        $button.prop('disabled', true).text('Trashing...');

                        $.post(ajaxurl, {
                            action: 'so_trash_entries',
                            form_id: formId,
                            nonce: '<?php echo wp_create_nonce(
                            	"so_trash_entries_nonce",
                            ); ?>'
                        }, function(response){
                            if(response.success){
                                alert('All entries have been trashed successfully. Count: ' + response.data.count);
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                                $button.prop('disabled', false).text('<?= __(
                                	"Trash Entries",
                                	"simpliweb",
                                ) ?>');
                            }
                        });
                    });

                    // Delete Form Handler
                    $('.so-delete-form').on('click', function(e){
                        e.preventDefault();

                        var formId = $(this).data('form-id');

                        if(!confirm('This cannot be undone. Make a backup first. Continue?')) return;

                        $.post(ajaxurl, {
                            action: 'so_delete_form',
                            form_id: formId,
                            nonce: '<?php echo wp_create_nonce(
                            	"so_delete_form_nonce",
                            ); ?>'
                        }, function(response){
                            if(response.success){
                                alert('Form deleted successfully.');
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        });
                    });
                });
                </script>


                <?php endif;?>
            </div>
        </div>
<?php
	}

	/**
	 * Get last entry date for each Gravity Form
	 */
	private function form_entries()
	{
		// Safety check (in case Gravity Forms isn't active)
		if (!class_exists("GFAPI")) {
			return [];
		}

		$forms = GFAPI::get_forms();
		$results = [];

		foreach ($forms as $form) {
			// Get the last (most recent) entry
			$entries = GFAPI::get_entries($form["id"], [
				"orderby" => "date_created",
				"order" => "DESC",
				"page_size" => 1,
			]);

			$views = $this->view_count($form["id"]);
			$last_view = $this->last_view($form["id"]);

			$last_entry = !empty($entries) ? $entries[0]["date_created"] : null;
			$results[] = [
				"id" => $form["id"],
				"title" => $form["title"],
				"last_entry" => $last_entry,
				"total_views" => $views,
				"last_view" => $last_view,
				"formatted_last_view" => $last_view
					? date("F j, Y", strtotime($last_view))
					: "",
				"formatted_last_entry" => $last_entry
					? date("F j, Y", strtotime($last_entry))
					: "",
				"total_entries" => GFAPI::count_entries($form["id"]),
				"total_entries_formatted" => number_format(
					GFAPI::count_entries($form["id"]),
				),
			];
		}

		return $results;
	}

	private function view_count($form_id = null)
	{
		if (!$form_id) {
			return 0;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . "gf_form_view";

		$total_views = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(count) FROM $table_name WHERE form_id = %d",
				$form_id,
			),
		);

		return $total_views ? (int) $total_views : 0;
	}

	private function last_view($form_id = null)
	{
		if (!$form_id) {
			return null;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . "gf_form_view";

		$last_view = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(date_created) FROM $table_name WHERE form_id = %d",
				$form_id,
			),
		);

		return $last_view ?: null;
	}

	/**
	 * AJAX handler to trash all entries for a form
	 */
	public function ajax_trash_entries()
	{
		check_ajax_referer("so_trash_entries_nonce", "nonce");

		if (!current_user_can("manage_options")) {
			wp_send_json_error("Unauthorized");
		}

		$form_id = absint($_POST["form_id"] ?? 0);

		if (!$form_id || !class_exists("GFAPI")) {
			wp_send_json_error("Invalid form ID or Gravity Forms not active.");
		}

		// Get all entries for this form
		$entries = GFAPI::get_entries($form_id);

		if (is_wp_error($entries)) {
			wp_send_json_error($entries->get_error_message());
		}

		$count = 0;

		// Mark each entry as trash
		foreach ($entries as $entry) {
			$result = GFAPI::update_entry_property($entry["id"], "status", "trash");
			
			if (!is_wp_error($result)) {
				$count++;
			}
		}

		wp_send_json_success(["count" => $count]);
	}

	/**
	 * AJAX handler to delete a form
	 */
	public function ajax_delete_form()
	{
		check_ajax_referer("so_delete_form_nonce", "nonce");

		if (!current_user_can("manage_options")) {
			wp_send_json_error("Unauthorized");
		}

		$form_id = absint($_POST["form_id"] ?? 0);

		if (!$form_id || !class_exists("GFAPI")) {
			wp_send_json_error("Invalid form ID or Gravity Forms not active.");
		}

		$result = GFAPI::delete_form($form_id);

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success();
	}
}