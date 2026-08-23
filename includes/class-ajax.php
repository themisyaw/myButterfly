<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Ajax
{


    public function __construct()
    {

        add_action(
            'wp_ajax_rl_find_customer',
            array($this, 'find_customer')
        );


        add_action(
            'wp_ajax_rl_add_points',
            array($this, 'add_points')
        );


        add_action(
            'wp_ajax_rl_redeem_item',
            array($this, 'redeem_item')
        );


        add_action(
            'wp_ajax_rl_push_subscribe',
            array($this, 'push_subscribe')
        );


        add_action(
            'wp_ajax_rl_push_unsubscribe',
            array($this, 'push_unsubscribe')
        );

        add_action(
            'wp_ajax_rl_mark_onboarded',
            array($this, 'mark_onboarded')
        );

        add_action(
            'wp_ajax_rl_mark_notify_prompted',
            array($this, 'mark_notify_prompted')
        );

    }


    private function security_check()
    {

        if (
            !isset($_POST['nonce'])
            ||
            !wp_verify_nonce(
                $_POST['nonce'],
                'rl_ajax_nonce'
            )
        ) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Security check failed'
            ));

        }

        if (
            !current_user_can('scan_loyalty_points')
            &&
            !current_user_can('manage_loyalty_points')
        ) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Permission denied'
            ));

        }

        $this->rate_limit();

    }

    /**
     * Throttles how many scan/points requests one staff/manager
     * account can make per minute — blunts abuse from a compromised
     * or scripted account without affecting normal counter use (a
     * busy shift doing dozens of scans a minute is still well under
     * this). Uses a transient rather than a new table since it's
     * cheap, self-expiring, and doesn't need to survive a restart.
     */
    private function rate_limit($max_per_minute = 60)
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        $key   = 'rl_ajax_rl_' . $user_id;
        $count = (int) get_transient($key);

        if ($count >= $max_per_minute) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Too many requests — please slow down and try again in a moment.'
            ));
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    }

    /**
     * Nonce + rate-limit check for the push subscribe/unsubscribe
     * endpoints — a lighter version of security_check() above, since
     * those are called by an ordinary logged-in customer's browser,
     * not a staff/manager scanning device, so scan_loyalty_points /
     * manage_loyalty_points don't apply. Sends a JSON error and
     * halts if the caller isn't a logged-in customer.
     */
    private function customer_security_check()
    {

        if (
            !isset($_POST['nonce'])
            ||
            !wp_verify_nonce(
                $_POST['nonce'],
                'rl_ajax_nonce'
            )
        ) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Security check failed'
            ));

        }

        if (
            !is_user_logged_in()
            ||
            !in_array('customer', wp_get_current_user()->roles, true)
        ) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Permission denied'
            ));

        }

        $this->rate_limit();

    }


    /**
     * Confirms the current user is allowed to operate the location
     * they claim to be scanning for, and returns that location
     * (joined with its brand) on success. Sends a JSON error and
     * halts otherwise.
     */
    private function authorized_location()
    {

        $location_id = intval($_POST['location_id'] ?? 0);

        if (!$location_id) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Missing location'
            ));
        }

        $location = RL_Locations::get_with_brand($location_id);

        if (!$location) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Location not found'
            ));
        }

        $user = wp_get_current_user();

        if (in_array('administrator', $user->roles, true)) {
            return $location;
        }

        if (in_array('brand_manager', $user->roles, true)) {

            $brand = RL_Brands::get_by_manager($user->ID);

            if ($brand && intval($brand->id) === intval($location->brand_id)) {
                return $location;
            }
        }

        wp_send_json(array(
            'success' => false,
            'message' => 'You are not authorized for this location'
        ));
    }


    public function find_customer()
    {

        $this->security_check();

        $location = $this->authorized_location();

        global $wpdb;

        $token = sanitize_text_field(
            $_POST['token'] ?? ''
        );

        if (empty($token)) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Missing QR token'
            ));

        }

        $table = $wpdb->prefix . 'rl_customers';

        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM $table
                WHERE qr_token=%s
                LIMIT 1
                ",
                $token
            )
        );

        if (!$customer) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Customer not found'
            ));

        }

        $user = get_userdata(
            $customer->user_id
        );

        if (!$user) {

            wp_send_json(array(
                'success' => false,
                'message' => 'User missing'
            ));

        }

        $balance = RL_Points::get_balance($location->id, $customer->id);

        wp_send_json(array(

            'success' => true,

            'customer' => array(

                'id'     => $customer->id,
                'name'   => $user->display_name,
                'email'  => $user->user_email,
                'points' => (float) $balance

            ),

            'pooled' => !empty($location->pool_points),

            'redeem_items' => RL_Redeem_Items::get_all_grouped_by_category($location->id)

        ));

    }


    public function add_points()
    {
        $this->security_check();

        $location = $this->authorized_location();

        $customer_id = intval($_POST['customer_id'] ?? 0);

        $points = round(floatval($_POST['points'] ?? 0), 2);

        $note = sanitize_text_field($_POST['note'] ?? '');

        if ($customer_id <= 0 || $points <= 0) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Enter a valid customer and a positive points amount.'
            ));
        }

        $result = RL_Points::add_points(
            $location->id,
            $customer_id,
            $points,
            $note
        );

        if ($result === false) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Could not add points — check the amount and try again.'
            ));
        }

        wp_send_json(array(
            'success' => true,
            'points'  => $result,
            'message' => 'Points added'
        ));
    }


    public function redeem_item()
    {

        $this->security_check();

        $location = $this->authorized_location();

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $item_id     = intval($_POST['item_id'] ?? 0);

        $item = RL_Redeem_Items::get($item_id);

        if (!$item || intval($item->location_id) !== intval($location->id)) {

            wp_send_json(array(
                'success' => false,
                'message' => 'Item not found'
            ));

        }

        $balance = RL_Points::get_balance($location->id, $customer_id);

        if ($balance === false) {

            wp_send_json(array(
                'success' => false,
                'message' => "Couldn't check this customer's balance — rescan and try again."
            ));

        }

        if ($balance < floatval($item->points_cost)) {

            $short = ceil(floatval($item->points_cost) - floatval($balance));

            wp_send_json(array(
                'success' => false,
                'message' => $short . ' points short of ' . $item->title . ' — keep earning!'
            ));

        }

        $result = RL_Points::remove_points(
            $location->id,
            $customer_id,
            $item->points_cost,
            'Redeemed: ' . $item->title
        );

        if ($result === false) {

            // remove_points() re-checks the balance atomically at the
            // database level, so this also covers the case where two
            // redemptions raced and this one lost — the balance the
            // check above saw is no longer current.
            wp_send_json(array(
                'success' => false,
                'message' => "Redemption failed — their balance may have just changed. Rescan and try again."
            ));

        }

        wp_send_json(array(
            'success' => true,
            'points'  => $result,
            'message' => 'Redeemed ' . $item->title
        ));

    }


    public function push_subscribe()
    {

        $this->customer_security_check();

        $customer_id = RL_Users::ensure_customer_identity(get_current_user_id());

        $endpoint = esc_url_raw($_POST['endpoint'] ?? '');
        $p256dh   = sanitize_text_field($_POST['p256dh'] ?? '');
        $auth     = sanitize_text_field($_POST['auth'] ?? '');

        if (!$customer_id || empty($endpoint) || empty($p256dh) || empty($auth)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Missing subscription data'
            ));
        }

        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $saved = RL_Push_Subscriptions::save($customer_id, $endpoint, $p256dh, $auth, $user_agent);

        wp_send_json(array(
            'success' => (bool) $saved,
            'message' => $saved ? 'Subscribed' : 'Could not save subscription'
        ));

    }


    public function push_unsubscribe()
    {

        $this->customer_security_check();

        $endpoint = esc_url_raw($_POST['endpoint'] ?? '');

        if (empty($endpoint)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Missing endpoint'
            ));
        }

        RL_Push_Subscriptions::remove($endpoint);

        wp_send_json(array(
            'success' => true,
            'message' => 'Unsubscribed'
        ));

    }

    /**
     * Marks the first-run onboarding slides as seen, so they don't
     * show again on this customer's next visit. Stored as user meta
     * rather than a new DB column — a one-off flag, not app data.
     */
    public function mark_onboarded()
    {

        $this->customer_security_check();

        update_user_meta(get_current_user_id(), 'rl_onboarded', 1);

        wp_send_json(array(
            'success' => true
        ));

    }

    /**
     * Marks the in-context "enable notifications" prompt as shown, so
     * it only ever appears once (whether the customer allowed or
     * dismissed it) rather than nagging on every visit.
     */
    public function mark_notify_prompted()
    {

        $this->customer_security_check();

        update_user_meta(get_current_user_id(), 'rl_notify_prompted', 1);

        wp_send_json(array(
            'success' => true
        ));

    }

}
