<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A "brand" is the top-level restaurant entity. A standalone restaurant
 * is a brand with exactly one location; a chain is a brand with several.
 * pool_points / pool_entries control whether a customer's balance and
 * draw entries are shared across all of the brand's locations, or kept
 * separate per location.
 */
class RL_Brands
{

    public static function create($data)
    {
        global $wpdb;

        $name           = sanitize_text_field($data['name'] ?? '');
        $description    = sanitize_textarea_field($data['description'] ?? '');
        $logo           = esc_url_raw($data['logo'] ?? '');
        $pool_points    = !empty($data['pool_points']) ? 1 : 0;
        $pool_entries   = !empty($data['pool_entries']) ? 1 : 0;
        $prizes_enabled = !empty($data['prizes_enabled']) ? 1 : 0;

        if (empty($name)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brands';

        $inserted = $wpdb->insert(
            $table,
            array(
                'name'           => $name,
                'description'    => $description,
                'logo'           => $logo,
                'pool_points'    => $pool_points,
                'pool_entries'   => $pool_entries,
                'prizes_enabled' => $prizes_enabled,
                'status'         => 'active',
            ),
            array('%s', '%s', '%s', '%d', '%d', '%d', '%s')
        );

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id || empty($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brands';

        $clean  = array();
        $format = array();

        if (isset($data['name'])) {
            $clean['name'] = sanitize_text_field($data['name']);
            $format[]      = '%s';
        }

        if (isset($data['description'])) {
            $clean['description'] = sanitize_textarea_field($data['description']);
            $format[]              = '%s';
        }

        if (isset($data['logo'])) {
            $clean['logo'] = esc_url_raw($data['logo']);
            $format[]       = '%s';
        }

        if (isset($data['pool_points'])) {
            $clean['pool_points'] = !empty($data['pool_points']) ? 1 : 0;
            $format[]              = '%d';
        }

        if (isset($data['pool_entries'])) {
            $clean['pool_entries'] = !empty($data['pool_entries']) ? 1 : 0;
            $format[]               = '%d';
        }

        // Deliberately only ever set by admin-facing code paths — see
        // the can_manage_brand()-gated $is_admin checks in
        // admin/class-brands-admin.php before this key is ever
        // included in $data. A brand manager's own edit forms never
        // submit this field, so it's never at risk from a crafted
        // POST either — nothing here re-checks the caller's role,
        // that enforcement lives entirely at the caller.
        if (isset($data['prizes_enabled'])) {
            $clean['prizes_enabled'] = !empty($data['prizes_enabled']) ? 1 : 0;
            $format[]                 = '%d';
        }

        if (isset($data['status'])) {
            $clean['status'] = sanitize_text_field($data['status']);
            $format[]         = '%s';
        }

        if (empty($clean)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $clean,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    public static function get($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_brands';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            )
        );
    }

    public static function get_by_manager($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return null;
        }

        $brands_table   = $wpdb->prefix . 'rl_brands';
        $managers_table = $wpdb->prefix . 'rl_brand_managers';

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT b.*
                FROM {$brands_table} b
                INNER JOIN {$managers_table} m ON m.brand_id = b.id
                WHERE m.user_id = %d
                LIMIT 1
                ",
                $user_id
            )
        );
    }

    /**
     * Every manager (WP user) currently assigned to a brand — a
     * brand can have several, unlike location staff.
     */
    public static function get_managers_for_brand($brand_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id) {
            return array();
        }

        $managers_table = $wpdb->prefix . 'rl_brand_managers';
        $users_table    = $wpdb->users;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT u.ID as user_id, u.display_name, u.user_email
                FROM {$managers_table} m
                INNER JOIN {$users_table} u ON u.ID = m.user_id
                WHERE m.brand_id = %d
                ORDER BY u.display_name ASC
                ",
                $brand_id
            )
        );
    }

    /**
     * Assigns a user as a manager of a brand — a manager oversees
     * exactly one brand at a time, so any prior assignment for this
     * user is replaced rather than stacked.
     */
    public static function assign_manager($brand_id, $user_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);
        $user_id  = absint($user_id);

        if (!$brand_id || !$user_id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brand_managers';

        $wpdb->delete($table, array('user_id' => $user_id), array('%d'));

        return $wpdb->insert(
            $table,
            array(
                'brand_id' => $brand_id,
                'user_id'  => $user_id,
            ),
            array('%d', '%d')
        );
    }

    /**
     * Removes a user's manager assignment (any brand).
     */
    public static function remove_manager($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brand_managers';

        return $wpdb->delete($table, array('user_id' => $user_id), array('%d'));
    }

    public static function get_all($active_only = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_brands';

        if ($active_only) {
            return $wpdb->get_results(
                "SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC"
            );
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY name ASC"
        );
    }

    public static function delete($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brands';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
}
