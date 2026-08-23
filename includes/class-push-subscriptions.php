<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Storage + fan-out for browser push subscriptions. RL_Web_Push
 * handles the actual crypto/HTTP for a single message to a single
 * subscription; this class handles the "many subscriptions for many
 * customers" side of it and keeps rl_push_subscriptions clean.
 */
class RL_Push_Subscriptions
{

    public static function save($customer_id, $endpoint, $p256dh, $auth, $user_agent = '')
    {
        global $wpdb;

        $customer_id = absint($customer_id);
        $endpoint    = esc_url_raw($endpoint);

        if (!$customer_id || empty($endpoint) || empty($p256dh) || empty($auth)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_push_subscriptions';
        $hash  = md5($endpoint);

        // One row per endpoint (a given browser/device install) —
        // re-subscribing (e.g. after clearing site data) just
        // updates who it belongs to and refreshes the keys, rather
        // than piling up duplicate dead rows.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE endpoint_hash = %s",
                $hash
            )
        );

        $data = array(
            'customer_id' => $customer_id,
            'endpoint'    => $endpoint,
            'p256dh'      => sanitize_text_field($p256dh),
            'auth'        => sanitize_text_field($auth),
            'user_agent'  => substr(sanitize_text_field($user_agent), 0, 255),
        );

        if ($existing) {
            $wpdb->update($table, $data, array('id' => intval($existing)));
        } else {
            $data['endpoint_hash'] = $hash;
            $wpdb->insert($table, $data);
        }

        return true;
    }

    public static function remove($endpoint)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_push_subscriptions';

        return $wpdb->delete($table, array('endpoint_hash' => md5(esc_url_raw($endpoint))));
    }

    public static function get_for_customers($customer_ids)
    {
        global $wpdb;

        $customer_ids = array_filter(array_map('absint', (array) $customer_ids));

        if (empty($customer_ids)) {
            return array();
        }

        $table        = $wpdb->prefix . 'rl_push_subscriptions';
        $placeholders = implode(',', array_fill(0, count($customer_ids), '%d'));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id IN ({$placeholders})",
                $customer_ids
            )
        );
    }

    public static function count_for_customers($customer_ids)
    {
        return count(self::get_for_customers($customer_ids));
    }

    /**
     * Sends one push message to every subscription belonging to the
     * given customer IDs. Prunes subscriptions the push service
     * reports as gone (404/410 — the browser has unsubscribed or the
     * endpoint expired) so the table doesn't accumulate dead rows.
     *
     * Returns array('recipients' => int customers with >=1 sub,
     * 'sent' => int successful deliveries, 'failed' => int).
     */
    public static function send_to_customers($customer_ids, $title, $body, $url = '')
    {
        $subscriptions = self::get_for_customers($customer_ids);

        $result = array(
            'recipients' => count(array_unique(wp_list_pluck($subscriptions, 'customer_id'))),
            'sent'       => 0,
            'failed'     => 0,
        );

        if (empty($subscriptions)) {
            return $result;
        }

        $payload = array(
            'title' => $title,
            'body'  => $body,
            'url'   => $url ?: site_url('/'),
        );

        foreach ($subscriptions as $subscription) {

            $outcome = RL_Web_Push::send($subscription, $payload);

            if ($outcome === true) {
                $result['sent']++;
            } else {
                $result['failed']++;

                if ($outcome === 404 || $outcome === 410) {
                    self::remove($subscription->endpoint);
                }
            }
        }

        return $result;
    }
}
