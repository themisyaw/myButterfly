<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outbound transactional emails. Currently just the lucky-draw
 * winner notification, sent alongside the browser push notification
 * (see RL_Draws::pick_winner() callers) so a winner finds out even
 * if they never enabled/subscribed to push.
 *
 * Actual delivery depends on the site having real SMTP configured
 * (e.g. WP Mail SMTP + a provider like Brevo) — wp_mail() without
 * that is unreliable on most hosting and will likely fail silently
 * or land in spam. This class doesn't care how mail gets delivered,
 * it just calls wp_mail() the same way any WordPress email does.
 */
class RL_Notifications
{
    /**
     * Shared HTML signature block appended to every outbound branded
     * email (winner/entry/custom notifications, email verification,
     * password reset). Table-based layout — not flexbox — since this
     * renders in real mail clients (Outlook's Word engine doesn't
     * support flexbox), and the logo is referenced by URL rather than
     * embedded as a data URI, since Outlook desktop also doesn't
     * render inline base64 images. The image is served straight from
     * the plugin's own assets folder, same pattern already used for
     * the site favicon.
     */
    public static function email_signature_html()
    {
        $logo_url = RL_PLUGIN_URL . 'assets/images/email-logo.png';
        $site_url = untrailingslashit(preg_replace('#^https?://#', '', site_url()));

        $html  = '<div style="border-top:1px solid #e5e7eb;padding-top:14px;margin-top:24px;">';
        $html .= '<table cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= '<td style="vertical-align:middle;padding-right:10px;"><img src="' . esc_url($logo_url) . '" width="32" height="27" style="display:block;" alt="MyButterfly"></td>';
        $html .= '<td style="vertical-align:middle;line-height:1.5;font-size:12px;color:#6b7280;font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<strong style="color:#0f766e;">MyButterfly</strong> &middot; Loyalty made simple<br>';
        $html .= '<a href="' . esc_url(site_url('/')) . '" style="color:#0f766e;text-decoration:none;">' . esc_html($site_url) . '</a>';
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Emails a draw's winner. Two templates: a platform-branded one
     * for the Butterfly-wide draw, and a restaurant-branded one that
     * names the brand/location for a brand- or location-scoped draw
     * — using the same RL_Draws::get_public_scope_label() logic the
     * rest of the app already uses for this, so the wording matches
     * what the customer sees on prize cards.
     */
    public static function send_winner_email($draw_id, $customer_id)
    {
        global $wpdb;

        $draw_id     = absint($draw_id);
        $customer_id = absint($customer_id);

        if (!$draw_id || !$customer_id) {
            return false;
        }

        $draw = RL_Draws::get($draw_id);

        if (!$draw) {
            return false;
        }

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}rl_customers WHERE id = %d",
                $customer_id
            )
        );

        $user = $user_id ? get_userdata($user_id) : null;

        if (!$user || empty($user->user_email)) {
            return false;
        }

        $is_platform = empty($draw->brand_id);

        if ($is_platform) {
            $subject = 'You won the Butterfly giveaway! 🦋';
            $intro   = "Great news — you're the winner of this month's Butterfly platform giveaway.";
        } else {
            $scope_label = RL_Draws::get_public_scope_label($draw);
            $subject     = 'You won a prize at ' . $scope_label . '! 🎉';
            $intro       = 'Great news — you\'re the winner of the lucky draw at <strong>' . esc_html($scope_label) . '</strong>.';
        }

        $dashboard_url = site_url('/my-entries');

        $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;">';
        $body .= '<h2 style="color:#0f766e;margin-bottom:4px;">' . esc_html($draw->title) . '</h2>';
        $body .= '<p style="font-size:15px;color:#111827;">Hi ' . esc_html($user->display_name) . ',</p>';
        $body .= '<p style="font-size:15px;color:#111827;">' . $intro . '</p>';

        if (!empty($draw->prize_description)) {
            $body .= '<p style="font-size:15px;color:#374151;background:#f8fafc;padding:14px;border-radius:12px;">' . esc_html($draw->prize_description) . '</p>';
        }

        $body .= '<p style="font-size:14px;color:#6b7280;">Check your My Entries page for details, or get in touch with the restaurant to arrange collecting your prize.</p>';
        $body .= '<p style="margin-top:20px;"><a href="' . esc_url($dashboard_url) . '" style="background:#0f766e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block;">View My Entries</a></p>';
        $body .= self::email_signature_html();
        $body .= '</div>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * Emails a customer the moment they earn a new lucky-draw entry —
     * called once per matched draw from RL_Draws::
     * process_entries_for_transaction(), right after the entry row
     * itself is inserted. Same two-template split as
     * send_winner_email() (platform-wide vs. brand/location-scoped
     * wording), deliberately not the winner template itself — this is
     * "you're in the running," not "you won."
     */
    public static function send_entry_email($draw_id, $customer_id)
    {
        global $wpdb;

        $draw_id     = absint($draw_id);
        $customer_id = absint($customer_id);

        if (!$draw_id || !$customer_id) {
            return false;
        }

        $draw = RL_Draws::get($draw_id);

        if (!$draw) {
            return false;
        }

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}rl_customers WHERE id = %d",
                $customer_id
            )
        );

        $user = $user_id ? get_userdata($user_id) : null;

        if (!$user || empty($user->user_email)) {
            return false;
        }

        $is_platform = empty($draw->brand_id);

        if ($is_platform) {
            $subject = "You're entered! 🎟️";
            $intro   = "Nice — that purchase just earned you an entry into the Butterfly platform giveaway.";
        } else {
            $scope_label = RL_Draws::get_public_scope_label($draw);
            $subject     = "You're entered at " . $scope_label . "! 🎟️";
            $intro       = 'Nice — that purchase just earned you an entry into the lucky draw at <strong>' . esc_html($scope_label) . '</strong>.';
        }

        $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;">';
        $body .= '<h2 style="color:#0f766e;margin-bottom:4px;">' . esc_html($draw->title) . '</h2>';
        $body .= '<p style="font-size:15px;color:#111827;">Hi ' . esc_html($user->display_name) . ',</p>';
        $body .= '<p style="font-size:15px;color:#111827;">' . $intro . '</p>';

        if (!empty($draw->prize_description)) {
            $body .= '<p style="font-size:15px;color:#374151;background:#f8fafc;padding:14px;border-radius:12px;">' . esc_html($draw->prize_description) . '</p>';
        }

        $body .= '<p style="font-size:14px;color:#6b7280;">Keep earning points for more entries — check My Entries any time to see where you stand.</p>';
        $body .= '<p style="margin-top:20px;"><a href="' . esc_url(site_url('/my-entries')) . '" style="background:#0f766e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block;">View My Entries</a></p>';
        $body .= self::email_signature_html();
        $body .= '</div>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * The recipient for support/bug-report messages from the Settings
     * page — a fixed inbox, not a wp-admin setting, since there's
     * only one person reading it right now. Kept as a single named
     * constant so there's exactly one place to change if that ever
     * needs to become configurable.
     */
    const SUPPORT_EMAIL = 'themisspyridhs@gmail.com';

    /**
     * Sends a customer's Settings > Support submission. Name/email
     * always come from the logged-in $user object, never from request
     * input, so the "From" identity in the email body can't be
     * spoofed — only the subject/message are customer-supplied (and
     * already sanitized by the caller). Reply-To is set to the
     * customer's own address so replying in a normal mail client goes
     * straight back to them.
     */
    public static function send_support_email($user, $subject, $message)
    {
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $email_subject = 'Butterfly support: ' . $subject;

        $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;">';
        $body .= '<h2 style="color:#0f766e;margin-bottom:4px;">New support message</h2>';
        $body .= '<p style="font-size:13px;color:#6b7280;">From <strong>' . esc_html($user->display_name) . '</strong> &lt;' . esc_html($user->user_email) . '&gt;</p>';
        $body .= '<p style="font-size:15px;color:#111827;"><strong>' . esc_html($subject) . '</strong></p>';
        $body .= '<p style="font-size:15px;color:#374151;background:#f8fafc;padding:14px;border-radius:12px;white-space:pre-line;">' . esc_html($message) . '</p>';
        $body .= '</div>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $user->display_name . ' <' . $user->user_email . '>',
        );

        return wp_mail(self::SUPPORT_EMAIL, $email_subject, $body, $headers);
    }

    /**
     * Generic branded email for the admin notification composer —
     * unlike send_winner_email() there's no draw/restaurant context
     * here, just whatever title/body the admin typed, wrapped in the
     * same styling so it still looks like it came from the app.
     */
    public static function send_custom_email($customer_id, $title, $body_text)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return false;
        }

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}rl_customers WHERE id = %d",
                $customer_id
            )
        );

        $user = $user_id ? get_userdata($user_id) : null;

        if (!$user || empty($user->user_email)) {
            return false;
        }

        $body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;">';
        $body .= '<h2 style="color:#0f766e;margin-bottom:4px;">' . esc_html($title) . '</h2>';
        $body .= '<p style="font-size:15px;color:#111827;">Hi ' . esc_html($user->display_name) . ',</p>';
        $body .= '<p style="font-size:15px;color:#374151;white-space:pre-line;">' . esc_html($body_text) . '</p>';
        $body .= '<p style="margin-top:20px;"><a href="' . esc_url(site_url('/')) . '" style="background:#0f766e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block;">Open Butterfly</a></p>';
        $body .= self::email_signature_html();
        $body .= '</div>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($user->user_email, $title, $body, $headers);
    }
}
