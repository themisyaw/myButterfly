<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * wp-admin screen (site admin only — see the "who can send" decision
 * this was scoped with) for broadcasting a push and/or email
 * notification to a chosen audience: everyone, one specific
 * customer, or everyone who's visited a given brand at least once.
 *
 * There's no separate "send yourself a test" button — a brand
 * manager/staff/admin account isn't a customer and has no push
 * subscription of its own to test with. To verify delivery before a
 * real broadcast, subscribe from a test CUSTOMER account in a
 * browser, then send using the "Single user" audience targeting
 * that account.
 */
class RL_Notifications_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_menu_page(
            'Notifications',
            'Notifications',
            'manage_options',
            'rl-notifications',
            array($this, 'page'),
            'dashicons-megaphone',
            34
        );
    }

    public function page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission.');
        }

        $result = $this->handle_send();

        echo '<div class="wrap"><h1>Notifications</h1>';

        if (!RL_Web_Push::is_supported()) {
            echo '<div class="notice notice-warning"><p>This server\'s PHP build doesn\'t support push notifications (requires PHP 8.1+) — email will still work. See <a href="' . esc_url(admin_url('admin.php?page=rl-settings')) . '">Settings</a>.</p></div>';
        } elseif (!RL_Web_Push::has_keys()) {
            echo '<div class="notice notice-warning"><p>Push notification keys haven\'t been generated yet — email will still work. See <a href="' . esc_url(admin_url('admin.php?page=rl-settings')) . '">Settings</a>.</p></div>';
        }

        if ($result) {
            $this->render_result($result);
        }

        $this->render_form();

        echo '<hr><h2>Recent notifications</h2>';
        $this->render_log();

        echo '</div>';
    }

    private function render_result($result)
    {
        if (!empty($result['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';

            if (!empty($result['matches'])) {
                echo '<ul>';
                foreach ($result['matches'] as $match) {
                    echo '<li>' . esc_html($match['name']) . ' — ' . esc_html($match['email']) . '</li>';
                }
                echo '</ul>';
            }

            return;
        }

        echo '<div class="notice notice-success"><p>Sent to ' . intval($result['recipients']) . ' customer(s).';

        if (isset($result['push'])) {
            echo ' Push: ' . intval($result['push']['sent']) . ' delivered, ' . intval($result['push']['failed']) . ' failed.';
        }

        if (isset($result['email_sent'])) {
            echo ' Email: ' . intval($result['email_sent']) . ' sent.';
        }

        echo '</p></div>';
    }

    private function render_form()
    {
        $brands = RL_Brands::get_all(true);
        ?>
        <form method="post">
            <?php wp_nonce_field('rl_send_notification', 'rl_notification_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th>Title</th>
                    <td><input type="text" name="title" required maxlength="255" style="width:420px;" placeholder="e.g. New prize just dropped!"></td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td><textarea name="body" required rows="4" style="width:420px;" placeholder="What do you want to tell them?"></textarea></td>
                </tr>
                <tr>
                    <th>Audience</th>
                    <td>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="radio" name="audience_type" value="all" checked>
                            All users
                        </label>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="radio" name="audience_type" value="single">
                            Single user —
                            <input type="text" name="single_user_query" placeholder="Name or email">
                        </label>
                        <p class="description" style="margin:0 0 12px 24px;">If more than one account matches, you'll be asked to narrow it down.</p>
                        <label style="display:block;">
                            <input type="radio" name="audience_type" value="brand">
                            Visitors of a specific brand —
                            <select name="brand_id">
                                <option value="">Select a brand&hellip;</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo intval($brand->id); ?>"><?php echo esc_html($brand->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <p class="description" style="margin:4px 0 0 24px;">Everyone who has at least one recorded visit (points added or redeemed) at any location under this brand.</p>
                    </td>
                </tr>
                <tr>
                    <th>Channel</th>
                    <td>
                        <label style="margin-right:16px;"><input type="checkbox" name="channels[]" value="push" checked> Push notification</label>
                        <label><input type="checkbox" name="channels[]" value="email"> Email</label>
                    </td>
                </tr>
            </table>

            <button type="submit" name="rl_send_notification" class="button button-primary" onclick="return confirm('Send this notification now? This can\'t be undone.');">Send Notification</button>
        </form>
        <?php
    }

    private function render_log()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_notifications_log';
        $rows  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 20");

        if (empty($rows)) {
            echo '<p class="description">Nothing sent yet.</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Title</th>
                    <th>Audience</th>
                    <th>Channel</th>
                    <th>Recipients</th>
                    <th>Push</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('M j, Y g:ia', $row->created_at)); ?></td>
                        <td><?php echo esc_html($row->title); ?></td>
                        <td><?php echo esc_html($this->describe_audience($row->audience_type, $row->audience_ref)); ?></td>
                        <td><?php echo esc_html($row->channel); ?></td>
                        <td><?php echo intval($row->recipient_count); ?></td>
                        <td><?php echo intval($row->push_sent_count); ?></td>
                        <td><?php echo intval($row->email_sent_count); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function describe_audience($type, $ref)
    {
        if ($type === 'all') {
            return 'All users';
        }

        if ($type === 'single') {
            return 'Single user (#' . intval($ref) . ')';
        }

        if ($type === 'brand') {
            $brand = RL_Brands::get(intval($ref));
            return $brand ? ('Visitors of ' . $brand->name) : 'Visitors of a deleted brand';
        }

        return $type;
    }

    private function handle_send()
    {
        if (!isset($_POST['rl_send_notification'])) {
            return null;
        }

        if (!isset($_POST['rl_notification_nonce']) || !wp_verify_nonce($_POST['rl_notification_nonce'], 'rl_send_notification')) {
            return array('error' => 'Security check failed.');
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $body  = sanitize_textarea_field($_POST['body'] ?? '');

        if (empty($title) || empty($body)) {
            return array('error' => 'Title and message are both required.');
        }

        $channels = array_map('sanitize_text_field', (array) ($_POST['channels'] ?? array()));
        $channels = array_intersect($channels, array('push', 'email'));

        if (empty($channels)) {
            return array('error' => 'Choose at least one channel (push or email).');
        }

        $audience_type = sanitize_text_field($_POST['audience_type'] ?? 'all');
        $audience_ref  = null;
        $customer_ids  = array();

        if ($audience_type === 'all') {

            $customer_ids = RL_Notification_Audience::resolve_all();

        } elseif ($audience_type === 'single') {

            $query = sanitize_text_field($_POST['single_user_query'] ?? '');

            if (empty($query)) {
                return array('error' => 'Enter a name or email to search for.');
            }

            $matches = RL_Users::search_customers($query, 6);

            if (empty($matches)) {
                return array('error' => 'No customer matched "' . $query . '".');
            }

            if (count($matches) > 1) {
                return array(
                    'error'   => 'More than one customer matched "' . $query . '" — use a full email address to pick one.',
                    'matches' => $matches,
                );
            }

            $audience_ref = $matches[0]['id'];
            $customer_ids = RL_Notification_Audience::resolve_single($audience_ref);

        } elseif ($audience_type === 'brand') {

            $audience_ref = absint($_POST['brand_id'] ?? 0);

            if (!$audience_ref) {
                return array('error' => 'Select a brand.');
            }

            $customer_ids = RL_Notification_Audience::resolve_brand_visitors($audience_ref);

        } else {

            return array('error' => 'Unknown audience type.');

        }

        if (empty($customer_ids)) {
            return array('error' => 'No customers match that audience — nothing was sent.');
        }

        $result = array(
            'recipients' => count($customer_ids),
        );

        if (in_array('push', $channels, true)) {
            $result['push'] = RL_Push_Subscriptions::send_to_customers($customer_ids, $title, $body, site_url('/my-entries'));
        }

        if (in_array('email', $channels, true)) {
            $email_sent = 0;

            foreach ($customer_ids as $customer_id) {
                if (RL_Notifications::send_custom_email($customer_id, $title, $body)) {
                    $email_sent++;
                }
            }

            $result['email_sent'] = $email_sent;
        }

        $this->log($title, $body, $audience_type, $audience_ref, $channels, $result);

        return $result;
    }

    private function log($title, $body, $audience_type, $audience_ref, $channels, $result)
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'rl_notifications_log',
            array(
                'title'             => $title,
                'body'              => $body,
                'audience_type'     => $audience_type,
                'audience_ref'      => $audience_ref,
                'channel'           => implode('+', $channels),
                'recipient_count'   => intval($result['recipients']),
                'push_sent_count'   => isset($result['push']) ? intval($result['push']['sent']) : 0,
                'email_sent_count'  => isset($result['email_sent']) ? intval($result['email_sent']) : 0,
                'created_by'        => get_current_user_id(),
            )
        );
    }
}
