<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lucky draws. A draw is scoped by brand_id/location_id, plus an
 * optional rl_draw_locations join table for an explicit subset:
 *   - brand_id NULL, location_id NULL                    -> platform-wide draw (run by the app owner)
 *   - brand_id set,  location_id NULL, no join rows       -> every one of the brand's locations
 *   - brand_id set,  location_id NULL, join rows present  -> an explicit subset of locations
 *   - brand_id set,  location_id set                      -> single-location draw
 *
 * The manager picks this per draw — it's independent of the brand's
 * pool_points/pool_entries settings, which only govern how customer
 * wallets/entries are pooled, not which locations a given draw runs at.
 *
 * Entries are logged automatically whenever RL_Points::add_points()
 * records a qualifying transaction (see process_entries_for_transaction).
 */
class RL_Draws
{

    /**
     * The admin form's <input type="datetime-local"> submits values
     * like "2026-08-08T14:30" — MySQL DATETIME columns want
     * "2026-08-08 14:30:00". Left un-normalized this silently fails
     * to match any date-range comparison, so a draw that looks
     * "active" in the admin list never actually counts as active
     * anywhere (no entries ever get logged against it).
     */
    private static function normalize_datetime($value)
    {
        $value = trim((string) $value);

        if (empty($value)) {
            return '';
        }

        $value = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        return $value;
    }

    /**
     * Normalizes the caller's location scope down to either a single
     * $location_id (for exactly one location — the plain column, same
     * as before) or a $location_ids array with 2+ entries (an explicit
     * subset, stored in rl_draw_locations after the insert). An empty
     * array with a brand_id means "every location" (brand-wide);
     * without a brand_id it's ignored (platform-wide draws have no
     * location scope at all).
     */
    private static function normalize_location_scope($data)
    {
        $location_ids = array();

        if (!empty($data['location_ids']) && is_array($data['location_ids'])) {
            $location_ids = array_values(array_unique(array_filter(array_map('absint', $data['location_ids']))));
        } elseif (!empty($data['location_id'])) {
            // Backward-compatible single-location callers.
            $location_ids = array(absint($data['location_id']));
        }

        if (count($location_ids) === 1) {
            return array('location_id' => $location_ids[0], 'location_ids' => array());
        }

        return array('location_id' => null, 'location_ids' => $location_ids);
    }

    /**
     * Replaces a draw's explicit location subset. Only meaningful for
     * a brand-scoped draw with location_id left NULL — a single
     * location uses the plain column instead, and an empty subset
     * here means "every location."
     */
    private static function set_draw_locations($draw_id, $location_ids)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_draw_locations';

        $wpdb->delete($table, array('draw_id' => $draw_id), array('%d'));

        foreach ($location_ids as $location_id) {
            $wpdb->insert(
                $table,
                array('draw_id' => $draw_id, 'location_id' => $location_id),
                array('%d', '%d')
            );
        }
    }

    /**
     * The explicit location subset stored for a draw — empty means
     * either "every location" (brand-scoped, no rows) or "not
     * applicable" (platform-wide, or a single-location draw, which
     * callers should check via $draw->location_id directly).
     */
    public static function get_draw_location_ids($draw_id)
    {
        global $wpdb;

        $draw_id = absint($draw_id);

        if (!$draw_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_draw_locations';

        $rows = $wpdb->get_col(
            $wpdb->prepare("SELECT location_id FROM {$table} WHERE draw_id = %d", $draw_id)
        );

        return array_map('intval', $rows);
    }

    public static function create($data)
    {
        global $wpdb;

        $brand_id = !empty($data['brand_id']) ? absint($data['brand_id']) : null;
        $scope    = self::normalize_location_scope($data);

        $title          = sanitize_text_field($data['title'] ?? '');
        $prize_desc     = sanitize_textarea_field($data['prize_description'] ?? '');
        $prize_desc_nl  = sanitize_textarea_field($data['prize_description_nl'] ?? '');
        $prize_image    = esc_url_raw($data['prize_image'] ?? '');
        $start_date   = self::normalize_datetime($data['start_date'] ?? '');
        $end_date     = self::normalize_datetime($data['end_date'] ?? '');
        $threshold    = (isset($data['min_points_threshold']) && $data['min_points_threshold'] !== '')
            ? round(floatval($data['min_points_threshold']), 2)
            : null;

        if (empty($title) || empty($start_date) || empty($end_date) || $end_date <= $start_date) {
            return false;
        }

        // A platform-wide draw (no brand_id) never has a location scope.
        $location_id = $brand_id ? $scope['location_id'] : null;

        $table = $wpdb->prefix . 'rl_draws';

        $inserted = $wpdb->insert(
            $table,
            array(
                'brand_id'              => $brand_id,
                'location_id'           => $location_id,
                'title'                 => $title,
                'prize_description'     => $prize_desc,
                'prize_description_nl'  => $prize_desc_nl,
                'prize_image'           => $prize_image,
                'start_date'            => $start_date,
                'end_date'              => $end_date,
                'min_points_threshold'  => $threshold,
                'status'                => 'active',
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s')
        );

        if (!$inserted) {
            return false;
        }

        $draw_id = $wpdb->insert_id;

        if ($brand_id && !$location_id && !empty($scope['location_ids'])) {
            self::set_draw_locations($draw_id, $scope['location_ids']);
        }

        return $draw_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id || empty($data)) {
            return false;
        }

        $table  = $wpdb->prefix . 'rl_draws';
        $clean  = array();
        $format = array();

        if (isset($data['title'])) {
            $clean['title'] = sanitize_text_field($data['title']);
            $format[]        = '%s';
        }

        if (isset($data['prize_description'])) {
            $clean['prize_description'] = sanitize_textarea_field($data['prize_description']);
            $format[]                     = '%s';
        }

        if (isset($data['prize_description_nl'])) {
            $clean['prize_description_nl'] = sanitize_textarea_field($data['prize_description_nl']);
            $format[]                        = '%s';
        }

        if (isset($data['prize_image'])) {
            $clean['prize_image'] = esc_url_raw($data['prize_image']);
            $format[]               = '%s';
        }

        if (isset($data['start_date'])) {
            $clean['start_date'] = self::normalize_datetime($data['start_date']);
            $format[]              = '%s';
        }

        if (isset($data['end_date'])) {
            $clean['end_date'] = self::normalize_datetime($data['end_date']);
            $format[]             = '%s';
        }

        if (isset($data['min_points_threshold'])) {
            $clean['min_points_threshold'] = $data['min_points_threshold'] === ''
                ? null
                : round(floatval($data['min_points_threshold']), 2);
            $format[] = '%f';
        }

        if (isset($data['status'])) {
            $clean['status'] = sanitize_text_field($data['status']);
            $format[]          = '%s';
        }

        $did_something = false;

        if (!empty($clean)) {
            $wpdb->update($table, $clean, array('id' => $id), $format, array('%d'));
            $did_something = true;
        }

        // Optional: change which locations a brand-scoped draw applies
        // to. Not exposed in the admin UI yet (draws are edit-once via
        // pick-winner/end/delete today), but supported for future use.
        // A literal NULL can't be bound through wpdb->update()'s
        // format array, so the location_id column is set directly.
        if (isset($data['location_ids']) || isset($data['location_id'])) {

            $draw = self::get($id);

            if ($draw && $draw->brand_id) {

                $scope = self::normalize_location_scope($data);

                if ($scope['location_id']) {
                    $wpdb->update($table, array('location_id' => $scope['location_id']), array('id' => $id), array('%d'), array('%d'));
                } else {
                    $wpdb->query($wpdb->prepare("UPDATE {$table} SET location_id = NULL WHERE id = %d", $id));
                }

                self::set_draw_locations($id, $scope['location_id'] ? array() : $scope['location_ids']);

                $did_something = true;
            }
        }

        return $did_something;
    }

    public static function get($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_draws';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
        );
    }

    public static function delete($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_draws';

        $wpdb->delete($wpdb->prefix . 'rl_draw_locations', array('draw_id' => $id), array('%d'));

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get_all_for_brand($brand_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_draws';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE brand_id = %d ORDER BY start_date DESC",
                $brand_id
            )
        );
    }

    public static function get_platform_draws()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_draws';

        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE brand_id IS NULL AND location_id IS NULL ORDER BY start_date DESC"
        );
    }

    /**
     * Every currently-running draw across the whole platform, with
     * brand/location names attached, for the public "active prizes" feed.
     */
    public static function get_all_active_public()
    {
        global $wpdb;

        $draws     = $wpdb->prefix . 'rl_draws';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';
        $now       = current_time('mysql');

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    d.*,
                    b.name AS brand_name,
                    l.name AS location_name
                FROM {$draws} d
                LEFT JOIN {$brands} b ON b.id = d.brand_id
                LEFT JOIN {$locations} l ON l.id = d.location_id
                WHERE d.status = 'active'
                  AND d.start_date <= %s
                  AND d.end_date >= %s
                ORDER BY d.end_date ASC
                ",
                $now,
                $now
            )
        );
    }

    public static function get_active_platform_draws()
    {
        return self::get_active_matching('brand_id IS NULL AND location_id IS NULL', array());
    }

    /**
     * Rewards a successful referral: the referring customer gets one
     * entry in every platform-wide draw currently running (usually
     * just the current "big prize"). No-op if nothing is active right
     * now — the referral itself is still recorded on the new
     * customer's row regardless.
     */
    public static function grant_referral_entry($referrer_customer_id)
    {
        global $wpdb;

        $referrer_customer_id = absint($referrer_customer_id);

        if (!$referrer_customer_id) {
            return 0;
        }

        $entries_table = $wpdb->prefix . 'rl_draw_entries';
        $granted       = 0;

        foreach (self::get_active_platform_draws() as $draw) {

            $inserted = $wpdb->insert(
                $entries_table,
                array(
                    'draw_id'        => $draw->id,
                    'customer_id'    => $referrer_customer_id,
                    'transaction_id' => null,
                ),
                array('%d', '%d', '%s')
            );

            if ($inserted) {
                $granted++;
            }
        }

        return $granted;
    }

    private static function get_active_matching($where, $params)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_draws';
        $now   = current_time('mysql');

        $sql = "
            SELECT *
            FROM {$table}
            WHERE status = 'active'
              AND start_date <= %s
              AND end_date >= %s
              AND {$where}
        ";

        array_unshift($params, $now, $now);

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Called from RL_Points::add_points() right after a transaction is
     * recorded. Logs one entry per matching active draw: the platform
     * draw (if any), a single-location draw for this exact location
     * (if any), and any brand-scoped draw for this location's brand
     * that applies here — either because it targets every location, or
     * because this location is explicitly in its chosen subset. This
     * is the manager's per-draw choice, independent of the brand's
     * pool_points/pool_entries settings.
     */
    public static function process_entries_for_transaction($location, $customer_id, $points, $transaction_id)
    {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'rl_draw_entries';
        $matched_draws = array();

        // Platform-wide draw: every transaction anywhere qualifies,
        // no minimum threshold.
        $platform_draws = self::get_active_matching(
            'brand_id IS NULL AND location_id IS NULL',
            array()
        );

        foreach ($platform_draws as $draw) {
            $matched_draws[] = $draw;
        }

        // Single-location draw for this exact location.
        $location_draws = self::get_active_matching(
            'location_id = %d',
            array($location->id)
        );

        foreach ($location_draws as $draw) {
            $matched_draws[] = $draw;
        }

        // Brand-scoped draws (location_id NULL) for this brand — each
        // one applies here if it targets every location (no subset
        // rows) or explicitly includes this one.
        $brand_draws = self::get_active_matching(
            'brand_id = %d AND location_id IS NULL',
            array($location->brand_id)
        );

        foreach ($brand_draws as $draw) {

            $subset = self::get_draw_location_ids($draw->id);

            if (empty($subset) || in_array(intval($location->id), $subset, true)) {
                $matched_draws[] = $draw;
            }
        }

        foreach ($matched_draws as $draw) {

            if (
                $draw->min_points_threshold !== null
                && floatval($points) < floatval($draw->min_points_threshold)
            ) {
                continue;
            }

            $inserted = $wpdb->insert(
                $entries_table,
                array(
                    'draw_id'        => $draw->id,
                    'customer_id'    => $customer_id,
                    'transaction_id' => $transaction_id,
                ),
                array('%d', '%d', '%d')
            );

            // Notify right away, same as pick_winner() — push first
            // (best-effort, only reaches them if they're subscribed)
            // then email (reaches their inbox regardless). Deliberately
            // one notification per matched draw, not one combined
            // notification per transaction — a single purchase can
            // land entries in more than one draw at once (e.g. a
            // location draw and the platform-wide giveaway together),
            // and each is its own distinct thing worth knowing about.
            if ($inserted && class_exists('RL_Notifications')) {

                RL_Notifications::send_entry_email($draw->id, $customer_id);

                if (class_exists('RL_Push_Subscriptions')) {
                    RL_Push_Subscriptions::send_to_customers(
                        array($customer_id),
                        'New entry! 🎟️',
                        'You\'re entered in "' . $draw->title . '" — good luck!',
                        site_url('/my-entries')
                    );
                }
            }
        }
    }

    public static function get_entry_count($draw_id, $customer_id)
    {
        global $wpdb;

        $draw_id     = absint($draw_id);
        $customer_id = absint($customer_id);

        if (!$draw_id || !$customer_id) {
            return 0;
        }

        $table = $wpdb->prefix . 'rl_draw_entries';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE draw_id = %d AND customer_id = %d",
                $draw_id,
                $customer_id
            )
        );
    }

    /**
     * One row per draw the customer has entries in — grouped rather
     * than one row per entry, since a purchase-heavy customer could
     * otherwise have dozens of rows for the same draw. Used by the
     * "Ended" view on the My Entries page (see get_customer_ended_entries()
     * below for the currently-running-only counterpart).
     */
    public static function get_customer_entry_summary($customer_id, $limit = 30)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return array();
        }

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 30;
        }

        if ($limit > 100) {
            $limit = 100;
        }

        $entries   = $wpdb->prefix . 'rl_draw_entries';
        $draws     = $wpdb->prefix . 'rl_draws';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    d.id AS draw_id,
                    d.title AS draw_title,
                    d.status AS draw_status,
                    d.winner_customer_id,
                    b.name AS brand_name,
                    l.name AS location_name,
                    COUNT(e.id) AS entry_count,
                    MAX(e.created_at) AS last_entry_at
                FROM {$entries} e
                INNER JOIN {$draws} d ON d.id = e.draw_id
                LEFT JOIN {$brands} b ON b.id = d.brand_id
                LEFT JOIN {$locations} l ON l.id = d.location_id
                WHERE e.customer_id = %d
                GROUP BY d.id
                ORDER BY last_entry_at DESC
                LIMIT %d
                ",
                $customer_id,
                $limit
            )
        );
    }

    /**
     * Same shape as get_customer_entry_summary() above, but the
     * complement of get_customer_active_entries() — every draw the
     * customer entered that ISN'T currently running (manually ended,
     * or simply past its end_date without anyone clicking "End").
     * Powers the "Ended" toggle on the My Entries page; the "Active"
     * toggle there uses get_customer_active_entries() instead.
     */
    public static function get_customer_ended_entries($customer_id, $limit = 30)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return array();
        }

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 30;
        }

        if ($limit > 100) {
            $limit = 100;
        }

        $entries   = $wpdb->prefix . 'rl_draw_entries';
        $draws     = $wpdb->prefix . 'rl_draws';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';
        $now       = current_time('mysql');

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    d.id AS draw_id,
                    d.title AS draw_title,
                    d.status AS draw_status,
                    d.winner_customer_id,
                    b.name AS brand_name,
                    l.name AS location_name,
                    COUNT(e.id) AS entry_count,
                    MAX(e.created_at) AS last_entry_at
                FROM {$entries} e
                INNER JOIN {$draws} d ON d.id = e.draw_id
                LEFT JOIN {$brands} b ON b.id = d.brand_id
                LEFT JOIN {$locations} l ON l.id = d.location_id
                WHERE e.customer_id = %d
                  AND NOT (d.status = 'active' AND d.start_date <= %s AND d.end_date >= %s)
                GROUP BY d.id
                ORDER BY last_entry_at DESC
                LIMIT %d
                ",
                $customer_id,
                $now,
                $now,
                $limit
            )
        );
    }

    /**
     * A customer's tickets in every draw that's currently running
     * (status = 'active' AND within its start/end window) — unlike
     * get_customer_entry_summary() above, this excludes ended/future
     * draws entirely, since it powers the "My Entries" page which
     * only ever shows prizes you can still win. Ordered soonest-
     * expiring first. Includes the prize image/description and each
     * draw's brand_id/location_id (plus the names already joined in)
     * so RL_Draws::get_public_scope_label() can be called directly
     * on each row without a second lookup.
     */
    public static function get_customer_active_entries($customer_id, $limit = 50)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return array();
        }

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 50;
        }

        if ($limit > 200) {
            $limit = 200;
        }

        $entries   = $wpdb->prefix . 'rl_draw_entries';
        $draws     = $wpdb->prefix . 'rl_draws';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';
        $now       = current_time('mysql');

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    d.id AS draw_id,
                    d.title AS draw_title,
                    d.prize_description,
                    d.prize_description_nl,
                    d.prize_image,
                    d.end_date,
                    d.brand_id,
                    d.location_id,
                    b.name AS brand_name,
                    l.name AS location_name,
                    COUNT(e.id) AS entry_count
                FROM {$entries} e
                INNER JOIN {$draws} d ON d.id = e.draw_id
                LEFT JOIN {$brands} b ON b.id = d.brand_id
                LEFT JOIN {$locations} l ON l.id = d.location_id
                WHERE e.customer_id = %d
                  AND d.status = 'active'
                  AND d.start_date <= %s
                  AND d.end_date >= %s
                GROUP BY d.id
                ORDER BY d.end_date ASC
                LIMIT %d
                ",
                $customer_id,
                $now,
                $now,
                $limit
            )
        );
    }

    public static function get_total_entries($draw_id)
    {
        global $wpdb;

        $draw_id = absint($draw_id);

        if (!$draw_id) {
            return 0;
        }

        $table = $wpdb->prefix . 'rl_draw_entries';

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE draw_id = %d", $draw_id)
        );
    }

    /**
     * A customer's odds on a given draw right now: their entry count,
     * the draw's total entries, and their share of them as a
     * percentage (each entry is one ticket, so this is a plain
     * entries-mine / entries-total split — it shifts as more people
     * enter). Recomputed fresh on every page load, not cached, since
     * new entries land constantly.
     */
    public static function get_win_chance($draw_id, $customer_id)
    {
        $total = self::get_total_entries($draw_id);
        $mine  = $customer_id ? self::get_entry_count($draw_id, $customer_id) : 0;

        $percentage = ($total > 0 && $mine > 0) ? round(($mine / $total) * 100, 1) : 0.0;

        return array(
            'entries'    => $mine,
            'total'      => $total,
            'percentage' => $percentage,
        );
    }

    /**
     * Randomly picks a winning entry (weighted naturally by how many
     * entries each customer has), marks the draw ended. Manager/admin
     * triggered — never automatic.
     */
    public static function pick_winner($draw_id)
    {
        global $wpdb;

        $draw_id = absint($draw_id);

        if (!$draw_id) {
            return false;
        }

        $entries_table = $wpdb->prefix . 'rl_draw_entries';

        $winning_entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT customer_id FROM {$entries_table} WHERE draw_id = %d ORDER BY RAND() LIMIT 1",
                $draw_id
            )
        );

        if (!$winning_entry) {
            return false;
        }

        $draws_table = $wpdb->prefix . 'rl_draws';

        $updated = $wpdb->update(
            $draws_table,
            array(
                'winner_customer_id' => $winning_entry->customer_id,
                'drawn_at'           => current_time('mysql'),
                'status'             => 'ended',
            ),
            array('id' => $draw_id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            return false;
        }

        return intval($winning_entry->customer_id);
    }

    /**
     * Whether a brand-scoped draw actually applies at a given
     * location — true for a single-location draw matching exactly,
     * or a brand-wide/subset draw that includes it. Used to decide
     * what shows on a restaurant's own page.
     */
    public static function draw_applies_to_location($draw, $location_id, $brand_id)
    {
        if (empty($draw->brand_id) || intval($draw->brand_id) !== intval($brand_id)) {
            return false;
        }

        if (!empty($draw->location_id)) {
            return intval($draw->location_id) === intval($location_id);
        }

        $subset = self::get_draw_location_ids($draw->id);

        if (empty($subset)) {
            return true;
        }

        return in_array(intval($location_id), $subset, true);
    }

    /**
     * Just the "where" part of a draw's scope — "Platform", a single
     * location's name, "All locations", or a comma-joined list of an
     * explicit subset. Used in the admin draws table, where the brand
     * is already implied by the page.
     */
    public static function get_location_scope_summary($draw)
    {
        if (empty($draw->brand_id)) {
            return rl_t('draw_scope_platform');
        }

        if (!empty($draw->location_id)) {
            $location_name = $draw->location_name ?? null;

            if ($location_name === null) {
                $loc           = RL_Locations::get($draw->location_id);
                $location_name = $loc ? $loc->name : ('Location #' . $draw->location_id);
            }

            return $location_name;
        }

        $location_ids = self::get_draw_location_ids($draw->id);

        if (empty($location_ids)) {
            return rl_t('draw_scope_all_locations');
        }

        global $wpdb;

        $table        = $wpdb->prefix . 'rl_locations';
        $placeholders = implode(',', array_fill(0, count($location_ids), '%d'));

        $names = $wpdb->get_col(
            $wpdb->prepare("SELECT name FROM {$table} WHERE id IN ({$placeholders})", $location_ids)
        );

        return implode(', ', $names);
    }

    /**
     * The full public-facing scope label — brand name plus the
     * location summary above — for prize cards and the restaurant
     * page, where the brand isn't already implied by the surrounding
     * page.
     */
    public static function get_public_scope_label($draw)
    {
        if (empty($draw->brand_id)) {
            return rl_t('draw_scope_platform');
        }

        $brand_name = $draw->brand_name ?? null;

        if ($brand_name === null) {
            $brand      = RL_Brands::get($draw->brand_id);
            $brand_name = $brand ? $brand->name : '';
        }

        $scope_summary = self::get_location_scope_summary($draw);

        // A standalone restaurant's brand name and its one location's
        // name are the same string, and "All locations" is just a
        // generic filler — showing "Name — Name" (or "Name — All
        // locations") would repeat/clutter the restaurant's own name,
        // so collapse down to just the name in those cases. A genuine
        // subset of a chain's locations is still worth spelling out.
        // Compared against the translated string (not a hardcoded
        // English literal) since get_location_scope_summary() now
        // returns whatever language is active.
        if ($scope_summary === $brand_name || $scope_summary === rl_t('draw_scope_all_locations')) {
            return $brand_name;
        }

        return trim($brand_name . ' — ' . $scope_summary);
    }
}
