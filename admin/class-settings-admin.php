<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * wp-admin screen (site admin only) for platform-wide integrations
 * that aren't scoped to a single brand — currently just the Google
 * OAuth credentials that power "Continue with Google" on the
 * front-end login page.
 */
class RL_Settings_Admin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_menu_page(
            'Settings',
            'Settings',
            'manage_options',
            'rl-settings',
            array($this, 'page'),
            'dashicons-admin-generic',
            33
        );
    }

    public function page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission.');
        }

        $this->handle_save();
        $this->handle_generate_vapid_keys();

        $client_id       = get_option('rl_google_client_id', '');
        $client_secret   = get_option('rl_google_client_secret', '');
        $turnstile_site  = get_option('rl_turnstile_site_key', '');
        $turnstile_secret = get_option('rl_turnstile_secret_key', '');
        $vapid_subject   = get_option('rl_vapid_subject', '');
        ?>
        <div class="wrap">
            <h1>Settings</h1>

            <h2>Google Sign-In</h2>
            <p class="description">
                Lets customers, brand managers, and staff log in with their Google account from the front-end login page, instead of (or alongside) a password. Existing accounts are matched by email; a Google sign-in from an email that doesn't match any account creates a new customer account automatically — brand manager and staff accounts are never auto-created this way, only matched.
            </p>

            <table class="form-table">
                <tr>
                    <th>Authorized redirect URI</th>
                    <td>
                        <code><?php echo esc_html(RL_Google_Auth::redirect_uri()); ?></code>
                        <p class="description">
                            In <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a>, create an OAuth 2.0 Client ID (Application type: "Web application"), and paste this exact URL into its "Authorized redirect URIs" list.
                        </p>
                    </td>
                </tr>
            </table>

            <form method="post">
                <?php wp_nonce_field('rl_save_settings', 'rl_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Client ID</th>
                        <td><input type="text" name="rl_google_client_id" value="<?php echo esc_attr($client_id); ?>" style="width:420px;"></td>
                    </tr>
                    <tr>
                        <th>Client Secret</th>
                        <td><input type="password" autocomplete="off" name="rl_google_client_secret" value="<?php echo esc_attr($client_secret); ?>" style="width:420px;"></td>
                    </tr>
                </table>

                <h2>Bot Protection (Cloudflare Turnstile)</h2>
                <p class="description">
                    Shows an invisible bot check on the public registration form only — never on login, and never on the staff QR scan flow. Get a free site key/secret pair from <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">the Cloudflare Turnstile dashboard</a> (add both your live domain and any local dev domain as allowed hostnames there).
                </p>
                <table class="form-table">
                    <tr>
                        <th>Site Key</th>
                        <td><input type="text" name="rl_turnstile_site_key" value="<?php echo esc_attr($turnstile_site); ?>" style="width:420px;"></td>
                    </tr>
                    <tr>
                        <th>Secret Key</th>
                        <td><input type="password" autocomplete="off" name="rl_turnstile_secret_key" value="<?php echo esc_attr($turnstile_secret); ?>" style="width:420px;"></td>
                    </tr>
                </table>

                <h2>Push Notifications</h2>
                <p class="description">
                    Powers the browser push notifications sent from the admin Notifications composer, and the automatic winner alert. Requires the site to be served over HTTPS (LocalWP: use the "Trust" button to enable a locally-trusted certificate; on Hostinger, a real SSL certificate) — browsers refuse push subscriptions on plain HTTP except on literal <code>localhost</code>.
                </p>
                <table class="form-table">
                    <tr>
                        <th>Contact email</th>
                        <td>
                            <input type="email" name="rl_vapid_subject_email" value="<?php echo esc_attr(str_replace('mailto:', '', $vapid_subject)); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" style="width:420px;">
                            <p class="description">Included in the push identification token so a push service can contact you if something's misbehaving — not shown to customers.</p>
                        </td>
                    </tr>
                </table>

                <button type="submit" name="rl_save_settings" class="button button-primary">Save Settings</button>
            </form>

            <?php if (!RL_Web_Push::is_supported()): ?>
                <p style="color:#b91c1c;font-weight:600;">&#9888; This server's PHP build doesn't support push notifications (missing openssl_pkey_derive — requires PHP 8.1+). Ask your host to update the PHP version before generating keys.</p>
            <?php elseif (RL_Web_Push::has_keys()): ?>
                <p style="color:#0f766e;font-weight:600;">&#10003; Push notification keys are set up.</p>
                <table class="form-table">
                    <tr>
                        <th>Public key</th>
                        <td><code style="word-break:break-all;"><?php echo esc_html(RL_Web_Push::public_key()); ?></code></td>
                    </tr>
                </table>
                <form method="post" onsubmit="return confirm('Regenerating replaces the current keys. Existing subscribers keep working, but do this only if you have a real reason to.');">
                    <?php wp_nonce_field('rl_generate_vapid_keys', 'rl_vapid_nonce'); ?>
                    <button type="submit" name="rl_generate_vapid_keys" class="button">Regenerate Keys</button>
                </form>
            <?php else: ?>
                <p class="description">Push notifications aren't set up yet.</p>
                <form method="post">
                    <?php wp_nonce_field('rl_generate_vapid_keys', 'rl_vapid_nonce'); ?>
                    <button type="submit" name="rl_generate_vapid_keys" class="button button-primary">Generate Push Notification Keys</button>
                </form>
            <?php endif; ?>

            <?php if (RL_Google_Auth::is_configured()): ?>
                <p style="color:#0f766e;font-weight:600;">&#10003; Google Sign-In is configured — the button now appears on the login page.</p>
            <?php else: ?>
                <p class="description">Google Sign-In is not configured yet — the button stays hidden on the login page until both fields above are filled in.</p>
            <?php endif; ?>

            <?php if (RL_Turnstile::is_configured()): ?>
                <p style="color:#0f766e;font-weight:600;">&#10003; Turnstile is configured — the registration form is now protected.</p>
            <?php else: ?>
                <p class="description">Turnstile is not configured yet — registration stays open with no bot check until both keys above are filled in.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handle_save()
    {
        if (!isset($_POST['rl_save_settings'])) {
            return;
        }

        if (!isset($_POST['rl_settings_nonce']) || !wp_verify_nonce($_POST['rl_settings_nonce'], 'rl_save_settings')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
            return;
        }

        update_option('rl_google_client_id', sanitize_text_field($_POST['rl_google_client_id'] ?? ''));
        update_option('rl_google_client_secret', sanitize_text_field($_POST['rl_google_client_secret'] ?? ''));
        update_option('rl_turnstile_site_key', sanitize_text_field($_POST['rl_turnstile_site_key'] ?? ''));
        update_option('rl_turnstile_secret_key', sanitize_text_field($_POST['rl_turnstile_secret_key'] ?? ''));

        $vapid_email = sanitize_email($_POST['rl_vapid_subject_email'] ?? '');
        update_option('rl_vapid_subject', $vapid_email ? ('mailto:' . $vapid_email) : '');

        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    private function handle_generate_vapid_keys()
    {
        if (!isset($_POST['rl_generate_vapid_keys'])) {
            return;
        }

        if (!isset($_POST['rl_vapid_nonce']) || !wp_verify_nonce($_POST['rl_vapid_nonce'], 'rl_generate_vapid_keys')) {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
            return;
        }

        if (!RL_Web_Push::is_supported()) {
            echo '<div class="notice notice-error"><p>This server does not support push notifications (PHP 8.1+ required).</p></div>';
            return;
        }

        $generated = RL_Web_Push::generate_vapid_keys();

        if ($generated) {
            echo '<div class="notice notice-success"><p>Push notification keys generated.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Could not generate keys.';

            $error = RL_Web_Push::last_error();

            if ($error) {
                echo ' Reason: <code>' . esc_html($error) . '</code>';
            }

            echo '</p></div>';
        }
    }
}
