<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Shortcodes
{


    public function __construct()
    {
        add_shortcode('rl_lost_password', array($this, 'lost_password'));
        add_shortcode('rl_reset_password', array($this, 'reset_password'));
        add_shortcode(
            'rl_home',
            array(
                $this,
                'home'
            )
        );

        add_shortcode(
            'rl_register',
            array(
                $this,
                'register'
            )
        );


        add_shortcode(
            'rl_restaurant_dashboard',
            array(
                $this,
                'restaurant_dashboard'
            )
        );


        add_shortcode(
            'rl_dashboard',
            array(
                $this,
                'dashboard'
            )
        );


        add_shortcode(
            'rl_explore',
            array(
                $this,
                'explore'
            )
        );


        add_shortcode(
            'rl_restaurant',
            array(
                $this,
                'restaurant_page'
            )
        );


        add_shortcode(
            'rl_my_entries',
            array(
                $this,
                'my_entries_page'
            )
        );

        add_shortcode(
            'rl_privacy_policy',
            array(
                $this,
                'privacy_policy_page'
            )
        );

        add_shortcode(
            'rl_verify_email',
            array(
                $this,
                'verify_email_page'
            )
        );


    }

    /**
     * Resolves the rl_customers.id for the currently logged-in user,
     * or null if they're a guest / not a customer account. Shared by
     * the standalone Explore page and the embedded dashboard tab.
     */
    /**
     * A small "Open now" / "Closed now" / "Closed today" badge for a
     * location's brand, built from RL_Brand_Hours. Returns '' when
     * the brand hasn't configured any hours at all — nothing to show.
     */
    private function render_hours_badge($brand_id)
    {
        if (!RL_Brand_Hours::has_any_hours($brand_id)) {
            return '';
        }

        $status = RL_Brand_Hours::get_today_status($brand_id);

        if (empty($status['label'])) {
            return '';
        }

        $class = $status['closed'] ? 'rl-hours-badge rl-hours-badge-closed' : 'rl-hours-badge rl-hours-badge-open';

        return '<span class="' . esc_attr($class) . '">' . esc_html($status['label']) . '</span>';
    }

    /**
     * The full week's hours for a brand, Monday first, today
     * highlighted — for the restaurant's own page. Returns '' when
     * nothing has been configured.
     */
    private function render_hours_week($brand_id)
    {
        if (!RL_Brand_Hours::has_any_hours($brand_id)) {
            return '';
        }

        $hours = RL_Brand_Hours::get_all_for_brand($brand_id);
        $today = intval(current_time('w'));

        ob_start();
        ?>
        <div class="rl-hours-week">
            <?php foreach (RL_Brand_Hours::DISPLAY_ORDER as $day): ?>
                <?php $row = $hours[$day]; ?>
                <div class="rl-hours-week-row<?php echo $day === $today ? ' rl-hours-week-today' : ''; ?>">
                    <span class="rl-hours-week-day"><?php echo esc_html(rl_t('day_' . strtolower(RL_Brand_Hours::DAY_LABELS[$day]))); ?></span>
                    <span class="rl-hours-week-range">
                        <?php if (!empty($row->is_closed)): ?>
                            <?php echo esc_html(rl_t('hours_closed')); ?>
                        <?php else: ?>
                            <?php $range = RL_Brand_Hours::format_range($row->opening_time, $row->closing_time); ?>
                            <?php echo $range ? esc_html($range) : '—'; ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function current_customer_id()
    {

        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();

        if (!in_array('customer', $user->roles)) {
            return null;
        }

        global $wpdb;

        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rl_customers WHERE user_id = %d",
                $user->ID
            )
        );

        return $customer ? intval($customer->id) : null;
    }

    /**
     * The featured "big prize" card — the current platform-wide draw,
     * if one is running. Shared between Explore and the dashboard
     * Home tab so it always shows the same live entry count/odds.
     * Returns '' when there's nothing active right now.
     */
    private function render_big_prize_card($customer_id)
    {

        $active_platform_draws = RL_Draws::get_active_platform_draws();
        $big_prize = !empty($active_platform_draws) ? $active_platform_draws[0] : null;

        if (!$big_prize) {
            return '';
        }

        $chance = RL_Draws::get_win_chance($big_prize->id, $customer_id);

        ob_start();
        ?>

        <div class="rl-big-prize">
            <p class="rl-big-prize-eyebrow"><?php echo esc_html(rl_t('big_prize_eyebrow')); ?></p>
            <p class="rl-big-prize-title"><?php echo esc_html($big_prize->title); ?></p>

            <?php if (!empty($big_prize->prize_description)): ?>
                <p class="rl-big-prize-desc"><?php echo esc_html(RL_I18n::pick($big_prize->prize_description, $big_prize->prize_description_nl ?? '')); ?></p>
            <?php endif; ?>

            <div class="rl-big-prize-meta">
                <span class="rl-big-prize-pill">
                    <?php echo $customer_id ? intval($chance['entries']) . ' ' . esc_html(rl_t('big_prize_entries_suffix')) : esc_html(rl_t('big_prize_signup_to_enter')); ?>
                </span>
                <span class="rl-big-prize-ends"><?php echo esc_html(rl_t('big_prize_ends')); ?> <?php echo esc_html($big_prize->end_date); ?></span>
            </div>

            <?php if ($customer_id && $chance['entries'] > 0): ?>
                <p class="rl-big-prize-odds"><?php echo intval($chance['entries']); ?> <?php echo esc_html(rl_t('big_prize_odds')); ?> · <?php echo intval($chance['total']); ?> <?php echo esc_html(rl_t('big_prize_odds_total')); ?></p>
            <?php endif; ?>

            <p class="rl-big-prize-footnote"><?php echo esc_html(rl_t('big_prize_footnote')); ?></p>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * The same top header (logo + user name + total points) used on
     * the dashboard, for standalone pages outside the tabbed SPA —
     * keeps them feeling like part of the same app instead of a bare
     * page. Always shows the logged-in customer, not whatever page
     * you're on (e.g. a restaurant's name) — that stays constant
     * while page-specific context lives in the page title below it.
     */
    public function render_page_header()
    {
        $is_logged_in = is_user_logged_in();
        $customer_id  = $this->current_customer_id();

        $total_points = 0;

        if ($customer_id) {
            foreach (RL_Points::get_all_balances_for_customer($customer_id) as $wallet) {
                $total_points += floatval($wallet->points);
            }
        }

        $parts = explode('.', number_format(floatval($total_points), 2, '.', ''));

        ob_start();
        ?>
        <div class="rl-header">
            <div class="logoUsernameWrapper">
                <img
                    src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png"
                    class="rl-logo"
                    alt="Logo">

                <div class="rl-header-text">
                    <?php if ($is_logged_in): ?>
                        <h2><?php echo esc_html(rl_t('header_hello')); ?> <?php echo esc_html(wp_get_current_user()->display_name); ?></h2>
                        <p><?php echo esc_html(rl_t('header_welcome_back')); ?></p>
                    <?php else: ?>
                        <h2><?php echo esc_html(rl_t('header_welcome')); ?></h2>
                        <p><?php echo esc_html(rl_t('header_sign_in_earn')); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($customer_id): ?>
                <div class="rl-header-points">
                    <div class="points-badge-wrapper">
                        <div class="points-circle">
                            <span class="points-number">
                                <?php echo $parts[0]; ?>.<small><?php echo $parts[1]; ?></small>
                            </span>
                            <div class="points-tag">
                                <span class="tag-text rl-points-icon">⭐</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * The same bottom nav used on the dashboard, for standalone pages
     * outside the tabbed SPA (e.g. a restaurant's own page). There
     * are no in-page tabs to switch here, so each button is a plain
     * link back into the main app instead of a data-tab toggle — the
     * QR button deep-links via #qr, which customer-tabs.js picks up
     * on load to auto-select that tab.
     */
    private function render_bottom_nav()
    {
        ob_start();
        ?>
        <div class="rl-menu-overlay"></div>

        <div class="rl-menu-sheet">

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/#restaurants')); ?>">
                <span class="dashicons dashicons-location-alt"></span>
                <span><?php echo esc_html(rl_t('nav_restaurants')); ?></span>
            </a>

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/#prizes')); ?>">
                <span class="dashicons dashicons-tickets-alt"></span>
                <span><?php echo esc_html(rl_t('nav_prizes')); ?></span>
            </a>

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/my-entries')); ?>">
                <span class="dashicons dashicons-tickets"></span>
                <span><?php echo esc_html(rl_t('nav_entries')); ?></span>
            </a>

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/#history')); ?>">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <span><?php echo esc_html(rl_t('nav_activity')); ?></span>
            </a>

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/#invite')); ?>">
                <span class="dashicons dashicons-groups"></span>
                <span><?php echo esc_html(rl_t('nav_invite')); ?></span>
            </a>

            <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/#settings')); ?>">
                <span class="dashicons dashicons-admin-generic"></span>
                <span><?php echo esc_html(rl_t('nav_settings')); ?></span>
            </a>

            <a href="<?php echo esc_url(wp_logout_url(site_url('/login'))); ?>" class="rl-sheet-item">
                <span class="dashicons dashicons-migrate"></span>
                <span><?php echo esc_html(rl_t('nav_logout')); ?></span>
            </a>

        </div>

        <nav class="rl-bottom-nav">

            <a class="rl-nav-item" href="<?php echo esc_url(site_url('/')); ?>">
                <span class="dashicons dashicons-admin-home"></span>
            </a>

            <a class="rl-nav-center" href="<?php echo esc_url(site_url('/#qr')); ?>">
                <div class="rl-qr-button">
                    <svg width="50%" height="50%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.5 6.5H6.51M17.5 6.5H17.51M6.5 17.5H6.51M13 13H13.01M17.5 17.5H17.51M17 21H21V17M14 16.5V21M21 14H16.5M15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 19.4 3H15.6C15.0399 3 14.7599 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10ZM4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10ZM4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </a>

            <button type="button" class="rl-nav-menu" id="rl-more-restaurant-dash-btn">
                <span class="dashicons dashicons-menu"></span>
            </button>

        </nav>
        <?php
        return ob_get_clean();
    }

    /**
     * The bottom nav + hamburger sheet for restaurant_dashboard() (the
     * QR-scanner page brand managers/admins land on). Extracted out of
     * that method so the new front-end manager pages (Brand &
     * Locations / Reward Menus / Lucky Draws) can share the exact same
     * nav instead of duplicating it. $is_brand_manager also covers
     * administrators viewing this page — matches the caller's own
     * variable naming, kept as-is rather than renamed mid-refactor.
     */
    public function render_manager_bottom_nav($is_brand_manager)
    {
        ob_start();
        ?>
        <nav class="rl-bottom-nav">
    <?php if($is_brand_manager): ?>
        <a class="rl-nav-item" data-tab="home" href="<?php echo esc_url(site_url('/manage-menu')); ?>">
            <span class="dashicons dashicons-edit"></span>
        </a>
    <?php endif; ?>

    <button class="rl-nav-center" data-tab="">
        <div class="rl-qr-button">
            <span class="dashicons dashicons-camera-alt"></span>
        </div>
    </button>

    <button class="rl-nav-menu" id="rl-more-restaurant-dash-btn">
        <span class="dashicons dashicons-menu"></span>
    </button>
</nav>

<!-- Slide-up Menu Drawer -->
<div class="rl-menu-overlay" id="rl-restaurant-menu-overlay"></div>

<div class="rl-menu-sheet" id="rl-restaurant-menu-sheet">

    <?php if($is_brand_manager): ?>
        <a href="<?php echo esc_url(site_url('/manage-brand')); ?>" class="rl-sheet-item">
            <span class="dashicons dashicons-store"></span>
            <span>Brand & Locations</span>
        </a>

        <a href="<?php echo esc_url(site_url('/manage-menu')); ?>" class="rl-sheet-item">
            <span class="dashicons dashicons-tickets-alt"></span>
            <span>Reward Menus</span>
        </a>

        <?php
        // Lucky Draws is an opt-in extra feature, toggled per-brand
        // by an admin (RL_Brands_Admin's "Enable Lucky Draws for this
        // restaurant" checkbox) — administrators always see it
        // (they manage every brand's draws from wp-admin regardless),
        // a brand manager only if their own specific brand has it on.
        $show_lucky_draws = current_user_can('administrator');

        if (!$show_lucky_draws) {
            $nav_brand        = RL_Brands::get_by_manager(get_current_user_id());
            $show_lucky_draws = $nav_brand && !empty($nav_brand->prizes_enabled);
        }
        ?>
        <?php if ($show_lucky_draws): ?>
            <a href="<?php echo esc_url(site_url('/manage-draws')); ?>" class="rl-sheet-item">
                <span class="dashicons dashicons-awards"></span>
                <span>Lucky Draws</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <a href="<?php echo esc_url(wp_logout_url(site_url('/login'))); ?>" class="rl-sheet-item">
        <span class="dashicons dashicons-migrate"></span>
        <span>Logout</span>
    </a>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * A single restaurant/location's own page: address, the
     * customer's points balance there (if they're logged in and have
     * one), any prizes currently running for it, and its reward menu
     * grouped by category. Linked to from "Your points" on the
     * dashboard and from the Restaurants directory.
     */
    public function restaurant_page()
    {

        $location_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $location    = $location_id ? RL_Locations::get_with_brand($location_id) : null;

        if (!$location || $location->status !== 'active' || $location->brand_status !== 'active') {

            ob_start();
            ?>
            <div class="rl-dashboard rl-restaurant-page-wrap">

                <?php echo $this->render_page_header(); ?>

                <a href="<?php echo esc_url(site_url('/')); ?>" class="rl-page-back-btn" aria-label="Back">&larr;</a>

                <div class="rl-explore-page">
                    <div class="rl-page-title">
                        <div class="rl-title-content">
                            <h3><?php echo esc_html(rl_t('restaurant_not_found')); ?></h3>
                            <p><?php echo esc_html(rl_t('restaurant_not_found_body')); ?></p>
                        </div>
                    </div>
                </div>

            </div>

            <?php echo $this->render_bottom_nav(); ?>
            <?php
            return ob_get_clean();
        }

        $customer_id = $this->current_customer_id();

        $balance = null;
        if ($customer_id) {
            $balance = RL_Points::get_balance($location->id, $customer_id);
        }

        $menu_groups = RL_Redeem_Items::get_all_grouped_by_category($location->id);

        $has_named_categories = false;
        foreach ($menu_groups as $group) {
            if ($group['category']) {
                $has_named_categories = true;
                break;
            }
        }

        $active_prizes = array();
        foreach (RL_Draws::get_all_active_public() as $draw) {
            if (RL_Draws::draw_applies_to_location($draw, $location->id, $location->brand_id)) {
                $active_prizes[] = $draw;
            }
        }

        $hours_badge_html = $this->render_hours_badge($location->brand_id);

        $has_pin = ($location->lat !== null && $location->lng !== null && $location->lat !== '' && $location->lng !== '');

        // Google Maps' universal "dir" link works the same on
        // desktop, Android, and iOS — it opens the Google Maps app if
        // installed, otherwise falls back to maps in the browser.
        // Coordinates give a precise pin; the address is a reasonable
        // fallback for a location that hasn't been geocoded yet.
        $directions_url = '';

        if ($has_pin) {
            $directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($location->lat . ',' . $location->lng);
        } elseif (!empty($location->address)) {
            $directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($location->name . ', ' . $location->address);
        }

        // The cheapest reward still out of reach (the "get there"
        // motivator) alongside every reward already affordable right
        // now — a balance alone doesn't say "you can already claim
        // something," so that's worth surfacing just as much as what's
        // still ahead.
        $next_reward = null;
        $available_rewards = array();

        if ($customer_id && $balance !== null && $balance !== false) {
            foreach (RL_Redeem_Items::get_all($location->id, true) as $reward_candidate) {
                if (floatval($reward_candidate->points_cost) > floatval($balance)) {
                    if (!$next_reward || floatval($reward_candidate->points_cost) < floatval($next_reward->points_cost)) {
                        $next_reward = $reward_candidate;
                    }
                } else {
                    $available_rewards[] = $reward_candidate;
                }
            }

            // Priciest (most valuable) first, so the best thing a
            // customer can already claim is the one they see first.
            usort($available_rewards, function ($a, $b) {
                return floatval($b->points_cost) <=> floatval($a->points_cost);
            });
        }

        ob_start();
        ?>

        <div class="rl-dashboard rl-restaurant-page-wrap">

            <?php echo $this->render_page_header(); ?>

        <div class="rl-explore-page rl-restaurant-page">

            <a href="javascript:history.back()" class="rl-page-back-btn" aria-label="Back">&larr;</a>

            <div class="rl-restaurant-hero-photo">
                <?php if (!empty($location->brand_logo)): ?>
                    <img src="<?php echo esc_url($location->brand_logo); ?>" alt="">
                <?php else: ?>
                    <span class="rl-restaurant-hero-photo-fallback dashicons dashicons-store"></span>
                <?php endif; ?>
            </div>

            <div class="rl-restaurant-header">

                <div class="rl-restaurant-name-row">
                    <h3><?php echo esc_html($location->name); ?></h3>
                </div>

                <?php if (!empty($location->address)): ?>
                    <p class="rl-restaurant-address"><?php echo esc_html($location->address); ?></p>
                <?php endif; ?>

                <?php if ($has_pin): ?>
                    <a class="rl-restaurant-distance rl-directions-link" href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer" data-lat="<?php echo esc_attr($location->lat); ?>" data-lng="<?php echo esc_attr($location->lng); ?>">
                        <span class="rl-distance-icon">📍</span>
                        <span class="rl-distance-value"><span class="rl-distance-spinner" aria-hidden="true"></span></span>
                        <span class="rl-directions-label"><?php echo esc_html(rl_t('get_directions')); ?></span>
                    </a>
                <?php elseif ($directions_url): ?>
                    <a class="rl-directions-link rl-directions-link-standalone" href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="rl-distance-icon">📍</span>
                        <span class="rl-directions-label"><?php echo esc_html(rl_t('get_directions')); ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($hours_badge_html || $active_prizes): ?>
                    <div class="rl-restaurant-info-card-footer">
                        <?php echo $hours_badge_html; ?>
                        <?php if ($active_prizes): ?>
                            <span class="rl-active-prize-badge">🎁 <?php echo count($active_prizes) > 1 ? intval(count($active_prizes)) . ' active prizes' : 'Active prize'; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php if ($customer_id && $balance !== null && $balance !== false): ?>
                <div class="rl-restaurant-balance-card">
                    <div class="rl-restaurant-balance-row">
                        <span><?php echo esc_html(rl_t('restaurant_balance_here')); ?></span>
                        <div class="rl-reward-price">⭐ <?php echo intval($balance); ?></div>
                    </div>

                    <?php if ($next_reward): ?>
                        <?php
                        $restaurant_progress_pct = floatval($balance) > 0
                            ? min(100, round((floatval($balance) / floatval($next_reward->points_cost)) * 100))
                            : 0;
                        ?>
                        <div class="rl-reward-progress">
                            <div class="rl-reward-progress-label">
                                <span><?php echo esc_html(rl_t('restaurant_next')); ?> <?php echo esc_html($next_reward->title); ?></span>
                                <b><?php echo intval($balance); ?> / <?php echo intval($next_reward->points_cost); ?></b>
                            </div>
                            <div class="rl-reward-progress-track">
                                <div class="rl-reward-progress-fill" style="width:<?php echo esc_attr($restaurant_progress_pct); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($available_rewards): ?>
                        <div class="rl-balance-available">
                            <div class="rl-balance-available-label"><?php echo esc_html(rl_t('home_ready_to_redeem')); ?></div>
                            <div class="rl-balance-available-chips">
                                <?php foreach ($available_rewards as $available_item): ?>
                                    <span class="rl-balance-available-chip">🎁 <?php echo esc_html($available_item->title); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($customer_id && !empty($location->pool_points)): ?>
                <p class="rl-restaurant-balance-note"><?php echo esc_html(rl_t('home_shared_across')); ?> <?php echo esc_html($location->brand_name); ?> <?php echo esc_html(rl_t('home_locations')); ?></p>
            <?php elseif (!is_user_logged_in()): ?>
                <p class="rl-register-text">
                    <a href="<?php echo esc_url(site_url('/register')); ?>"><?php echo esc_html(rl_t('restaurant_create_account')); ?></a> <?php echo esc_html(rl_t('restaurant_start_earning')); ?>
                </p>
            <?php endif; ?>

            <?php $hours_week = $this->render_hours_week($location->brand_id); ?>

            <?php if ($active_prizes): ?>
                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3>Active prizes</h3>
                        <p>Running right now at this restaurant.</p>
                    </div>
                </div>

                <?php foreach ($active_prizes as $draw): ?>
                    <?php $prize_chance = RL_Draws::get_win_chance($draw->id, $customer_id); ?>
                    <div class="rl-prize-card">
                        <?php if (!empty($draw->prize_image)): ?>
                            <div class="rl-prize-card-image">
                                <img src="<?php echo esc_url($draw->prize_image); ?>" alt="<?php echo esc_attr($draw->title); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="rl-prize-card-body">
                            <span class="rl-prize-name-badge">🎁 <?php echo esc_html($draw->title); ?></span>
                            <?php if (!empty($draw->prize_description)): ?>
                                <p class="rl-prize-card-desc"><?php echo esc_html(RL_I18n::pick($draw->prize_description, $draw->prize_description_nl ?? '')); ?></p>
                            <?php endif; ?>
                            <div class="rl-prize-card-footer">
                                <span class="rl-prize-card-ends">Ends <?php echo esc_html($draw->end_date); ?></span>
                                <?php if ($customer_id && $prize_chance['entries'] > 0): ?>
                                    <span class="rl-prize-card-odds"><?php echo intval($prize_chance['entries']); ?> entries</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="rl-page-title">
                <div class="rl-title-content">
                    <h3><?php echo esc_html(rl_t('restaurant_menu')); ?></h3>
                    <p><?php echo esc_html(rl_t('restaurant_menu_subtitle')); ?></p>
                </div>
            </div>

            <?php if ($menu_groups && $has_named_categories): ?>
                <div class="rl-filter-chips rl-menu-category-tabs" id="rl-menu-category-tabs">
                    <button type="button" class="rl-filter-chip active" data-category="all"><?php echo esc_html(rl_t('filter_all')); ?></button>
                    <?php foreach ($menu_groups as $group): ?>
                        <?php
                        $cat_key   = $group['category'] ? 'cat-' . intval($group['category']->id) : 'uncategorized';
                        $cat_label = $group['category'] ? $group['category']->name : 'More';
                        ?>
                        <button type="button" class="rl-filter-chip" data-category="<?php echo esc_attr($cat_key); ?>"><?php echo esc_html($cat_label); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($menu_groups): ?>

                <?php foreach ($menu_groups as $group): ?>

                    <?php $cat_key = $group['category'] ? 'cat-' . intval($group['category']->id) : 'uncategorized'; ?>

                    <div class="rl-menu-category-group" data-category="<?php echo esc_attr($cat_key); ?>">

                    <?php if ($group['category']): ?>
                        <h4 class="rl-menu-category-title"><?php echo esc_html($group['category']->name); ?></h4>
                    <?php elseif (count($menu_groups) > 1): ?>
                        <h4 class="rl-menu-category-title"><?php echo esc_html(rl_t('restaurant_menu_more')); ?></h4>
                    <?php endif; ?>

                    <?php foreach ($group['items'] as $item): ?>

                        <?php
                        $is_locked     = ($balance !== null && $balance !== false && floatval($balance) < floatval($item->points_cost));
                        $points_needed = $is_locked ? ceil(floatval($item->points_cost) - floatval($balance)) : 0;
                        ?>

                        <div class="rl-reward-item<?php echo $is_locked ? ' rl-reward-item-locked' : ''; ?>">

                            <div class="rl-reward-image">
                                <?php if (!empty($item->image)): ?>
                                    <img src="<?php echo esc_url($item->image); ?>" alt="<?php echo esc_attr($item->title); ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="rl-reward-image-fallback">🎁</span>
                                <?php endif; ?>
                            </div>

                            <div class="rl-reward-content">
                                <div class="rl-reward-top">
                                    <h4><?php echo esc_html($item->title); ?></h4>
                                    <div class="rl-reward-price">⭐ <?php echo intval($item->points_cost); ?></div>
                                </div>

                                <?php if ($is_locked): ?>
                                    <p class="rl-reward-locked-note"><span class="dashicons dashicons-lock"></span> <?php echo intval($points_needed); ?> points to go</p>
                                <?php elseif (!empty($item->description)): ?>
                                    <p><?php echo esc_html($item->description); ?></p>
                                <?php endif; ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p><?php echo esc_html(rl_t('restaurant_no_menu')); ?></p>

            <?php endif; ?>

            <?php if ($has_pin): ?>
                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3><?php echo esc_html(rl_t('restaurant_location')); ?></h3>
                    </div>
                </div>
                <div
                    id="rl-restaurant-single-map"
                    class="rl-restaurant-single-map"
                    data-lat="<?php echo esc_attr($location->lat); ?>"
                    data-lng="<?php echo esc_attr($location->lng); ?>"
                    data-name="<?php echo esc_attr($location->name); ?>"
                ></div>
            <?php endif; ?>

            <?php if ($hours_week): ?>
                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3><?php echo esc_html(rl_t('restaurant_hours')); ?></h3>
                    </div>
                </div>
                <?php echo $hours_week; ?>
            <?php endif; ?>

        </div>

        </div>

        <?php echo $this->render_bottom_nav(); ?>

        <?php
        return ob_get_clean();
    }

    /**
     * "My Entries": every prize draw the customer currently has
     * tickets in that's still actually running — restaurant name,
     * prize name, ticket count, and expiry, one card per draw, same
     * rl-prize-card style used everywhere else in the app. Ended and
     * not-yet-started draws are left out entirely; the full history
     * (including ended ones) still lives in the dashboard's
     * Activity → Entries view.
     */
    public function my_entries_page()
    {
        if (!is_user_logged_in()) {
            wp_redirect(site_url('/login'));
            exit;
        }

        $customer_id    = $this->current_customer_id();
        $active_entries = $customer_id ? RL_Draws::get_customer_active_entries($customer_id) : array();
        $ended_entries  = $customer_id ? RL_Draws::get_customer_ended_entries($customer_id) : array();

        ob_start();
        ?>
        <div class="rl-dashboard rl-restaurant-page-wrap">

            <?php echo $this->render_page_header(); ?>

            <div class="rl-explore-page">

                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3><?php echo esc_html(rl_t('entries_title')); ?></h3>
                        <p><?php echo esc_html(rl_t('entries_subtitle')); ?></p>
                    </div>
                </div>

                <div class="rl-filter-chips" id="rl-entries-filters">
                    <button type="button" class="rl-filter-chip active" data-view="active"><?php echo esc_html(rl_t('entries_active')); ?></button>
                    <button type="button" class="rl-filter-chip" data-view="ended"><?php echo esc_html(rl_t('entries_ended')); ?></button>
                </div>

                <div id="rl-entries-active-view">

                    <?php if ($active_entries): ?>

                        <?php foreach ($active_entries as $row): ?>

                            <?php $scope_label = esc_html(RL_Draws::get_public_scope_label($row)); ?>

                            <div class="rl-prize-card">

                                <?php if (!empty($row->prize_image)): ?>
                                    <div class="rl-prize-card-image">
                                        <img src="<?php echo esc_url($row->prize_image); ?>" alt="<?php echo esc_attr($row->draw_title); ?>">
                                    </div>
                                <?php endif; ?>

                                <div class="rl-prize-card-body">

                                    <p class="rl-prize-card-scope"><?php echo $scope_label; ?></p>

                                    <span class="rl-prize-name-badge">🎁 <?php echo esc_html($row->draw_title); ?></span>

                                    <?php if (!empty($row->prize_description)): ?>
                                        <p class="rl-prize-card-desc"><?php echo esc_html(RL_I18n::pick($row->prize_description, $row->prize_description_nl ?? '')); ?></p>
                                    <?php endif; ?>

                                    <div class="rl-prize-card-footer">
                                        <span class="rl-prize-card-ends"><?php echo esc_html(rl_t('big_prize_ends')); ?> <?php echo esc_html($row->end_date); ?></span>
                                        <span class="rl-prize-card-odds"><?php echo intval($row->entry_count); ?> <?php echo esc_html($row->entry_count == 1 ? rl_t('entries_entry_singular') : rl_t('entries_entry_plural')); ?></span>
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p><?php echo esc_html(rl_t('entries_none_active')); ?></p>

                    <?php endif; ?>

                </div>

                <div id="rl-entries-ended-view" style="display:none;">

                    <?php if ($ended_entries): ?>

                        <?php foreach ($ended_entries as $row): ?>

                            <?php
                            $entry_scope = rl_t('entries_platform_prize');
                            if (!empty($row->location_name)) {
                                $entry_scope = esc_html($row->brand_name . ' — ' . $row->location_name);
                            } elseif (!empty($row->brand_name)) {
                                $entry_scope = esc_html($row->brand_name) . ' — ' . esc_html(rl_t('entries_all_locations_suffix'));
                            }

                            $you_won = ($row->winner_customer_id && intval($row->winner_customer_id) === intval($customer_id));
                            ?>

                            <div class="rl-transaction">

                                <strong><?php echo intval($row->entry_count); ?> <?php echo esc_html($row->entry_count == 1 ? rl_t('entries_entry_singular') : rl_t('entries_entry_plural')); ?></strong>

                                <p class="rl-transaction-place"><?php echo $entry_scope; ?></p>

                                <p><?php echo esc_html($row->draw_title); ?>
                                    <?php if ($you_won): ?>
                                        <span class="rl-prize-card-ends" style="margin-left:6px;"><?php echo esc_html(rl_t('entries_you_won')); ?></span>
                                    <?php elseif ($row->draw_status === 'ended'): ?>
                                        <span style="color:#9ca3af;"> · <?php echo esc_html(rl_t('entries_draw_ended')); ?></span>
                                    <?php endif; ?>
                                </p>

                                <small><?php echo esc_html(rl_t('entries_last_entry')); ?> <?php echo esc_html($row->last_entry_at); ?></small>

                            </div>

                            <hr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p><?php echo esc_html(rl_t('entries_none_ended')); ?></p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <?php echo $this->render_bottom_nav(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Privacy & Cookie Policy — open to everyone (guests included),
     * since it has to be reachable before someone even has an
     * account, and linked from the registration form's consent
     * checkbox. Content reflects this plugin's actual data practices
     * as of when it was written — review it again if the app's data
     * handling changes (new third-party service, new data field,
     * etc.) rather than assuming it stays accurate on its own.
     */
    public function privacy_policy_page()
    {
        ob_start();
        ?>
        <div class="rl-dashboard rl-restaurant-page-wrap">

            <?php echo $this->render_page_header(); ?>

            <div class="rl-explore-page">

                <div class="rl-page-title">
                    <div class="rl-title-content">
                        <h3><?php echo rl_t('policy_page_title'); ?></h3>
                        <p><?php echo esc_html(rl_t('policy_last_updated')); ?></p>
                    </div>
                </div>

                <div class="rl-policy-content">

                    <h4><?php echo esc_html(rl_t('policy_s1_heading')); ?></h4>
                    <?php echo rl_t('policy_s1_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s2_heading')); ?></h4>
                    <?php echo rl_t('policy_s2_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s3_heading')); ?></h4>
                    <?php echo rl_t('policy_s3_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s4_heading')); ?></h4>
                    <?php echo rl_t('policy_s4_body'); ?>

                    <h4><?php echo rl_t('policy_s5_heading'); ?></h4>
                    <?php echo rl_t('policy_s5_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s6_heading')); ?></h4>
                    <?php echo rl_t('policy_s6_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s7_heading')); ?></h4>
                    <?php echo rl_t('policy_s7_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s8_heading')); ?></h4>
                    <?php echo rl_t('policy_s8_body'); ?>

                    <h4><?php echo esc_html(rl_t('policy_s9_heading')); ?></h4>
                    <?php echo rl_t('policy_s9_body'); ?>

                </div>

            </div>

        </div>

        <?php echo $this->render_bottom_nav(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Public directory: every active restaurant on the app, on a map,
     * plus every currently running lucky draw across all of them.
     * Open to guests as well as logged-in customers. Used both as the
     * standalone /explore page and — via render_prizes_body() /
     * render_restaurants_body() — embedded as tabs inside the
     * logged-in dashboard.
     */
    public function explore()
    {

        $customer_id = $this->current_customer_id();

        ob_start();
        ?>

        <div class="rl-explore-page">

            <div class="rl-page-title">
                <div class="rl-title-content">
                    <h3><?php echo esc_html(rl_t('explore_title')); ?></h3>
                    <p><?php echo esc_html(rl_t('explore_subtitle')); ?></p>
                </div>
            </div>

            <?php if (!is_user_logged_in()): ?>
                <p class="rl-register-text">
                    <a href="<?php echo esc_url(site_url('/register')); ?>"><?php echo esc_html(rl_t('restaurant_create_account')); ?></a> <?php echo esc_html(rl_t('explore_create_account_suffix')); ?>
                </p>
            <?php endif; ?>

            <div class="rl-filter-chips" id="rl-explore-section-tabs">
                <button type="button" class="rl-filter-chip active" data-section="prizes"><?php echo esc_html(rl_t('explore_tab_prizes')); ?></button>
                <button type="button" class="rl-filter-chip" data-section="restaurants"><?php echo esc_html(rl_t('explore_tab_restaurants')); ?></button>
            </div>

            <div id="rl-explore-prizes-section">
                <?php echo $this->render_prizes_body($customer_id); ?>
            </div>

            <div id="rl-explore-restaurants-section" style="display:none;">
                <?php echo $this->render_restaurants_body($customer_id); ?>
            </div>

        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * The Prizes tab/page: the platform "big prize" featured on its
     * own, plus every prize currently running at an individual
     * restaurant. Returns raw HTML — caller supplies the page chrome.
     */
    private function render_prizes_body($customer_id)
    {

        $draws = RL_Draws::get_all_active_public();

        $other_prizes = array();

        foreach ($draws as $draw) {
            if (!(empty($draw->brand_id) && empty($draw->location_id))) {
                $other_prizes[] = $draw;
            }
        }

        ob_start();
        ?>

        <?php echo $this->render_big_prize_card($customer_id); ?>

        <div class="rl-page-title">
            <div class="rl-title-content">
                <h3><?php echo esc_html(rl_t('prizes_active_title')); ?></h3>
                <p><?php echo esc_html(rl_t('prizes_active_subtitle')); ?></p>
            </div>
        </div>

        <?php if ($other_prizes): ?>

            <div class="rl-filter-chips" id="rl-prizes-filters">
                <button type="button" class="rl-filter-chip active" data-view="list"><?php echo esc_html(rl_t('filter_all')); ?></button>
                <button type="button" class="rl-filter-chip" data-view="map"><?php echo esc_html(rl_t('filter_map')); ?></button>
            </div>

            <div id="rl-prizes-list-view">

                <?php foreach ($other_prizes as $draw): ?>

                    <?php
                    $scope_label  = esc_html(RL_Draws::get_public_scope_label($draw));
                    $prize_chance = RL_Draws::get_win_chance($draw->id, $customer_id);

                    // Every location this draw currently applies to (a
                    // single restaurant, an explicit subset, or the whole
                    // chain) — used to show "how far is this" and to link
                    // through to the right pin on the map.
                    $draw_locations = array();

                    if (!empty($draw->location_id)) {
                        $single = RL_Locations::get($draw->location_id);
                        if ($single) {
                            $draw_locations[] = $single;
                        }
                    } elseif (!empty($draw->brand_id)) {
                        $subset_ids      = RL_Draws::get_draw_location_ids($draw->id);
                        $brand_locations = RL_Locations::get_all_by_brand($draw->brand_id, true);

                        foreach ($brand_locations as $bl) {
                            if (empty($subset_ids) || in_array((int) $bl->id, $subset_ids, true)) {
                                $draw_locations[] = $bl;
                            }
                        }
                    }

                    $mapped_locations = array();

                    foreach ($draw_locations as $dl) {
                        if ($dl->lat !== null && $dl->lng !== null && $dl->lat !== '' && $dl->lng !== '') {
                            $mapped_locations[] = array(
                                'id'  => intval($dl->id),
                                'lat' => floatval($dl->lat),
                                'lng' => floatval($dl->lng),
                            );
                        }
                    }

                    $default_pin = !empty($mapped_locations) ? $mapped_locations[0] : null;
                    ?>

                    <div class="rl-prize-card">

                        <?php if (!empty($draw->prize_image)): ?>
                            <div class="rl-prize-card-image">
                                <img src="<?php echo esc_url($draw->prize_image); ?>" alt="<?php echo esc_attr($draw->title); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="rl-prize-card-body">

                            <p class="rl-prize-card-scope"><?php echo $scope_label; ?></p>

                            <div class="rl-prize-card-top">
                                <span class="rl-prize-name-badge">🎁 <?php echo esc_html($draw->title); ?></span>

                                <?php if ($default_pin): ?>
                                    <button
                                        type="button"
                                        class="rl-prize-view-on-map-btn"
                                        data-locations="<?php echo esc_attr(wp_json_encode($mapped_locations)); ?>"
                                        data-location-id="<?php echo intval($default_pin['id']); ?>"
                                        data-lat="<?php echo esc_attr($default_pin['lat']); ?>"
                                        data-lng="<?php echo esc_attr($default_pin['lng']); ?>"
                                        title="<?php echo esc_attr(rl_t('view_on_map')); ?>"
                                    >
                                        <span class="dashicons dashicons-location"></span>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($default_pin): ?>
                                <p class="rl-prize-card-distance">
                                    <span class="rl-distance-icon">📍</span>
                                    <span class="rl-distance-value"><span class="rl-distance-spinner" aria-hidden="true"></span></span>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($draw->prize_description)): ?>
                                <p class="rl-prize-card-desc"><?php echo esc_html(RL_I18n::pick($draw->prize_description, $draw->prize_description_nl ?? '')); ?></p>
                            <?php endif; ?>

                            <?php if ($draw->min_points_threshold !== null && floatval($draw->min_points_threshold) > 0): ?>
                                <p class="rl-prize-card-rule"><?php echo esc_html(sprintf(rl_t('prize_min_points_rule'), number_format(floatval($draw->min_points_threshold), 0))); ?></p>
                            <?php endif; ?>

                            <div class="rl-prize-card-footer">
                                <span class="rl-prize-card-ends"><?php echo esc_html(rl_t('big_prize_ends')); ?> <?php echo esc_html($draw->end_date); ?></span>

                                <?php if ($customer_id && $prize_chance['entries'] > 0): ?>
                                    <span class="rl-prize-card-odds"><?php echo intval($prize_chance['entries']); ?> <?php echo esc_html(rl_t('big_prize_entries_suffix')); ?></span>
                                <?php elseif ($customer_id): ?>
                                    <span class="rl-prize-card-odds rl-prize-card-odds-none"><?php echo esc_html(rl_t('prize_not_entered_yet')); ?></span>
                                <?php else: ?>
                                    <span class="rl-prize-card-odds rl-prize-card-odds-none"><?php echo esc_html(rl_t('big_prize_signup_to_enter')); ?></span>
                                <?php endif; ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div id="rl-prizes-map-view" style="display:none;">
                <div id="rl-prizes-map"></div>
            </div>

        <?php else: ?>

            <p><?php echo esc_html(rl_t('prizes_none_running')); ?></p>

        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    /**
     * The Restaurants tab/page: the full directory (list or map),
     * each restaurant linking through to its own page/menu. Returns
     * raw HTML — caller supplies the page chrome.
     */
    private function render_restaurants_body($customer_id)
    {

        $locations = RL_Locations::get_all_public();
        $active_draws = RL_Draws::get_all_active_public();

        $visited_location_ids = $customer_id ? RL_Transactions::get_visited_location_ids($customer_id) : array();

        $by_brand = array();

        foreach ($locations as $loc) {
            $by_brand[$loc->brand_name][] = $loc;
        }

        ob_start();
        ?>

        <div class="rl-page-title">
            <div class="rl-title-content">
                <h3><?php echo esc_html(rl_t('restaurants_title')); ?></h3>
                <p><?php echo esc_html(rl_t('restaurants_subtitle')); ?></p>
            </div>
        </div>

        <div class="rl-explore-searchbar">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="rl-explore-search" placeholder="<?php echo esc_attr(rl_t('restaurants_search')); ?>" autocomplete="off">
        </div>

        <div class="rl-filter-chips" id="rl-explore-filters">
            <button type="button" class="rl-filter-chip active" data-view="list"><?php echo esc_html(rl_t('filter_all')); ?></button>
            <?php if ($customer_id): ?>
                <button type="button" class="rl-filter-chip" data-view="visited"><?php echo esc_html(rl_t('filter_visited')); ?></button>
            <?php endif; ?>
            <button type="button" class="rl-filter-chip" data-view="map"><?php echo esc_html(rl_t('filter_near_me')); ?></button>
        </div>

        <div id="rl-explore-list-view">

            <?php if ($by_brand): ?>

                <?php foreach ($by_brand as $brand_name => $brand_locations): ?>

                    <?php foreach ($brand_locations as $loc): ?>

                        <?php
                        $loc_balance = $customer_id ? RL_Points::get_balance($loc->id, $customer_id) : null;
                        $loc_has_pin = ($loc->lat !== null && $loc->lng !== null && $loc->lat !== '' && $loc->lng !== '');
                        $loc_visited = in_array((int) $loc->id, $visited_location_ids, true);
                        $loc_hours_badge = $this->render_hours_badge($loc->brand_id);

                        $loc_active_prize_title = '';

                        foreach ($active_draws as $draw) {
                            if (RL_Draws::draw_applies_to_location($draw, $loc->id, $loc->brand_id)) {
                                $loc_active_prize_title = $draw->title;
                                break;
                            }
                        }

                        // Only worth querying this location's menu when the
                        // customer actually has a balance there — most cards
                        // in the directory are places they've never earned
                        // points at, so this stays a no-op for those.
                        $loc_next_reward = null;
                        $loc_available_rewards = array();

                        if ($customer_id && $loc_balance !== null && $loc_balance !== false && floatval($loc_balance) > 0) {
                            foreach (RL_Redeem_Items::get_all($loc->id, true) as $reward_candidate) {
                                if (floatval($reward_candidate->points_cost) > floatval($loc_balance)) {
                                    if (!$loc_next_reward || floatval($reward_candidate->points_cost) < floatval($loc_next_reward->points_cost)) {
                                        $loc_next_reward = $reward_candidate;
                                    }
                                } else {
                                    $loc_available_rewards[] = $reward_candidate;
                                }
                            }

                            usort($loc_available_rewards, function ($a, $b) {
                                return floatval($b->points_cost) <=> floatval($a->points_cost);
                            });
                        }
                        ?>

                        <div
                            class="rl-reward-item rl-reward-item-card rl-restaurant-card-has-photo"
                            id="rl-location-<?php echo intval($loc->id); ?>"
                            data-visited="<?php echo $loc_visited ? '1' : '0'; ?>"
                            data-search="<?php echo esc_attr(strtolower($loc->brand_name . ' ' . $loc->name)); ?>"
                            <?php if ($loc_has_pin): ?>
                                data-lat="<?php echo esc_attr($loc->lat); ?>"
                                data-lng="<?php echo esc_attr($loc->lng); ?>"
                            <?php endif; ?>
                        >

                            <a class="rl-reward-item-link" href="<?php echo esc_url(site_url('/restaurant/?id=' . intval($loc->id))); ?>">

                                <div class="rl-restaurant-card-photo">
                                    <?php if (!empty($loc->brand_logo)): ?>
                                        <img src="<?php echo esc_url($loc->brand_logo); ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="rl-restaurant-card-photo-fallback dashicons dashicons-store"></span>
                                    <?php endif; ?>

                                    <?php if ($loc_has_pin): ?>
                                        <button
                                            type="button"
                                            class="rl-view-on-map-btn"
                                            data-location-id="<?php echo intval($loc->id); ?>"
                                            data-lat="<?php echo esc_attr($loc->lat); ?>"
                                            data-lng="<?php echo esc_attr($loc->lng); ?>"
                                            title="<?php echo esc_attr(rl_t('view_on_map')); ?>"
                                        >
                                            <span class="dashicons dashicons-location"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="rl-reward-content">

                                    <div class="rl-reward-top">
                                        <h4><?php echo esc_html($loc->name); ?></h4>

                                        <?php if ($customer_id): ?>
                                            <div class="rl-reward-price">⭐ <?php echo intval($loc_balance); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($loc->address)): ?>
                                        <small><?php echo esc_html($loc->address); ?></small>
                                    <?php endif; ?>

                                    <?php if ($loc_has_pin): ?>
                                        <p class="rl-restaurant-distance" data-lat="<?php echo esc_attr($loc->lat); ?>" data-lng="<?php echo esc_attr($loc->lng); ?>">
                                            <span class="rl-distance-icon">📍</span>
                                            <span class="rl-distance-value"><span class="rl-distance-spinner" aria-hidden="true"></span></span>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($loc_next_reward): ?>
                                        <?php
                                        $loc_progress_pct = floatval($loc_balance) > 0
                                            ? min(100, round((floatval($loc_balance) / floatval($loc_next_reward->points_cost)) * 100))
                                            : 0;
                                        ?>
                                        <div class="rl-reward-progress">
                                            <div class="rl-reward-progress-label">
                                                <span><?php echo esc_html(rl_t('restaurant_next')); ?> <?php echo esc_html($loc_next_reward->title); ?></span>
                                                <b><?php echo intval($loc_balance); ?> / <?php echo intval($loc_next_reward->points_cost); ?></b>
                                            </div>
                                            <div class="rl-reward-progress-track">
                                                <div class="rl-reward-progress-fill" style="width:<?php echo esc_attr($loc_progress_pct); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($loc_available_rewards): ?>
                                        <div class="rl-balance-available">
                                            <div class="rl-balance-available-label"><?php echo esc_html(rl_t('home_ready_to_redeem')); ?></div>
                                            <div class="rl-balance-available-chips">
                                                <?php foreach ($loc_available_rewards as $loc_available_item): ?>
                                                    <span class="rl-balance-available-chip">🎁 <?php echo esc_html($loc_available_item->title); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                </div>

                            </a>

                            <?php if ($loc_hours_badge || $loc_active_prize_title !== ''): ?>
                                <div class="rl-reward-footer-row">

                                    <div class="rl-reward-badges">
                                        <?php echo $loc_hours_badge; ?>

                                        <?php if ($loc_active_prize_title !== ''): ?>
                                            <span class="rl-active-prize-badge">🎁 <?php echo esc_html($loc_active_prize_title); ?></span>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            <?php else: ?>

                <p><?php echo esc_html(rl_t('restaurants_none_joined')); ?></p>

            <?php endif; ?>

        </div>

        <div id="rl-explore-map-view" style="display:none;">
            <div id="rl-explore-map"></div>
        </div>

        <?php
        return ob_get_clean();
    }



    public function home()
{
    if (!is_user_logged_in()) {

        // This page issues a fresh per-visit cookie (RL_Google_Auth::
        // get_authorize_url()'s CSRF state binding) and a fresh WP
        // login nonce on every render — caching it anywhere (CDN,
        // browser, a caching plugin) means a visitor gets served a
        // stale, already-superseded token: Google sign-in fails with
        // a generic "sign-in failed" error, and a plain username/
        // password login fails its nonce check on the first attempt
        // (then works on retry, since the failed-attempt response
        // itself isn't cached and carries a fresh nonce) — both look
        // like real bugs rather than a stale cache.
        //
        // nocache_headers() covers well-behaved layers that respect
        // standard Cache-Control headers (confirmed working against
        // Hostinger's CDN). LiteSpeed Cache (the WordPress plugin)
        // does NOT reliably honor it on this host and kept re-caching
        // this page anyway — it needs to be told directly via its own
        // API, which this second call does. Safe to call even when
        // LiteSpeed Cache isn't installed (do_action on a hook with
        // no listeners is a no-op).
        nocache_headers();
        do_action('litespeed_control_set_nocache', 'rl_login_page_dynamic_tokens');

        $error_message    = '';
        $unverified_email = '';
        $resend_success   = false;

        // Surface an error bounced back from the Google OAuth callback
        // (RL_Google_Auth::maybe_handle_callback()) — e.g. the user
        // cancelled, or their Google email isn't verified.
        if (!empty($_GET['rl_google_error'])) {
            $error_message = 'Google sign-in failed. Please try again, or log in with your email and password.';
        }

        // 1. Process login submission FIRST (before ob_start so wp_redirect works properly)
        if (isset($_POST['rl_login_submit'])) {

            if (
                !isset($_POST['rl_login_nonce'])
                ||
                !wp_verify_nonce(
                    $_POST['rl_login_nonce'],
                    'rl_login_action'
                )
            ) {
                $error_message = 'Security check failed.';
            } else {

                $user = wp_signon(
                    array(
                        'user_login'    => sanitize_text_field(
                            $_POST['rl_login']
                        ),
                        'user_password' => $_POST['rl_login_password'],
                        'remember'      => true
                    )
                );

                if (is_wp_error($user)) {
                    $error_message = 'Invalid username or password.';
                } else {

                    // Self-registered accounts stay logged out until
                    // the email link is clicked — wp_signon() above
                    // already set an auth cookie for them, so a
                    // straight wp_logout() is needed to undo it.
                    $needs_verification = in_array('customer', $user->roles, true)
                        && get_user_meta($user->ID, 'rl_email_verified', true) === '0';

                    if ($needs_verification) {

                        wp_logout();

                        $unverified_email = $user->user_email;
                        $error_message    = 'Please confirm your email before logging in — check your inbox for the link we sent when you signed up.';

                    } else {

                        wp_set_current_user(
                            $user->ID
                        );

                        wp_set_auth_cookie(
                            $user->ID
                        );

                        wp_redirect(
                            site_url('/')
                        );

                        exit;
                    }
                }
            }
        }

        // 1b. Process "resend verification email" submission
        if (isset($_POST['rl_resend_verify_submit'])) {

            if (
                !isset($_POST['rl_login_nonce'])
                ||
                !wp_verify_nonce(
                    $_POST['rl_login_nonce'],
                    'rl_login_action'
                )
            ) {
                $error_message = 'Security check failed.';
            } else {

                $resend_email = sanitize_email($_POST['rl_resend_email'] ?? '');
                $resend_result = RL_Users::resend_verification_email($resend_email);

                if (is_wp_error($resend_result)) {
                    $error_message    = $resend_result->get_error_message();
                    $unverified_email = $resend_email;
                } else {
                    $resend_success   = true;
                    $unverified_email = $resend_email;
                }
            }
        }

        // 2. Buffer and render the Login Page HTML
        ob_start();
        ?>

        <div class="rl-login-page">

            <div class="rl-login-card">

                <div class="rl-login-logo">
                    <img
                        src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png"
                        class="rl-logo"
                        alt="Logo">
                </div>

                <h2>
                    <?php echo esc_html(rl_t('login_welcome_back')); ?>
                </h2>

                <p class="rl-login-subtitle">
                    <?php echo esc_html(rl_t('login_subtitle')); ?>
                </p>

                <?php if (!empty($error_message)): ?>
                    <p class="rl-alert rl-alert-error">
                        <?php echo esc_html($error_message); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($resend_success)): ?>
                    <p class="rl-alert rl-alert-success">
                        <?php echo esc_html(rl_t('login_verification_sent')); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($unverified_email)): ?>
                    <form method="post">
                        <?php wp_nonce_field('rl_login_action', 'rl_login_nonce'); ?>
                        <input type="hidden" name="rl_resend_email" value="<?php echo esc_attr($unverified_email); ?>">
                        <button type="submit" name="rl_resend_verify_submit" class="rl-btn-secondary" style="width:100%;margin-bottom:16px;">
                            <?php echo esc_html(rl_t('login_resend_button')); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <form method="post">

                    <?php wp_nonce_field(
                        'rl_login_action',
                        'rl_login_nonce'
                    ); ?>

                    <div class="rl-input-group">
                        <input
                            type="text"
                            name="rl_login"
                            placeholder="<?php echo esc_attr(rl_t('login_username_email')); ?>"
                            required>
                    </div>

                    <div class="rl-input-group">
                        <input
                            type="password"
                            name="rl_login_password"
                            placeholder="<?php echo esc_attr(rl_t('login_password')); ?>"
                            required>
                    </div>

                    <button
                        type="submit"
                        name="rl_login_submit">
                        <?php echo esc_html(rl_t('login_button')); ?>
                    </button>

                </form>

                <?php $google_auth_url = RL_Google_Auth::get_authorize_url(); ?>
                <?php if (!empty($google_auth_url)): ?>
                    <div class="rl-divider"><span><?php echo esc_html(rl_t('login_or')); ?></span></div>

                    <a href="<?php echo esc_url($google_auth_url); ?>" class="rl-google-btn">
                        <svg class="rl-google-icon" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84c-.21 1.13-.84 2.09-1.8 2.73v2.27h2.91c1.7-1.57 2.69-3.88 2.69-6.64z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.17l-2.91-2.27c-.81.54-1.84.86-3.05.86-2.35 0-4.34-1.58-5.05-3.71H.96v2.34C2.44 15.98 5.48 18 9 18z"/>
                            <path fill="#FBBC05" d="M3.95 10.71c-.18-.54-.28-1.11-.28-1.71s.1-1.17.28-1.71V4.95H.96C.35 6.17 0 7.55 0 9s.35 2.83.96 4.05l2.99-2.34z"/>
                            <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.95l2.99 2.34C4.66 5.16 6.65 3.58 9 3.58z"/>
                        </svg>
                        <?php echo esc_html(rl_t('login_continue_google')); ?>
                    </a>
                <?php endif; ?>

                <p class="rl-register-text">
                    <?php echo esc_html(rl_t('login_no_account')); ?>
                    <a href="<?php echo site_url('/register'); ?>">
                        <?php echo esc_html(rl_t('login_create_account')); ?>
                    </a>
                </p>

                <p class="rl-lost-password-text rl-register-text">
                    <?php echo esc_html(rl_t('login_forgot_password')); ?>
                    <a href="<?php echo site_url('/lost-password'); ?>">
                        <?php echo esc_html(rl_t('login_reset_password')); ?>
                    </a>
                </p>

                <p class="rl-footer-policy-link">
                    <a href="<?php echo esc_url(site_url('/privacy-policy')); ?>">
                        <?php echo esc_html(rl_t('settings_privacy_policy')); ?>
                    </a>
                </p>

                <?php echo RL_I18n::render_switcher(); ?>

            </div>

        </div>

        <?php
        return ob_get_clean();
    }

    // 3. User is logged in -> Route based on role
    $user = wp_get_current_user();

    if (
        in_array(
            'customer',
            $user->roles
        )
    ) {
        return $this->dashboard();
    }

    if (
        in_array(
            'brand_manager',
            $user->roles
        )
        ||
        in_array(
            'administrator',
            $user->roles
        )
    ) {
        return $this->restaurant_dashboard();
    }

    return '';
}



    public function register()
{
    if (is_user_logged_in()) {
        return '<p>You are already logged in.</p>';
    }

    $error_message     = '';
    $registered_email  = '';

    // A referral code can arrive via the link (?ref=CODE) or be
    // carried through the form once the page has been submitted once.
    $referral_code = sanitize_text_field($_POST['rl_ref'] ?? $_GET['ref'] ?? '');

    // 1. Process submission FIRST
    if (isset($_POST['rl_register_submit'])) {

        if (!isset($_POST['rl_register_nonce']) || !wp_verify_nonce($_POST['rl_register_nonce'], 'rl_register_action')) {

            $error_message = rl_t('register_bot_check_failed');

        } elseif (!RL_Turnstile::verify($_POST['cf-turnstile-response'] ?? '')) {

            $error_message = rl_t('register_bot_check_failed');

        } elseif (empty($_POST['rl_agree_policy'])) {

            // Required, and checked server-side — the checkbox's own
            // "required" attribute is a convenience, not something a
            // request can be trusted to have actually honored.
            $error_message = rl_t('register_agree_required');

        } elseif (($_POST['rl_password'] ?? '') !== ($_POST['rl_password_confirm'] ?? '')) {

            $error_message = rl_t('register_password_mismatch');

        } else {

        $name     = sanitize_text_field($_POST['rl_name'] ?? '');
        $email    = sanitize_email($_POST['rl_email'] ?? '');
        $password = $_POST['rl_password'] ?? '';

        $result = RL_Users::create_customer($name, $email, $password, $referral_code);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } else {
            // Not logged in yet — the account stays inactive until
            // they click the confirmation link we just emailed them,
            // otherwise a fake/nonexistent address would work exactly
            // as well as a real one for signing up.
            $registered_email = $email;
        }

        }
    }

    // 2. Buffer and render HTML
    ob_start();
    ?>

    <div class="rl-login-page">

        <div class="rl-login-card">

            <div class="rl-login-logo">
                <img src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png" class="rl-logo" alt="Logo">
            </div>

            <?php if (!empty($registered_email)): ?>

                <h2><?php echo esc_html(rl_t('register_check_email_title')); ?></h2>

                <p class="rl-login-subtitle">
                    <?php echo esc_html(rl_t('register_check_email_pre')); ?> <strong><?php echo esc_html($registered_email); ?></strong>.
                    <?php echo esc_html(rl_t('register_check_email_post')); ?>
                </p>

                <p class="rl-register-text">
                    <a href="<?php echo esc_url(site_url('/login')); ?>"><?php echo esc_html(rl_t('register_back_to_login')); ?></a>
                </p>

            <?php else: ?>

            <h2><?php echo esc_html(rl_t('register_title')); ?></h2>

            <p class="rl-login-subtitle">
                <?php echo esc_html(rl_t('register_subtitle')); ?>
            </p>

            <?php if (!empty($error_message)): ?>
                <div class="rl-alert rl-alert-error">
                    <?php
                    // Remove outer <p> tags WP might attach to error strings
                    $clean_error = preg_replace('/^<p>(.*)<\/p>$/i', '$1', trim($error_message));
                    echo esc_html($clean_error);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($referral_code)): ?>
                <div class="rl-alert rl-alert-success">
                    <?php echo esc_html(rl_t('register_referral_bonus')); ?>
                </div>
            <?php endif; ?>

            <form method="post">

                <?php wp_nonce_field('rl_register_action', 'rl_register_nonce'); ?>
                <input type="hidden" name="rl_ref" value="<?php echo esc_attr($referral_code); ?>">

                <div class="rl-input-group">
                    <label><?php echo esc_html(rl_t('register_name')); ?></label>
                    <input type="text" name="rl_name" placeholder="<?php echo esc_attr(rl_t('register_name_placeholder')); ?>" value="<?php echo esc_attr($_POST['rl_name'] ?? ''); ?>" required>
                </div>

                <div class="rl-input-group">
                    <label><?php echo esc_html(rl_t('register_email')); ?></label>
                    <input type="email" name="rl_email" placeholder="your@email.com" value="<?php echo esc_attr($_POST['rl_email'] ?? ''); ?>" required>
                </div>

                <div class="rl-input-group">
                    <label><?php echo esc_html(rl_t('register_password')); ?></label>
                    <input type="password" id="rl_password" name="rl_password" placeholder="<?php echo esc_attr(rl_t('register_password_placeholder')); ?>" required>
                </div>

                <div class="rl-input-group">
                    <label><?php echo esc_html(rl_t('register_repeat_password')); ?></label>
                    <input type="password" id="rl_password_confirm" name="rl_password_confirm" placeholder="<?php echo esc_attr(rl_t('register_repeat_placeholder')); ?>" required>
                    <span class="rl-field-hint rl-field-hint-error" id="rl-password-mismatch-hint" style="display:none;"><?php echo esc_html(rl_t('register_password_mismatch')); ?></span>
                </div>

                <?php echo RL_Turnstile::render_widget(); ?>

                <label class="rl-policy-consent">
                    <input type="checkbox" name="rl_agree_policy" value="1" <?php checked(!empty($_POST['rl_agree_policy'])); ?> required>
                    <span><?php echo esc_html(rl_t('register_agree_policy_pre')); ?> <a href="<?php echo esc_url(site_url('/privacy-policy')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(rl_t('register_agree_policy_link')); ?></a></span>
                </label>

                <button type="submit" name="rl_register_submit">
                    <?php echo esc_html(rl_t('register_button')); ?>
                </button>

            </form>

            <script>
            (function () {
                var pass = document.getElementById('rl_password');
                var confirm = document.getElementById('rl_password_confirm');
                var hint = document.getElementById('rl-password-mismatch-hint');
                if (!pass || !confirm || !hint) return;

                function check() {
                    var mismatch = confirm.value.length > 0 && pass.value !== confirm.value;
                    hint.style.display = mismatch ? 'block' : 'none';
                    confirm.setCustomValidity(mismatch ? '<?php echo esc_js(rl_t('register_password_mismatch')); ?>' : '');
                }

                pass.addEventListener('input', check);
                confirm.addEventListener('input', check);
            })();
            </script>

            <?php $google_auth_url = RL_Google_Auth::get_authorize_url(); ?>
            <?php if (!empty($google_auth_url)): ?>
                <div class="rl-divider"><span><?php echo esc_html(rl_t('login_or')); ?></span></div>

                <a href="<?php echo esc_url($google_auth_url); ?>" class="rl-google-btn">
                    <svg class="rl-google-icon" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84c-.21 1.13-.84 2.09-1.8 2.73v2.27h2.91c1.7-1.57 2.69-3.88 2.69-6.64z"/>
                        <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.17l-2.91-2.27c-.81.54-1.84.86-3.05.86-2.35 0-4.34-1.58-5.05-3.71H.96v2.34C2.44 15.98 5.48 18 9 18z"/>
                        <path fill="#FBBC05" d="M3.95 10.71c-.18-.54-.28-1.11-.28-1.71s.1-1.17.28-1.71V4.95H.96C.35 6.17 0 7.55 0 9s.35 2.83.96 4.05l2.99-2.34z"/>
                        <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.95l2.99 2.34C4.66 5.16 6.65 3.58 9 3.58z"/>
                    </svg>
                    <?php echo esc_html(rl_t('login_continue_google')); ?>
                </a>
            <?php endif; ?>

            <p class="rl-register-text">
                <?php echo esc_html(rl_t('register_have_account')); ?>
                <a href="<?php echo site_url('/login'); ?>"><?php echo esc_html(rl_t('register_login')); ?></a>
            </p>

            <?php echo RL_I18n::render_switcher(); ?>

            <?php endif; ?>

        </div>

    </div>

    <?php
    return ob_get_clean();
}

    /**
     * Landing page for the confirmation link sent by
     * RL_Users::send_verification_email() — the other half of the
     * double opt-in gate in home()'s login handling.
     */
    public function verify_email_page()
    {
        if (is_user_logged_in()) {
            wp_redirect(site_url('/'));
            exit;
        }

        $uid = isset($_GET['uid']) ? absint($_GET['uid']) : 0;
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        $result = ($uid && $key)
            ? RL_Users::verify_email($uid, $key)
            : new WP_Error('invalid_link', 'This verification link is invalid.');

        ob_start();
        ?>

        <div class="rl-login-page">

            <div class="rl-login-card">

                <div class="rl-login-logo">
                    <img src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png" class="rl-logo" alt="Logo">
                </div>

                <?php if (is_wp_error($result)): ?>

                    <h2><?php echo esc_html(rl_t('verify_fail_title')); ?></h2>

                    <p class="rl-login-subtitle">
                        <?php echo esc_html($result->get_error_message()); ?>
                    </p>

                    <a href="<?php echo esc_url(site_url('/login')); ?>" class="rl-btn-primary" style="width:100%;">
                        <?php echo esc_html(rl_t('register_back_to_login')); ?>
                    </a>

                <?php else: ?>

                    <h2><?php echo esc_html(rl_t('verify_success_title')); ?></h2>

                    <p class="rl-login-subtitle">
                        <?php echo esc_html(rl_t('verify_success_body')); ?>
                    </p>

                    <a href="<?php echo esc_url(site_url('/login')); ?>" class="rl-btn-primary" style="width:100%;">
                        <?php echo esc_html(rl_t('verify_go_login')); ?>
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <?php
        return ob_get_clean();
    }









    public function dashboard()
    {


        if(
            !is_user_logged_in()
        ){

            return '<p>Please login first.</p>';

        }



        $user = wp_get_current_user();



        if(
            !in_array(
                'customer',
                $user->roles
            )
        ){

            return '<p>This page is for customers only.</p>';

        }





        global $wpdb;


        $table = $wpdb->prefix . 'rl_customers';



        $customer = $wpdb->get_row(

            $wpdb->prepare(

                "
                SELECT *
                FROM $table
                WHERE user_id = %d
                ",

                $user->ID

            )

        );


        // Self-heal: every 'customer' role should have a row here.
        // If it's missing for any reason, create it now instead of
        // dead-ending the page — then re-fetch.
        if(!$customer && class_exists('RL_Users')){

            RL_Users::ensure_customer_identity($user->ID);

            $customer = $wpdb->get_row(

                $wpdb->prepare(

                    "
                    SELECT *
                    FROM $table
                    WHERE user_id = %d
                    ",

                    $user->ID

                )

            );

        }



        if(!$customer){

            return '<p>Customer profile not found.</p>';

        }


        // Backfills a referral code for accounts created before this
        // feature existed — no-op once it's already set.
        if (empty($customer->referral_code)) {
            $customer->referral_code = RL_Users::ensure_referral_code($customer->id);
        }





        /*
        =========================
        GET BALANCES + REWARD MENUS
        (one wallet per restaurant this customer has visited —
        pooled brands show one combined balance across locations,
        unpooled brands show one balance per location)
        =========================
        */


        $balances = RL_Points::get_all_balances_for_customer($customer->id);

        $home_active_draws = RL_Draws::get_all_active_public();

        $total_points = 0;

        $restaurant_sections = array();

        foreach ($balances as $wallet) {

            $total_points += floatval($wallet->points);

            if ($wallet->scope_type === 'brand') {
                $wallet_locations = RL_Locations::get_all_by_brand($wallet->brand_id, true);
            } else {
                $single = RL_Locations::get($wallet->location_id);
                $wallet_locations = $single ? array($single) : array();
            }

            // The Home tab only shows where the customer has points and
            // a link into that restaurant's own page — the menu itself
            // now lives there, not inline.
            $restaurant_sections[] = array(
                'wallet'    => $wallet,
                'locations' => $wallet_locations,
            );
        }







        // Sum of entry_count across every draw this customer currently
        // has entries in — reuses the exact same "active" definition
        // (status='active' AND within the date window) that the My
        // Entries page's Active/Ended split already uses, rather than
        // a second copy of that logic. A draw that ends drops its
        // entries out of this count on its own (they just stop
        // matching that WHERE clause); a deleted draw's entries are
        // now explicitly removed by RL_Draws::delete() too.
        $active_entries_count = 0;
        foreach (RL_Draws::get_customer_active_entries($customer->id) as $entry_row) {
            $active_entries_count += intval($entry_row->entry_count);
        }

        /*
        =========================
        GET TRANSACTIONS
        =========================
        */


        $transactions =
        RL_Transactions::get_customer_transactions(
            $customer->id
        );


        $show_onboarding = !get_user_meta($user->ID, 'rl_onboarded', true);


        ob_start();


        ?>

        <?php if ($show_onboarding): ?>
        <div class="rl-onboarding" id="rl-onboarding">

            <div class="rl-onboarding-skip" id="rl-onboarding-skip">Skip</div>

            <div class="rl-onboarding-slide active" data-slide="0">
                <div class="rl-onboarding-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <h3>Every purchase earns points</h3>
                <p>Scan the QR code at the counter and points land in your account instantly — redeem them for rewards at that same restaurant.</p>
            </div>

            <div class="rl-onboarding-slide" data-slide="1">
                <div class="rl-onboarding-icon">
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <h3>There's a prize running right now</h3>
                <p>Every restaurant on Butterfly feeds into a live giveaway — you get an entry every time you earn points, automatically. No separate signup.</p>
            </div>

            <div class="rl-onboarding-slide" data-slide="2">
                <div class="rl-onboarding-icon">
                    <span class="dashicons dashicons-camera-alt"></span>
                </div>
                <h3>Scan to earn, anytime</h3>
                <p>The camera button at the bottom of the app is always one tap away — hand it to staff at the counter and you're earning.</p>
            </div>

            <div class="rl-onboarding-footer">
                <div class="rl-onboarding-dots">
                    <span class="rl-onboarding-dot active" data-dot="0"></span>
                    <span class="rl-onboarding-dot" data-dot="1"></span>
                    <span class="rl-onboarding-dot" data-dot="2"></span>
                </div>
                <button type="button" class="rl-onboarding-next" id="rl-onboarding-next">Next</button>
            </div>

        </div>
        <?php endif; ?>

<div class="rl-menu-overlay"></div>

<div class="rl-menu-sheet">

    <button class="rl-sheet-item" data-tab="restaurants">

        <span class="dashicons dashicons-location-alt"></span>

        <span><?php echo esc_html(rl_t('nav_restaurants')); ?></span>

    </button>


    <button class="rl-sheet-item" data-tab="prizes">

        <span class="dashicons dashicons-tickets-alt"></span>

        <span><?php echo esc_html(rl_t('nav_prizes')); ?></span>

    </button>


    <a class="rl-sheet-item" href="<?php echo esc_url(site_url('/my-entries')); ?>">

        <span class="dashicons dashicons-tickets"></span>

        <span><?php echo esc_html(rl_t('nav_entries')); ?></span>

    </a>


    <button class="rl-sheet-item" data-tab="history">

        <span class="dashicons dashicons-welcome-write-blog"></span>

        <span><?php echo esc_html(rl_t('nav_activity')); ?></span>

    </button>


    <button class="rl-sheet-item" data-tab="invite">

        <span class="dashicons dashicons-groups"></span>

        <span><?php echo esc_html(rl_t('nav_invite')); ?></span>

    </button>


    <button class="rl-sheet-item" data-tab="settings">

        <span class="dashicons dashicons-admin-generic"></span>

        <span><?php echo esc_html(rl_t('nav_settings')); ?></span>

    </button>


    <a href="<?php echo esc_url( wp_logout_url( site_url('/login') ) ); ?>"  class="rl-sheet-item">

        <span class="dashicons dashicons-migrate"></span>

        <span><?php echo esc_html(rl_t('nav_logout')); ?></span>

    </a>

</div>

<nav class="rl-bottom-nav">

    <button class="rl-nav-item active"
            data-tab="home">
       <span class="dashicons dashicons-admin-home"></span>

    </button>

    <!-- <button class="rl-nav-item"
            data-tab="rewards">
        <span class="dashicons dashicons-awards"></span>
       
    </button> -->

    <button
        class="rl-nav-center"
        data-tab="qr">

        <div class="rl-qr-button">
            <svg width="50%" height="50%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.5 6.5H6.51M17.5 6.5H17.51M6.5 17.5H6.51M13 13H13.01M17.5 17.5H17.51M17 21H21V17M14 16.5V21M21 14H16.5M15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 19.4 3H15.6C15.0399 3 14.7599 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10ZM4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10ZM4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

    </button>

   

    <button class="rl-nav-menu" id="rl-more-btn">

        <span class="dashicons dashicons-menu"></span>

    </button>

    <!-- <button class="rl-nav-item"
            data-tab="profile">
        <span class="dashicons dashicons-admin-users"></span>
       
    </button> -->

</nav>




        <div class="rl-dashboard">





            <div class="rl-header">
                <div class="logoUsernameWrapper">
                    <img
                        src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png"
                        class="rl-logo"
                        alt="Logo">

                    <div class="rl-header-text">

                        <h2>
                            <?php echo esc_html(rl_t('header_hello')); ?> <?php echo esc_html($user->display_name); ?>
                        </h2>

                        <p>
                            <?php echo esc_html(rl_t('header_welcome_back')); ?>
                        </p>

                    </div>
                    </div>

                    
            <?php
$points = floatval($total_points);
$parts = explode('.', number_format($points, 2, '.', ''));
?>

<div class="rl-header-points">
    <div class="points-badge-wrapper">
        <div class="points-circle">
            <span class="points-number">
                <?php echo $parts[0]; ?>.<small><?php echo $parts[1]; ?></small>
            </span>

            <div class="points-tag">
                <span class="tag-text rl-points-icon">⭐</span>
            </div>
        </div>
    </div>
</div>


                

            </div>
            








            <div class="rl-quick-actions">
                <a href="<?php echo esc_url(site_url('/my-entries')); ?>" class="rl-quick-action" id="rl-quick-action-entries">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <span class="rl-quick-action-label"><?php echo esc_html(rl_t('home_quick_entries')); ?></span>
                    <span class="rl-quick-action-count" id="rl-quick-action-entries-count" style="<?php echo $active_entries_count > 0 ? '' : 'display:none;'; ?>"><?php echo intval($active_entries_count); ?></span>
                </a>
                <button type="button" class="rl-quick-action" data-tab="prizes">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <span class="rl-quick-action-label"><?php echo esc_html(rl_t('home_quick_prizes')); ?></span>
                </button>
                <button type="button" class="rl-quick-action" data-tab="restaurants">
                    <span class="dashicons dashicons-location-alt"></span>
                    <span class="rl-quick-action-label"><?php echo esc_html(rl_t('nav_restaurants')); ?></span>
                </button>
                <button type="button" class="rl-quick-action" data-tab="invite">
                    <span class="dashicons dashicons-groups"></span>
                    <span class="rl-quick-action-label"><?php echo esc_html(rl_t('home_quick_invite')); ?></span>
                </button>
            </div>

            <div id="rl-home-tab" class="rl-tab active">

                <div class="rl-rewards">

                    <?php echo $this->render_big_prize_card($customer->id); ?>

                    <!-- <h3>
                        Rewards
                    </h3> -->
                    <div class="rl-page-title">


                        <div class="rl-title-content">

                            <h3>
                                <?php echo esc_html(rl_t('home_your_points')); ?>
                            </h3>

                            <p>
                                <?php echo esc_html(rl_t('home_your_points_subtitle')); ?>
                            </p>

                        </div>

                    </div>


                    <?php if($restaurant_sections): ?>

                        <?php foreach($restaurant_sections as $section): ?>

                            <?php
                            $wallet    = $section['wallet'];
                            $locations = $section['locations'];
                            $single_location = (count($locations) === 1) ? $locations[0] : null;
                            ?>

                            <?php if($single_location): ?>

                                <?php
                                $home_loc_has_pin = ($single_location->lat !== null && $single_location->lng !== null && $single_location->lat !== '' && $single_location->lng !== '');
                                $home_loc_hours_badge = $this->render_hours_badge($single_location->brand_id);
                                $home_loc_active_prize_title = '';

                                foreach ($home_active_draws as $draw) {
                                    if (RL_Draws::draw_applies_to_location($draw, $single_location->id, $single_location->brand_id)) {
                                        $home_loc_active_prize_title = $draw->title;
                                        break;
                                    }
                                }

                                // The cheapest reward still out of reach, plus
                                // anything already affordable right now — a
                                // concrete "get there" target next to whatever
                                // can already be claimed, not just a running total.
                                $home_loc_next_reward = null;
                                $home_loc_available_rewards = array();

                                foreach (RL_Redeem_Items::get_all($single_location->id, true) as $reward_candidate) {
                                    if (floatval($reward_candidate->points_cost) > floatval($wallet->points)) {
                                        if (!$home_loc_next_reward || floatval($reward_candidate->points_cost) < floatval($home_loc_next_reward->points_cost)) {
                                            $home_loc_next_reward = $reward_candidate;
                                        }
                                    } else {
                                        $home_loc_available_rewards[] = $reward_candidate;
                                    }
                                }

                                usort($home_loc_available_rewards, function ($a, $b) {
                                    return floatval($b->points_cost) <=> floatval($a->points_cost);
                                });
                                ?>

                                <div class="rl-reward-item rl-reward-item-card rl-restaurant-card-has-photo" id="rl-home-location-<?php echo intval($single_location->id); ?>">

                                    <a class="rl-reward-item-link" href="<?php echo esc_url(site_url('/restaurant/?id=' . intval($single_location->id))); ?>">

                                        <div class="rl-restaurant-card-photo">
                                            <?php if (!empty($wallet->brand_logo)): ?>
                                                <img src="<?php echo esc_url($wallet->brand_logo); ?>" alt="" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span class="rl-restaurant-card-photo-fallback dashicons dashicons-store"></span>
                                            <?php endif; ?>

                                            <?php if ($home_loc_has_pin): ?>
                                                <button
                                                    type="button"
                                                    class="rl-view-on-map-btn"
                                                    data-location-id="<?php echo intval($single_location->id); ?>"
                                                    data-lat="<?php echo esc_attr($single_location->lat); ?>"
                                                    data-lng="<?php echo esc_attr($single_location->lng); ?>"
                                                    title="<?php echo esc_attr(rl_t('view_on_map')); ?>"
                                                >
                                                    <span class="dashicons dashicons-location"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div class="rl-reward-content">

                                            <div class="rl-reward-top">
                                                <h4>
                                                    <?php echo esc_html($wallet->brand_name); ?>
                                                    <?php if($wallet->scope_type === 'location' && !empty($wallet->location_name)): ?>
                                                        <span class="rl-restaurant-location-tag">— <?php echo esc_html($wallet->location_name); ?></span>
                                                    <?php endif; ?>
                                                </h4>

                                                <div class="rl-reward-price" data-scope-type="<?php echo esc_attr($wallet->scope_type); ?>" data-scope-id="<?php echo esc_attr($wallet->scope_type === 'brand' ? $wallet->brand_id : $wallet->location_id); ?>">⭐ <?php echo intval($wallet->points); ?></div>
                                            </div>

                                            <?php if (!empty($single_location->address)): ?>
                                                <small><?php echo esc_html($single_location->address); ?></small>
                                            <?php endif; ?>

                                            <?php if ($home_loc_has_pin): ?>
                                                <p class="rl-restaurant-distance" data-lat="<?php echo esc_attr($single_location->lat); ?>" data-lng="<?php echo esc_attr($single_location->lng); ?>">
                                                    <span class="rl-distance-icon">📍</span>
                                                    <span class="rl-distance-value"><span class="rl-distance-spinner" aria-hidden="true"></span></span>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($home_loc_next_reward): ?>
                                                <?php
                                                $home_loc_progress_pct = floatval($wallet->points) > 0
                                                    ? min(100, round((floatval($wallet->points) / floatval($home_loc_next_reward->points_cost)) * 100))
                                                    : 0;
                                                ?>
                                                <div class="rl-reward-progress">
                                                    <div class="rl-reward-progress-label">
                                                        <span><?php echo esc_html(rl_t('restaurant_next')); ?> <?php echo esc_html($home_loc_next_reward->title); ?></span>
                                                        <b><?php echo intval($wallet->points); ?> / <?php echo intval($home_loc_next_reward->points_cost); ?></b>
                                                    </div>
                                                    <div class="rl-reward-progress-track">
                                                        <div class="rl-reward-progress-fill" style="width:<?php echo esc_attr($home_loc_progress_pct); ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($home_loc_available_rewards): ?>
                                                <div class="rl-balance-available">
                                                    <div class="rl-balance-available-label"><?php echo esc_html(rl_t('home_ready_to_redeem')); ?></div>
                                                    <div class="rl-balance-available-chips">
                                                        <?php foreach ($home_loc_available_rewards as $home_available_item): ?>
                                                            <span class="rl-balance-available-chip">🎁 <?php echo esc_html($home_available_item->title); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        </div>

                                    </a>

                                    <?php if ($home_loc_hours_badge || $home_loc_active_prize_title !== ''): ?>
                                        <div class="rl-reward-footer-row">

                                            <div class="rl-reward-badges">
                                                <?php echo $home_loc_hours_badge; ?>

                                                <?php if ($home_loc_active_prize_title !== ''): ?>
                                                    <span class="rl-active-prize-badge">🎁 <?php echo esc_html($home_loc_active_prize_title); ?></span>
                                                <?php endif; ?>
                                            </div>

                                        </div>
                                    <?php endif; ?>

                                </div>

                            <?php else: ?>

                                <div class="rl-reward-item rl-reward-item-card rl-restaurant-card-has-photo">

                                    <div class="rl-restaurant-card-photo">
                                        <?php if (!empty($wallet->brand_logo)): ?>
                                            <img src="<?php echo esc_url($wallet->brand_logo); ?>" alt="">
                                        <?php else: ?>
                                            <span class="rl-restaurant-card-photo-fallback dashicons dashicons-store"></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="rl-reward-content">

                                        <div class="rl-reward-top">
                                            <h4><?php echo esc_html($wallet->brand_name); ?></h4>
                                            <div class="rl-reward-price" data-scope-type="<?php echo esc_attr($wallet->scope_type); ?>" data-scope-id="<?php echo esc_attr($wallet->scope_type === 'brand' ? $wallet->brand_id : $wallet->location_id); ?>">⭐ <?php echo intval($wallet->points); ?></div>
                                        </div>

                                        <?php if($locations): ?>

                                            <div class="rl-restaurant-location-links">

                                                <?php foreach($locations as $loc): ?>

                                                    <a class="rl-restaurant-location-pill" href="<?php echo esc_url(site_url('/restaurant/?id=' . intval($loc->id))); ?>">
                                                        <?php echo esc_html($loc->name); ?>
                                                    </a>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="rl-empty-home">

                            <div class="rl-empty-home-icon">🎉</div>
                            <h4><?php echo esc_html(rl_t('home_empty_title')); ?></h4>
                            <p><?php echo esc_html(rl_t('home_empty_body')); ?></p>

                            <div class="rl-empty-home-steps">
                                <div class="rl-empty-home-step">
                                    <span class="dashicons dashicons-camera-alt"></span>
                                    <span>1. <?php echo esc_html(rl_t('home_empty_step_scan')); ?></span>
                                </div>
                                <div class="rl-empty-home-step">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <span>2. <?php echo esc_html(rl_t('home_empty_step_earn')); ?></span>
                                </div>
                                <div class="rl-empty-home-step">
                                    <span class="dashicons dashicons-tickets-alt"></span>
                                    <span>3. <?php echo esc_html(rl_t('home_empty_step_redeem')); ?></span>
                                </div>
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php $home_explore_locations = array_slice(RL_Locations::get_all_public(), 0, 3); ?>

                    <?php if ($home_explore_locations): ?>

                        <div class="rl-page-title">
                            <div class="rl-title-content">
                                <h3><?php echo esc_html(rl_t('home_explore_restaurants')); ?></h3>
                            </div>
                            <a href="#" class="rl-home-activity-see-all" data-tab="restaurants"><?php echo esc_html(rl_t('home_see_all')); ?></a>
                        </div>

                        <div class="rl-home-explore-row">
                            <?php foreach ($home_explore_locations as $ex_loc): ?>
                                <?php
                                $ex_has_pin = ($ex_loc->lat !== null && $ex_loc->lng !== null && $ex_loc->lat !== '' && $ex_loc->lng !== '');
                                $ex_balance = RL_Points::get_balance($ex_loc->id, $customer->id);
                                $ex_balance = ($ex_balance !== null && $ex_balance !== false) ? floatval($ex_balance) : 0;
                                ?>
                                <a class="rl-home-explore-card" href="<?php echo esc_url(site_url('/restaurant/?id=' . intval($ex_loc->id))); ?>">
                                    <div class="rl-restaurant-card-photo">
                                        <?php if (!empty($ex_loc->brand_logo)): ?>
                                            <img src="<?php echo esc_url($ex_loc->brand_logo); ?>" alt="" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span class="rl-restaurant-card-photo-fallback dashicons dashicons-store"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rl-home-explore-card-body">
                                        <h4><?php echo esc_html($ex_loc->brand_name); ?></h4>
                                        <?php if ($ex_has_pin): ?>
                                            <p class="rl-restaurant-distance" data-lat="<?php echo esc_attr($ex_loc->lat); ?>" data-lng="<?php echo esc_attr($ex_loc->lng); ?>">
                                                <span class="rl-distance-icon">📍</span>
                                                <span class="rl-distance-value"><span class="rl-distance-spinner" aria-hidden="true"></span></span>
                                            </p>
                                        <?php endif; ?>
                                        <div class="rl-reward-price">⭐ <?php echo intval($ex_balance); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                    <?php if ($restaurant_sections && !empty($transactions)): ?>

                        <div class="rl-page-title">
                            <div class="rl-title-content">
                                <h3><?php echo esc_html(rl_t('home_recent_activity')); ?></h3>
                            </div>
                            <a href="#" class="rl-home-activity-see-all" data-tab="history"><?php echo esc_html(rl_t('home_see_all')); ?></a>
                        </div>

                        <div class="rl-activity-teaser">
                            <?php foreach (array_slice($transactions, 0, 3) as $recent_txn): ?>
                                <div class="rl-activity-teaser-row">
                                    <span class="rl-activity-teaser-icon"><?php echo $recent_txn->transaction_type === 'redeem' ? '🎁' : '⭐'; ?></span>
                                    <span class="rl-activity-teaser-text">
                                        <?php if ($recent_txn->transaction_type === 'redeem'): ?>
                                            Redeemed<?php echo !empty($recent_txn->note) ? ' ' . esc_html(preg_replace('/^Redeemed:\s*/', '', $recent_txn->note)) : ''; ?>
                                        <?php else: ?>
                                            +<?php echo intval($recent_txn->points); ?> pts<?php echo !empty($recent_txn->brand_name) ? ' at ' . esc_html($recent_txn->brand_name) : ''; ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="rl-activity-teaser-when"><?php echo esc_html(human_time_diff(strtotime($recent_txn->created_at), current_time('timestamp'))); ?> ago</span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>



            </div>

            </div>








        <div id="rl-rewards-tab" class="rl-tab">

            
        </div>








            <div id="rl-history-tab" class="rl-tab">

                <div class="rl-transactions">

                    <div class="rl-page-title">

                    
                        <div class="rl-title-content">

                            <h3>
                                <?php echo esc_html(rl_t('nav_activity')); ?>
                            </h3>

                            <p>
                                <?php echo esc_html(rl_t('activity_subtitle')); ?>
                            </p>

                        </div>

                    </div>

                <?php if($transactions): ?>



                    <?php foreach($transactions as $transaction): ?>


                        <div class="rl-transaction">


                            <strong>


                            <?php

                            if(
                                $transaction->transaction_type == 'add'
                            ){

                                echo '+';

                            }
                            else{

                                echo '-';

                            }


                            echo intval(
                                $transaction->points
                            );


                            ?>

                            <?php echo esc_html(rl_t('activity_points_suffix')); ?>


                            </strong>



                            <p class="rl-transaction-place">
                                <?php echo esc_html($transaction->brand_name ?? ''); ?>
                                <?php if(!empty($transaction->location_name)): ?>
                                    — <?php echo esc_html($transaction->location_name); ?>
                                <?php endif; ?>
                            </p>

                            <p>

                            <?php echo esc_html(
                                $transaction->note
                            ); ?>

                            </p>




                            <small>

                            <?php echo esc_html(
                                $transaction->created_at
                            ); ?>

                            </small>



                        </div>


                        <hr>


                    <?php endforeach; ?>



                <?php else: ?>


                    <p>
                        <?php echo esc_html(rl_t('activity_none')); ?>
                    </p>


                <?php endif; ?>

            </div>
        </div>









<div id="rl-qr-tab" class="rl-tab">
        <div class="rl-qr-header">  
                    <div class="rl-title-content">
                        <h3>
                            <?php echo esc_html(rl_t('qr_title')); ?>
                        </h3>

                        <p>
                            <?php echo esc_html(rl_t('qr_subtitle')); ?>
                        </p>

                    </div>
           
        </div>

        <div class="rl-qr-container">

    <div class="rl-qr-frame">

        <span class="qr-corner top-left"></span>
        <span class="qr-corner top-right"></span>
        <span class="qr-corner bottom-left"></span>
        <span class="qr-corner bottom-right"></span>

        <div
            id="rl-qr-code-container"
            data-token="<?php echo esc_attr($customer->qr_token); ?>"
            aria-label="<?php echo esc_attr(rl_t('qr_aria_label')); ?>"
        ></div>

    </div>

</div>
        <div class="rl-qr-footer">

            <div class="rl-qr-tip">

                <span>📱</span>

                <p>
                    <?php echo esc_html(rl_t('qr_tip')); ?>
                </p>

            </div>

        </div>

</div>


<div id="rl-prizes-tab" class="rl-tab">

    <div class="rl-page-title">
        <div class="rl-title-content">
            <h3><?php echo esc_html(rl_t('nav_prizes')); ?></h3>
            <p><?php echo esc_html(rl_t('prizes_tab_subtitle')); ?></p>
        </div>
    </div>

    <?php echo $this->render_prizes_body($customer->id); ?>

</div>


<div id="rl-restaurants-tab" class="rl-tab">

    <?php echo $this->render_restaurants_body($customer->id); ?>

</div>


<div id="rl-invite-tab" class="rl-tab">

    <div class="rl-qr-header">
        <div class="rl-title-content">
            <h3><?php echo esc_html(rl_t('nav_invite')); ?></h3>
            <p><?php echo esc_html(rl_t('invite_subtitle')); ?></p>
        </div>
    </div>

    <div class="rl-invite-code-box">
        <span class="rl-invite-code-label"><?php echo esc_html(rl_t('invite_your_code')); ?></span>
        <span class="rl-invite-code" id="rl-invite-code-text"><?php echo esc_html($customer->referral_code); ?></span>
    </div>

    <button type="button" class="rl-invite-copy-btn" id="rl-invite-copy-btn" data-link="<?php echo esc_url(site_url('/register?ref=' . rawurlencode($customer->referral_code))); ?>" data-copied-text="<?php echo esc_attr(rl_t('invite_copied')); ?>">
        <?php echo esc_html(rl_t('invite_copy_link')); ?>
    </button>

    <p class="rl-invite-hint" id="rl-invite-hint">&nbsp;</p>

</div>


<div id="rl-settings-tab" class="rl-tab">

    <div class="rl-qr-header">
        <div class="rl-title-content">
            <h3><?php echo esc_html(rl_t('settings_title')); ?></h3>
        </div>
    </div>

    <div class="rl-settings-section">
        <h4 class="rl-settings-section-title"><?php echo esc_html(rl_t('settings_app_section')); ?></h4>
        <div class="rl-settings-row">
            <div class="rl-settings-row-text">
                <strong><?php echo esc_html(rl_t('settings_install_title')); ?></strong>
                <span><?php echo esc_html(rl_t('settings_install_body')); ?></span>
            </div>
            <button type="button" id="rl-settings-install-btn" class="rl-btn-secondary"><?php echo esc_html(rl_t('settings_download_app')); ?></button>
        </div>
    </div>

    <div class="rl-settings-section">
        <h4 class="rl-settings-section-title"><?php echo esc_html(rl_t('settings_notifications')); ?></h4>
        <div class="rl-settings-row">
            <div class="rl-settings-row-text">
                <strong><?php echo esc_html(rl_t('settings_notify_title')); ?></strong>
                <span><?php echo esc_html(rl_t('settings_notify_body')); ?></span>
            </div>
            <button type="button" id="rl-push-toggle" class="rl-switch" role="switch" aria-checked="false" data-subscribed="0">
                <span class="rl-switch-knob"></span>
            </button>
        </div>
    </div>

    <div class="rl-settings-section">
        <h4 class="rl-settings-section-title"><?php echo esc_html(rl_t('settings_language_section')); ?></h4>
        <div class="rl-settings-row rl-settings-lang-row">
            <div class="rl-settings-row-text">
                <strong><?php echo esc_html(rl_t('settings_language_title')); ?></strong>
                <span><?php echo esc_html(rl_t('settings_language_body')); ?></span>
            </div>
            <?php echo RL_I18n::render_switcher(); ?>
        </div>
    </div>

    <div class="rl-settings-section">
        <h4 class="rl-settings-section-title"><?php echo esc_html(rl_t('settings_legal')); ?></h4>
        <div class="rl-settings-row">
            <div class="rl-settings-row-text">
                <strong><?php echo esc_html(rl_t('settings_privacy_policy')); ?></strong>
                <span><?php echo esc_html(rl_t('settings_privacy_body')); ?></span>
            </div>
            <a href="<?php echo esc_url(site_url('/privacy-policy')); ?>" class="rl-btn-secondary"><?php echo esc_html(rl_t('settings_view')); ?></a>
        </div>
    </div>

</div>







                <!-- <div id="rl-profile-tab" class="rl-tab">

    

                    <div class="rl-account">


                        <h3>
                            Account
                        </h3>


                        <p>

                            <?php echo esc_html($user->user_email); ?>

                        </p>


                    </div>
                </div> -->





        </div>




        <?php


        return ob_get_clean();


    }









    public function restaurant_dashboard()
    {


        if(
            !is_user_logged_in()
        ){

            return '<p>Please login.</p>';

        }



        $user = wp_get_current_user();




        if(
            !in_array(
                'brand_manager',
                $user->roles
            )
            &&
            !in_array(
                'administrator',
                $user->roles
            )
        ){

            return '<p>You do not have permission.</p>';

        }


        $is_brand_manager = in_array('brand_manager', $user->roles) || in_array('administrator', $user->roles);

        $scan_locations = array();

        if($is_brand_manager && !in_array('administrator', $user->roles)){

            $brand = RL_Brands::get_by_manager($user->ID);

            if($brand){
                $scan_locations = RL_Locations::get_all_by_brand($brand->id, true);
            }

        }


        ob_start();

        ?>


      <div class="rl-manager-dashboard">

        <?php echo $this->render_manager_bottom_nav($is_brand_manager); ?>

            <div class="rl-header">
                <div class="logoUsernameWrapper">
                    <img
                        src="<?php echo RL_PLUGIN_URL; ?>assets/images/butterfly-logo.png"
                        class="rl-logo"
                        alt="Logo">

                    <div class="rl-header-text">

                        <h2>
                            Hello <?php echo esc_html($user->display_name); ?>
                        </h2>

                        <p>
                            Welcome to dashboard
                        </p>

                    </div>
                </div>

            </div>
            
    <div class="rl-scanner-card">

        <div class="rl-scanner-title">

            <div class="rl-scanner-icon">

                <svg width="50%" height="50%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.5 6.5H6.51M17.5 6.5H17.51M6.5 17.5H6.51M13 13H13.01M17.5 17.5H17.51M17 21H21V17M14 16.5V21M21 14H16.5M15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 19.4 3H15.6C15.0399 3 14.7599 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10ZM4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10ZM4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

            </div>

            <div>
                <h3>
                    QR Scanner
                </h3>
                <p>
                    Scan a customer's QR code.
                </p>
            </div>

        </div>

        <?php if(count($scan_locations) > 1): ?>

            <div class="rl-input-group">
                <label>Scanning for</label>
                <select id="rl-location-select">
                    <?php foreach($scan_locations as $loc): ?>
                        <option value="<?php echo intval($loc->id); ?>">
                            <?php echo esc_html($loc->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        <?php elseif(empty($scan_locations)): ?>

            <p class="rl-alert rl-alert-error">
                No location is assigned to you yet. Contact your brand manager.
            </p>

        <?php endif; ?>

        <div id="rl-scanner">



        </div>

        <div id="rl-scanner-result"></div>

    </div>

</div>


        <?php


        return ob_get_clean();


    }
    public function lost_password()
    {
        if (is_user_logged_in()) {
            wp_redirect(site_url('/'));
            exit;
        }

        $error_message   = '';
        $success_message = '';

        if (isset($_POST['rl_lost_pass_submit'])) {
            if (!isset($_POST['rl_lost_pass_nonce']) || !wp_verify_nonce($_POST['rl_lost_pass_nonce'], 'rl_lost_pass_action')) {
                $error_message = 'Security check failed.';
            } else {
                $result = RL_Users::request_password_reset($_POST['rl_user_input']);

                if (is_wp_error($result) && $result->get_error_code() === 'empty_login') {
                    // The only error safe to surface directly — it
                    // says nothing about whether an account exists.
                    $error_message = $result->get_error_message();
                } else {
                    // Every other outcome — success, or WordPress's
                    // own "no such user" error — shows the exact same
                    // message. Distinguishing them here would let
                    // this form be used to check which emails or
                    // usernames have an account on the site.
                    $success_message = rl_t('lost_pass_success');
                }
            }
        }

        ob_start();
        ?>
        <div class="rl-login-page">
            <div class="rl-login-card">
                <div class="rl-login-logo">
                    <img src="<?php echo esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-logo.png'); ?>" class="rl-logo" alt="Logo">
                </div>

                <h2><?php echo esc_html(rl_t('lost_pass_title')); ?></h2>
                <p class="rl-login-subtitle"><?php echo esc_html(rl_t('lost_pass_subtitle')); ?></p>

                <?php if (!empty($error_message)): ?>
                    <div class="rl-alert rl-alert-error">
                        <p><?php echo esc_html($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="rl-alert rl-alert-success">
                        <p><?php echo esc_html($success_message); ?></p>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <?php wp_nonce_field('rl_lost_pass_action', 'rl_lost_pass_nonce'); ?>

                        <div class="rl-input-group">
                            <label><?php echo esc_html(rl_t('lost_pass_email_label')); ?></label>
                            <input type="text" name="rl_user_input" placeholder="your@email.com" required>
                        </div>

                        <button type="submit" name="rl_lost_pass_submit"><?php echo esc_html(rl_t('lost_pass_send_button')); ?></button>
                    </form>
                <?php endif; ?>

                <p class="rl-register-text">
                    <?php echo esc_html(rl_t('lost_pass_remember')); ?>
                    <a href="<?php echo esc_url(site_url('/login')); ?>"><?php echo esc_html(rl_t('login_button')); ?></a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

public function reset_password()
{
    // Don't call wp_logout() here! It destroys cookies mid-request and triggers redirect hooks.
    // If they are logged in, either let them set a new password or show a simple notice:
    if (is_user_logged_in()) {
        return '<div class="rl-login-page"><div class="rl-login-card"><p class="rl-alert">' . esc_html(rl_t('reset_pass_already_logged_in')) . '</p></div></div>';
    }

    $key   = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

    if (empty($key) || empty($login)) {
        return '<div class="rl-login-page"><div class="rl-login-card"><p class="rl-alert rl-alert-error">' . esc_html(rl_t('reset_pass_invalid_token')) . '</p></div></div>';
    }

    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) {
        return '<div class="rl-login-page"><div class="rl-login-card"><h2>' . esc_html(rl_t('reset_pass_link_expired_title')) . '</h2><p class="rl-login-subtitle">' . esc_html(rl_t('reset_pass_link_expired_body')) . '</p><p class="rl-register-text"><a href="' . esc_url(site_url('/lost-password')) . '">' . esc_html(rl_t('reset_pass_request_new')) . '</a></p></div></div>';
    }

    $error_message   = '';
    $success_message = '';

    if (isset($_POST['rl_reset_pass_submit'])) {
        if (!isset($_POST['rl_reset_pass_nonce']) || !wp_verify_nonce($_POST['rl_reset_pass_nonce'], 'rl_reset_pass_action')) {
            $error_message = 'Security check failed.';
        } else {
            $result = RL_Users::reset_password($key, $login, $_POST['rl_new_password']);

            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                $success_message = rl_t('reset_pass_success');
            }
        }
    }

    ob_start();
    ?>
    <div class="rl-login-page">
        <div class="rl-login-card">
            <div class="rl-login-logo">
                <img src="<?php echo esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-logo.png'); ?>" class="rl-logo" alt="Logo">
            </div>

            <h2><?php echo esc_html(rl_t('reset_pass_title')); ?></h2>
            <p class="rl-login-subtitle"><?php echo esc_html(rl_t('reset_pass_subtitle')); ?></p>

            <?php if (!empty($error_message)): ?>
                <div class="rl-alert rl-alert-error">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="rl-alert rl-alert-success">
                    <p><?php echo esc_html($success_message); ?></p>
                </div>
                <p class="rl-register-text">
                    <a href="<?php echo esc_url(site_url('/login')); ?>"><?php echo esc_html(rl_t('reset_pass_proceed_login')); ?></a>
                </p>
            <?php else: ?>
                <form method="post">
                    <?php wp_nonce_field('rl_reset_pass_action', 'rl_reset_pass_nonce'); ?>

                    <div class="rl-input-group">
                        <label><?php echo esc_html(rl_t('reset_pass_new_label')); ?></label>
                        <input type="password" name="rl_new_password" placeholder="<?php echo esc_attr(rl_t('reset_pass_new_placeholder')); ?>" required>
                    </div>

                    <button type="submit" name="rl_reset_pass_submit"><?php echo esc_html(rl_t('reset_pass_save_button')); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


}