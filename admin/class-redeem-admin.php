<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Redeem_Admin
{


    public function __construct()
    {

        add_action(
            'admin_menu',
            array(
                $this,
                'menu'
            )
        );

    }


    public function menu()
    {

        add_menu_page(

            'Redeem Menu',

            'Redeem Menu',

            'manage_redeem_items',

            'rl-redeem-menu',

            array(
                $this,
                'page'
            ),

            'dashicons-awards',

            30

        );

    }


    /**
     * Locations the current user is allowed to manage reward menus
     * for — every location for admins, only their own brand's
     * locations for a brand manager.
     */
    private function accessible_locations()
    {

        if (current_user_can('administrator')) {

            $brands = RL_Brands::get_all();
            $all    = array();

            foreach ($brands as $brand) {
                foreach (RL_Locations::get_all_by_brand($brand->id) as $loc) {
                    $loc->brand_name = $brand->name;
                    $all[]           = $loc;
                }
            }

            return $all;
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        if (!$brand) {
            return array();
        }

        $locations = RL_Locations::get_all_by_brand($brand->id);

        foreach ($locations as $loc) {
            $loc->brand_name = $brand->name;
        }

        return $locations;
    }


    public function page()
    {

        if (
            !current_user_can(
                'manage_redeem_items'
            )
        ) {

            wp_die(
                'You do not have permission.'
            );

        }

        wp_enqueue_media();

        $locations = $this->accessible_locations();

        if (empty($locations)) {
            echo '<div class="wrap"><h1>Redeem Menu</h1><p>No locations yet. <a href="' . esc_url(admin_url('admin.php?page=rl-brand-manage')) . '">Add one first</a>.</p></div>';
            return;
        }

        // wpdb returns numeric columns as strings — cast to int so the
        // strict in_array() checks below actually match.
        $location_ids = array_map('intval', wp_list_pluck($locations, 'id'));

        $location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : intval($location_ids[0]);

        if (!in_array($location_id, $location_ids)) {
            $location_id = intval($location_ids[0]);
        }

        // The brand that owns the currently selected location —
        // categories are defined per brand, not per location.
        $current_brand_id = 0;

        foreach ($locations as $loc) {
            if (intval($loc->id) === $location_id) {
                $current_brand_id = intval($loc->brand_id);
                break;
            }
        }

        // CSRF guard shared by every state-changing action below —
        // dies via wp_die() on failure, same as WordPress's own
        // admin screens.
        $has_pending_action = isset($_POST['rl_add_category'])
            || isset($_GET['delete_category'])
            || isset($_GET['delete'])
            || isset($_POST['rl_edit_item'])
            || isset($_POST['rl_add_item']);

        if ($has_pending_action) {
            if (!isset($_REQUEST['rl_redeem_nonce']) || !wp_verify_nonce($_REQUEST['rl_redeem_nonce'], 'rl_redeem_admin_action')) {
                wp_die('Security check failed — please go back and try again.');
            }
        }


        /*
        =========================
        ADD CATEGORY
        =========================
        */

        if (isset($_POST['rl_add_category'])) {

            $target_brand = intval($_POST['category_brand_id'] ?? $current_brand_id);

            if ($target_brand === $current_brand_id && $target_brand) {

                RL_Redeem_Categories::create($target_brand, $_POST['category_name'] ?? '');

                echo '<div class="notice notice-success"><p>Category added.</p></div>';
            }
        }


        /*
        =========================
        DELETE CATEGORY
        =========================
        */

        if (isset($_GET['delete_category'])) {

            $category = RL_Redeem_Categories::get(intval($_GET['delete_category']));

            if ($category && intval($category->brand_id) === $current_brand_id) {

                RL_Redeem_Categories::delete($category->id);

                echo '<div class="notice notice-success"><p>Category deleted. Its items are now uncategorized.</p></div>';
            }
        }


        /*
        =========================
        DELETE ITEM
        =========================
        */

        if (isset($_GET['delete'])) {

            $item = RL_Redeem_Items::get(intval($_GET['delete']));

            if ($item && in_array(intval($item->location_id), $location_ids, true)) {

                RL_Redeem_Items::delete(intval($_GET['delete']));

                echo '<div class="notice notice-success"><p>Item deleted.</p></div>';
            }
        }


        /*
        =========================
        EDIT ITEM
        =========================
        */

        if (isset($_POST['rl_edit_item'])) {

            $item = RL_Redeem_Items::get(intval($_POST['item_id']));

            if ($item && in_array(intval($item->location_id), $location_ids, true)) {

                RL_Redeem_Items::update(
                    intval($_POST['item_id']),
                    array(
                        'title'       => sanitize_text_field($_POST['title']),
                        'description' => sanitize_textarea_field($_POST['description']),
                        'points_cost' => intval($_POST['points_cost']),
                        'image'       => esc_url_raw($_POST['image']),
                        'category_id' => intval($_POST['category_id'] ?? 0),
                    )
                );

                echo '<div class="notice notice-success"><p>Item updated.</p></div>';
            }
        }


        /*
        =========================
        CREATE ITEM
        =========================
        */

        if (isset($_POST['rl_add_item'])) {

            $target_location = intval($_POST['location_id'] ?? $location_id);

            if (in_array($target_location, $location_ids, true)) {

                RL_Redeem_Items::create(
                    $target_location,
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['points_cost'],
                    $_POST['image'],
                    intval($_POST['category_id'] ?? 0)
                );

                echo '<div class="notice notice-success"><p>Item created.</p></div>';
            }
        }


        $edit_item = null;

        if (isset($_GET['edit'])) {

            $candidate = RL_Redeem_Items::get(intval($_GET['edit']));

            if ($candidate && in_array(intval($candidate->location_id), $location_ids, true)) {
                $edit_item = $candidate;
            }
        }


        $items      = RL_Redeem_Items::get_all($location_id);
        $categories = RL_Redeem_Categories::get_all_by_brand($current_brand_id);

        ?>

        <div class="wrap">

            <h1>Redeem Menu</h1>

            <?php if (count($locations) > 1): ?>
                <form method="get" style="margin-bottom:20px;">
                    <input type="hidden" name="page" value="rl-redeem-menu">
                    <label>Location:
                        <select name="location_id" onchange="this.form.submit()">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo intval($loc->id); ?>" <?php selected($location_id, $loc->id); ?>>
                                    <?php echo esc_html($loc->brand_name . ' — ' . $loc->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            <?php endif; ?>

            <h2>Menu Categories</h2>
            <p class="description">Categories belong to the whole brand, so the same set is available across every one of its locations.</p>

            <?php if ($categories): ?>
                <ul style="margin-bottom:12px;">
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <?php echo esc_html($cat->name); ?>
                            &nbsp;—&nbsp;
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-redeem-menu&location_id=' . $location_id . '&delete_category=' . $cat->id), 'rl_redeem_admin_action', 'rl_redeem_nonce')); ?>" onclick="return confirm('Delete this category? Its items will become uncategorized.');">Delete</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No categories yet.</p>
            <?php endif; ?>

            <form method="post" style="margin-bottom:24px;">
                <?php wp_nonce_field('rl_redeem_admin_action', 'rl_redeem_nonce'); ?>
                <input type="hidden" name="category_brand_id" value="<?php echo intval($current_brand_id); ?>">
                <input type="text" name="category_name" placeholder="New category name" required>
                <button type="submit" name="rl_add_category" class="button">Add Category</button>
            </form>

            <?php if ($edit_item): ?>

                <h2>Edit Item</h2>

                <form method="post">

                    <?php wp_nonce_field('rl_redeem_admin_action', 'rl_redeem_nonce'); ?>
                    <input type="hidden" name="item_id" value="<?php echo intval($edit_item->id); ?>">

                    <table class="form-table">

                        <tr>
                            <th>Name</th>
                            <td><input type="text" name="title" value="<?php echo esc_attr($edit_item->title); ?>" required></td>
                        </tr>

                        <tr>
                            <th>Description</th>
                            <td><textarea name="description"><?php echo esc_textarea($edit_item->description); ?></textarea></td>
                        </tr>

                        <tr>
                            <th>Image</th>
                            <td>
                                <input type="text" id="rl_edit_image" name="image" value="<?php echo esc_attr($edit_item->image); ?>" style="width:70%;">
                                <button type="button" class="button rl-upload-image">Select Image</button>
                                <button type="button" class="button rl-remove-image">Remove</button>
                                <br><br>
                                <img id="rl_edit_preview" src="<?php echo esc_url($edit_item->image); ?>" style="max-width:150px;<?php echo empty($edit_item->image) ? 'display:none;' : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th>Points Cost</th>
                            <td><input type="number" name="points_cost" value="<?php echo intval($edit_item->points_cost); ?>" required></td>
                        </tr>

                        <tr>
                            <th>Category</th>
                            <td>
                                <select name="category_id">
                                    <option value="">— No category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo intval($cat->id); ?>" <?php selected(intval($edit_item->category_id ?? 0), $cat->id); ?>>
                                            <?php echo esc_html($cat->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                    </table>

                    <button class="button button-primary" name="rl_edit_item">Update Item</button>

                </form>

                <hr>

            <?php endif; ?>

            <h2>Add New Item</h2>

            <form method="post">

                <?php wp_nonce_field('rl_redeem_admin_action', 'rl_redeem_nonce'); ?>
                <input type="hidden" name="location_id" value="<?php echo intval($location_id); ?>">

                <table class="form-table">

                    <tr>
                        <th>Name</th>
                        <td><input type="text" name="title" required></td>
                    </tr>

                    <tr>
                        <th>Description</th>
                        <td><textarea name="description"></textarea></td>
                    </tr>

                    <tr>
                        <th>Image</th>
                        <td>
                            <input type="text" id="rl_add_image" name="image" style="width:70%;">
                            <button type="button" class="button rl-upload-image">Select Image</button>
                            <button type="button" class="button rl-remove-image">Remove</button>
                            <br><br>
                            <img id="rl_add_preview" style="max-width:150px;display:none;">
                        </td>
                    </tr>

                    <tr>
                        <th>Points Cost</th>
                        <td><input type="number" name="points_cost" required></td>
                    </tr>

                    <tr>
                        <th>Category</th>
                        <td>
                            <select name="category_id">
                                <option value="">— No category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo intval($cat->id); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                </table>

                <button class="button button-primary" name="rl_add_item">Add Item</button>

            </form>

            <hr>

            <h2>Existing Items</h2>

            <table class="widefat fixed">

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($items as $item): ?>

                    <?php
                    $item_category = !empty($item->category_id) ? RL_Redeem_Categories::get($item->category_id) : null;
                    ?>

                    <tr>

                        <td>
                            <?php if (!empty($item->image)): ?>
                                <img src="<?php echo esc_url($item->image); ?>" width="60">
                            <?php endif; ?>
                        </td>

                        <td><?php echo esc_html($item->title); ?></td>

                        <td><?php echo $item_category ? esc_html($item_category->name) : '—'; ?></td>

                        <td><?php echo intval($item->points_cost); ?> points</td>

                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=rl-redeem-menu&location_id=' . $location_id . '&edit=' . $item->id)); ?>">Edit</a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-redeem-menu&location_id=' . $location_id . '&delete=' . $item->id), 'rl_redeem_admin_action', 'rl_redeem_nonce')); ?>" onclick="return confirm('Delete this item?');">Delete</a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <script>
        jQuery(document).ready(function($){

            $('.rl-upload-image').click(function(e){
                e.preventDefault();

                let button = $(this);
                let input = button.siblings('input');
                let preview = button.parent().find('img');

                let frame = wp.media({
                    title: 'Select Image',
                    button: { text: 'Use Image' },
                    multiple: false
                });

                frame.on('select', function(){
                    let attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                    preview.attr('src', attachment.url);
                    preview.show();
                });

                frame.open();
            });

            $('.rl-remove-image').click(function(){
                let button = $(this);
                let input = button.siblings('input');
                let preview = button.parent().find('img');

                input.val('');
                preview.hide();
            });

        });
        </script>

        <?php

    }

}
