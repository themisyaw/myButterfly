<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves an admin's notification-composer audience choice ("all
 * users" / "a single user" / "everyone who's visited a brand at
 * least once") down to a plain array of rl_customers.id, which is
 * what RL_Push_Subscriptions and the email sender both key off.
 */
class RL_Notification_Audience
{

    public static function resolve_all()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_customers';

        return array_map('intval', $wpdb->get_col("SELECT id FROM {$table}"));
    }

    public static function resolve_single($customer_id)
    {
        $customer_id = absint($customer_id);

        return $customer_id ? array($customer_id) : array();
    }

    /**
     * "Visited at least once" = has at least one row in
     * rl_transactions for this brand — the same table the
     * "Visited" filter chip on the Restaurants tab reads from, so
     * this matches what a customer already sees labeled that way.
     */
    public static function resolve_brand_visitors($brand_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_transactions';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT customer_id FROM {$table} WHERE brand_id = %d",
                $brand_id
            )
        );

        return array_map('intval', $ids);
    }
}
