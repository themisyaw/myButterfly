<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Continue with Google" for the front-end login/register page
 * ([rl_home] / [rl_register]) — covers customers, brand managers,
 * and location staff, since they all share that same login form.
 *
 * Implements the OAuth 2.0 authorization code flow directly against
 * Google's endpoints (no SDK/composer dependency): send the visitor
 * to Google, they come back with a `code`, we exchange it for an
 * access token, fetch their verified email/name, then either log an
 * existing account in or auto-register a new customer. Brand
 * manager / location staff accounts are never auto-created this way
 * — those must already exist (created from wp-admin) for Google
 * sign-in to work for them; only unrecognized emails become new
 * customers.
 */
class RL_Google_Auth
{
    const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL      = 'https://oauth2.googleapis.com/token';
    const USERINFO_URL   = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct()
    {
        add_action('init', array($this, 'maybe_handle_callback'));
    }

    public static function is_configured()
    {
        return self::client_id() !== '' && self::client_secret() !== '';
    }

    public static function client_id()
    {
        return trim(get_option('rl_google_client_id', ''));
    }

    public static function client_secret()
    {
        return trim(get_option('rl_google_client_secret', ''));
    }

    /**
     * Must be pasted into Google Cloud Console -> Credentials -> this
     * OAuth client -> "Authorized redirect URIs", exactly as-is.
     */
    public static function redirect_uri()
    {
        return add_query_arg('rl_google_callback', '1', site_url('/'));
    }

    /**
     * The href for the "Continue with Google" button. Empty string
     * if not configured yet — callers should hide the button then.
     *
     * The `state` value is a random token bound to this specific
     * browser via a short-lived cookie (not just a wp_create_nonce()
     * action nonce) — a plain nonce for a logged-out visitor isn't
     * tied to any session, so the same valid state would work for
     * every visitor within its lifetime. That would let an attacker
     * start the OAuth flow under their own Google account, capture
     * the resulting authorization code+state pair, and hand that
     * callback URL to a victim — whose browser would complete the
     * exchange and log them into the attacker's account (login CSRF).
     * Binding state to a cookie only this browser holds closes that.
     */
    public static function get_authorize_url()
    {
        if (!self::is_configured()) {
            return '';
        }

        $state = wp_generate_password(32, false);

        setcookie('rl_google_oauth_state', $state, array(
            'expires'  => time() + 10 * MINUTE_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));

        $params = array(
            'client_id'     => self::client_id(),
            'redirect_uri'  => self::redirect_uri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        );

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Hooked to 'init' so it can wp_redirect()/exit before any
     * output starts — same pattern as the other one-time/early
     * handlers in the main plugin file.
     */
    public function maybe_handle_callback()
    {
        if (empty($_GET['rl_google_callback'])) {
            return;
        }

        if (is_user_logged_in()) {
            wp_redirect(site_url('/'));
            exit;
        }

        if (!empty($_GET['error'])) {
            $this->fail('access_denied');
        }

        $expected_state = isset($_COOKIE['rl_google_oauth_state']) ? sanitize_text_field($_COOKIE['rl_google_oauth_state']) : '';

        // Clear the single-use state cookie regardless of outcome —
        // it should never be valid for a second callback.
        setcookie('rl_google_oauth_state', '', array(
            'expires'  => time() - HOUR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));

        if (
            empty($_GET['code'])
            || empty($_GET['state'])
            || empty($expected_state)
            || !hash_equals($expected_state, sanitize_text_field($_GET['state']))
        ) {
            $this->fail('invalid_request');
        }

        if (!self::is_configured()) {
            $this->fail('not_configured');
        }

        $token_response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 15,
                'body'    => array(
                    'code'          => sanitize_text_field($_GET['code']),
                    'client_id'     => self::client_id(),
                    'client_secret' => self::client_secret(),
                    'redirect_uri'  => self::redirect_uri(),
                    'grant_type'    => 'authorization_code',
                ),
            )
        );

        if (is_wp_error($token_response)) {
            $this->fail('token_request_failed');
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);

        if (empty($token_body['access_token'])) {
            $this->fail('token_exchange_failed');
        }

        $userinfo_response = wp_remote_get(
            self::USERINFO_URL,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token_body['access_token'],
                ),
            )
        );

        if (is_wp_error($userinfo_response)) {
            $this->fail('userinfo_request_failed');
        }

        $profile = json_decode(wp_remote_retrieve_body($userinfo_response), true);

        $email          = isset($profile['email']) ? sanitize_email($profile['email']) : '';
        $name           = isset($profile['name']) ? sanitize_text_field($profile['name']) : '';
        $email_verified = !empty($profile['email_verified']);

        if (empty($email) || !is_email($email) || !$email_verified) {
            $this->fail('unverified_email');
        }

        $user = get_user_by('email', $email);

        if (!$user) {

            if (empty($name)) {
                $name = current(explode('@', $email));
            }

            // New Google sign-ins always become plain customers —
            // brand manager / location staff accounts are only ever
            // created deliberately from wp-admin (see RL_Users::
            // create_staff_user()), never auto-provisioned here.
            $new_user_id = RL_Users::create_customer($name, $email, wp_generate_password(24, true), '', true);

            if (is_wp_error($new_user_id)) {
                $this->fail('account_creation_failed');
            }

            update_user_meta($new_user_id, 'rl_google_linked', 1);

            $user = get_user_by('id', $new_user_id);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        wp_redirect(site_url('/'));
        exit;
    }

    private function fail($reason)
    {
        wp_redirect(add_query_arg('rl_google_error', $reason, site_url('/login')));
        exit;
    }
}
