<?php

if (!defined('ABSPATH')) {
    exit;
}

class RL_DB
{
    public static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        /*
        =========================
        CUSTOMERS TABLE
        (global member identity — one row per WP user, one QR token
        usable at every brand/location)
        =========================
        */
        $customers = $wpdb->prefix . 'rl_customers';

        $sql = "CREATE TABLE {$customers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            qr_token varchar(255) NOT NULL,
            referral_code varchar(20) NULL,
            referred_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY qr_token (qr_token),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY referral_code (referral_code)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        BRANDS TABLE
        (a standalone restaurant is a brand with 1 location;
        a chain is a brand with several)
        =========================
        */
        $brands = $wpdb->prefix . 'rl_brands';

        // manager_user_id intentionally omitted here — brand manager
        // assignment now lives in rl_brand_managers (below), which
        // supports more than one manager per brand. Sites upgrading
        // from the old single-manager column are carried forward by
        // migrate_legacy_brand_managers() / drop_legacy_brand_manager_column()
        // in RL_Activator::activate(). It's left out of this CREATE
        // TABLE (rather than kept and dropped every run, as with the
        // legacy brand-hours columns below) because it was NOT NULL
        // with no default — re-adding it via dbDelta on a table that
        // already has rows would fail under strict SQL mode.
        $sql = "CREATE TABLE {$brands} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            logo varchar(255) NULL,
            description text NULL,
            pool_points tinyint(1) NOT NULL DEFAULT 0,
            pool_entries tinyint(1) NOT NULL DEFAULT 0,
            prizes_enabled tinyint(1) NOT NULL DEFAULT 0,
            opening_time time NULL,
            closing_time time NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        LOCATIONS TABLE
        (a physical branch belonging to a brand)
        =========================
        */
        $locations = $wpdb->prefix . 'rl_locations';

        $sql = "CREATE TABLE {$locations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            address varchar(255) NULL,
            lat decimal(10,7) NULL,
            lng decimal(10,7) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        BRAND MANAGERS TABLE
        Maps brand_manager WP users to the brand they manage. A brand
        can have several managers (multiple rows sharing brand_id),
        but — same as location staff — each manager user is only
        ever assigned to one brand at a time (UNIQUE KEY user_id).
        Replaces the old single rl_brands.manager_user_id column.
        =========================
        */
        $brand_managers = $wpdb->prefix . 'rl_brand_managers';

        $sql = "CREATE TABLE {$brand_managers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY brand_id (brand_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        CUSTOMER POINTS TABLE (wallets)
        One row per customer per scope. scope_type/scope_id is
        'brand'+brand_id when the brand pools points, or
        'location'+location_id when it doesn't.
        =========================
        */
        $customer_points = $wpdb->prefix . 'rl_customer_points';

        $sql = "CREATE TABLE {$customer_points} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            scope_type varchar(10) NOT NULL,
            scope_id bigint(20) unsigned NOT NULL,
            points decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY customer_scope (customer_id, scope_type, scope_id),
            KEY customer_id (customer_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        REDEEM CATEGORIES TABLE
        Scoped per brand (not per location) so a chain's manager
        defines a category set once — e.g. "Drinks", "Mains" — and
        reuses it across every location's own menu.
        =========================
        */
        $redeem_categories = $wpdb->prefix . 'rl_redeem_categories';

        $sql = "CREATE TABLE {$redeem_categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        REDEEM ITEMS TABLE
        (menus are per-location; category_id is optional and points
        at a category owned by the location's brand)
        =========================
        */
        $redeem_items = $wpdb->prefix . 'rl_redeem_items';

        $sql = "CREATE TABLE {$redeem_items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            image varchar(255) NULL,
            points_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY location_id (location_id),
            KEY category_id (category_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        TRANSACTIONS TABLE
        =========================
        */
        $transactions = $wpdb->prefix . 'rl_transactions';

        $sql = "CREATE TABLE {$transactions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            brand_id bigint(20) unsigned NOT NULL,
            staff_id bigint(20) unsigned NOT NULL,
            transaction_type varchar(20) NOT NULL,
            points decimal(10,2) NOT NULL DEFAULT 0.00,
            note text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY location_id (location_id),
            KEY brand_id (brand_id),
            KEY staff_id (staff_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        DRAWS TABLE
        brand_id NULL + location_id NULL  = platform-wide draw
        brand_id set + location_id NULL   = brand-wide draw (all locations)
        brand_id set + location_id set    = single-location draw
        =========================
        */
        $draws = $wpdb->prefix . 'rl_draws';

        $sql = "CREATE TABLE {$draws} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) unsigned NULL,
            location_id bigint(20) unsigned NULL,
            title varchar(255) NOT NULL,
            prize_description text NULL,
            prize_description_nl text NULL,
            prize_image varchar(255) NULL,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            min_points_threshold decimal(10,2) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            winner_customer_id bigint(20) unsigned NULL,
            drawn_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id),
            KEY location_id (location_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        DRAW ENTRIES TABLE
        =========================
        */
        $draw_entries = $wpdb->prefix . 'rl_draw_entries';

        $sql = "CREATE TABLE {$draw_entries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            draw_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            transaction_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY draw_id (draw_id),
            KEY customer_id (customer_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        BRAND HOURS TABLE
        One row per day of the week (0 = Sunday .. 6 = Saturday,
        matching PHP's date('w')) that's actually been configured — a
        restaurant can have different hours each day or be closed
        entirely on some, which a single flat opening/closing pair
        can't express.
        =========================
        */
        $brand_hours = $wpdb->prefix . 'rl_brand_hours';

        $sql = "CREATE TABLE {$brand_hours} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint(1) unsigned NOT NULL,
            is_closed tinyint(1) NOT NULL DEFAULT 0,
            opening_time time NULL,
            closing_time time NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY brand_day (brand_id, day_of_week),
            KEY brand_id (brand_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        DRAW LOCATIONS TABLE
        Lets a brand-scoped draw (rl_draws.location_id IS NULL) target
        an explicit subset of the brand's locations rather than either
        one single location (rl_draws.location_id set) or literally
        every location (brand-scoped, no rows here at all).
        =========================
        */
        $draw_locations = $wpdb->prefix . 'rl_draw_locations';

        $sql = "CREATE TABLE {$draw_locations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            draw_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY draw_location (draw_id, location_id),
            KEY draw_id (draw_id),
            KEY location_id (location_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        PUSH SUBSCRIPTIONS TABLE
        One row per browser/device a customer has enabled push
        notifications on (a customer can have several — phone,
        laptop, etc). endpoint is the full Web Push URL from the
        browser and can be long, so it's stored as text with a
        fixed-length md5 hash alongside it for the UNIQUE key —
        MySQL can't put a key on a text column directly.
        =========================
        */
        $push_subscriptions = $wpdb->prefix . 'rl_push_subscriptions';

        $sql = "CREATE TABLE {$push_subscriptions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            endpoint text NOT NULL,
            endpoint_hash char(32) NOT NULL,
            p256dh varchar(255) NOT NULL,
            auth varchar(255) NOT NULL,
            user_agent varchar(255) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY endpoint_hash (endpoint_hash),
            KEY customer_id (customer_id)
        ) {$charset_collate};";

        dbDelta($sql);

        /*
        =========================
        NOTIFICATIONS LOG TABLE
        A record of every broadcast sent from the admin composer —
        who it went to and through which channel(s) — kept for
        troubleshooting/history, not shown to customers.
        =========================
        */
        $notifications_log = $wpdb->prefix . 'rl_notifications_log';

        $sql = "CREATE TABLE {$notifications_log} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            body text NULL,
            audience_type varchar(20) NOT NULL,
            audience_ref bigint(20) unsigned NULL,
            channel varchar(20) NOT NULL,
            recipient_count int(11) NOT NULL DEFAULT 0,
            push_sent_count int(11) NOT NULL DEFAULT 0,
            email_sent_count int(11) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * One-time cleanup for sites upgrading from the single-restaurant
     * schema (v1.x): the old flat `points` balance on rl_customers is
     * replaced by the rl_customer_points wallet table.
     */
    public static function drop_legacy_columns()
    {
        global $wpdb;

        $customers = $wpdb->prefix . 'rl_customers';

        $column = $wpdb->get_results(
            "SHOW COLUMNS FROM {$customers} LIKE 'points'"
        );

        if (!empty($column)) {
            $wpdb->query(
                "ALTER TABLE {$customers} DROP COLUMN points"
            );
        }
    }

    /**
     * One-time migration for sites that set the old flat
     * opening_time/closing_time pair on rl_brands (v2.3.0) before
     * per-day hours existed: carries that single range over to every
     * day of the week in rl_brand_hours, so nobody's existing hours
     * silently disappear. No-op once the legacy columns are gone.
     */
    public static function migrate_legacy_brand_hours()
    {
        global $wpdb;

        $brands_table = $wpdb->prefix . 'rl_brands';
        $hours_table  = $wpdb->prefix . 'rl_brand_hours';

        $has_legacy_columns = $wpdb->get_results(
            "SHOW COLUMNS FROM {$brands_table} LIKE 'opening_time'"
        );

        if (empty($has_legacy_columns)) {
            return;
        }

        $brands = $wpdb->get_results(
            "SELECT id, opening_time, closing_time FROM {$brands_table}
             WHERE opening_time IS NOT NULL AND closing_time IS NOT NULL"
        );

        foreach ($brands as $brand) {

            $already_migrated = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$hours_table} WHERE brand_id = %d", $brand->id)
            );

            if ($already_migrated > 0) {
                continue;
            }

            for ($day = 0; $day <= 6; $day++) {
                $wpdb->insert(
                    $hours_table,
                    array(
                        'brand_id'     => $brand->id,
                        'day_of_week'  => $day,
                        'is_closed'    => 0,
                        'opening_time' => $brand->opening_time,
                        'closing_time' => $brand->closing_time,
                    ),
                    array('%d', '%d', '%d', '%s', '%s')
                );
            }
        }
    }

    /**
     * Drops the old flat opening_time/closing_time columns on
     * rl_brands now that per-day hours (rl_brand_hours) own this —
     * run only after migrate_legacy_brand_hours() has had a chance
     * to carry their values forward.
     */
    public static function drop_legacy_brand_hours_columns()
    {
        global $wpdb;

        $brands_table = $wpdb->prefix . 'rl_brands';

        foreach (array('opening_time', 'closing_time') as $column) {

            $exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$brands_table} LIKE '{$column}'"
            );

            if (!empty($exists)) {
                $wpdb->query("ALTER TABLE {$brands_table} DROP COLUMN {$column}");
            }
        }
    }

    /**
     * One-time migration for sites still on the single-manager schema
     * (rl_brands.manager_user_id): carries each brand's existing
     * manager over into rl_brand_managers as its first manager row,
     * so nobody's brand loses its manager when the column is dropped.
     * No-op once the legacy column is gone.
     */
    public static function migrate_legacy_brand_managers()
    {
        global $wpdb;

        $brands_table   = $wpdb->prefix . 'rl_brands';
        $managers_table = $wpdb->prefix . 'rl_brand_managers';

        $has_legacy_column = $wpdb->get_results(
            "SHOW COLUMNS FROM {$brands_table} LIKE 'manager_user_id'"
        );

        if (empty($has_legacy_column)) {
            return;
        }

        $brands = $wpdb->get_results(
            "SELECT id, manager_user_id FROM {$brands_table} WHERE manager_user_id IS NOT NULL AND manager_user_id > 0"
        );

        foreach ($brands as $brand) {

            $already_migrated = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$managers_table} WHERE brand_id = %d AND user_id = %d",
                    $brand->id,
                    $brand->manager_user_id
                )
            );

            if ($already_migrated > 0) {
                continue;
            }

            // A manager user can only belong to one brand at a time
            // (UNIQUE KEY user_id) — skip if this user was already
            // carried over for a different brand.
            $already_assigned_elsewhere = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$managers_table} WHERE user_id = %d",
                    $brand->manager_user_id
                )
            );

            if ($already_assigned_elsewhere > 0) {
                continue;
            }

            $wpdb->insert(
                $managers_table,
                array(
                    'brand_id' => $brand->id,
                    'user_id'  => $brand->manager_user_id,
                ),
                array('%d', '%d')
            );
        }
    }

    /**
     * Drops the old single manager_user_id column on rl_brands now
     * that rl_brand_managers owns manager assignment — run only
     * after migrate_legacy_brand_managers() has had a chance to
     * carry its values forward.
     */
    public static function drop_legacy_brand_manager_column()
    {
        global $wpdb;

        $brands_table = $wpdb->prefix . 'rl_brands';

        $exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$brands_table} LIKE 'manager_user_id'"
        );

        if (!empty($exists)) {
            $wpdb->query("ALTER TABLE {$brands_table} DROP COLUMN manager_user_id");
        }
    }

    /**
     * Drops the now-unused rl_location_staff table on sites that
     * created it before the location_staff role/feature was removed
     * (every brand now uses one brand_manager account instead of
     * per-location staff logins). No-op once it's gone.
     */
    public static function drop_legacy_location_staff_table()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rl_location_staff';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            $wpdb->query("DROP TABLE {$table}");
        }
    }
}
