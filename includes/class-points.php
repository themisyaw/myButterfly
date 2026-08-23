<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Points are held in wallets keyed by a "scope": either the whole
 * brand (when the brand pools points across its locations) or a
 * single location (when it doesn't). All public methods take the
 * *location* a transaction physically happened at and resolve the
 * correct wallet scope internally, so callers never need to know
 * about pooling.
 */
class RL_Points
{

    private static function resolve_scope($location)
    {
        if (!empty($location->pool_points)) {
            return array(
                'scope_type' => 'brand',
                'scope_id'   => intval($location->brand_id),
            );
        }

        return array(
            'scope_type' => 'location',
            'scope_id'   => intval($location->id),
        );
    }

    /**
     * The highest number of points a single Add Points action can
     * grant. There's no natural ceiling otherwise — a compromised or
     * malicious staff/manager account could otherwise credit an
     * arbitrary amount in one AJAX call. Filterable in case a brand
     * genuinely needs a higher one-time bonus for some reason.
     */
    private static function max_points_per_transaction()
    {
        return apply_filters('rl_max_points_per_transaction', 10000);
    }

    /**
     * Guards against crediting/debiting a customer_id that doesn't
     * actually correspond to a real customer — there's no database
     * foreign key enforcing this (rl_customer_points.customer_id is
     * a plain indexed column, not a constraint), so without this
     * check a bogus id would silently create an orphaned wallet and
     * transaction row instead of failing.
     */
    private static function customer_exists($customer_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_customers';

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d LIMIT 1",
                $customer_id
            )
        );
    }

    private static function get_wallet_row($customer_id, $scope_type, $scope_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_customer_points';

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$table}
                WHERE customer_id = %d AND scope_type = %s AND scope_id = %d
                LIMIT 1
                ",
                $customer_id,
                $scope_type,
                $scope_id
            )
        );
    }

    /**
     * Adjust a wallet balance by $delta (positive or negative).
     * Returns the new balance, or false on failure / insufficient funds.
     *
     * Both branches are single atomic SQL statements rather than a
     * read-balance-then-write-balance pair, specifically so two
     * concurrent calls for the same wallet (e.g. a double-tapped
     * redeem button, or two rapid scans) can't both read the same
     * starting balance and both succeed — the database, not PHP,
     * makes the check-then-act indivisible.
     */
    private static function adjust_wallet($customer_id, $scope_type, $scope_id, $delta)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_customer_points';

        if ($delta < 0) {

            // The WHERE clause's balance check and the write happen
            // as one indivisible operation — if two requests race,
            // only one UPDATE can affect a row; the loser affects
            // zero rows and is treated as insufficient funds below,
            // even if its pre-check upstream already saw a
            // sufficient (now stale) balance.
            $updated_rows = $wpdb->query(
                $wpdb->prepare(
                    "
                    UPDATE {$table}
                    SET points = points + %f
                    WHERE customer_id = %d AND scope_type = %s AND scope_id = %d
                    AND points >= %f
                    ",
                    $delta,
                    $customer_id,
                    $scope_type,
                    $scope_id,
                    -$delta
                )
            );

            if (!$updated_rows) {
                return false;
            }

        } else {

            // INSERT ... ON DUPLICATE KEY UPDATE relies on the
            // UNIQUE KEY (customer_id, scope_type, scope_id) to make
            // "create the wallet or add to it" one indivisible
            // operation too, closing the same race for a customer's
            // very first transaction in a given scope.
            $result = $wpdb->query(
                $wpdb->prepare(
                    "
                    INSERT INTO {$table} (customer_id, scope_type, scope_id, points)
                    VALUES (%d, %s, %d, %f)
                    ON DUPLICATE KEY UPDATE points = points + VALUES(points)
                    ",
                    $customer_id,
                    $scope_type,
                    $scope_id,
                    $delta
                )
            );

            if ($result === false) {
                return false;
            }

        }

        $row = self::get_wallet_row($customer_id, $scope_type, $scope_id);

        return $row ? round(floatval($row->points), 2) : false;
    }

    public static function add_points($location_id, $customer_id, $points, $note = '')
    {
        global $wpdb;

        $location = RL_Locations::get_with_brand($location_id);

        if (!$location) {
            return false;
        }

        $customer_id = intval($customer_id);
        $points      = round(floatval($points), 2);
        $note        = sanitize_text_field($note);

        if ($customer_id <= 0 || $points <= 0 || $points > self::max_points_per_transaction()) {
            return false;
        }

        if (!self::customer_exists($customer_id)) {
            return false;
        }

        $scope = self::resolve_scope($location);

        $new_balance = self::adjust_wallet(
            $customer_id,
            $scope['scope_type'],
            $scope['scope_id'],
            $points
        );

        if ($new_balance === false) {
            return false;
        }

        $transactions_table = $wpdb->prefix . 'rl_transactions';

        $wpdb->insert(
            $transactions_table,
            array(
                'customer_id'      => $customer_id,
                'location_id'      => $location->id,
                'brand_id'         => $location->brand_id,
                'staff_id'         => get_current_user_id(),
                'transaction_type' => 'add',
                'points'           => $points,
                'note'             => $note,
            ),
            array('%d', '%d', '%d', '%d', '%s', '%f', '%s')
        );

        $transaction_id = $wpdb->insert_id;

        if (class_exists('RL_Draws')) {
            RL_Draws::process_entries_for_transaction($location, $customer_id, $points, $transaction_id);
        }

        return $new_balance;
    }

    public static function remove_points($location_id, $customer_id, $points, $note = '')
    {
        global $wpdb;

        $location = RL_Locations::get_with_brand($location_id);

        if (!$location) {
            return false;
        }

        $customer_id = intval($customer_id);
        $points      = round(floatval($points), 2);
        $note        = sanitize_text_field($note);

        if ($customer_id <= 0 || $points <= 0) {
            return false;
        }

        if (!self::customer_exists($customer_id)) {
            return false;
        }

        $scope = self::resolve_scope($location);

        $current = self::get_wallet_row($customer_id, $scope['scope_type'], $scope['scope_id']);

        if (!$current || floatval($current->points) < $points) {
            return false;
        }

        $new_balance = self::adjust_wallet(
            $customer_id,
            $scope['scope_type'],
            $scope['scope_id'],
            -$points
        );

        if ($new_balance === false) {
            return false;
        }

        $transactions_table = $wpdb->prefix . 'rl_transactions';

        $wpdb->insert(
            $transactions_table,
            array(
                'customer_id'      => $customer_id,
                'location_id'      => $location->id,
                'brand_id'         => $location->brand_id,
                'staff_id'         => get_current_user_id(),
                'transaction_type' => 'redeem',
                'points'           => $points,
                'note'             => $note,
            ),
            array('%d', '%d', '%d', '%d', '%s', '%f', '%s')
        );

        return $new_balance;
    }

    /**
     * Balance available to spend at a given location (already resolves
     * pooled-vs-not).
     */
    public static function get_balance($location_id, $customer_id)
    {
        $location = RL_Locations::get_with_brand($location_id);

        if (!$location) {
            return false;
        }

        $customer_id = intval($customer_id);

        if ($customer_id <= 0) {
            return false;
        }

        $scope = self::resolve_scope($location);

        $row = self::get_wallet_row($customer_id, $scope['scope_type'], $scope['scope_id']);

        return $row ? floatval($row->points) : 0.0;
    }

    /**
     * Every balance a customer holds, across every brand/location
     * they've ever earned points at — for the "my restaurants" view
     * on the customer dashboard.
     */
    public static function get_all_balances_for_customer($customer_id)
    {
        global $wpdb;

        $customer_id = intval($customer_id);

        if ($customer_id <= 0) {
            return array();
        }

        $wallets   = $wpdb->prefix . 'rl_customer_points';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    w.points,
                    w.scope_type,
                    b.id AS brand_id,
                    b.name AS brand_name,
                    b.logo AS brand_logo,
                    NULL AS location_id,
                    NULL AS location_name
                FROM {$wallets} w
                INNER JOIN {$brands} b ON b.id = w.scope_id
                WHERE w.customer_id = %d AND w.scope_type = 'brand'

                UNION ALL

                SELECT
                    w.points,
                    w.scope_type,
                    b.id AS brand_id,
                    b.name AS brand_name,
                    b.logo AS brand_logo,
                    l.id AS location_id,
                    l.name AS location_name
                FROM {$wallets} w
                INNER JOIN {$locations} l ON l.id = w.scope_id
                INNER JOIN {$brands} b ON b.id = l.brand_id
                WHERE w.customer_id = %d AND w.scope_type = 'location'

                ORDER BY brand_name ASC
                ",
                $customer_id,
                $customer_id
            )
        );
    }
}
