<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end replacements for the brand-manager-facing wp-admin screens
 * (Brand & Locations, Reward Menus, Lucky Draws) — styled like the
 * rest of the customer-facing app instead of wp-admin. The old
 * wp-admin screens (admin/class-brands-admin.php etc.) are left
 * working as-is; these are additive, not a replacement of that code.
 *
 * Every form here gets a nonce (the wp-admin originals have none —
 * a known gap this rebuild closes rather than carries forward), and
 * every brand-scoped action re-validates ownership against the
 * logged-in manager's own brand rather than trusting a posted id.
 */
class RL_Manager_Pages
{

    /** @var RL_Shortcodes shared instance, for render_page_header()/render_manager_bottom_nav() */
    private $shortcodes;

    public function __construct(RL_Shortcodes $shortcodes)
    {
        $this->shortcodes = $shortcodes;

        add_shortcode('rl_manage_brand', array($this, 'manage_brand_page'));
        add_shortcode('rl_manage_menu', array($this, 'manage_menu_page'));
        add_shortcode('rl_manage_draws', array($this, 'manage_draws_page'));
    }

    /**
     * Every page on this class needs the same guard: logged in, and
     * either a brand manager or an administrator. Returns the current
     * WP_User on success; redirects and exits otherwise, matching the
     * pattern used by the rest of the front end's standalone pages.
     */
    private function require_brand_manager()
    {
        if (!is_user_logged_in()) {
            wp_redirect(site_url('/login'));
            exit;
        }

        $user = wp_get_current_user();

        if (!in_array('brand_manager', $user->roles, true) && !in_array('administrator', $user->roles, true)) {
            wp_redirect(site_url('/'));
            exit;
        }

        return $user;
    }

    /**
     * Ported verbatim from admin/class-brands-admin.php and
     * admin/class-draws-admin.php (identical logic in both) — true for
     * an administrator, otherwise only true if $brand_id is the
     * logged-in manager's own brand.
     */
    private function can_manage_brand($brand_id, $is_admin)
    {
        if ($is_admin) {
            return true;
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        return $brand && intval($brand->id) === intval($brand_id);
    }


    /**
     * Shared page chrome: header, fixed back button (to the manager's
     * QR-scanner dashboard), page title, and the manager hamburger
     * nav — the same shell every standalone front-end page in this
     * plugin already uses (see restaurant_page()/my_entries_page()).
     */
    private function render_page_shell($title, $subtitle, $body_html)
    {
        ob_start();
        ?>
        <div class="rl-dashboard rl-restaurant-page-wrap">

            <?php echo $this->shortcodes->render_page_header(); ?>

            <?php /* There's no standalone "restaurant-dashboard" page — home() renders the
                     manager's QR-scanner dashboard directly at the site root for this role. */ ?>
            <a href="<?php echo esc_url(site_url('/')); ?>" class="rl-page-back-btn" aria-label="Back to dashboard">&larr;</a>

            <div class="rl-explore-page">

                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3><?php echo esc_html($title); ?></h3>
                        <?php if ($subtitle): ?>
                            <p><?php echo esc_html($subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php echo $body_html; ?>

            </div>

        </div>

        <?php echo $this->shortcodes->render_manager_bottom_nav(true); ?>

        <?php
        return ob_get_clean();
    }

    public function manage_brand_page()
    {
        $this->require_brand_manager();

        if (!current_user_can('manage_brand')) {
            return '<p>You do not have permission.</p>';
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        if (!$brand) {
            return $this->render_page_shell('Brand & Locations', '', '<p>No brand is assigned to you yet. Contact an administrator.</p>');
        }

        $brand_id = intval($brand->id);
        $notice   = '';

        // Add manager — always creates a brand-new account, same as
        // the wp-admin original (no "assign an existing account"
        // option; there's no reliable way to know a typed email
        // belongs to the intended person).
        if (isset($_POST['rl_add_manager']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_brand_manager_add')) {

            $created = RL_Users::create_staff_user(
                $_POST['new_manager_name'] ?? '',
                $_POST['new_manager_email'] ?? '',
                $_POST['new_manager_password'] ?? '',
                'brand_manager'
            );

            if (!is_wp_error($created)) {
                RL_Brands::assign_manager($brand_id, $created);
                $notice = 'Manager added.';
            } else {
                $notice = $created->get_error_message();
            }
        }

        // Remove manager
        if (isset($_GET['remove_manager']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_brand_manager_remove_' . intval($_GET['remove_manager']))) {
            RL_Brands::remove_manager(intval($_GET['remove_manager']));
            $notice = 'Manager removed.';
        }

        // Update brand settings (chain view)
        if (isset($_POST['rl_update_brand']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_brand_update')) {

            // No 'logo' key here on purpose — the cover photo field
            // was removed from this front-end form (admin-only, set
            // from wp-admin's Brands screen instead), and RL_Brands::
            // update() only touches columns actually present in this
            // array, so omitting it leaves the existing photo alone
            // instead of blanking it out on every unrelated save.
            RL_Brands::update($brand_id, array(
                'name'         => $_POST['name'] ?? '',
                'description'  => $_POST['description'] ?? '',
                'pool_points'  => !empty($_POST['pool_points']),
                'pool_entries' => !empty($_POST['pool_entries']),
            ));

            RL_Brand_Hours::save_for_brand($brand_id, $_POST['hours'] ?? array());

            $notice = 'Brand settings updated.';
        }

        // Update single-restaurant details (name/description/logo +
        // its one location's address/coords, together)
        if (isset($_POST['rl_update_single_restaurant']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_brand_update_single')) {

            $name = sanitize_text_field($_POST['name'] ?? '');

            // No 'logo' key here either — see the matching comment on
            // the rl_update_brand handler above.
            RL_Brands::update($brand_id, array(
                'name'        => $name,
                'description' => $_POST['description'] ?? '',
            ));

            RL_Brand_Hours::save_for_brand($brand_id, $_POST['hours'] ?? array());

            $lat = $_POST['lat'] ?? '';
            $lng = $_POST['lng'] ?? '';

            if (($lat === '' || $lng === '') && !empty($_POST['address'])) {
                $geo = RL_Locations::geocode_address($_POST['address']);
                if ($geo) {
                    $lat = $geo['lat'];
                    $lng = $geo['lng'];
                }
            }

            $existing_locations = RL_Locations::get_all_by_brand($brand_id);

            $location_data = array(
                'name'    => $name,
                'address' => $_POST['address'] ?? '',
                'lat'     => $lat,
                'lng'     => $lng,
            );

            if ($existing_locations) {
                RL_Locations::update($existing_locations[0]->id, $location_data);
            } else {
                $location_data['brand_id'] = $brand_id;
                RL_Locations::create($location_data);
            }

            $notice = 'Restaurant details updated.';
        }

        // Add a location (chain growth)
        if (isset($_POST['rl_add_location']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_brand_location_add')) {

            $lat = $_POST['lat'] ?? '';
            $lng = $_POST['lng'] ?? '';

            if (($lat === '' || $lng === '') && !empty($_POST['address'])) {
                $geo = RL_Locations::geocode_address($_POST['address']);
                if ($geo) {
                    $lat = $geo['lat'];
                    $lng = $geo['lng'];
                }
            }

            $created = RL_Locations::create(array(
                'brand_id' => $brand_id,
                'name'     => $_POST['location_name'] ?? '',
                'address'  => $_POST['address'] ?? '',
                'lat'      => $lat,
                'lng'      => $lng,
            ));

            $notice = $created ? 'Location added.' : 'Could not add location.';
        }

        // Delete a location — re-validated against this manager's own
        // brand (a location id belonging to another brand won't match).
        if (isset($_GET['delete_location']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_brand_location_delete_' . intval($_GET['delete_location']))) {

            $location = RL_Locations::get(intval($_GET['delete_location']));

            if ($location && intval($location->brand_id) === $brand_id) {
                RL_Locations::delete($location->id);
                $notice = 'Location deleted.';
            }
        }

        // Re-fetch after any of the above so the render reflects the
        // current state, not what it was at the top of the request.
        $brand     = RL_Brands::get($brand_id);
        $locations = RL_Locations::get_all_by_brand($brand_id);
        $is_chain  = count($locations) > 1;

        $body  = $is_chain
            ? $this->render_manage_brand_chain_body($brand, $locations, $notice)
            : $this->render_manage_brand_single_body($brand, $locations, $notice);

        return $this->render_page_shell('Brand & Locations', 'Your restaurant\'s details, hours, locations, and managers.', $body);
    }

    private function render_manage_brand_single_body($brand, $locations, $notice)
    {
        $location = !empty($locations) ? $locations[0] : null;

        ob_start();
        ?>

        <?php if ($notice): ?>
            <p class="rl-alert rl-alert-success"><?php echo esc_html($notice); ?></p>
        <?php endif; ?>

        <h4 class="rl-manage-subheading">Restaurant Details</h4>

        <form method="post" class="rl-manage-form">
            <?php wp_nonce_field('rl_manage_brand_update_single', 'rl_manage_nonce'); ?>

            <div class="rl-input-group">
                <label class="rl-form-label">Name</label>
                <input type="text" name="name" value="<?php echo esc_attr($brand->name); ?>" required>
            </div>

            <div class="rl-input-group">
                <label class="rl-form-label">Description</label>
                <textarea name="description"><?php echo esc_textarea($brand->description); ?></textarea>
            </div>

            <div class="rl-input-group">
                <label class="rl-form-label">Address</label>
                <input type="text" name="address" value="<?php echo esc_attr($location->address ?? ''); ?>" placeholder="Street, city, country">
            </div>

            <div class="rl-input-group">
                <label class="rl-form-label">Lat / Lng (optional override)</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="lat" value="<?php echo esc_attr($location->lat ?? ''); ?>" placeholder="Latitude">
                    <input type="text" name="lng" value="<?php echo esc_attr($location->lng ?? ''); ?>" placeholder="Longitude">
                </div>
                <p class="rl-manage-hint">Leave blank to auto-locate from the address.</p>
            </div>

            <?php echo $this->render_manage_hours_grid($brand->id); ?>

            <button type="submit" name="rl_update_single_restaurant" class="rl-btn-primary">Save Details</button>
        </form>

        <?php echo $this->render_manage_managers_section($brand); ?>

        <details class="rl-details-toggle">
            <summary class="rl-details-summary">Growing into a chain? Add another location</summary>

            <p class="rl-manage-hint">Adding a second location turns this into a chain — you'll then be able to choose whether points and lucky-draw entries are shared across locations or kept separate.</p>

            <form method="post" class="rl-manage-form">
                <?php wp_nonce_field('rl_manage_brand_location_add', 'rl_manage_nonce'); ?>

                <div class="rl-input-group">
                    <label class="rl-form-label">Location name</label>
                    <input type="text" name="location_name" required placeholder="e.g. Downtown">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Address</label>
                    <input type="text" name="address" placeholder="Street, city, country">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Lat / Lng (optional override)</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="lat" placeholder="Latitude">
                        <input type="text" name="lng" placeholder="Longitude">
                    </div>
                </div>

                <button type="submit" name="rl_add_location" class="rl-btn-primary">Add Location</button>
            </form>
        </details>

        <?php
        return ob_get_clean();
    }

    private function render_manage_brand_chain_body($brand, $locations, $notice)
    {
        ob_start();
        ?>

        <?php if ($notice): ?>
            <p class="rl-alert rl-alert-success"><?php echo esc_html($notice); ?></p>
        <?php endif; ?>

        <h4 class="rl-manage-subheading">Brand Settings</h4>

        <form method="post" class="rl-manage-form">
            <?php wp_nonce_field('rl_manage_brand_update', 'rl_manage_nonce'); ?>

            <div class="rl-input-group">
                <label class="rl-form-label">Name</label>
                <input type="text" name="name" value="<?php echo esc_attr($brand->name); ?>" required>
            </div>

            <div class="rl-input-group">
                <label class="rl-form-label">Description</label>
                <textarea name="description"><?php echo esc_textarea($brand->description); ?></textarea>
            </div>

            <?php echo $this->render_manage_hours_grid($brand->id); ?>

            <div class="rl-input-group">
                <label class="rl-form-label">Chain settings</label>
                <label style="display:block;font-weight:400;margin-bottom:6px;">
                    <input type="checkbox" name="pool_points" value="1" <?php checked($brand->pool_points, 1); ?>>
                    Share one points balance across all locations
                </label>
                <label style="display:block;font-weight:400;">
                    <input type="checkbox" name="pool_entries" value="1" <?php checked($brand->pool_entries, 1); ?>>
                    Share lucky-draw entries across all locations
                </label>
            </div>

            <button type="submit" name="rl_update_brand" class="rl-btn-primary">Save Settings</button>
        </form>

        <?php echo $this->render_manage_managers_section($brand); ?>

        <h4 class="rl-manage-subheading">Locations</h4>

        <div class="rl-manage-list">
            <?php foreach ($locations as $loc): ?>
                <div class="rl-manage-item-row">
                    <div class="rl-manage-item-thumb">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <div class="rl-manage-item-info">
                        <strong><?php echo esc_html($loc->name); ?></strong>
                        <span class="rl-manage-item-meta"><?php echo esc_html($loc->address); ?></span>
                    </div>
                    <div class="rl-manage-item-actions">
                        <a
                            href="<?php echo esc_url(wp_nonce_url(site_url('/manage-brand?delete_location=' . $loc->id), 'rl_manage_brand_location_delete_' . $loc->id)); ?>"
                            class="rl-btn-danger-text"
                            onclick="return confirm('Delete this location?');"
                        >Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <details class="rl-details-toggle">
            <summary class="rl-details-summary">Add Location</summary>

            <form method="post" class="rl-manage-form">
                <?php wp_nonce_field('rl_manage_brand_location_add', 'rl_manage_nonce'); ?>

                <div class="rl-input-group">
                    <label class="rl-form-label">Name</label>
                    <input type="text" name="location_name" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Address</label>
                    <input type="text" name="address" placeholder="Street, city, country">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Lat / Lng (optional override)</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="lat" placeholder="Latitude">
                        <input type="text" name="lng" placeholder="Longitude">
                    </div>
                    <p class="rl-manage-hint">Leave blank to auto-locate from the address.</p>
                </div>

                <button type="submit" name="rl_add_location" class="rl-btn-primary">Add Location</button>
            </form>
        </details>

        <?php
        return ob_get_clean();
    }

    /**
     * Shared by both the single-restaurant and chain views — lists
     * current managers with a remove link, plus an "add another"
     * form. Always creates a brand-new user account.
     */
    private function render_manage_managers_section($brand)
    {
        $managers = RL_Brands::get_managers_for_brand($brand->id);

        ob_start();
        ?>

        <h4 class="rl-manage-subheading">Managers</h4>

        <?php if ($managers): ?>
            <div class="rl-manage-list">
                <?php foreach ($managers as $m): ?>
                    <div class="rl-manage-item-row">
                        <div class="rl-manage-item-thumb">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                        <div class="rl-manage-item-info">
                            <strong><?php echo esc_html($m->display_name); ?></strong>
                            <span class="rl-manage-item-meta"><?php echo esc_html($m->user_email); ?></span>
                        </div>
                        <div class="rl-manage-item-actions">
                            <a
                                href="<?php echo esc_url(wp_nonce_url(site_url('/manage-brand?remove_manager=' . $m->user_id), 'rl_manage_brand_manager_remove_' . $m->user_id)); ?>"
                                class="rl-btn-danger-text"
                                onclick="return confirm('Remove this manager?');"
                            >Remove</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="rl-manage-hint">No managers assigned yet.</p>
        <?php endif; ?>

        <details class="rl-details-toggle">
            <summary class="rl-details-summary">Add another manager</summary>

            <form method="post" class="rl-manage-form">
                <?php wp_nonce_field('rl_manage_brand_manager_add', 'rl_manage_nonce'); ?>
                <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">

                <div class="rl-input-group">
                    <label class="rl-form-label">Name</label>
                    <input type="text" name="new_manager_name">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Email</label>
                    <input type="email" name="new_manager_email">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Password</label>
                    <input type="password" name="new_manager_password" placeholder="Min 8 characters">
                </div>

                <button type="submit" name="rl_add_manager" class="rl-btn-primary">Add Manager</button>
            </form>
        </details>

        <?php
        return ob_get_clean();
    }

    /**
     * Mobile-first, stacked day-by-day opening-hours editor — same
     * data (RL_Brand_Hours) as the wp-admin version's wide table, new
     * markup for a phone-width page. Must be placed inside an open
     * <form>; outputs its own field wrapper, not a <tr>.
     */
    private function render_manage_hours_grid($brand_id)
    {
        $hours = RL_Brand_Hours::get_all_for_brand($brand_id);

        ob_start();
        ?>
        <div class="rl-input-group">
            <label class="rl-form-label">Opening hours</label>
            <div class="rl-hours-grid">
                <?php foreach (RL_Brand_Hours::DISPLAY_ORDER as $day): ?>
                    <?php $row = $hours[$day]; ?>
                    <div class="rl-hours-grid-row">
                        <div class="rl-hours-grid-day">
                            <span class="rl-hours-grid-day-label"><?php echo esc_html(RL_Brand_Hours::DAY_LABELS[$day]); ?></span>
                            <label class="rl-hours-grid-closed">
                                <input type="checkbox" name="hours[<?php echo intval($day); ?>][is_closed]" value="1" <?php checked(!empty($row->is_closed)); ?>>
                                Closed
                            </label>
                        </div>
                        <div class="rl-hours-grid-times">
                            <input type="time" name="hours[<?php echo intval($day); ?>][opening_time]" value="<?php echo esc_attr(substr($row->opening_time ?? '', 0, 5)); ?>">
                            <span>&mdash;</span>
                            <input type="time" name="hours[<?php echo intval($day); ?>][closing_time]" value="<?php echo esc_attr(substr($row->closing_time ?? '', 0, 5)); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="rl-manage-hint">Optional, and can differ by day — leave a day's times blank to not show hours for it, or check "Closed" for days you're not open.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * A manager's own brand's locations only — the manager-only branch
     * of admin/class-redeem-admin.php::accessible_locations(), with
     * the administrator-sees-everything branch dropped since this
     * page never needs a cross-brand view.
     */
    private function manager_locations()
    {
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

    public function manage_menu_page()
    {
        $this->require_brand_manager();

        if (!current_user_can('manage_redeem_items')) {
            return '<p>You do not have permission.</p>';
        }

        $locations = $this->manager_locations();

        if (empty($locations)) {
            $body = '<p>No locations yet — set up your restaurant on <a href="' . esc_url(site_url('/manage-brand')) . '">Brand &amp; Locations</a> first.</p>';
            return $this->render_page_shell('Reward Menus', '', $body);
        }

        $location_ids = array_map('intval', wp_list_pluck($locations, 'id'));

        $location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : intval($location_ids[0]);

        if (!in_array($location_id, $location_ids, true)) {
            $location_id = intval($location_ids[0]);
        }

        $current_brand_id = 0;

        foreach ($locations as $loc) {
            if (intval($loc->id) === $location_id) {
                $current_brand_id = intval($loc->brand_id);
                break;
            }
        }

        $notice = '';

        // Add category
        if (isset($_POST['rl_add_category']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_menu_category_add')) {

            $target_brand = intval($_POST['category_brand_id'] ?? $current_brand_id);

            if ($target_brand === $current_brand_id && $target_brand) {
                RL_Redeem_Categories::create($target_brand, $_POST['category_name'] ?? '');
                $notice = 'Category added.';
            }
        }

        // Delete category
        if (isset($_GET['delete_category']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_menu_category_delete_' . intval($_GET['delete_category']))) {

            $category = RL_Redeem_Categories::get(intval($_GET['delete_category']));

            if ($category && intval($category->brand_id) === $current_brand_id) {
                RL_Redeem_Categories::delete($category->id);
                $notice = 'Category deleted. Its items are now uncategorized.';
            }
        }

        // Delete item
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_menu_item_delete_' . intval($_GET['delete']))) {

            $item = RL_Redeem_Items::get(intval($_GET['delete']));

            if ($item && in_array(intval($item->location_id), $location_ids, true)) {
                RL_Redeem_Items::delete(intval($_GET['delete']));
                $notice = 'Item deleted.';
            }
        }

        // Edit item
        if (isset($_POST['rl_edit_item']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_menu_item_edit')) {

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
                $notice = 'Item updated.';
            }
        }

        // Create item
        if (isset($_POST['rl_add_item']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_menu_item_add')) {

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
                $notice = 'Item created.';
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

        $body = $this->render_manage_menu_body($locations, $location_id, $current_brand_id, $items, $categories, $edit_item, $notice);

        return $this->render_page_shell('Reward Menus', 'Categories and items customers can redeem their points for.', $body);
    }

    private function render_manage_menu_body($locations, $location_id, $current_brand_id, $items, $categories, $edit_item, $notice)
    {
        ob_start();
        ?>

        <?php if ($notice): ?>
            <p class="rl-alert rl-alert-success"><?php echo esc_html($notice); ?></p>
        <?php endif; ?>

        <?php if (count($locations) > 1): ?>
            <form method="get" class="rl-manage-location-picker">
                <input type="hidden" name="page" value="manage-menu">
                <div class="rl-input-group">
                    <label>Location</label>
                    <select name="location_id" onchange="this.form.submit()">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo intval($loc->id); ?>" <?php selected($location_id, $loc->id); ?>>
                                <?php echo esc_html($loc->brand_name . ' — ' . $loc->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>

        <h4 class="rl-manage-subheading">Categories</h4>
        <p class="rl-manage-hint">Categories belong to the whole brand — the same set is available at every location.</p>

        <?php if ($categories): ?>
            <div class="rl-manage-chip-list">
                <?php foreach ($categories as $cat): ?>
                    <span class="rl-manage-chip">
                        <?php echo esc_html($cat->name); ?>
                        <a
                            href="<?php echo esc_url(wp_nonce_url(site_url('/manage-menu?location_id=' . $location_id . '&delete_category=' . $cat->id), 'rl_manage_menu_category_delete_' . $cat->id)); ?>"
                            class="rl-manage-chip-remove"
                            onclick="return confirm('Delete this category? Its items will become uncategorized.');"
                            aria-label="Delete category"
                        >&times;</a>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="rl-manage-hint">No categories yet.</p>
        <?php endif; ?>

        <form method="post" class="rl-manage-inline-form">
            <?php wp_nonce_field('rl_manage_menu_category_add', 'rl_manage_nonce'); ?>
            <input type="hidden" name="category_brand_id" value="<?php echo intval($current_brand_id); ?>">
            <input type="text" name="category_name" placeholder="New category name" required>
            <button type="submit" name="rl_add_category" class="rl-btn-secondary">Add Category</button>
        </form>

        <?php if ($edit_item): ?>

            <h4 class="rl-manage-subheading">Edit Item</h4>

            <form method="post" class="rl-manage-form">
                <?php wp_nonce_field('rl_manage_menu_item_edit', 'rl_manage_nonce'); ?>
                <input type="hidden" name="item_id" value="<?php echo intval($edit_item->id); ?>">

                <div class="rl-input-group">
                    <label class="rl-form-label">Name</label>
                    <input type="text" name="title" value="<?php echo esc_attr($edit_item->title); ?>" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Description</label>
                    <textarea name="description"><?php echo esc_textarea($edit_item->description); ?></textarea>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Image URL</label>
                    <input type="text" name="image" value="<?php echo esc_attr($edit_item->image); ?>" placeholder="https://…">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Points Cost</label>
                    <input type="number" name="points_cost" value="<?php echo intval($edit_item->points_cost); ?>" min="1" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Category</label>
                    <select name="category_id">
                        <option value="">— No category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo intval($cat->id); ?>" <?php selected(intval($edit_item->category_id ?? 0), $cat->id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="rl_edit_item" class="rl-btn-primary">Update Item</button>
                <a href="<?php echo esc_url(site_url('/manage-menu?location_id=' . $location_id)); ?>" class="rl-btn-secondary">Cancel</a>
            </form>

        <?php endif; ?>

        <details class="rl-details-toggle">
            <summary class="rl-details-summary">Add New Item</summary>

            <form method="post" class="rl-manage-form">
                <?php wp_nonce_field('rl_manage_menu_item_add', 'rl_manage_nonce'); ?>
                <input type="hidden" name="location_id" value="<?php echo intval($location_id); ?>">

                <div class="rl-input-group">
                    <label class="rl-form-label">Name</label>
                    <input type="text" name="title" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Description</label>
                    <textarea name="description"></textarea>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Image URL</label>
                    <input type="text" name="image" placeholder="https://…">
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Points Cost</label>
                    <input type="number" name="points_cost" min="1" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Category</label>
                    <select name="category_id">
                        <option value="">— No category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo intval($cat->id); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="rl_add_item" class="rl-btn-primary">Add Item</button>
            </form>
        </details>

        <h4 class="rl-manage-subheading">Existing Items</h4>

        <?php if ($items): ?>
            <div class="rl-manage-list">
                <?php foreach ($items as $item): ?>
                    <?php $item_category = !empty($item->category_id) ? RL_Redeem_Categories::get($item->category_id) : null; ?>
                    <div class="rl-manage-item-row">
                        <div class="rl-manage-item-thumb">
                            <?php if (!empty($item->image)): ?>
                                <img src="<?php echo esc_url($item->image); ?>" alt="">
                            <?php else: ?>
                                <span class="dashicons dashicons-tickets-alt"></span>
                            <?php endif; ?>
                        </div>
                        <div class="rl-manage-item-info">
                            <strong><?php echo esc_html($item->title); ?></strong>
                            <span class="rl-manage-item-meta">
                                <?php echo $item_category ? esc_html($item_category->name) . ' · ' : ''; ?><?php echo intval($item->points_cost); ?> points
                            </span>
                        </div>
                        <div class="rl-manage-item-actions">
                            <a href="<?php echo esc_url(site_url('/manage-menu?location_id=' . $location_id . '&edit=' . $item->id)); ?>" class="rl-btn-secondary-text">Edit</a>
                            <a
                                href="<?php echo esc_url(wp_nonce_url(site_url('/manage-menu?location_id=' . $location_id . '&delete=' . $item->id), 'rl_manage_menu_item_delete_' . $item->id)); ?>"
                                class="rl-btn-danger-text"
                                onclick="return confirm('Delete this item?');"
                            >Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="rl-manage-hint">No items yet — add one above.</p>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Ported verbatim from admin/class-draws-admin.php — platform
     * draws (empty brand_id) are admin-only; brand draws via
     * can_manage_brand(). A manager page never creates/sees platform
     * draws, but a draw id could still be spoofed in a GET action, so
     * this still needs checking rather than assumed.
     */
    private function draw_authorized($draw, $is_admin)
    {
        if (empty($draw->brand_id)) {
            return $is_admin;
        }

        return $this->can_manage_brand($draw->brand_id, $is_admin);
    }

    public function manage_draws_page()
    {
        $this->require_brand_manager();

        if (!current_user_can('manage_draws')) {
            return '<p>You do not have permission.</p>';
        }

        $is_admin = current_user_can('administrator');

        // Temporarily brand-manager-facing-UI-only removal (not a
        // permanent takedown — the wp-admin Lucky Draws screen and
        // this page's own code are untouched): brand managers land
        // here without the nav link too, but this direct-URL guard
        // is the actual enforcement point.
        if (!$is_admin) {
            return $this->render_page_shell('Lucky Draws', '', '<p>Lucky Draws isn\'t available here yet — check back soon.</p>');
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        if (!$brand) {
            return $this->render_page_shell('Lucky Draws', '', '<p>No brand is assigned to you yet. Contact an administrator.</p>');
        }

        $brand_id = intval($brand->id);
        $notice   = '';

        // Create draw — brand_id is always this manager's own brand,
        // resolved server-side rather than trusted from a posted
        // field (unlike the admin screen, which lets an admin post an
        // arbitrary brand_id — a manager's request isn't trusted the
        // same way).
        if (isset($_POST['rl_create_draw']) && wp_verify_nonce($_POST['rl_manage_nonce'] ?? '', 'rl_manage_draws_create')) {

            $location_ids = array();

            if (($_POST['location_scope'] ?? 'all') === 'specific' && !empty($_POST['location_ids']) && is_array($_POST['location_ids'])) {
                foreach ($_POST['location_ids'] as $submitted_id) {
                    $location = RL_Locations::get(intval($submitted_id));

                    if ($location && intval($location->brand_id) === $brand_id) {
                        $location_ids[] = intval($location->id);
                    }
                }
            }

            $created = RL_Draws::create(array(
                'brand_id'             => $brand_id,
                'location_ids'         => $location_ids,
                'title'                => $_POST['title'] ?? '',
                'prize_description'    => $_POST['prize_description'] ?? '',
                'prize_description_nl' => $_POST['prize_description_nl'] ?? '',
                'prize_image'          => $_POST['prize_image'] ?? '',
                'start_date'           => $_POST['start_date'] ?? '',
                'end_date'             => $_POST['end_date'] ?? '',
                'min_points_threshold' => $_POST['min_points_threshold'] ?? '',
            ));

            $notice = $created ? 'Draw created.' : 'Could not create draw — check the required fields.';
        }

        // Pick winner
        if (isset($_GET['pick_winner']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_draws_pick_winner_' . intval($_GET['pick_winner']))) {

            $draw = RL_Draws::get(intval($_GET['pick_winner']));

            if ($draw && $this->draw_authorized($draw, $is_admin)) {

                $winner_customer_id = RL_Draws::pick_winner($draw->id);

                if ($winner_customer_id) {
                    RL_Notifications::send_winner_email($draw->id, $winner_customer_id);

                    if (class_exists('RL_Push_Subscriptions')) {
                        RL_Push_Subscriptions::send_to_customers(
                            array($winner_customer_id),
                            'You won! 🎉',
                            'You\'re the winner of "' . $draw->title . '" — open the app to see the details.',
                            site_url('/my-entries')
                        );
                    }

                    $notice = 'Winner picked (customer #' . intval($winner_customer_id) . ') and notified.';
                } else {
                    $notice = 'No entries to pick from.';
                }
            }
        }

        // End draw
        if (isset($_GET['end_draw']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_draws_end_' . intval($_GET['end_draw']))) {

            $draw = RL_Draws::get(intval($_GET['end_draw']));

            if ($draw && $this->draw_authorized($draw, $is_admin)) {
                RL_Draws::update($draw->id, array('status' => 'ended'));
                $notice = 'Draw ended.';
            }
        }

        // Delete draw
        if (isset($_GET['delete_draw']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rl_manage_draws_delete_' . intval($_GET['delete_draw']))) {

            $draw = RL_Draws::get(intval($_GET['delete_draw']));

            if ($draw && $this->draw_authorized($draw, $is_admin)) {
                RL_Draws::delete($draw->id);
                $notice = 'Draw deleted.';
            }
        }

        $draws     = RL_Draws::get_all_for_brand($brand_id);
        $locations = RL_Locations::get_all_by_brand($brand_id, true);

        $body = $this->render_manage_draws_body($draws, $locations, $notice);

        return $this->render_page_shell('Lucky Draws', 'Prize draws running at your restaurant.', $body);
    }

    private function render_manage_draws_body($draws, $locations, $notice)
    {
        ob_start();
        ?>

        <?php if ($notice): ?>
            <p class="rl-alert rl-alert-success"><?php echo esc_html($notice); ?></p>
        <?php endif; ?>

        <h4 class="rl-manage-subheading">Current Draws</h4>

        <?php if ($draws): ?>
            <div class="rl-manage-list">
                <?php foreach ($draws as $draw): ?>

                    <?php
                    $scope       = RL_Draws::get_location_scope_summary($draw);
                    $now         = current_time('mysql');
                    $within_window    = ($draw->start_date <= $now && $draw->end_date >= $now);
                    $counting_entries = ($draw->status === 'active' && $within_window);

                    $winner_name = '';
                    if ($draw->winner_customer_id) {
                        global $wpdb;
                        $winner_user_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT user_id FROM {$wpdb->prefix}rl_customers WHERE id = %d",
                            $draw->winner_customer_id
                        ));
                        $winner_user = $winner_user_id ? get_userdata($winner_user_id) : null;
                        $winner_name = $winner_user ? $winner_user->display_name : '';
                    }
                    ?>

                    <div class="rl-manage-draw-card">
                        <div class="rl-manage-draw-top">
                            <strong><?php echo esc_html($draw->title); ?></strong>
                            <span class="rl-manage-draw-status rl-manage-draw-status-<?php echo esc_attr($draw->status); ?>"><?php echo esc_html($draw->status); ?></span>
                        </div>

                        <div class="rl-manage-item-meta"><?php echo esc_html($scope); ?></div>
                        <div class="rl-manage-item-meta"><?php echo esc_html($draw->start_date . ' → ' . $draw->end_date); ?></div>
                        <div class="rl-manage-item-meta">
                            Min. purchase: <?php echo $draw->min_points_threshold !== null ? esc_html($draw->min_points_threshold) . ' pts' : 'Any'; ?>
                            · <?php echo intval(RL_Draws::get_total_entries($draw->id)); ?> entries
                        </div>

                        <?php if ($counting_entries): ?>
                            <div class="rl-manage-draw-flag rl-manage-draw-flag-ok">● counting entries now</div>
                        <?php elseif ($draw->status === 'active' && !$within_window): ?>
                            <div class="rl-manage-draw-flag rl-manage-draw-flag-warn">⚠ outside its date window right now</div>
                        <?php endif; ?>

                        <?php if ($winner_name): ?>
                            <div class="rl-manage-item-meta">Winner: <strong><?php echo esc_html($winner_name); ?></strong></div>
                        <?php endif; ?>

                        <div class="rl-manage-draw-actions">
                            <a
                                href="<?php echo esc_url(wp_nonce_url(site_url('/manage-draws?pick_winner=' . $draw->id), 'rl_manage_draws_pick_winner_' . $draw->id)); ?>"
                                class="rl-btn-secondary-text"
                                onclick="return confirm('Pick a random winner from current entries?');"
                            >Pick Winner</a>
                            <a
                                href="<?php echo esc_url(wp_nonce_url(site_url('/manage-draws?end_draw=' . $draw->id), 'rl_manage_draws_end_' . $draw->id)); ?>"
                                class="rl-btn-secondary-text"
                            >End</a>
                            <a
                                href="<?php echo esc_url(wp_nonce_url(site_url('/manage-draws?delete_draw=' . $draw->id), 'rl_manage_draws_delete_' . $draw->id)); ?>"
                                class="rl-btn-danger-text"
                                onclick="return confirm('Delete this draw?');"
                            >Delete</a>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="rl-manage-hint">No draws yet.</p>
        <?php endif; ?>

        <details class="rl-details-toggle">
            <summary class="rl-details-summary">Create Draw</summary>

            <form method="post" class="rl-manage-form" id="rl-manage-draw-form">
                <?php wp_nonce_field('rl_manage_draws_create', 'rl_manage_nonce'); ?>

                <div class="rl-input-group">
                    <label class="rl-form-label">Title</label>
                    <input type="text" name="title" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Prize description</label>
                    <textarea name="prize_description"></textarea>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Prize description (Dutch)</label>
                    <textarea name="prize_description_nl"></textarea>
                    <p class="rl-manage-hint">Optional — shown to customers using the Dutch app language. Leave blank to always show the English description.</p>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Prize image URL</label>
                    <input type="text" name="prize_image" placeholder="https://…">
                </div>

                <?php if (count($locations) > 1): ?>
                    <div class="rl-input-group">
                        <label class="rl-form-label">Scope</label>
                        <label style="display:block;font-weight:400;margin-bottom:4px;">
                            <input type="radio" name="location_scope" value="all" class="rl-draw-scope-radio" checked> All locations
                        </label>
                        <label style="display:block;font-weight:400;">
                            <input type="radio" name="location_scope" value="specific" class="rl-draw-scope-radio"> Specific location(s)
                        </label>

                        <div class="rl-draw-location-checks" style="margin-top:8px;display:none;">
                            <?php foreach ($locations as $loc): ?>
                                <label style="display:block;font-weight:400;margin-bottom:4px;">
                                    <input type="checkbox" name="location_ids[]" value="<?php echo intval($loc->id); ?>"> <?php echo esc_html($loc->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rl-input-group">
                    <label class="rl-form-label">Start</label>
                    <input type="datetime-local" name="start_date" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">End</label>
                    <input type="datetime-local" name="end_date" required>
                </div>

                <div class="rl-input-group">
                    <label class="rl-form-label">Minimum purchase (points) for an entry</label>
                    <input type="number" name="min_points_threshold" step="0.01" min="0" placeholder="Leave blank = any purchase qualifies">
                </div>

                <button type="submit" name="rl_create_draw" class="rl-btn-primary">Create Draw</button>
            </form>
        </details>

        <?php if (count($locations) > 1): ?>
            <script>
            (function () {
                var form = document.getElementById('rl-manage-draw-form');
                if (!form) return;

                var radios = form.querySelectorAll('.rl-draw-scope-radio');

                radios.forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        var wrap = form.querySelector('.rl-draw-location-checks');
                        if (!wrap) return;

                        var specificChecked = form.querySelector('.rl-draw-scope-radio[value="specific"]').checked;
                        wrap.style.display = specificChecked ? 'block' : 'none';
                    });
                });
            })();
            </script>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

}
