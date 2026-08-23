<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * wp-admin screen for managing brands (chains) and their locations.
 * Administrators see/manage every brand; a brand manager only ever
 * sees their own.
 */
class RL_Brands_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_menu_page(
            'Brands',
            'Brands',
            'manage_brand',
            'rl-brand-manage',
            array($this, 'page'),
            'dashicons-store',
            31
        );
    }

    public function page()
    {
        if (!current_user_can('manage_brand')) {
            wp_die('You do not have permission.');
        }

        $is_admin = current_user_can('administrator');

        $this->handle_actions($is_admin);

        if ($is_admin && empty($_GET['brand_id'])) {
            $this->render_brand_list();
            return;
        }

        if ($is_admin) {
            $brand = RL_Brands::get(intval($_GET['brand_id']));
        } else {
            $brand = RL_Brands::get_by_manager(get_current_user_id());
        }

        if (!$brand) {
            echo '<div class="wrap"><h1>Brands</h1><p>No brand found.</p></div>';
            return;
        }

        $this->render_brand($brand, $is_admin);
    }

    /**
     * Shared CSRF nonce for every state-changing action on this
     * screen (POST forms and the GET action links alike) — dies via
     * wp_die() on failure, same as WordPress's own admin screens.
     */
    private function verify_nonce()
    {
        if (!isset($_REQUEST['rl_brands_nonce']) || !wp_verify_nonce($_REQUEST['rl_brands_nonce'], 'rl_brands_admin_action')) {
            wp_die('Security check failed — please go back and try again.');
        }
    }

    private function has_pending_action()
    {
        return isset($_POST['rl_create_brand'])
            || isset($_GET['delete_brand'])
            || isset($_POST['rl_add_manager'])
            || isset($_GET['remove_manager'])
            || isset($_POST['rl_update_brand'])
            || isset($_POST['rl_update_single_restaurant'])
            || isset($_POST['rl_add_location'])
            || isset($_POST['rl_edit_location'])
            || isset($_GET['delete_location']);
    }

    private function handle_actions($is_admin)
    {

        if ($this->has_pending_action()) {
            $this->verify_nonce();
        }

        // Create a new brand (admin only).
        if ($is_admin && isset($_POST['rl_create_brand'])) {

            $manager_user_id = 0;

            if (!empty($_POST['new_manager_email'])) {

                $created = RL_Users::create_staff_user(
                    $_POST['new_manager_name'] ?? '',
                    $_POST['new_manager_email'],
                    $_POST['new_manager_password'] ?? '',
                    'brand_manager'
                );

                if (!is_wp_error($created)) {
                    $manager_user_id = $created;
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($created->get_error_message()) . '</p></div>';
                }
            } else {

                echo '<div class="notice notice-error"><p>Fill in the manager name, email, and password before creating the brand.</p></div>';
            }

            if ($manager_user_id) {

                if (empty($_POST['name'])) {

                    echo '<div class="notice notice-error"><p>Restaurant name is required.</p></div>';

                } else {

                    // Every brand starts life as a single restaurant —
                    // pooling only becomes a visible choice once there's
                    // more than one location. Points default to shared
                    // across locations (the common case for a chain);
                    // a manager can turn that off later in Brand Settings.
                    $brand_id = RL_Brands::create(array(
                        'name'         => $_POST['name'] ?? '',
                        'description'  => $_POST['description'] ?? '',
                        'logo'         => $_POST['logo'] ?? '',
                        'pool_points'  => true,
                        'pool_entries' => false,
                    ));

                    if ($brand_id) {

                        RL_Brands::assign_manager($brand_id, $manager_user_id);

                        RL_Brand_Hours::save_for_brand($brand_id, $_POST['hours'] ?? array());

                        // Auto-create the first (and, usually, only)
                        // location right away — no separate step for
                        // the common single-restaurant case.
                        $lat = $_POST['lat'] ?? '';
                        $lng = $_POST['lng'] ?? '';

                        if (($lat === '' || $lng === '') && !empty($_POST['address'])) {
                            $geo = RL_Locations::geocode_address($_POST['address']);
                            if ($geo) {
                                $lat = $geo['lat'];
                                $lng = $geo['lng'];
                            }
                        }

                        RL_Locations::create(array(
                            'brand_id' => $brand_id,
                            'name'     => $_POST['name'] ?? '',
                            'address'  => $_POST['address'] ?? '',
                            'lat'      => $lat,
                            'lng'      => $lng,
                        ));

                        echo '<div class="notice notice-success"><p>Restaurant created.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Could not create the restaurant — check the name and manager.</p></div>';
                    }
                }
            }
        }

        // Delete a brand (admin only).
        if ($is_admin && isset($_GET['delete_brand'])) {
            RL_Brands::delete(intval($_GET['delete_brand']));
            echo '<div class="notice notice-success"><p>Brand deleted.</p></div>';
        }

        // Add another manager to an existing brand (admin, or the
        // brand's own manager). Always creates a brand-new user —
        // same reasoning as brand creation: assigning an existing
        // account by email doesn't make sense here since there's no
        // way to know a given email actually belongs to an intended
        // manager rather than, say, a customer.
        if (isset($_POST['rl_add_manager'])) {

            $brand_id = intval($_POST['brand_id']);

            if ($this->can_manage_brand($brand_id, $is_admin)) {

                $created = RL_Users::create_staff_user(
                    $_POST['new_manager_name'] ?? '',
                    $_POST['new_manager_email'] ?? '',
                    $_POST['new_manager_password'] ?? '',
                    'brand_manager'
                );

                if (!is_wp_error($created)) {
                    RL_Brands::assign_manager($brand_id, $created);
                    echo '<div class="notice notice-success"><p>Manager added.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($created->get_error_message()) . '</p></div>';
                }
            }
        }

        // Remove a manager (admin, or the brand's own manager —
        // scoped via can_manage_brand so a manager can't remove
        // another brand's managers).
        if (isset($_GET['remove_manager'])) {

            $manager_user_id = intval($_GET['remove_manager']);
            $brand           = intval($_GET['brand_id'] ?? 0);

            if ($this->can_manage_brand($brand, $is_admin)) {
                RL_Brands::remove_manager($manager_user_id);
                echo '<div class="notice notice-success"><p>Manager removed.</p></div>';
            }
        }

        // Update brand settings (admin, or the brand's own manager).
        if (isset($_POST['rl_update_brand'])) {

            $brand_id = intval($_POST['brand_id']);

            if ($this->can_manage_brand($brand_id, $is_admin)) {

                RL_Brands::update($brand_id, array(
                    'name'         => $_POST['name'] ?? '',
                    'description'  => $_POST['description'] ?? '',
                    'logo'         => $_POST['logo'] ?? '',
                    'pool_points'  => !empty($_POST['pool_points']),
                    'pool_entries' => !empty($_POST['pool_entries']),
                ));

                RL_Brand_Hours::save_for_brand($brand_id, $_POST['hours'] ?? array());

                echo '<div class="notice notice-success"><p>Brand settings updated.</p></div>';
            }
        }

        // Update a single-location restaurant's details — name,
        // description, and its one location's address/coordinates —
        // all from one combined form. Once a brand has a 2nd location
        // it switches to the full chain UI (brand settings + the
        // locations table) and this handler no longer applies.
        if (isset($_POST['rl_update_single_restaurant'])) {

            $brand_id = intval($_POST['brand_id']);

            if ($this->can_manage_brand($brand_id, $is_admin)) {

                $name = sanitize_text_field($_POST['name'] ?? '');

                RL_Brands::update($brand_id, array(
                    'name'        => $name,
                    'description' => $_POST['description'] ?? '',
                    'logo'        => $_POST['logo'] ?? '',
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

                $locations = RL_Locations::get_all_by_brand($brand_id);

                $location_data = array(
                    'name'    => $name,
                    'address' => $_POST['address'] ?? '',
                    'lat'     => $lat,
                    'lng'     => $lng,
                );

                if ($locations) {
                    // Keep the (single) location's name in sync with
                    // the restaurant name — there's no separate
                    // "location name" concept until it's a chain.
                    RL_Locations::update($locations[0]->id, $location_data);
                } else {
                    // Defensive: a brand somehow ended up with no
                    // location yet (e.g. the auto-create above failed).
                    // Don't leave the manager stuck — create it now.
                    $location_data['brand_id'] = $brand_id;
                    RL_Locations::create($location_data);
                }

                echo '<div class="notice notice-success"><p>Restaurant details updated.</p></div>';
            }
        }

        // Add a location.
        if (isset($_POST['rl_add_location'])) {

            $brand_id = intval($_POST['brand_id']);

            if ($this->can_manage_brand($brand_id, $is_admin)) {

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

                echo $created
                    ? '<div class="notice notice-success"><p>Location added.</p></div>'
                    : '<div class="notice notice-error"><p>Could not add location.</p></div>';
            }
        }

        // Update a location.
        if (isset($_POST['rl_edit_location'])) {

            $location_id = intval($_POST['location_id']);
            $location    = RL_Locations::get($location_id);

            if ($location && $this->can_manage_brand($location->brand_id, $is_admin)) {

                $lat = $_POST['lat'] ?? '';
                $lng = $_POST['lng'] ?? '';

                if (($lat === '' || $lng === '') && !empty($_POST['address'])) {
                    $geo = RL_Locations::geocode_address($_POST['address']);
                    if ($geo) {
                        $lat = $geo['lat'];
                        $lng = $geo['lng'];
                    }
                }

                RL_Locations::update($location_id, array(
                    'name'    => $_POST['location_name'] ?? '',
                    'address' => $_POST['address'] ?? '',
                    'lat'     => $lat,
                    'lng'     => $lng,
                ));

                echo '<div class="notice notice-success"><p>Location updated.</p></div>';
            }
        }

        // Delete a location.
        if (isset($_GET['delete_location'])) {

            $location = RL_Locations::get(intval($_GET['delete_location']));

            if ($location && $this->can_manage_brand($location->brand_id, $is_admin)) {
                RL_Locations::delete($location->id);
                echo '<div class="notice notice-success"><p>Location deleted.</p></div>';
            }
        }

    }

    private function can_manage_brand($brand_id, $is_admin)
    {
        if ($is_admin) {
            return true;
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        return $brand && intval($brand->id) === intval($brand_id);
    }


    private function render_brand_list()
    {
        $brands = RL_Brands::get_all();
        ?>
        <div class="wrap">
            <h1>Restaurants</h1>

            <table class="widefat fixed" style="margin-bottom:30px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Manager(s)</th>
                        <th>Locations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $b): ?>
                        <?php
                        $managers       = RL_Brands::get_managers_for_brand($b->id);
                        $location_count = count(RL_Locations::get_all_by_brand($b->id));
                        ?>
                        <tr>
                            <td><?php echo esc_html($b->name); ?></td>
                            <td>
                                <?php if ($managers): ?>
                                    <?php echo esc_html(implode(', ', wp_list_pluck($managers, 'user_email'))); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($location_count); ?><?php echo $location_count > 1 ? ' (chain)' : ''; ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-brand-manage&brand_id=' . $b->id)); ?>">Manage</a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-brand-manage&delete_brand=' . $b->id), 'rl_brands_admin_action', 'rl_brands_nonce')); ?>" onclick="return confirm('Delete this restaurant and all its locations?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Add a Restaurant</h2>
            <p class="description">This creates the restaurant and its (first) location together. If it ever grows into a chain, you can add more locations from its management page — no need to decide that now.</p>

            <form method="post">
                <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Restaurant name</th>
                        <td><input type="text" name="name" required></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><textarea name="description"></textarea></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><input type="text" name="address" style="width:70%;" placeholder="Street, city, country"></td>
                    </tr>
                    <tr>
                        <th>Lat / Lng (optional override)</th>
                        <td>
                            <input type="text" name="lat" placeholder="Latitude" style="width:120px;">
                            <input type="text" name="lng" placeholder="Longitude" style="width:120px;">
                            <p class="description">Leave blank to auto-locate from the address.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cover photo</th>
                        <td>
                            <input type="text" name="logo" style="width:70%;" placeholder="https://…">
                            <p class="description">Image URL — shown as the cover photo on Explore and this restaurant's own page.</p>
                        </td>
                    </tr>
                    <?php $this->render_hours_fields(0); ?>
                    <tr>
                        <th>Manager</th>
                        <td>
                            <input type="text" name="new_manager_name" placeholder="Name" style="margin-bottom:6px;display:block;">
                            <input type="email" name="new_manager_email" placeholder="Email" style="margin-bottom:6px;display:block;">
                            <input type="password" name="new_manager_password" placeholder="Password (min 8 chars)">
                            <p class="description">Creates a new brand manager account for this restaurant. You can add more managers later from its management page.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="rl_create_brand" class="button button-primary">Create Restaurant</button>
            </form>
        </div>
        <?php
    }

    private function render_brand($brand, $is_admin)
    {
        $locations = RL_Locations::get_all_by_brand($brand->id);
        $is_chain  = count($locations) > 1;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($brand->name); ?></h1>

            <?php if ($is_admin): ?>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=rl-brand-manage')); ?>">&larr; All restaurants</a></p>
            <?php endif; ?>

            <?php if (!$is_chain): ?>

                <?php $this->render_single_restaurant_form($brand, $locations); ?>

            <?php else: ?>

            <h2>Brand Settings</h2>

            <form method="post">
                <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
                <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">
                <table class="form-table">
                    <tr>
                        <th>Name</th>
                        <td><input type="text" name="name" value="<?php echo esc_attr($brand->name); ?>" required></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><textarea name="description"><?php echo esc_textarea($brand->description); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Cover photo</th>
                        <td>
                            <input type="text" name="logo" value="<?php echo esc_attr($brand->logo ?? ''); ?>" style="width:70%;" placeholder="https://…">
                            <p class="description">Image URL — shown as the cover photo on Explore and every location's own page.</p>
                        </td>
                    </tr>
                    <?php $this->render_hours_fields($brand->id); ?>
                    <tr>
                        <th>Chain settings</th>
                        <td>
                            <label><input type="checkbox" name="pool_points" value="1" <?php checked($brand->pool_points, 1); ?>> Share one points balance across all locations</label><br>
                            <label><input type="checkbox" name="pool_entries" value="1" <?php checked($brand->pool_entries, 1); ?>> Share lucky-draw entries across all locations</label>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="rl_update_brand" class="button button-primary">Save Settings</button>
            </form>

            <hr>

            <?php $this->render_managers_section($brand); ?>

            <hr>

            <h2>Locations</h2>

            <table class="widefat fixed" style="margin-bottom:20px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Lat / Lng</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td><?php echo esc_html($loc->name); ?></td>
                            <td><?php echo esc_html($loc->address); ?></td>
                            <td><?php echo esc_html($loc->lat . ', ' . $loc->lng); ?></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-brand-manage&brand_id=' . $brand->id . '&delete_location=' . $loc->id), 'rl_brands_admin_action', 'rl_brands_nonce')); ?>" onclick="return confirm('Delete this location?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Add Location</h3>

            <form method="post">
                <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
                <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">
                <table class="form-table">
                    <tr>
                        <th>Name</th>
                        <td><input type="text" name="location_name" required></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><input type="text" name="address" style="width:70%;" placeholder="Street, city, country"></td>
                    </tr>
                    <tr>
                        <th>Lat / Lng (optional override)</th>
                        <td>
                            <input type="text" name="lat" placeholder="Latitude" style="width:120px;">
                            <input type="text" name="lng" placeholder="Longitude" style="width:120px;">
                            <p class="description">Leave blank to auto-locate from the address.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="rl_add_location" class="button button-primary">Add Location</button>
            </form>

            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * A 7-row, day-by-day hours editor shared by every brand-editing
     * form (create, single-restaurant details, chain Brand Settings)
     * — each day can have its own times or be marked closed entirely.
     * $brand_id of 0 renders a blank/unconfigured week (new brand).
     * Must be used inside an open <table class="form-table"> — it
     * outputs its own <tr>.
     */
    private function render_hours_fields($brand_id)
    {
        $hours = RL_Brand_Hours::get_all_for_brand($brand_id);
        ?>
        <tr>
            <th>Opening hours</th>
            <td>
                <table class="rl-hours-table" style="border-collapse:collapse;">
                    <?php foreach (RL_Brand_Hours::DISPLAY_ORDER as $day): ?>
                        <?php $row = $hours[$day]; ?>
                        <tr>
                            <td style="padding:4px 12px 4px 0;width:100px;"><?php echo esc_html(RL_Brand_Hours::DAY_LABELS[$day]); ?></td>
                            <td style="padding:4px 12px 4px 0;">
                                <label>
                                    <input type="checkbox" name="hours[<?php echo intval($day); ?>][is_closed]" value="1" <?php checked(!empty($row->is_closed)); ?>>
                                    Closed
                                </label>
                            </td>
                            <td style="padding:4px;">
                                <input type="time" name="hours[<?php echo intval($day); ?>][opening_time]" value="<?php echo esc_attr(substr($row->opening_time ?? '', 0, 5)); ?>">
                            </td>
                            <td style="padding:4px;">&mdash;</td>
                            <td style="padding:4px;">
                                <input type="time" name="hours[<?php echo intval($day); ?>][closing_time]" value="<?php echo esc_attr(substr($row->closing_time ?? '', 0, 5)); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="description">Optional, and can differ by day — leave a day's times blank to not show hours for it, or check "Closed" for days you're not open.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Lists a brand's current managers with a remove link, plus a
     * form to add another one — shared by the chain view
     * (render_brand()) and the single-restaurant view
     * (render_single_restaurant_form()). Always creates a brand-new
     * user for the new manager; there's no "assign an existing
     * account" option (see rl_add_manager in handle_actions()).
     */
    private function render_managers_section($brand)
    {
        $managers = RL_Brands::get_managers_for_brand($brand->id);
        ?>

        <h2>Managers</h2>

        <?php if ($managers): ?>
            <ul>
                <?php foreach ($managers as $m): ?>
                    <li>
                        <?php echo esc_html($m->display_name); ?> (<?php echo esc_html($m->user_email); ?>)
                        (<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-brand-manage&brand_id=' . $brand->id . '&remove_manager=' . $m->user_id), 'rl_brands_admin_action', 'rl_brands_nonce')); ?>" onclick="return confirm('Remove this manager?');">remove</a>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No managers assigned yet.</p>
        <?php endif; ?>

        <details>
            <summary>Add another manager</summary>
            <form method="post" style="margin-top:8px;max-width:400px;">
                <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
                <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">
                <input type="text" name="new_manager_name" placeholder="Name" style="display:block;margin-bottom:4px;width:100%;">
                <input type="email" name="new_manager_email" placeholder="Email" style="display:block;margin-bottom:4px;width:100%;">
                <input type="password" name="new_manager_password" placeholder="Password" style="display:block;margin-bottom:4px;width:100%;">
                <button type="submit" name="rl_add_manager" class="button">Add Manager</button>
            </form>
        </details>
        <?php
    }

    /**
     * The manage screen for a brand that's still just a single
     * restaurant (0 or 1 locations): one combined form for the
     * restaurant's own details plus its location's address/coords,
     * staff for that one location, and a low-key way to grow into a
     * chain by adding a second location — at which point render_brand()
     * switches to the full chain UI on the next page load.
     */
    private function render_single_restaurant_form($brand, $locations)
    {
        $location = !empty($locations) ? $locations[0] : null;
        ?>

        <h2>Restaurant Details</h2>

        <form method="post">
            <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
            <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">
            <table class="form-table">
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="name" value="<?php echo esc_attr($brand->name); ?>" required></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><textarea name="description"><?php echo esc_textarea($brand->description); ?></textarea></td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td><input type="text" name="address" value="<?php echo esc_attr($location->address ?? ''); ?>" style="width:70%;" placeholder="Street, city, country"></td>
                </tr>
                <tr>
                    <th>Lat / Lng (optional override)</th>
                    <td>
                        <input type="text" name="lat" value="<?php echo esc_attr($location->lat ?? ''); ?>" placeholder="Latitude" style="width:120px;">
                        <input type="text" name="lng" value="<?php echo esc_attr($location->lng ?? ''); ?>" placeholder="Longitude" style="width:120px;">
                        <p class="description">Leave blank to auto-locate from the address.</p>
                    </td>
                </tr>
                <tr>
                    <th>Cover photo</th>
                    <td>
                        <input type="text" name="logo" value="<?php echo esc_attr($brand->logo ?? ''); ?>" style="width:70%;" placeholder="https://…">
                        <p class="description">Image URL — shown as the cover photo on Explore and this restaurant's own page.</p>
                    </td>
                </tr>
                <?php $this->render_hours_fields($brand->id); ?>
            </table>
            <button type="submit" name="rl_update_single_restaurant" class="button button-primary">Save Details</button>
        </form>

        <hr>

        <?php $this->render_managers_section($brand); ?>

        <hr>

        <details>
            <summary>Growing into a chain? Add another location</summary>

            <p class="description">Adding a second location turns this into a chain — you'll then be able to choose whether points and lucky-draw entries are shared across locations or kept separate.</p>

            <form method="post" style="margin-top:8px;max-width:500px;">
                <?php wp_nonce_field('rl_brands_admin_action', 'rl_brands_nonce'); ?>
                <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">
                <table class="form-table">
                    <tr>
                        <th>Location name</th>
                        <td><input type="text" name="location_name" required placeholder="e.g. Downtown"></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><input type="text" name="address" style="width:100%;" placeholder="Street, city, country"></td>
                    </tr>
                    <tr>
                        <th>Lat / Lng (optional override)</th>
                        <td>
                            <input type="text" name="lat" placeholder="Latitude" style="width:120px;">
                            <input type="text" name="lng" placeholder="Longitude" style="width:120px;">
                        </td>
                    </tr>
                </table>
                <button type="submit" name="rl_add_location" class="button button-primary">Add Location</button>
            </form>
        </details>

        <?php
    }
}
