<?php

if (!defined('ABSPATH')) {
    exit;
}

class RL_Users
{

    /**
     * Looks up customers by name or email for the admin notification
     * composer's "single user" picker. Joins rl_customers to
     * wp_users since the searchable name/email live on the WP user,
     * not the customer row. Returns id/name/email triples, capped at
     * $limit — this is for a live-typeahead search box, not a bulk
     * export.
     */
    public static function search_customers($term, $limit = 15)
    {
        global $wpdb;

        $term = trim(sanitize_text_field($term));

        if ($term === '') {
            return array();
        }

        $customers_table = $wpdb->prefix . 'rl_customers';
        $like            = '%' . $wpdb->esc_like($term) . '%';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT c.id, u.display_name, u.user_email
                FROM {$customers_table} c
                INNER JOIN {$wpdb->users} u ON u.ID = c.user_id
                WHERE u.display_name LIKE %s OR u.user_email LIKE %s
                ORDER BY u.display_name ASC
                LIMIT %d
                ",
                $like,
                $like,
                absint($limit)
            )
        );

        $results = array();

        foreach ($rows as $row) {
            $results[] = array(
                'id'    => intval($row->id),
                'name'  => $row->display_name,
                'email' => $row->user_email,
            );
        }

        return $results;
    }

    /**
     * wp_delete_user() lives in wp-admin/includes/user.php, which
     * WordPress only autoloads inside wp-admin. Registration and
     * login run on the front end, so any rollback that deletes a
     * half-created account has to load that file first or it fatals.
     */
    private static function safe_delete_user($user_id)
    {

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        wp_delete_user($user_id);
    }

    /**
     * Creates a plain WP user with the internal brand_manager role.
     * Used by the admin screens where you set up a restaurant/chain
     * and its manager(s) — not self-registration.
     */
    public static function create_staff_user($name, $email, $password, $role)
    {

        $name  = sanitize_text_field($name);
        $email = sanitize_email($email);

        if ($role !== 'brand_manager') {
            return new WP_Error('invalid_role', 'Invalid staff role');
        }

        if (empty($name)) {
            return new WP_Error('invalid_name', 'Name is required');
        }

        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }

        if (empty($password) || strlen($password) < 8) {
            return new WP_Error('weak_password', 'Password must contain at least 8 characters');
        }

        if (email_exists($email)) {
            return new WP_Error('email_exists', 'Email already exists');
        }

        // Login username is derived from the person's name (e.g.
        // "John Smith" -> "john-smith"), not the email address —
        // falls back to the email's local part, then the role, only
        // if the name doesn't sanitize down to anything usable.
        $username = sanitize_title($name);

        if (empty($username)) {
            $username = sanitize_user(current(explode('@', $email)), true);
        }

        if (empty($username)) {
            $username = $role;
        }

        $original_username = $username;
        $counter            = 1;

        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user(array('ID' => $user_id, 'display_name' => $name));

        $user = new WP_User($user_id);
        $user->set_role($role);

        return $user_id;
    }

    public static function create_customer(
        $name,
        $email,
        $password,
        $referral_code = '',
        $pre_verified = false
    ) {

        global $wpdb;

        /*
        =========================
        VALIDATION
        =========================
        */

        $name = sanitize_text_field($name);
        $email = sanitize_email($email);

        if (empty($name)) {
            return new WP_Error(
                'invalid_name',
                'Name is required'
            );
        }

        if (!is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address'
            );
        }

        if (empty($password) || strlen($password) < 8) {
            return new WP_Error(
                'weak_password',
                'Password must contain at least 8 characters'
            );
        }

        /*
        =========================
        CHECK EXISTING USER
        =========================
        */

        if (email_exists($email)) {
            return new WP_Error(
                'email_exists',
                'Email already exists'
            );
        }

        /*
        =========================
        CREATE USERNAME
        =========================
        */

        $username = sanitize_user(
            current(
                explode('@', $email)
            ),
            true
        );

        if (empty($username)) {
            $username = 'customer';
        }

        $original_username = $username;

        $counter = 1;

        while (username_exists($username)) {

            $username = $original_username . $counter;

            $counter++;

        }

        /*
        =========================
        CREATE WORDPRESS USER
        =========================
        */

        $user_id = wp_create_user(
            $username,
            $password,
            $email
        );

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        /*
        =========================
        UPDATE PROFILE
        =========================
        */

        wp_update_user(
            array(
                'ID' => $user_id,
                'display_name' => $name
            )
        );

        /*
        =========================
        ASSIGN CUSTOMER ROLE
        =========================
        */

        $user = new WP_User($user_id);

        if (get_role('customer')) {

            $user->set_role(
                'customer'
            );

        }
        else {

            self::safe_delete_user(
                $user_id
            );

            return new WP_Error(
                'missing_role',
                'Customer role does not exist'
            );

        }

        /*
        =========================
        CREATE LOYALTY ACCOUNT
        =========================
        */

        $customer_id = self::ensure_customer_identity($user_id);

        if (!$customer_id) {

            self::safe_delete_user(
                $user_id
            );

            return new WP_Error(
                'customer_creation_failed',
                'Could not create customer account'
            );

        }

        /*
        =========================
        EMAIL VERIFICATION
        Self-registered accounts must click a link sent to the email
        they entered before they can log in — otherwise nothing stops
        someone from farming referral draw entries with unlimited
        made-up addresses. A referral bonus earned here is held until
        that click happens (see verify_email()) instead of granted
        immediately. Accounts we already know the email is real for
        (Google sign-in, whose email Google itself verified) skip
        straight to verified and apply the referral now.
        =========================
        */

        if ($pre_verified) {

            update_user_meta($user_id, 'rl_email_verified', '1');

            if (!empty($referral_code)) {
                self::apply_referral($customer_id, $referral_code);
            }

        } else {

            update_user_meta($user_id, 'rl_email_verified', '0');

            if (!empty($referral_code)) {
                update_user_meta($user_id, 'rl_pending_referral_code', sanitize_text_field($referral_code));
            }

            self::send_verification_email($user_id);

        }

        /*
        =========================
        SUCCESS
        =========================
        */

        return $user_id;

    }

    /**
     * Emails a one-click confirmation link to prove the address
     * entered at registration actually belongs to the person signing
     * up. Safe to call repeatedly (e.g. for a "resend" button) — it
     * just issues a fresh token each time, invalidating the old one.
     */
    public static function send_verification_email($user_id)
    {
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return false;
        }

        $token = wp_generate_password(32, false);

        update_user_meta($user_id, 'rl_email_verify_token', $token);
        update_user_meta($user_id, 'rl_email_verify_sent_at', time());

        $verify_url = add_query_arg(
            array(
                'uid' => $user_id,
                'key' => $token,
            ),
            site_url('/verify-email')
        );

        $message = sprintf(
            "Hi %s,\r\n\r\nPlease confirm your email address to activate your Butterfly account:\r\n\r\n<%s>\r\n\r\nIf you didn't create this account, you can safely ignore this email.\r\n",
            $user->display_name,
            $verify_url
        );

        wp_mail($user->user_email, 'Confirm your email — Butterfly', $message);

        return true;
    }

    /**
     * Re-sends the confirmation email for a still-unverified account.
     * Deliberately returns success (without actually sending) for a
     * verified account or an email/username that doesn't match
     * anything — same anti-enumeration approach as password reset.
     * Rate-limited to one send per minute per account so the "resend"
     * button can't be used to spam someone's inbox.
     */
    public static function resend_verification_email($login_or_email)
    {
        $login_or_email = sanitize_text_field($login_or_email);

        if (empty($login_or_email)) {
            return true;
        }

        $user = is_email($login_or_email)
            ? get_user_by('email', $login_or_email)
            : get_user_by('login', $login_or_email);

        if (!$user) {
            return true;
        }

        if (get_user_meta($user->ID, 'rl_email_verified', true) === '1') {
            return true;
        }

        $last_sent = intval(get_user_meta($user->ID, 'rl_email_verify_sent_at', true));

        if ($last_sent && (time() - $last_sent) < 60) {
            return new WP_Error(
                'too_soon',
                'Please wait a minute before requesting another email.'
            );
        }

        self::send_verification_email($user->ID);

        return true;
    }

    /**
     * Confirms the link sent by send_verification_email(). On success,
     * marks the account verified and — since granting it up front
     * would have let anyone farm draw entries with fake addresses —
     * applies any referral bonus that was deferred at registration.
     */
    public static function verify_email($user_id, $key)
    {
        $user_id = absint($user_id);
        $key     = sanitize_text_field($key);

        if (!$user_id || empty($key)) {
            return new WP_Error('invalid_link', 'This verification link is invalid.');
        }

        $user = get_user_by('id', $user_id);

        if (!$user) {
            return new WP_Error('invalid_link', 'This verification link is invalid.');
        }

        if (get_user_meta($user_id, 'rl_email_verified', true) === '1') {
            return true;
        }

        $stored_token = get_user_meta($user_id, 'rl_email_verify_token', true);

        if (empty($stored_token) || !hash_equals($stored_token, $key)) {
            return new WP_Error('invalid_link', 'This verification link is invalid or has expired.');
        }

        $sent_at = intval(get_user_meta($user_id, 'rl_email_verify_sent_at', true));

        if ($sent_at && (time() - $sent_at) > 2 * DAY_IN_SECONDS) {
            return new WP_Error(
                'expired_link',
                'This verification link has expired. Please request a new one from the login page.'
            );
        }

        update_user_meta($user_id, 'rl_email_verified', '1');
        delete_user_meta($user_id, 'rl_email_verify_token');

        $customer_id  = self::ensure_customer_identity($user_id);
        $pending_code = get_user_meta($user_id, 'rl_pending_referral_code', true);

        if ($customer_id && !empty($pending_code)) {
            self::apply_referral($customer_id, $pending_code);
            delete_user_meta($user_id, 'rl_pending_referral_code');
        }

        return true;
    }

    /**
     * Every user with the 'customer' role needs a matching rl_customers
     * row (their global identity + QR token) — otherwise the dashboard
     * has nothing to show them. create_customer() above calls this
     * during self-registration; it's also hooked to WordPress's
     * set_user_role action so a customer created/promoted straight
     * from wp-admin Users still gets one automatically. Safe to call
     * repeatedly — it's a no-op if the row already exists.
     */
    public static function ensure_customer_identity($user_id)
    {

        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_customers';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ($existing) {
            return intval($existing->id);
        }

        if (!class_exists('RL_QR')) {
            return false;
        }

        $qr_token = RL_QR::generate_token();

        if (empty($qr_token)) {
            return false;
        }

        $referral_code = self::generate_unique_referral_code();

        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id'       => $user_id,
                'qr_token'      => $qr_token,
                'referral_code' => $referral_code,
            ),
            array('%d', '%s', '%s')
        );

        if (!$inserted) {
            return false;
        }

        return intval($wpdb->insert_id);
    }

    /**
     * Backfills a referral code for customers created before this
     * feature existed. Safe/cheap to call on every dashboard load —
     * it's a no-op once the code is set.
     */
    public static function ensure_referral_code($customer_id)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return '';
        }

        $table = $wpdb->prefix . 'rl_customers';

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT referral_code FROM {$table} WHERE id = %d LIMIT 1",
                $customer_id
            )
        );

        if (!empty($existing)) {
            return $existing;
        }

        $code = self::generate_unique_referral_code();

        $wpdb->update(
            $table,
            array('referral_code' => $code),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );

        return $code;
    }

    private static function generate_unique_referral_code()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_customers';

        for ($attempt = 0; $attempt < 10; $attempt++) {

            $code = RL_QR::generate_referral_code();

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE referral_code = %s LIMIT 1",
                    $code
                )
            );

            if (!$exists) {
                return $code;
            }
        }

        return $code . wp_rand(10, 99);
    }

    public static function get_customer_by_referral_code($code)
    {
        global $wpdb;

        $code = strtoupper(sanitize_text_field($code));

        if (empty($code)) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_customers';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE referral_code = %s LIMIT 1",
                $code
            )
        );
    }

    /**
     * Links a freshly-registered customer to whoever referred them and
     * rewards the referrer with an entry in the current platform
     * draw(s). Silently does nothing on an invalid/self code — referral
     * bonuses should never block registration.
     */
    public static function apply_referral($new_customer_id, $referral_code)
    {
        global $wpdb;

        $new_customer_id = absint($new_customer_id);

        if (!$new_customer_id || empty($referral_code)) {
            return false;
        }

        $referrer = self::get_customer_by_referral_code($referral_code);

        if (!$referrer || intval($referrer->id) === $new_customer_id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_customers';

        $wpdb->update(
            $table,
            array('referred_by' => $referrer->id),
            array('id' => $new_customer_id),
            array('%d'),
            array('%d')
        );

        if (class_exists('RL_Draws')) {
            RL_Draws::grant_referral_entry($referrer->id);
        }

        return true;
    }

    /*
    =========================
    LOGIN USER (EMAIL OR USERNAME)
    =========================
    */

    public static function login_user($login_input, $password, $remember = true)
    {

        $login_input = sanitize_text_field($login_input);

        if (empty($login_input) || empty($password)) {
            return new WP_Error(
                'empty_fields',
                'Username or email, and password are required.'
            );
        }

        $username = $login_input;

        if (is_email($login_input)) {
            $user = get_user_by('email', $login_input);
            if ($user) {
                $username = $user->user_login;
            } else {
                return new WP_Error(
                    'invalid_credentials',
                    'Invalid credentials.'
                );
            }
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid credentials.'
            );
        }

        return $user;

    }

    /*
    =========================
    REQUEST PASSWORD RESET
    =========================
    */

    public static function request_password_reset($email_or_login)
    {

        $input = sanitize_text_field($email_or_login);

        if (empty($input)) {
            return new WP_Error(
                'empty_login',
                'Please enter an email address or username.'
            );
        }

        // Triggers WordPress core reset logic & sends the native email
        $result = retrieve_password($input);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;

    }

    /*
    =========================
    RESET PASSWORD
    =========================
    */

    public static function reset_password($key, $login, $new_password)
    {

        if (empty($key) || empty($login)) {
            return new WP_Error(
                'invalid_key',
                'Invalid or missing reset token.'
            );
        }

        if (empty($new_password) || strlen($new_password) < 8) {
            return new WP_Error(
                'weak_password',
                'Password must contain at least 8 characters.'
            );
        }

        // Validates key & login against database
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            return $user; // Key expired or invalid
        }

        // Updates user password & invalidates token
        reset_password($user, $new_password);

        return true;

    }

}