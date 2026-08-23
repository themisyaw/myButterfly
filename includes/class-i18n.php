<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * English/Dutch UI language. Deliberately not WordPress's standard
 * gettext (.po/.mo) — this app's strings live in PHP templates and
 * JS, and a from-scratch plugin with only two languages doesn't need
 * a translation-file compiler; a plain lookup table is far less
 * mechanical work to wire up and just as easy for a person to edit.
 *
 * Language choice: a cookie for guests, mirrored to usermeta once
 * someone logs in so it follows their account across devices instead
 * of resetting on a fresh browser. ?rl_lang=en|nl on any URL switches
 * it — caught early on 'init' (before any output) so it works as a
 * plain link with no JS required.
 */
class RL_I18n
{
    const COOKIE_NAME  = 'rl_lang';
    const SUPPORTED     = array('en', 'nl');
    const DEFAULT_LANG  = 'en';

    private static $lang    = null;
    private static $strings = null;

    public function __construct()
    {
        add_action('init', array($this, 'maybe_switch_language'), 1);
    }

    public function maybe_switch_language()
    {
        if (empty($_GET['rl_lang']) || !in_array($_GET['rl_lang'], self::SUPPORTED, true)) {
            return;
        }

        $lang = sanitize_text_field($_GET['rl_lang']);

        setcookie(self::COOKIE_NAME, $lang, time() + YEAR_IN_SECONDS, '/', '', is_ssl(), true);
        $_COOKIE[self::COOKIE_NAME] = $lang;

        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'rl_language', $lang);
        }

        self::$lang = $lang;

        wp_redirect(remove_query_arg('rl_lang'));
        exit;
    }

    public static function current_language()
    {
        if (self::$lang !== null) {
            return self::$lang;
        }

        if (is_user_logged_in()) {
            $meta = get_user_meta(get_current_user_id(), 'rl_language', true);
            if (in_array($meta, self::SUPPORTED, true)) {
                self::$lang = $meta;
                return self::$lang;
            }
        }

        if (isset($_COOKIE[self::COOKIE_NAME]) && in_array($_COOKIE[self::COOKIE_NAME], self::SUPPORTED, true)) {
            self::$lang = $_COOKIE[self::COOKIE_NAME];
            return self::$lang;
        }

        self::$lang = self::DEFAULT_LANG;
        return self::$lang;
    }

    /**
     * Translated string for $key, in the current language. Falls
     * back to English, then to the raw key itself — a string that's
     * missing from the table (or a key typo) shows up as visibly odd
     * text instead of a fatal error or a blank page.
     */
    public static function t($key)
    {
        if (self::$strings === null) {
            self::$strings = require RL_PLUGIN_PATH . 'includes/i18n-strings.php';
        }

        $lang = self::current_language();

        if (isset(self::$strings[$key][$lang])) {
            return self::$strings[$key][$lang];
        }

        if (isset(self::$strings[$key][self::DEFAULT_LANG])) {
            return self::$strings[$key][self::DEFAULT_LANG];
        }

        return $key;
    }

    /**
     * For admin/manager-entered content that has its own separate
     * Dutch field (e.g. a prize description) rather than a fixed app
     * string — picks the Dutch value when that's the active language
     * and something was actually typed into it, otherwise falls back
     * to the English value so content entered before the Dutch field
     * existed (or simply left blank) still displays instead of
     * showing nothing.
     */
    public static function pick($english, $dutch)
    {
        if (self::current_language() === 'nl' && !empty($dutch)) {
            return $dutch;
        }

        return $english;
    }

    /**
     * A compact "EN | NL" switcher — every link is a plain URL (the
     * current page with ?rl_lang=.. added), so it works with no JS.
     */
    public static function render_switcher()
    {
        $current = self::current_language();
        $base    = remove_query_arg('rl_lang');

        ob_start();
        ?>
        <div class="rl-lang-switcher">
            <a href="<?php echo esc_url(add_query_arg('rl_lang', 'en', $base)); ?>" class="rl-lang-option<?php echo $current === 'en' ? ' active' : ''; ?>">EN</a>
            <span class="rl-lang-divider">|</span>
            <a href="<?php echo esc_url(add_query_arg('rl_lang', 'nl', $base)); ?>" class="rl-lang-option<?php echo $current === 'nl' ? ' active' : ''; ?>">NL</a>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('rl_t')) {
    function rl_t($key)
    {
        return RL_I18n::t($key);
    }
}
