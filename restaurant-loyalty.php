<?php
/**
 * Plugin Name: Restaurant Loyalty
 * Description: Digital loyalty points system for restaurants.
 * Version: 2.12.0
 * Author: Your Name
 * Text Domain: restaurant-loyalty
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RL_VERSION', '2.12.0');

define(
    'RL_PLUGIN_PATH',
    plugin_dir_path(__FILE__)
);

define(
    'RL_PLUGIN_URL',
    plugin_dir_url(__FILE__)
);

require_once RL_PLUGIN_PATH . 'includes/class-db.php';
require_once RL_PLUGIN_PATH . 'includes/class-activator.php';
require_once RL_PLUGIN_PATH . 'includes/class-deactivator.php';

require_once RL_PLUGIN_PATH . 'includes/class-qr.php';
require_once RL_PLUGIN_PATH . 'includes/class-brands.php';
require_once RL_PLUGIN_PATH . 'includes/class-brand-hours.php';
require_once RL_PLUGIN_PATH . 'includes/class-locations.php';
require_once RL_PLUGIN_PATH . 'includes/class-users.php';
require_once RL_PLUGIN_PATH . 'includes/class-google-auth.php';
require_once RL_PLUGIN_PATH . 'includes/class-turnstile.php';
require_once RL_PLUGIN_PATH . 'includes/class-draws.php';
require_once RL_PLUGIN_PATH . 'includes/class-notifications.php';
require_once RL_PLUGIN_PATH . 'includes/class-web-push.php';
require_once RL_PLUGIN_PATH . 'includes/class-push-subscriptions.php';
require_once RL_PLUGIN_PATH . 'includes/class-notification-audience.php';
require_once RL_PLUGIN_PATH . 'includes/class-points.php';
require_once RL_PLUGIN_PATH . 'includes/class-transactions.php';
require_once RL_PLUGIN_PATH . 'includes/class-redeem-categories.php';
require_once RL_PLUGIN_PATH . 'includes/class-redeem-items.php';

require_once RL_PLUGIN_PATH . 'includes/class-i18n.php';
require_once RL_PLUGIN_PATH . 'includes/class-shortcodes.php';
require_once RL_PLUGIN_PATH . 'includes/class-manager-pages.php';
require_once RL_PLUGIN_PATH . 'includes/class-assets.php';

require_once RL_PLUGIN_PATH . 'includes/class-ajax.php';

require_once RL_PLUGIN_PATH . 'admin/class-redeem-admin.php';
require_once RL_PLUGIN_PATH . 'admin/class-brands-admin.php';
require_once RL_PLUGIN_PATH . 'admin/class-draws-admin.php';
require_once RL_PLUGIN_PATH . 'admin/class-settings-admin.php';
require_once RL_PLUGIN_PATH . 'admin/class-notifications-admin.php';

register_activation_hook(
    __FILE__,
    array(
        'RL_Activator',
        'activate'
    )
);

register_deactivation_hook(
    __FILE__,
    array(
        'RL_Deactivator',
        'deactivate'
    )
);

/*
=========================
AUTO-UPGRADE ON VERSION BUMP (LOCAL/DEV ONLY)
Runs the activation routine again (creates/alters tables, roles,
pages — including destructive legacy-column/table cleanup) whenever
RL_VERSION is newer than what's stored, so you don't have to manually
deactivate/reactivate after every update while developing.

Deliberately restricted to local/development environments
(WP_ENVIRONMENT_TYPE) — on staging/production this migration touches
real customer data and should never fire automatically on whichever
visitor happens to load a page first. There, apply an update by
deactivating and reactivating the plugin in wp-admin instead: that
still runs this exact same RL_Activator::activate() routine (via
register_activation_hook below), but as a deliberate, timed action
you control — ideally right after confirming a backup exists.
=========================
*/

add_action('init', function () {
    if (!in_array(wp_get_environment_type(), array('local', 'development'), true)) {
        return;
    }

    if (get_option('rl_version') !== RL_VERSION) {
        RL_Activator::activate();
    }
}, 20);

/*
=========================
HIDE WORDPRESS ADMIN BAR
FOR NON ADMINS
=========================
*/

add_action(
    'after_setup_theme',
    'rl_hide_admin_bar'
);

function rl_hide_admin_bar()
{
    if (!current_user_can('administrator')) {
        show_admin_bar(false);
    }
}

/*
=========================
CUSTOM PASSWORD RESET EMAIL
=========================
*/

add_filter('retrieve_password_notification_email', 'rl_custom_reset_password_email', 10, 4);

function rl_custom_reset_password_email($defaults, $key, $user_login, $user_data) {
    $reset_url = add_query_arg(
        array(
            'key'   => $key,
            'login' => rawurlencode($user_login),
        ),
        site_url('/reset-password')
    );

    // Same branded HTML style as RL_Notifications' emails — a real
    // button survives across mail clients far more reliably than a
    // plain-text link (some render plain text as HTML and silently
    // strip anything that looks like an unrecognized tag).
    $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;">';
    $body .= '<h2 style="color:#0f766e;margin-bottom:4px;">Reset your password</h2>';
    $body .= '<p style="font-size:15px;color:#111827;">Someone requested a password reset for the account: <strong>' . esc_html($user_login) . '</strong>.</p>';
    $body .= '<p style="font-size:15px;color:#111827;">If this was you, click below to choose a new password.</p>';
    $body .= '<p style="margin-top:20px;"><a href="' . esc_url($reset_url) . '" style="background:#0f766e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block;">Reset Password</a></p>';
    $body .= '<p style="font-size:12px;color:#9ca3af;margin-top:30px;">If this wasn\'t you, you can safely ignore this email — your password won\'t change.</p>';
    $body .= '</div>';

    $defaults['message'] = $body;
    $defaults['headers'] = array('Content-Type: text/html; charset=UTF-8');

    return $defaults;
}

/*
=========================
SAFETY NET: CUSTOMER IDENTITY
Any user that ends up with the 'customer' role — whether through
self-registration or being created/promoted directly from wp-admin
Users — gets a matching rl_customers row (QR token etc.) so the
dashboard always has something to show them.
=========================
*/

add_action('set_user_role', function ($user_id, $role) {
    if ($role === 'customer' && class_exists('RL_Users')) {
        RL_Users::ensure_customer_identity($user_id);
    }
}, 10, 2);

/*
=========================
INIT PLUGIN
=========================
*/

function rl_start_plugin()
{
    new RL_I18n();
    $shortcodes = new RL_Shortcodes();
    new RL_Manager_Pages($shortcodes);
    new RL_Assets();
    new RL_Ajax();
    new RL_Google_Auth();
    new RL_Redeem_Admin();
    new RL_Brands_Admin();
    new RL_Draws_Admin();
    new RL_Settings_Admin();
    new RL_Notifications_Admin();
}

add_action(
    'plugins_loaded',
    'rl_start_plugin'
);


add_action('wp_head', function () {
    // Optional: Only output on your plugin pages if you want to restrict it
    // e.g., if ( is_page('customer-dashboard') || is_page('login') ) { ... }

    $favicon_url = RL_PLUGIN_URL . 'assets/images/butterfly-favicon.png'; // Path to your logo/ico
    ?>
    <link rel="icon" type="image/png" href="<?php echo esc_url($favicon_url); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($favicon_url); ?>">
    <?php
}, 1);