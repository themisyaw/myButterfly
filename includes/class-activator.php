<?php

if (!defined('ABSPATH')) {
    exit;
}

class RL_Activator
{

    public static function activate()
    {

        require_once RL_PLUGIN_PATH . 'includes/class-db.php';

        self::create_roles();

        RL_DB::create_tables();

        RL_DB::drop_legacy_columns();

        RL_DB::drop_legacy_location_staff_table();

        RL_DB::migrate_legacy_brand_hours();
        RL_DB::drop_legacy_brand_hours_columns();

        RL_DB::migrate_legacy_brand_managers();
        RL_DB::drop_legacy_brand_manager_column();

        self::create_pages();

        update_option(
            'rl_version',
            RL_VERSION
        );

        flush_rewrite_rules();

    }

    private static function create_pages()
    {
        $pages = array(
            'login' => array(
                'title'   => 'Login',
                'content' => '[rl_home]',
            ),
            'register' => array(
                'title'   => 'Register',
                'content' => '[rl_register]',
            ),
            'lost-password' => array(
                'title'   => 'Lost Password',
                'content' => '[rl_lost_password]',
            ),
            'reset-password' => array(
                'title'   => 'Reset Password',
                'content' => '[rl_reset_password]',
            ),
            'explore' => array(
                'title'   => 'Explore',
                'content' => '[rl_explore]',
            ),
            'restaurant' => array(
                'title'   => 'Restaurant',
                'content' => '[rl_restaurant]',
            ),
            'my-entries' => array(
                'title'   => 'My Entries',
                'content' => '[rl_my_entries]',
            ),
            'manage-brand' => array(
                'title'   => 'Manage Restaurant',
                'content' => '[rl_manage_brand]',
            ),
            'manage-menu' => array(
                'title'   => 'Manage Redeem Menu',
                'content' => '[rl_manage_menu]',
            ),
            'manage-draws' => array(
                'title'   => 'Manage Lucky Draws',
                'content' => '[rl_manage_draws]',
            ),
            'privacy-policy' => array(
                'title'   => 'Privacy & Cookie Policy',
                'content' => '[rl_privacy_policy]',
            ),
            'verify-email' => array(
                'title'   => 'Verify Email',
                'content' => '[rl_verify_email]',
            ),
            'support' => array(
                'title'   => 'Support',
                'content' => '[rl_support]',
            ),
        );

        foreach ($pages as $slug => $page_data) {
            $existing_page = get_page_by_path($slug);

            if (!$existing_page) {
                wp_insert_post(array(
                    'post_title'     => $page_data['title'],
                    'post_content'   => $page_data['content'],
                    'post_status'    => 'publish',
                    'post_type'      => 'page',
                    'post_name'      => $slug,
                    'comment_status' => 'closed',
                ));
            }
        }

        self::claim_privacy_policy_page();
    }

    /**
     * Every fresh WordPress install auto-creates its own draft
     * "Privacy Policy" page at the same 'privacy-policy' slug this
     * plugin wants, pre-filled with WP core's own generic boilerplate
     * ("Suggested text: ..."). Since that page already exists, the
     * loop above's get_page_by_path() guard skips creating ours —
     * silently leaving WP's unpublished placeholder in place instead
     * of the plugin's real policy. This replaces it, but only when
     * it's unmistakably still that untouched default (identified by
     * the privacy-policy-tutorial class WP's own suggested text
     * uses) — never a page someone has actually written content into.
     */
    private static function claim_privacy_policy_page()
    {
        $page = get_page_by_path('privacy-policy');

        if (!$page || strpos($page->post_content, 'privacy-policy-tutorial') === false) {
            return;
        }

        wp_update_post(array(
            'ID'           => $page->ID,
            'post_title'   => 'Privacy & Cookie Policy',
            'post_content' => '[rl_privacy_policy]',
            'post_status'  => 'publish',
        ));

        // Also register it as WordPress's own designated privacy
        // policy page, so core's own privacy-policy links (e.g. on
        // comment forms) point at the same page instead of nothing.
        update_option('wp_page_for_privacy_policy', $page->ID);
    }

    private static function create_roles()
    {

        /*
        =========================
        BRAND MANAGER ROLE
        Oversees a whole brand/chain: locations, managers,
        reward menus, and draws.
        =========================
        */

        $brand_caps = array(
            'read'                  => true,
            'upload_files'          => true,
            'manage_brand'          => true,
            'manage_locations'      => true,
            'manage_redeem_items'   => true,
            'manage_loyalty_points' => true,
            'manage_draws'          => true,
        );

        $brand_manager = get_role('brand_manager');

        if (!$brand_manager) {

            add_role(
                'brand_manager',
                'Brand Manager',
                $brand_caps
            );

        } else {

            foreach ($brand_caps as $cap => $grant) {
                $brand_manager->add_cap($cap);
            }

        }

        /*
        =========================
        CUSTOMER ROLE
        =========================
        */

        $customer = get_role(
            'customer'
        );

        if (!$customer) {

            add_role(

                'customer',

                'Customer',

                array(

                    'read' => true

                )

            );

        }

        /*
        =========================
        ADMIN ACCESS
        (platform owner — full control over everything)
        =========================
        */

        $admin = get_role(
            'administrator'
        );

        if ($admin) {

            $admin_caps = array_keys($brand_caps);

            foreach ($admin_caps as $cap) {
                $admin->add_cap($cap);
            }

        }

        /*
        =========================
        LEGACY LOCATION STAFF ROLE
        Removed — every brand now uses one brand_manager account
        instead of per-location staff logins. This just cleans up the
        WP role on sites that registered it before the feature was
        removed; safe to run every time, no-op once it's gone.
        =========================
        */

        if (get_role('location_staff')) {
            remove_role('location_staff');
        }

    }

}