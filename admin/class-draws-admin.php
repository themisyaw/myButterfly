<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * wp-admin screen for lucky draws. Brand managers manage their own
 * brand's draws and choose the scope per draw — every location, one
 * specific location, or an explicit subset. Administrators
 * additionally manage the platform-wide draw(s) — the app-owner
 * giveaway that spans every restaurant on the app.
 */
class RL_Draws_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_menu_page(
            'Lucky Draws',
            'Lucky Draws',
            'manage_draws',
            'rl-draws-manage',
            array($this, 'page'),
            'dashicons-tickets-alt',
            32
        );
    }

    public function page()
    {
        if (!current_user_can('manage_draws')) {
            wp_die('You do not have permission.');
        }

        $is_admin = current_user_can('administrator');

        $this->handle_actions($is_admin);

        echo '<div class="wrap"><h1>Lucky Draws</h1>';

        if ($is_admin) {
            $this->render_platform_draws();
            echo '<hr>';
            $this->render_brand_picker();
        }

        $brand = $is_admin
            ? (!empty($_GET['brand_id']) ? RL_Brands::get(intval($_GET['brand_id'])) : null)
            : RL_Brands::get_by_manager(get_current_user_id());

        if ($brand) {
            $this->render_brand_draws($brand);
        } elseif (!$is_admin) {
            echo '<p>No brand found for your account.</p>';
        }

        echo '</div>';
    }

    private function can_manage_brand($brand_id, $is_admin)
    {
        if ($is_admin) {
            return true;
        }

        $brand = RL_Brands::get_by_manager(get_current_user_id());

        return $brand && intval($brand->id) === intval($brand_id);
    }

    /**
     * Shared CSRF nonce for every state-changing action on this
     * screen — dies via wp_die() on failure. Especially important
     * for pick_winner: it emails/pushes a real customer and can't be
     * undone, so it must not be triggerable by a bare prefetched GET.
     */
    private function verify_nonce()
    {
        if (!isset($_REQUEST['rl_draws_nonce']) || !wp_verify_nonce($_REQUEST['rl_draws_nonce'], 'rl_draws_admin_action')) {
            wp_die('Security check failed — please go back and try again.');
        }
    }

    private function has_pending_action()
    {
        return isset($_POST['rl_create_draw'])
            || isset($_GET['pick_winner'])
            || isset($_GET['end_draw'])
            || isset($_GET['delete_draw']);
    }

    private function handle_actions($is_admin)
    {

        if ($this->has_pending_action()) {
            $this->verify_nonce();
        }

        if (isset($_POST['rl_create_draw'])) {

            $brand_id = !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : null;

            // "All locations" (the default / only option for a
            // single-location restaurant) means no explicit subset.
            // "Specific" means whatever was checked, validated below
            // to actually belong to this brand.
            $location_ids = array();

            if ($brand_id && ($_POST['location_scope'] ?? 'all') === 'specific' && !empty($_POST['location_ids']) && is_array($_POST['location_ids'])) {

                foreach ($_POST['location_ids'] as $submitted_id) {
                    $location = RL_Locations::get(intval($submitted_id));

                    if ($location && intval($location->brand_id) === intval($brand_id)) {
                        $location_ids[] = intval($location->id);
                    }
                }
            }

            $authorized = $brand_id
                ? $this->can_manage_brand($brand_id, $is_admin)
                : $is_admin; // only admins create platform (brand-less) draws

            if ($authorized) {

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

                echo $created
                    ? '<div class="notice notice-success"><p>Draw created.</p></div>'
                    : '<div class="notice notice-error"><p>Could not create draw — check the required fields.</p></div>';
            }
        }

        if (isset($_GET['pick_winner'])) {

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

                    echo '<div class="notice notice-success"><p>Winner picked (customer #' . intval($winner_customer_id) . ') and notified by email' . (RL_Web_Push::has_keys() ? ' and push notification' : '') . '.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>No entries to pick from.</p></div>';
                }
            }
        }

        if (isset($_GET['end_draw'])) {

            $draw = RL_Draws::get(intval($_GET['end_draw']));

            if ($draw && $this->draw_authorized($draw, $is_admin)) {
                RL_Draws::update($draw->id, array('status' => 'ended'));
                echo '<div class="notice notice-success"><p>Draw ended.</p></div>';
            }
        }

        if (isset($_GET['delete_draw'])) {

            $draw = RL_Draws::get(intval($_GET['delete_draw']));

            if ($draw && $this->draw_authorized($draw, $is_admin)) {
                RL_Draws::delete($draw->id);
                echo '<div class="notice notice-success"><p>Draw deleted.</p></div>';
            }
        }
    }

    private function draw_authorized($draw, $is_admin)
    {
        if (empty($draw->brand_id)) {
            return $is_admin;
        }

        return $this->can_manage_brand($draw->brand_id, $is_admin);
    }

    private function render_platform_draws()
    {
        $draws = RL_Draws::get_platform_draws();
        ?>
        <h2>Platform-Wide Draws</h2>
        <p class="description">Runs across every restaurant on the app — one entry per transaction, anywhere.</p>

        <?php $this->render_draws_table($draws); ?>

        <h3>Create Platform Draw</h3>
        <form method="post">
            <?php wp_nonce_field('rl_draws_admin_action', 'rl_draws_nonce'); ?>
            <?php $this->render_draw_fields(null, array()); ?>
            <button type="submit" name="rl_create_draw" class="button button-primary">Create Platform Draw</button>
        </form>
        <?php
    }

    private function render_brand_picker()
    {
        $brands = RL_Brands::get_all();
        ?>
        <h2>Manage a Brand's Draws</h2>
        <form method="get">
            <input type="hidden" name="page" value="rl-draws-manage">
            <select name="brand_id" onchange="this.form.submit()">
                <option value="">— Select a brand —</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?php echo intval($b->id); ?>" <?php selected(intval($_GET['brand_id'] ?? 0), $b->id); ?>>
                        <?php echo esc_html($b->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
    }

    private function render_brand_draws($brand)
    {
        $draws     = RL_Draws::get_all_for_brand($brand->id);
        $locations = RL_Locations::get_all_by_brand($brand->id, true);
        ?>
        <h2><?php echo esc_html($brand->name); ?> — Draws</h2>

        <?php $this->render_draws_table($draws); ?>

        <h3>Create Draw</h3>
        <form method="post">
            <?php wp_nonce_field('rl_draws_admin_action', 'rl_draws_nonce'); ?>
            <input type="hidden" name="brand_id" value="<?php echo intval($brand->id); ?>">

            <?php $this->render_draw_fields($brand->id, $locations); ?>

            <button type="submit" name="rl_create_draw" class="button button-primary">Create Draw</button>
        </form>
        <?php
    }

    private function render_draw_fields($brand_id, $locations)
    {
        ?>
        <table class="form-table">
            <tr>
                <th>Title</th>
                <td><input type="text" name="title" required style="width:60%;"></td>
            </tr>
            <tr>
                <th>Prize description</th>
                <td><textarea name="prize_description" style="width:60%;"></textarea></td>
            </tr>
            <tr>
                <th>Prize description (Dutch)</th>
                <td>
                    <textarea name="prize_description_nl" style="width:60%;"></textarea>
                    <p class="description">Optional — shown to customers using the Dutch app language instead of the English description above. Leave blank to always show the English text.</p>
                </td>
            </tr>
            <tr>
                <th>Prize image URL</th>
                <td><input type="text" name="prize_image" style="width:60%;"></td>
            </tr>
            <?php if ($brand_id && count($locations) > 1): ?>
            <tr>
                <th>Scope</th>
                <td>
                    <label><input type="radio" name="location_scope" value="all" class="rl-draw-scope-radio" checked> All locations</label><br>
                    <label><input type="radio" name="location_scope" value="specific" class="rl-draw-scope-radio"> Specific location(s)</label>

                    <div class="rl-draw-location-checks" style="margin:8px 0 0 24px;display:none;">
                        <?php foreach ($locations as $loc): ?>
                            <label><input type="checkbox" name="location_ids[]" value="<?php echo intval($loc->id); ?>"> <?php echo esc_html($loc->name); ?></label><br>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Start</th>
                <td><input type="datetime-local" name="start_date" required></td>
            </tr>
            <tr>
                <th>End</th>
                <td><input type="datetime-local" name="end_date" required></td>
            </tr>
            <?php if ($brand_id): ?>
            <tr>
                <th>Minimum purchase (points) for an entry</th>
                <td>
                    <input type="number" name="min_points_threshold" step="0.01" min="0" placeholder="Leave blank = any purchase qualifies">
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if ($brand_id && count($locations) > 1): ?>
        <script>
        (function () {
            var radios = document.querySelectorAll('.rl-draw-scope-radio');

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    var table = radio.closest('table');
                    var wrap  = table ? table.querySelector('.rl-draw-location-checks') : null;

                    if (!wrap) {
                        return;
                    }

                    var specificChecked = table.querySelector('.rl-draw-scope-radio[value="specific"]').checked;
                    wrap.style.display = specificChecked ? 'block' : 'none';
                });
            });
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    private function render_draws_table($draws)
    {
        if (empty($draws)) {
            echo '<p>No draws yet.</p>';
            return;
        }
        ?>
        <table class="widefat fixed" style="margin-bottom:20px;">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Scope</th>
                    <th>Window</th>
                    <th>Min. points</th>
                    <th>Status</th>
                    <th>Entries</th>
                    <th>Winner</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($draws as $draw): ?>
                    <?php
                    $scope = RL_Draws::get_location_scope_summary($draw);

                    $winner_name = '—';
                    if ($draw->winner_customer_id) {
                        global $wpdb;
                        $winner_user_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT user_id FROM {$wpdb->prefix}rl_customers WHERE id = %d",
                            $draw->winner_customer_id
                        ));
                        $winner_user = $winner_user_id ? get_userdata($winner_user_id) : null;
                        $winner_name = $winner_user ? $winner_user->display_name : '—';
                    }
                    ?>
                    <?php
                    $now              = current_time('mysql');
                    $within_window    = ($draw->start_date <= $now && $draw->end_date >= $now);
                    $counting_entries = ($draw->status === 'active' && $within_window);
                    ?>
                    <tr>
                        <td><?php echo esc_html($draw->title); ?></td>
                        <td><?php echo esc_html($scope); ?></td>
                        <td><?php echo esc_html($draw->start_date . ' → ' . $draw->end_date); ?></td>
                        <td><?php echo $draw->min_points_threshold !== null ? esc_html($draw->min_points_threshold) : 'Any'; ?></td>
                        <td>
                            <?php echo esc_html($draw->status); ?>
                            <?php if ($counting_entries): ?>
                                <br><span style="color:#0f766e;font-weight:600;">● counting entries now</span>
                            <?php elseif ($draw->status === 'active' && !$within_window): ?>
                                <br><span style="color:#b91c1c;font-weight:600;">⚠ outside its date window right now</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo intval(RL_Draws::get_total_entries($draw->id)); ?></td>
                        <td><?php echo esc_html($winner_name); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('pick_winner', $draw->id), 'rl_draws_admin_action', 'rl_draws_nonce')); ?>" onclick="return confirm('Pick a random winner from current entries? This emails/notifies them immediately and cannot be undone.');">Pick Winner</a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('end_draw', $draw->id), 'rl_draws_admin_action', 'rl_draws_nonce')); ?>" onclick="return confirm('End this draw?');">End</a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('delete_draw', $draw->id), 'rl_draws_admin_action', 'rl_draws_nonce')); ?>" onclick="return confirm('Delete this draw?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
