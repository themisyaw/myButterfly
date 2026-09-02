<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Assets
{



    public function block_wp_admin_access()
{

    if(
        !is_user_logged_in()
    ){

        return;

    }


    $user = wp_get_current_user();


    // A user can hold more than one role at once (e.g. an
    // administrator who is *also* set as a brand's manager, for
    // testing or because they genuinely run that restaurant
    // themselves) — administrator must always win that combination,
    // never get redirected out of wp-admin just because
    // brand_manager also happens to be in the list.
    if(
        in_array(
            'administrator',
            $user->roles
        )
    ){

        return;

    }


    if(
        in_array(
            'customer',
            $user->roles
        )
        ||
        in_array(
            'brand_manager',
            $user->roles
        )
    ){

        if(
            is_admin()
            &&
            !wp_doing_ajax()
        ){

            wp_redirect(
                home_url()
            );

            exit;

        }

    }

}

    public function hide_admin_bar($show)
    {


        if(
            !is_user_logged_in()
        ){

            return false;

        }



        $user = wp_get_current_user();



        if(
            in_array(
                'customer',
                $user->roles
            )
            ||
            in_array(
                'brand_manager',
                $user->roles
            )
        ){

            return false;

        }



        return $show;


    }





   public function __construct()
{

    add_filter(
        'body_class',
        array(
            $this,
            'customer_app_body_class'
        )
    );


    add_filter(
        'show_admin_bar',
        array(
            $this,
            'hide_admin_bar'
        )
    );


    add_action(
        'template_redirect',
        array(
            $this,
            'control_frontend_access'
        )
    );


    add_action(
        'template_redirect',
        array(
            $this,
            'render_404'
        ),
        20
    );


    add_action(
        'admin_init',
        array(
            $this,
            'block_wp_admin_access'
        )
    );


    add_action(
        'wp_enqueue_scripts',
        array(
            $this,
            'load'
        )
    );


    add_action(
        'init',
        array(
            $this,
            'serve_service_worker'
        ),
        1
    );


    add_action(
        'init',
        array(
            $this,
            'serve_manifest'
        ),
        1
    );


    add_action(
        'wp_head',
        array(
            $this,
            'output_manifest_link'
        ),
        1
    );


}




    /**
     * Serves the push-notification service worker at the site ROOT
     * (e.g. https://site.com/rl-sw.js) instead of its real location
     * under wp-content/plugins/... — a service worker's push scope
     * is limited to its own URL path and everything below it, so
     * serving it from inside /wp-content/plugins/restaurant-loyalty/
     * would only ever cover that folder, not the whole app. No
     * rewrite rule / flush needed since this matches the request
     * directly on `init`, before WordPress routes to a template.
     */
    public function serve_service_worker()
    {

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if ($path !== 'rl-sw.js') {
            return;
        }

        $file = RL_PLUGIN_PATH . 'assets/js/rl-sw.js';

        if (!file_exists($file)) {
            status_header(404);
            exit;
        }

        $contents = file_get_contents($file);

        $contents = str_replace(
            array('__RL_ICON_URL__', '__RL_BADGE_URL__'),
            array(
                esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-logo.png'),
                esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-favicon.png'),
            ),
            $contents
        );

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');

        echo $contents;
        exit;

    }

    /**
     * Serves a web app manifest at the site root (e.g.
     * https://site.com/manifest.json) so the browser has what it
     * needs to consider the app installable — without this,
     * beforeinstallprompt (the add-to-home-screen banner's trigger on
     * Chrome/Android) never fires, even with the service worker
     * already in place. Same direct-match-on-init approach as
     * serve_service_worker(), no rewrite rule needed.
     */
    public function serve_manifest()
    {

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if ($path !== 'manifest.json') {
            return;
        }

        $manifest = array(
            'name'             => 'Butterfly',
            'short_name'       => 'Butterfly',
            'description'      => 'Digital loyalty points, prizes, and rewards for your favorite restaurants.',
            'start_url'        => home_url('/'),
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => '#0f766e',
            'icons'            => array(
                array(
                    'src'   => RL_PLUGIN_URL . 'assets/images/butterfly-favicon.png',
                    'sizes' => 'any',
                    'type'  => 'image/png',
                ),
            ),
        );

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache');

        echo wp_json_encode($manifest);
        exit;

    }

    /**
     * Links the manifest above and sets the browser UI (address bar
     * on Android, status bar tint in standalone mode) to the brand
     * teal, on every front-end page — installability isn't limited to
     * the customer app pages, so this isn't conditional on those.
     */
    public function output_manifest_link()
    {

        if (is_admin()) {
            return;
        }

        ?>
        <link rel="manifest" href="<?php echo esc_url(home_url('/manifest.json')); ?>">
        <meta name="theme-color" content="#0f766e">
        <?php

    }







    /**
     * Frontend body class
     */
    public function customer_app_body_class($classes)
    {


        if(
            is_front_page()
            ||
            is_page(
                array(
                    'register',
                    'restaurant-dashboard',
                    'lost-password',
                    'reset-password',
                    'explore',
                    'restaurant',
                    'my-entries',
                    'manage-brand',
                    'manage-menu',
                    'manage-draws',
                    'privacy-policy',
                    'verify-email',
                    'support'
                )
            )
        ){

            $classes[] = 'rl-customer-app-page';

        }



        return $classes;


    }









    /**
     * Control frontend routes
     */
    public function control_frontend_access()
    {


      /*
=========================
CONTROL WP ADMIN ACCESS
=========================
*/


if(
    is_admin()
){


    if(
        !is_user_logged_in()
    ){

        wp_redirect(
            home_url()
        );

        exit;

    }



    $user = wp_get_current_user();





    /*
    =========================
    ADMINISTRATOR
    =========================
    */


    if(
        in_array(
            'administrator',
            $user->roles
        )
    ){

        return;

    }







    /*
    =========================
    BRAND MANAGER
    REDEEM MENU, BRAND/LOCATION SETTINGS, DRAWS
    =========================
    */


    if(
        in_array(
            'brand_manager',
            $user->roles
        )
    ){


        $allowed_pages = array(
            'rl-redeem-menu',
            'rl-brand-manage',
            'rl-draws-manage'
        );


        if(
            isset($_GET['page'])
            &&
            in_array($_GET['page'], $allowed_pages, true)
        ){

            return;

        }


        wp_redirect(
            home_url()
        );

        exit;


    }






    /*
    =========================
    CUSTOMERS
    =========================
    */


    wp_redirect(
        home_url()
    );

    exit;


}




        /*
        =========================
        CURRENT PAGE
        =========================
        */


        $current_path = trim(
            parse_url(
                $_SERVER['REQUEST_URI'],
                PHP_URL_PATH
            ),
            '/'
        );







        /*
        =========================
        ALLOW PUBLIC PAGES
        =========================
        */


        if(
            in_array(
                $current_path,
                array(
                    '',
                    'register',
                    'lost-password',
                    'reset-password',
                    'explore',
                    'restaurant',
                    'my-entries',
                    'privacy-policy',
                    'verify-email',
                    'support'
                )
            )
        ){

            return;

        }








        /*
        =========================
        LOGGED USERS
        =========================
        */


        if(
            is_user_logged_in()
        ){


            $user = wp_get_current_user();







            /*
            =========================
            CUSTOMER
            =========================
            */


            if(
                in_array(
                    'customer',
                    $user->roles
                )
            ){


                if(
                    $current_path === 'customer-dashboard'
                    ||
                    $current_path === ''
                ){

                    return;

                }



                wp_redirect(
                    home_url()
                );

                exit;


            }








            /*
            =========================
            BRAND MANAGER
            =========================
            */


            if(
                in_array(
                    'brand_manager',
                    $user->roles
                )
            ){


                $brand_manager_allowed_paths = array(
                    'restaurant-dashboard',
                    'manage-brand',
                    'manage-menu',
                    'manage-draws',
                    ''
                );

                if(
                    in_array(
                        $current_path,
                        $brand_manager_allowed_paths,
                        true
                    )
                ){

                    return;

                }



                wp_redirect(
                    home_url()
                );

                exit;


            }








            /*
            =========================
            ADMIN
            =========================
            */


            if(
                in_array(
                    'administrator',
                    $user->roles
                )
            ){

                return;

            }



        }








        /*
        =========================
        GUEST USERS
        =========================
        */


        if(
            !is_user_logged_in()
        ){


            wp_redirect(
                home_url()
            );

            exit;


        }



    }


    /**
     * Replaces the theme's 404 template with a branded one on the
     * front end — this is a single-purpose app, a bare "not found"
     * from the underlying WordPress theme looks broken to a customer.
     */
    public function render_404()
    {

        if(!is_404() || is_admin()){

            return;

        }

        status_header(404);
        nocache_headers();

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found</title>
<link rel="icon" type="image/png" href="<?php echo esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-favicon.png'); ?>">
<style>
* { box-sizing: border-box; }
html, body {
    margin: 0;
    height: 100%;
    background: #f7f5f2;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}
.rl-404-wrap {
    min-height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.rl-404-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px 32px;
    max-width: 360px;
    width: 100%;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.rl-404-logo {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    display: block;
    object-fit: contain;
}
.rl-404-card h1 {
    font-size: 20px;
    margin: 0 0 8px;
    color: #111827;
}
.rl-404-card p {
    font-size: 14px;
    color: #6b7280;
    margin: 0 0 24px;
    line-height: 1.5;
}
.rl-404-btn {
    display: inline-block;
    background: #0f766e;
    color: #fff;
    text-decoration: none;
    padding: 12px 26px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
}
.rl-404-btn:hover {
    background: #0b625d;
}
</style>
</head>
<body>
    <div class="rl-404-wrap">
        <div class="rl-404-card">
            <img class="rl-404-logo" src="<?php echo esc_url(RL_PLUGIN_URL . 'assets/images/butterfly-logo.png'); ?>" alt="">
            <h1>Page not found</h1>
            <p>This page doesn't exist or may have moved.</p>
            <a class="rl-404-btn" href="<?php echo esc_url(home_url('/')); ?>">Back to Butterfly</a>
        </div>
    </div>
</body>
</html>
        <?php
        exit;

    }









    public function load()
    {


        /*
        =========================
        ONLY LOAD ON APP PAGES
        =========================
        */


        if(
            !is_page(
                array(
                    'restaurant-dashboard',
                    'customer-dashboard',
                    'lost-password',
                    'reset-password',
                    'explore',
                    'restaurant',
                    'my-entries',
                    'manage-brand',
                    'manage-menu',
                    'manage-draws',
                    'privacy-policy',
                    'verify-email'
                )
            )
            &&
            !is_front_page()
            &&
            !is_page('register')
        ){

            return;

        }









        /*
        =========================
        QR LIBRARY
        =========================
        */


        wp_enqueue_script(

            'html5-qrcode',

            'https://unpkg.com/html5-qrcode',

            array(),

            '2.3.8',

            true

        );


        /*
        =========================
        QR CODE GENERATION (customer's own code)
        Renders the customer's QR entirely client-side instead of
        loading it from a third-party image API — their token never
        leaves the browser this way, and it doesn't depend on an
        outside service being reachable.
        =========================
        */


        wp_enqueue_script(

            'qrcodejs',

            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',

            array(),

            '1.0.0',

            true

        );


        wp_enqueue_script(

            'rl-qr-display',

            RL_PLUGIN_URL . 'assets/js/qr-display.js',

            array('qrcodejs'),

            RL_VERSION,

            true

        );



        wp_enqueue_style(
            'dashicons'
        );






        /*
        =========================
        APP JS
        =========================
        */


        wp_enqueue_script(

            'rl-app',

            RL_PLUGIN_URL . 'assets/js/app.js',

            array(),

            RL_VERSION,

            true

        );









        /*
        =========================
        SCANNER JS
        =========================
        */


        wp_enqueue_script(

            'rl-scanner',

            RL_PLUGIN_URL . 'assets/js/scanner.js',

            array(
                'html5-qrcode',
                'rl-app'
            ),

            RL_VERSION,

            true

        );
        wp_enqueue_script(

            'rl-customer-tabs',

            RL_PLUGIN_URL . 'assets/js/customer-tabs.js',

            array(),

            RL_VERSION,

            true

        );

        wp_localize_script(

            'rl-customer-tabs',

            'RL_HOME_REFRESH',

            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('rl_ajax_nonce'),
            )

        );


        /*
        =========================
        PUSH NOTIFICATIONS (subscribe opt-in)
        Only meaningful for a logged-in customer — brand managers/
        staff/guests don't have a "My Entries"-style notification
        audience, and the composer only ever targets customers.
        =========================
        */


        wp_enqueue_script(

            'rl-push-subscribe',

            RL_PLUGIN_URL . 'assets/js/push-subscribe.js',

            array(),

            RL_VERSION,

            true

        );


        $is_current_customer = is_user_logged_in() && in_array('customer', wp_get_current_user()->roles, true);

        // The in-context "enable notifications" prompt only makes
        // sense once — after the customer's already seen the value
        // (they've earned points at least once) — and only once ever,
        // tracked via the same rl_notify_prompted flag whether they
        // allowed or dismissed it.
        $show_notify_prompt = false;

        if ($is_current_customer) {

            $current_user_id = get_current_user_id();

            if (!get_user_meta($current_user_id, 'rl_notify_prompted', true) && class_exists('RL_Users') && class_exists('RL_Transactions')) {

                $prompt_customer_id = RL_Users::ensure_customer_identity($current_user_id);

                if ($prompt_customer_id) {
                    $show_notify_prompt = !empty(RL_Transactions::get_customer_transactions($prompt_customer_id));
                }
            }
        }

        wp_localize_script(

            'rl-push-subscribe',

            'RL_PUSH',

            array(
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('rl_ajax_nonce'),
                'vapidPublicKey'   => ($is_current_customer && class_exists('RL_Web_Push')) ? RL_Web_Push::public_key() : '',
                'showNotifyPrompt' => $show_notify_prompt,
            )

        );


        /*
        =========================
        FIRST-RUN ONBOARDING
        The overlay markup only exists in the DOM when the server
        decides it hasn't been seen yet — this script just drives the
        slides and tells the server once it has been.
        =========================
        */


        wp_enqueue_script(

            'rl-onboarding',

            RL_PLUGIN_URL . 'assets/js/onboarding.js',

            array(),

            RL_VERSION,

            true

        );

        wp_localize_script(

            'rl-onboarding',

            'RL_ONBOARDING',

            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('rl_ajax_nonce'),
            )

        );


        /*
        =========================
        ADD-TO-HOME-SCREEN PROMPT
        Soft, dismissible banner shown after a 2nd visit — visit
        counting and dismissal are entirely client-side (localStorage),
        no server data needed.
        =========================
        */


        wp_enqueue_script(

            'rl-install-prompt',

            RL_PLUGIN_URL . 'assets/js/install-prompt.js',

            array(),

            RL_VERSION,

            true

        );









        /*
        =========================
        AJAX DATA
        =========================
        */


        $scan_locations = array();
        $default_location_id = 0;

        if(is_user_logged_in()){

            $user = wp_get_current_user();

            if(in_array('brand_manager', $user->roles, true)){

                $brand = RL_Brands::get_by_manager($user->ID);

                if($brand){

                    $locations = RL_Locations::get_all_by_brand($brand->id, true);

                    foreach($locations as $location){

                        $scan_locations[] = array(
                            'id'   => intval($location->id),
                            'name' => $location->name
                        );

                    }

                    if(!empty($scan_locations)){
                        $default_location_id = $scan_locations[0]['id'];
                    }

                }

            }

        }

        wp_localize_script(

            'rl-scanner',

            'RL_AJAX',

            array(

                'ajax_url'=>admin_url(
                    'admin-ajax.php'
                ),

                'nonce'=>wp_create_nonce(
                    'rl_ajax_nonce'
                ),

                'locations'=>$scan_locations,

                'location_id'=>$default_location_id

            )

        );









        /*
        =========================
        EXPLORE: MAP
        (free OpenStreetMap tiles via Leaflet — no API key)
        Explore now lives both as its own page and as a tab inside
        the logged-in dashboard, so this loads on every app page —
        load() already only runs on the pages checked above.
        =========================
        */


        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );

        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'rl-explore',
            RL_PLUGIN_URL . 'assets/js/explore.js',
            array('leaflet'),
            RL_VERSION,
            true
        );

        // Active draws, so each map marker can show which locations
        // currently have a prize running — resolved per-location the
        // same way the restaurant page and prize cards do.
        $active_draws = class_exists('RL_Draws') ? RL_Draws::get_all_active_public() : array();

        $map_locations = array();

        foreach(RL_Locations::get_all_public() as $loc){

            if($loc->lat !== null && $loc->lng !== null && $loc->lat !== '' && $loc->lng !== ''){

                $prizes = array();

                foreach($active_draws as $draw){
                    if(RL_Draws::draw_applies_to_location($draw, $loc->id, $loc->brand_id)){
                        $prizes[] = $draw->title;
                    }
                }

                $map_locations[] = array(
                    'id'     => intval($loc->id),
                    'name'   => $loc->name,
                    'brand'  => $loc->brand_name,
                    'lat'    => floatval($loc->lat),
                    'lng'    => floatval($loc->lng),
                    'prizes' => $prizes,
                );

            }

        }

        wp_localize_script(
            'rl-explore',
            'RL_EXPLORE',
            array(
                'locations' => $map_locations,
                'logoUrl'   => RL_PLUGIN_URL . 'assets/images/butterfly-logo.png',
            )
        );


        /*
        =========================
        CSS
        =========================
        */


        wp_enqueue_style(

            'rl-customer-app',

            RL_PLUGIN_URL . 'assets/css/customer-app.css',

            array(),

            RL_VERSION

        );


    }



}