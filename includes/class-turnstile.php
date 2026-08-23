<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cloudflare Turnstile bot check for the public registration form —
 * the one anonymous, unauthenticated form on the site, and the one
 * that matters most to protect since a bot registering fake accounts
 * can also farm the referral system's draw entries. Deliberately not
 * used anywhere else (login, and definitely not the staff QR scan
 * flow) — those either aren't public/anonymous or need to stay as
 * frictionless as possible.
 */
class RL_Turnstile
{
    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function is_configured()
    {
        return self::site_key() !== '' && self::secret_key() !== '';
    }

    public static function site_key()
    {
        return trim(get_option('rl_turnstile_site_key', ''));
    }

    public static function secret_key()
    {
        return trim(get_option('rl_turnstile_secret_key', ''));
    }

    /**
     * The widget + the Turnstile script tag, ready to echo directly
     * into a form. Callers should check is_configured() first and
     * skip calling this entirely when it's not — no key, no script,
     * no layout shift.
     */
    public static function render_widget()
    {
        if (!self::is_configured()) {
            return '';
        }

        ob_start();
        ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(self::site_key()); ?>" data-theme="light"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Verifies a submitted token server-side. Always call this
     * before trusting a form submission that rendered the widget —
     * the widget alone is client-side only and proves nothing by
     * itself.
     */
    public static function verify($token)
    {
        if (!self::is_configured()) {
            // Not configured means the widget was never shown, so
            // there's nothing to verify against — don't block
            // registration over a check that isn't set up yet.
            return true;
        }

        $token = trim((string) $token);

        if (empty($token)) {
            return false;
        }

        $response = wp_remote_post(
            self::VERIFY_URL,
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => self::secret_key(),
                    'response' => $token,
                    'remoteip' => self::client_ip(),
                ),
            )
        );

        if (is_wp_error($response)) {
            // Fail closed on our side, but don't hard-block a real
            // customer over a transient network error to Cloudflare —
            // treat a verification-service outage as a pass rather
            // than locking registration entirely.
            return true;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['success']);
    }

    private static function client_ip()
    {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return '';
    }
}
