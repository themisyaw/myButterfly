<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * English/Dutch string table for RL_I18n::t(). Keyed by feature area
 * so a missing translation is easy to spot — RL_I18n::t() falls back
 * to the English value (or the raw key) if a language/key is missing,
 * so an incomplete entry here never breaks a page.
 *
 * This intentionally does NOT cover every string in the plugin yet —
 * see the "Language & Translation" project memory for what's still
 * English-only (manager pages, the Privacy Policy body, and most
 * AJAX/toast messages).
 */
return array(

    // Bottom nav + hamburger menu
    'nav_restaurants' => array('en' => 'Restaurants', 'nl' => 'Restaurants'),
    'nav_prizes'       => array('en' => 'Prizes', 'nl' => 'Prijzen'),
    'nav_entries'      => array('en' => 'My Entries', 'nl' => 'Mijn deelnames'),
    'nav_activity'     => array('en' => 'Activity', 'nl' => 'Activiteit'),
    'nav_invite'       => array('en' => 'Invite friends', 'nl' => 'Vrienden uitnodigen'),
    'nav_settings'     => array('en' => 'Settings', 'nl' => 'Instellingen'),
    'nav_support'      => array('en' => 'Support', 'nl' => 'Ondersteuning'),
    'nav_logout'       => array('en' => 'Logout', 'nl' => 'Uitloggen'),
    'nav_home'         => array('en' => 'Home', 'nl' => 'Home'),
    'nav_scan'         => array('en' => 'Scan', 'nl' => 'Scannen'),

    // Header
    'header_hello'        => array('en' => 'Hello', 'nl' => 'Hallo'),
    'header_welcome_back' => array('en' => 'Welcome back', 'nl' => 'Welkom terug'),
    'header_welcome'      => array('en' => 'Welcome', 'nl' => 'Welkom'),
    'header_sign_in_earn' => array('en' => 'Sign in to earn points', 'nl' => 'Log in om punten te sparen'),
    'header_language'     => array('en' => 'Language', 'nl' => 'Taal'),

    // Home dashboard
    'home_explore_restaurants' => array('en' => 'Explore restaurants', 'nl' => 'Ontdek restaurants'),
    'home_recent_activity'     => array('en' => 'Recent activity', 'nl' => 'Recente activiteit'),
    'home_see_all'             => array('en' => 'See all', 'nl' => 'Alles bekijken'),
    'home_your_points'         => array('en' => 'Your points', 'nl' => 'Jouw punten'),
    'home_your_points_subtitle'=> array('en' => 'Points are separate per restaurant — tap one to see its menu.', 'nl' => 'Punten zijn per restaurant apart — tik op een restaurant om het menu te zien.'),
    'home_empty_title'         => array('en' => "Let's get your first points", 'nl' => 'Verdien je eerste punten'),
    'home_empty_body'          => array('en' => 'Scan the QR code at the counter on your next order — here\'s how it works:', 'nl' => 'Scan de QR-code bij de kassa bij je volgende bestelling — zo werkt het:'),
    'home_empty_step_scan'     => array('en' => 'Scan', 'nl' => 'Scannen'),
    'home_empty_step_earn'     => array('en' => 'Earn', 'nl' => 'Sparen'),
    'home_empty_step_redeem'   => array('en' => 'Redeem', 'nl' => 'Inwisselen'),
    'home_quick_scan'          => array('en' => 'Scan QR', 'nl' => 'QR scannen'),
    'home_quick_prizes'        => array('en' => 'Prizes', 'nl' => 'Prijzen'),
    'home_quick_invite'        => array('en' => 'Invite', 'nl' => 'Uitnodigen'),
    'home_quick_entries'       => array('en' => 'Entries', 'nl' => 'Deelnames'),
    'home_next_reward'         => array('en' => 'Next', 'nl' => 'Volgende'),
    'home_ready_to_redeem'     => array('en' => 'Ready to redeem now', 'nl' => 'Nu al in te wisselen'),
    'home_shared_across'       => array('en' => 'Shared across all', 'nl' => 'Gedeeld over alle'),
    'home_locations'           => array('en' => 'locations', 'nl' => 'locaties'),

    // Explore / Restaurants / Prizes tabs
    'explore_title'              => array('en' => 'Explore', 'nl' => 'Ontdekken'),
    'explore_subtitle'           => array('en' => 'Every restaurant on the app, plus everything you can currently win.', 'nl' => 'Elk restaurant in de app, plus alles wat je nu kunt winnen.'),
    'explore_tab_prizes'         => array('en' => 'Prizes', 'nl' => 'Prijzen'),
    'explore_tab_restaurants'    => array('en' => 'Restaurants', 'nl' => 'Restaurants'),
    'restaurants_title'          => array('en' => 'Restaurants', 'nl' => 'Restaurants'),
    'restaurants_subtitle'       => array('en' => 'Earn points anywhere — redeem them where you earned them.', 'nl' => 'Spaar overal punten — wissel ze in waar je ze verdiende.'),
    'restaurants_search'         => array('en' => 'Search restaurants…', 'nl' => 'Zoek restaurants…'),
    'filter_all'                 => array('en' => 'All', 'nl' => 'Alle'),
    'filter_visited'             => array('en' => 'Visited', 'nl' => 'Bezocht'),
    'filter_near_me'             => array('en' => 'Near me', 'nl' => 'Bij mij in de buurt'),
    'filter_map'                 => array('en' => 'Map', 'nl' => 'Kaart'),
    'filter_list'                => array('en' => 'List', 'nl' => 'Lijst'),
    'big_prize_eyebrow'          => array('en' => "This month's butterfly giveaway", 'nl' => 'De weggeefactie van deze maand'),
    'big_prize_entries_suffix'   => array('en' => 'entries', 'nl' => 'deelnames'),
    'big_prize_signup_to_enter'  => array('en' => 'Sign up to enter', 'nl' => 'Meld je aan om mee te doen'),
    'big_prize_ends'             => array('en' => 'Ends', 'nl' => 'Eindigt op'),
    'big_prize_odds'             => array('en' => 'of your entries', 'nl' => 'van jouw deelnames'),
    'big_prize_odds_total'       => array('en' => 'entries total', 'nl' => 'deelnames totaal'),
    'big_prize_footnote'         => array('en' => '1 entry every purchase, at any restaurant on the app — plus 1 entry every time you refer a friend.', 'nl' => '1 deelname bij elke aankoop, bij elk restaurant in de app — plus 1 deelname elke keer dat je een vriend(in) uitnodigt.'),
    'prizes_active_title'        => array('en' => 'Active prizes', 'nl' => 'Actieve prijzen'),
    'prizes_active_subtitle'     => array('en' => 'From individual restaurants, running right now.', 'nl' => 'Van individuele restaurants, nu actief.'),
    'prize_min_points_rule'      => array('en' => 'Min. %s points per order to earn 1 entry.', 'nl' => 'Min. %s punten per bestelling voor 1 deelname.'),
    'prize_not_entered_yet'      => array('en' => 'Not entered yet', 'nl' => 'Nog niet meegedaan'),
    'prizes_none_running'        => array('en' => 'No restaurant prizes running right now — check back soon.', 'nl' => 'Op dit moment lopen er geen restaurantprijzen — kom snel terug.'),
    'draw_scope_platform'        => array('en' => 'Platform', 'nl' => 'Platform'),
    'draw_scope_all_locations'   => array('en' => 'All locations', 'nl' => 'Alle locaties'),

    // Days of the week (keyed off RL_Brand_Hours::DAY_LABELS' English values)
    'day_sunday'    => array('en' => 'Sunday', 'nl' => 'Zondag'),
    'day_monday'    => array('en' => 'Monday', 'nl' => 'Maandag'),
    'day_tuesday'   => array('en' => 'Tuesday', 'nl' => 'Dinsdag'),
    'day_wednesday' => array('en' => 'Wednesday', 'nl' => 'Woensdag'),
    'day_thursday'  => array('en' => 'Thursday', 'nl' => 'Donderdag'),
    'day_friday'    => array('en' => 'Friday', 'nl' => 'Vrijdag'),
    'day_saturday'  => array('en' => 'Saturday', 'nl' => 'Zaterdag'),
    'hours_closed'  => array('en' => 'Closed', 'nl' => 'Gesloten'),
    'hours_open_now' => array('en' => 'Open now', 'nl' => 'Nu open'),
    'hours_closed_now' => array('en' => 'Closed now', 'nl' => 'Nu gesloten'),
    'hours_closed_today' => array('en' => 'Closed today', 'nl' => 'Vandaag gesloten'),

    // QR tab
    'qr_title'      => array('en' => 'Your QR Code', 'nl' => 'Jouw QR-code'),
    'qr_subtitle'   => array('en' => 'Scan this code at checkout to collect or redeem rewards.', 'nl' => 'Scan deze code bij de kassa om punten te sparen of in te wisselen.'),
    'qr_aria_label' => array('en' => 'Your QR code', 'nl' => 'Jouw QR-code'),
    'qr_tip'        => array('en' => 'Display this QR code when visiting us.', 'nl' => 'Laat deze QR-code zien als je ons bezoekt.'),

    // Prizes tab wrapper
    'prizes_tab_subtitle' => array('en' => 'Everything you can currently win, anywhere on Butterfly.', 'nl' => 'Alles wat je nu kunt winnen, overal op Butterfly.'),

    // Invite tab
    'invite_subtitle'   => array('en' => 'Every friend who joins with your link gets you an entry into the Butterfly prize.', 'nl' => 'Elke vriend(in) die zich aanmeldt met jouw link levert jou een deelname op voor de Butterfly-prijs.'),
    'invite_your_code'  => array('en' => 'Your code', 'nl' => 'Jouw code'),
    'invite_copy_link'  => array('en' => 'Copy invite link', 'nl' => 'Uitnodigingslink kopiëren'),
    'invite_copied'     => array('en' => 'Link copied — go ahead and share it.', 'nl' => 'Link gekopieerd — je kunt hem nu delen.'),

    // Activity tab
    'activity_subtitle'     => array('en' => 'Points earned and redeemed at every restaurant on the app.', 'nl' => 'Gespaarde en ingewisselde punten bij elk restaurant in de app.'),
    'activity_points_suffix'=> array('en' => 'points', 'nl' => 'punten'),
    'activity_none'         => array('en' => 'No activity yet.', 'nl' => 'Nog geen activiteit.'),
    'view_on_map'                => array('en' => 'View on map', 'nl' => 'Op kaart bekijken'),
    'get_directions'             => array('en' => 'Get Directions', 'nl' => 'Route plannen'),
    'explore_create_account_suffix' => array('en' => 'to start earning points and entries.', 'nl' => 'om punten en deelnames te beginnen sparen.'),

    // Restaurant single page
    'restaurant_balance_here'  => array('en' => 'Your balance here', 'nl' => 'Jouw saldo hier'),
    'restaurant_next'          => array('en' => 'Next:', 'nl' => 'Volgende:'),
    'restaurant_menu'          => array('en' => 'Menu', 'nl' => 'Menu'),
    'restaurant_menu_subtitle' => array('en' => 'Redeem points earned here.', 'nl' => 'Wissel hier verdiende punten in.'),
    'restaurant_location'      => array('en' => 'Location', 'nl' => 'Locatie'),
    'restaurant_hours'         => array('en' => 'Hours', 'nl' => 'Openingstijden'),
    'restaurant_not_found'     => array('en' => 'Restaurant not found', 'nl' => 'Restaurant niet gevonden'),
    'restaurant_not_found_body'=> array('en' => 'This restaurant may have moved or is no longer on the app.', 'nl' => 'Dit restaurant is mogelijk verplaatst of niet meer beschikbaar in de app.'),
    'restaurant_no_menu'       => array('en' => 'No menu items yet — check back soon.', 'nl' => 'Nog geen menu-items — kom snel terug.'),
    'restaurant_menu_more'     => array('en' => 'More', 'nl' => 'Meer'),
    'restaurant_create_account'=> array('en' => 'Create an account', 'nl' => 'Maak een account aan'),
    'restaurant_start_earning' => array('en' => 'to start earning points here.', 'nl' => 'om hier punten te beginnen sparen.'),

    // My Entries
    'entries_title'    => array('en' => 'My Entries', 'nl' => 'Mijn deelnames'),
    'entries_subtitle' => array('en' => "Every prize draw you've picked up tickets in.", 'nl' => 'Elke prijstrekking waar je aan meedoet.'),
    'entries_active'   => array('en' => 'Active', 'nl' => 'Actief'),
    'entries_ended'    => array('en' => 'Ended', 'nl' => 'Afgelopen'),
    'entries_entry_singular'  => array('en' => 'entry', 'nl' => 'deelname'),
    'entries_entry_plural'    => array('en' => 'entries', 'nl' => 'deelnames'),
    'entries_none_active'     => array('en' => 'No active draw entries right now — earn points or refer a friend to pick some up.', 'nl' => 'Op dit moment geen actieve deelnames — spaar punten of nodig een vriend(in) uit om er wat te verzamelen.'),
    'entries_none_ended'      => array('en' => "No ended draws yet — entries you've picked up will show here once their draw closes.", 'nl' => 'Nog geen afgelopen trekkingen — deelnames die je hebt verzameld verschijnen hier zodra hun trekking sluit.'),
    'entries_platform_prize'  => array('en' => 'Butterfly platform prize', 'nl' => 'Butterfly-platformprijs'),
    'entries_all_locations_suffix' => array('en' => 'all locations', 'nl' => 'alle locaties'),
    'entries_you_won'         => array('en' => 'You won!', 'nl' => 'Je hebt gewonnen!'),
    'entries_draw_ended'      => array('en' => 'draw ended', 'nl' => 'trekking afgelopen'),
    'entries_last_entry'      => array('en' => 'Last entry', 'nl' => 'Laatste deelname'),

    // Settings tab
    'settings_title'             => array('en' => 'Settings', 'nl' => 'Instellingen'),
    'settings_app_section'       => array('en' => 'App', 'nl' => 'App'),
    'settings_install_title'     => array('en' => 'Install Butterfly', 'nl' => 'Installeer Butterfly'),
    'settings_install_body'      => array('en' => 'Add it to your home screen for one-tap access.', 'nl' => 'Zet het op je startscherm voor toegang met één tik.'),
    'settings_download_app'      => array('en' => 'Download', 'nl' => 'Downloaden'),
    'settings_notifications'     => array('en' => 'Notifications', 'nl' => 'Meldingen'),
    'settings_notify_title'      => array('en' => 'Prizes & winners', 'nl' => 'Prijzen & winnaars'),
    'settings_notify_body'       => array('en' => 'Get notified about new giveaways and if you win.', 'nl' => 'Ontvang een melding bij nieuwe weggeefacties en als je wint.'),
    'settings_language_section'  => array('en' => 'Language', 'nl' => 'Taal'),
    'settings_language_title'    => array('en' => 'App language', 'nl' => 'Apptaal'),
    'settings_language_body'     => array('en' => 'Choose English or Dutch.', 'nl' => 'Kies Engels of Nederlands.'),
    'settings_legal'             => array('en' => 'Legal', 'nl' => 'Juridisch'),
    'settings_privacy_policy'    => array('en' => 'Privacy & Cookie Policy', 'nl' => 'Privacy- en cookiebeleid'),
    'settings_privacy_body'      => array('en' => 'What we collect and why.', 'nl' => 'Wat we verzamelen en waarom.'),
    'settings_view'               => array('en' => 'View', 'nl' => 'Bekijken'),

    // Support page (/support)
    'support_title'               => array('en' => 'Support', 'nl' => 'Ondersteuning'),
    'support_subtitle'            => array('en' => 'Tell us what\'s wrong or what you\'d like to see — it goes straight to us.', 'nl' => 'Laat ons weten wat er mis is of wat je zou willen zien — het komt direct bij ons terecht.'),
    'support_subject_label'       => array('en' => 'Subject', 'nl' => 'Onderwerp'),
    'support_message_label'       => array('en' => 'Message', 'nl' => 'Bericht'),
    'support_submit'              => array('en' => 'Send message', 'nl' => 'Bericht versturen'),
    'support_sent'                => array('en' => 'Thanks — your message has been sent.', 'nl' => 'Bedankt — je bericht is verzonden.'),
    'support_error_required'      => array('en' => 'Please fill in both subject and message.', 'nl' => 'Vul zowel het onderwerp als het bericht in.'),
    'support_error_send_failed'   => array('en' => 'Could not send your message — please try again.', 'nl' => 'Je bericht kon niet worden verzonden — probeer het opnieuw.'),

    // Login / Register / Password
    'login_welcome_back'    => array('en' => 'Welcome back', 'nl' => 'Welkom terug'),
    'login_subtitle'        => array('en' => 'Sign in to access your rewards', 'nl' => 'Log in om je beloningen te bekijken'),
    'login_username_email'  => array('en' => 'Username or Email', 'nl' => 'Gebruikersnaam of e-mail'),
    'login_password'        => array('en' => 'Password', 'nl' => 'Wachtwoord'),
    'login_button'          => array('en' => 'Login', 'nl' => 'Inloggen'),
    'login_or'              => array('en' => 'or', 'nl' => 'of'),
    'login_continue_google' => array('en' => 'Continue with Google', 'nl' => 'Doorgaan met Google'),
    'login_no_account'      => array('en' => "Don't have an account?", 'nl' => 'Nog geen account?'),
    'login_create_account'  => array('en' => 'Create account', 'nl' => 'Account aanmaken'),
    'login_forgot_password' => array('en' => 'Forgot your password?', 'nl' => 'Wachtwoord vergeten?'),
    'login_reset_password'  => array('en' => 'Reset Password', 'nl' => 'Wachtwoord resetten'),
    'login_verification_sent' => array('en' => 'Verification email sent — check your inbox.', 'nl' => 'Verificatie-e-mail verzonden — controleer je inbox.'),
    'login_resend_button'     => array('en' => 'Resend verification email', 'nl' => 'Verificatie-e-mail opnieuw verzenden'),

    'register_title'           => array('en' => 'Create account', 'nl' => 'Account aanmaken'),
    'register_subtitle'        => array('en' => 'Join and start earning rewards', 'nl' => 'Meld je aan en begin met sparen'),
    'register_name'            => array('en' => 'Name', 'nl' => 'Naam'),
    'register_name_placeholder'=> array('en' => 'Your name', 'nl' => 'Jouw naam'),
    'register_email'           => array('en' => 'Email', 'nl' => 'E-mail'),
    'register_password'        => array('en' => 'Password', 'nl' => 'Wachtwoord'),
    'register_password_placeholder' => array('en' => 'Create a password', 'nl' => 'Kies een wachtwoord'),
    'register_repeat_password' => array('en' => 'Repeat password', 'nl' => 'Herhaal wachtwoord'),
    'register_repeat_placeholder' => array('en' => 'Type it again', 'nl' => 'Typ het opnieuw'),
    'register_password_mismatch' => array('en' => "Passwords don't match", 'nl' => 'Wachtwoorden komen niet overeen'),
    'register_agree_policy_pre'  => array('en' => 'I agree to the', 'nl' => 'Ik ga akkoord met het'),
    'register_agree_policy_link' => array('en' => 'Privacy & Cookie Policy', 'nl' => 'privacy- en cookiebeleid'),
    'register_button'          => array('en' => 'Create Account', 'nl' => 'Account aanmaken'),
    'register_have_account'    => array('en' => 'Already have an account?', 'nl' => 'Heb je al een account?'),
    'register_login'           => array('en' => 'Login', 'nl' => 'Inloggen'),
    'register_check_email_title' => array('en' => 'Check your email', 'nl' => 'Controleer je e-mail'),
    'register_check_email_pre'   => array('en' => 'We sent a confirmation link to', 'nl' => 'We hebben een bevestigingslink gestuurd naar'),
    'register_check_email_post'  => array('en' => 'Click it to activate your account and log in.', 'nl' => 'Klik erop om je account te activeren en in te loggen.'),
    'register_back_to_login'   => array('en' => 'Back to login', 'nl' => 'Terug naar inloggen'),
    'register_referral_bonus'  => array('en' => "You were invited by a friend — they'll get a bonus entry once you confirm your email.", 'nl' => 'Je bent uitgenodigd door een vriend(in) — die krijgt een bonusdeelname zodra je je e-mail bevestigt.'),
    'register_bot_check_failed'=> array('en' => 'Bot check failed — please try again.', 'nl' => 'Bot-controle mislukt — probeer het opnieuw.'),
    'register_agree_required'  => array('en' => 'Please agree to the Privacy Policy to create an account.', 'nl' => 'Ga akkoord met het privacybeleid om een account aan te maken.'),

    'verify_success_title'   => array('en' => 'Email confirmed', 'nl' => 'E-mail bevestigd'),
    'verify_success_body'    => array('en' => 'Your account is active — you can log in now.', 'nl' => 'Je account is actief — je kunt nu inloggen.'),
    'verify_fail_title'      => array('en' => "Link didn't work", 'nl' => 'Link werkte niet'),
    'verify_go_login'        => array('en' => 'Go to login', 'nl' => 'Naar inloggen'),

    // Privacy & Cookie Policy — full legal text. Values may contain
    // raw HTML (lists, <strong>/<code> tags) and are echoed
    // unescaped in privacy_policy_page(), same trust level as the
    // hand-written English original (no user input involved).
    'policy_page_title'   => array('en' => 'Privacy &amp; Cookie Policy', 'nl' => 'Privacy- en cookiebeleid'),
    'policy_last_updated' => array('en' => 'Last updated 16 August 2026', 'nl' => 'Laatst bijgewerkt op 16 augustus 2026'),
    'policy_s1_heading' => array('en' => 'Who we are', 'nl' => 'Wie wij zijn'),
    'policy_s1_body'    => array(
        'en' => 'Butterfly (this app) is operated by <strong>MyButterfly</strong>, registered at <strong>Amsterdam, Netherlands</strong>. For anything in this policy, or to exercise any of the rights below, contact us at <strong>info@mybutterfly.nl</strong>.',
        'nl' => 'Butterfly (deze app) wordt beheerd door <strong>MyButterfly</strong>, geregistreerd op <strong>Amsterdam, Nederland</strong>. Voor alles in dit beleid, of om een van onderstaande rechten uit te oefenen, kun je contact met ons opnemen via <strong>info@mybutterfly.nl</strong>.',
    ),

    'policy_s2_heading' => array('en' => 'What we collect', 'nl' => 'Wat we verzamelen'),
    'policy_s2_body'    => array(
        'en' => '<p>Only what the loyalty program actually needs to work:</p>
                    <ul>
                        <li><strong>Account details</strong> — your name, email address, and a password (stored securely hashed, never in plain text).</li>
                        <li><strong>Loyalty activity</strong> — your points balance at each restaurant, your redemption and points history, and the prize-draw entries you\'ve earned.</li>
                        <li><strong>Sign-in via Google</strong> (only if you choose that option instead of a password) — your Google account\'s verified email address and name. We never receive your Google password, and we don\'t request anything from Google beyond your basic email/name profile.</li>
                        <li><strong>Push notifications</strong> (only if you turn them on) — a subscription identifier and encryption keys your browser generates, used solely to deliver notifications to that device. No location or personal profile is attached to this.</li>
                        <li><strong>Your location</strong> (only if your browser grants permission, e.g. to show "how far away" a restaurant is) — used entirely on your own device to calculate distance and center the map. It is never sent to or stored on our servers.</li>
                        <li><strong>Referral activity</strong> — if you invite a friend, we record that the referral happened so we can credit your prize-draw entry.</li>
                    </ul>
                    <p>We do not collect payment card details, government ID, or any "special category" data (health, religion, etc.) — none of that is part of how this app works.</p>',
        'nl' => '<p>Alleen wat het spaarprogramma daadwerkelijk nodig heeft om te werken:</p>
                    <ul>
                        <li><strong>Accountgegevens</strong> — je naam, e-mailadres en een wachtwoord (veilig gehasht opgeslagen, nooit in platte tekst).</li>
                        <li><strong>Spaaractiviteit</strong> — je puntensaldo per restaurant, je inwissel- en puntengeschiedenis, en de prijstrekking-deelnames die je hebt verdiend.</li>
                        <li><strong>Inloggen via Google</strong> (alleen als je die optie kiest in plaats van een wachtwoord) — het geverifieerde e-mailadres en de naam van je Google-account. We ontvangen nooit je Google-wachtwoord en vragen niets bij Google op behalve je basale e-mail-/naamprofiel.</li>
                        <li><strong>Pushmeldingen</strong> (alleen als je ze inschakelt) — een abonnement-ID en encryptiesleutels die je browser genereert, uitsluitend gebruikt om meldingen naar dat apparaat te sturen. Hier is geen locatie of persoonlijk profiel aan gekoppeld.</li>
                        <li><strong>Jouw locatie</strong> (alleen als je browser hiervoor toestemming geeft, bijv. om te tonen hoe ver een restaurant weg is) — volledig op je eigen apparaat gebruikt om de afstand te berekenen en de kaart te centreren. Wordt nooit naar onze servers verstuurd of daar opgeslagen.</li>
                        <li><strong>Verwijzingsactiviteit</strong> — als je een vriend(in) uitnodigt, registreren we dat de verwijzing heeft plaatsgevonden zodat we jouw prijstrekking-deelname kunnen toekennen.</li>
                    </ul>
                    <p>We verzamelen geen betaalkaartgegevens, overheids-ID\'s of "bijzondere" persoonsgegevens (gezondheid, religie, enz.) — niets daarvan maakt deel uit van hoe deze app werkt.</p>',
    ),

    'policy_s3_heading' => array('en' => 'Why we process it (legal basis)', 'nl' => 'Waarom we het verwerken (rechtsgrond)'),
    'policy_s3_body'    => array(
        'en' => '<ul>
                        <li><strong>Running your account and the loyalty program</strong> (points, redemptions, prize draws) — necessary to provide the service you signed up for.</li>
                        <li><strong>Push notifications and use of your live location</strong> — only with your explicit, revocable consent (the permission prompt from your browser, or the toggle in Settings).</li>
                        <li><strong>Bot / fraud protection on registration</strong> (via Cloudflare Turnstile) — our legitimate interest in keeping the prize draws fair and free of fake accounts.</li>
                    </ul>',
        'nl' => '<ul>
                        <li><strong>Het beheren van je account en het spaarprogramma</strong> (punten, inwisselingen, prijstrekkingen) — noodzakelijk om de dienst te leveren waarvoor je je hebt aangemeld.</li>
                        <li><strong>Pushmeldingen en het gebruik van je live locatie</strong> — alleen met jouw uitdrukkelijke, herroepbare toestemming (de toestemmingsvraag van je browser, of de schakelaar in Instellingen).</li>
                        <li><strong>Bot-/fraudebescherming bij registratie</strong> (via Cloudflare Turnstile) — ons gerechtvaardigd belang om de prijstrekkingen eerlijk en vrij van nepaccounts te houden.</li>
                    </ul>',
    ),

    'policy_s4_heading' => array('en' => 'Who we share it with', 'nl' => 'Met wie we het delen'),
    'policy_s4_body'    => array(
        'en' => '<p>We don\'t sell your data. A small number of service providers process it on our behalf, only for the purpose stated:</p>
                    <ul>
                        <li><strong>Google</strong> — only if you use "Sign in with Google", to verify your identity.</li>
                        <li><strong>Cloudflare</strong> (Turnstile) — bot detection on the registration form only.</li>
                        <li><strong>Our email delivery provider</strong> — to send account, password-reset, and prize-winner emails.</li>
                        <li><strong>Browser push services</strong> (e.g. Google, Mozilla, Apple, depending on your browser) — the standard, unavoidable delivery path for any web push notification, only used if you\'ve opted in.</li>
                        <li><strong>Our hosting provider</strong> — to run the app and store the database.</li>
                    </ul>',
        'nl' => '<p>We verkopen je gegevens niet. Een klein aantal dienstverleners verwerkt ze namens ons, uitsluitend voor het genoemde doel:</p>
                    <ul>
                        <li><strong>Google</strong> — alleen als je "Inloggen met Google" gebruikt, om je identiteit te verifiëren.</li>
                        <li><strong>Cloudflare</strong> (Turnstile) — alleen botdetectie op het registratieformulier.</li>
                        <li><strong>Onze e-maildienstverlener</strong> — voor het versturen van account-, wachtwoordreset- en prijswinnaar-e-mails.</li>
                        <li><strong>Browser-pushdiensten</strong> (bijv. Google, Mozilla, Apple, afhankelijk van je browser) — de standaard, onvermijdelijke bezorgroute voor elke webpushmelding, alleen gebruikt als je je hebt aangemeld.</li>
                        <li><strong>Onze hostingprovider</strong> — om de app te draaien en de database op te slaan.</li>
                    </ul>',
    ),

    'policy_s5_heading' => array('en' => 'Cookies &amp; similar technology', 'nl' => 'Cookies en vergelijkbare technologie'),
    'policy_s5_body'    => array(
        'en' => '<p>We keep this to the minimum needed to make the app work — no advertising or cross-site tracking cookies are used.</p>
                    <ul>
                        <li><strong>Login/session cookies</strong> (WordPress) — strictly necessary, to keep you signed in.</li>
                        <li><strong>Cloudflare Turnstile cookie</strong> — set only on the registration page, for bot protection.</li>
                        <li><strong>Google sign-in cookies</strong> — set only if you use that sign-in option.</li>
                        <li><strong>On-device storage</strong> (not a cookie, and never sent to us) — we remember whether you\'ve dismissed the "add to home screen" prompt and how many times you\'ve visited, purely so we don\'t nag you about it repeatedly.</li>
                    </ul>
                    <p>Because none of this is used for advertising or tracking you across other sites, Dutch/EU cookie law doesn\'t require a consent banner for it — but you\'re always free to clear cookies/site data in your browser at any time.</p>',
        'nl' => '<p>We houden dit tot het minimum dat nodig is om de app te laten werken — er worden geen advertentie- of cross-site trackingcookies gebruikt.</p>
                    <ul>
                        <li><strong>Login-/sessiecookies</strong> (WordPress) — strikt noodzakelijk, om je ingelogd te houden.</li>
                        <li><strong>Cloudflare Turnstile-cookie</strong> — wordt alleen op de registratiepagina geplaatst, voor botbescherming.</li>
                        <li><strong>Google-inlogcookies</strong> — worden alleen geplaatst als je die inlogoptie gebruikt.</li>
                        <li><strong>Opslag op je apparaat</strong> (geen cookie, en nooit naar ons verstuurd) — we onthouden of je de melding "toevoegen aan startscherm" hebt weggeklikt en hoe vaak je de app hebt bezocht, puur zodat we je hier niet steeds opnieuw mee lastigvallen.</li>
                    </ul>
                    <p>Omdat niets hiervan wordt gebruikt voor advertenties of om je op andere sites te volgen, vereist de Nederlandse/EU-cookiewetgeving hiervoor geen toestemmingsbanner — maar je kunt op elk moment cookies/sitegegevens wissen in je browser.</p>',
    ),

    'policy_s6_heading' => array('en' => 'How long we keep it', 'nl' => 'Hoe lang we het bewaren'),
    'policy_s6_body'    => array(
        'en' => '<p>We keep your account and loyalty data for as long as your account is active. If you\'d like your account and personal data deleted, contact us and we\'ll action it — this isn\'t yet a self-service option in the app.</p>',
        'nl' => '<p>We bewaren je account- en spaargegevens zolang je account actief is. Als je wilt dat je account en persoonsgegevens worden verwijderd, neem dan contact met ons op — dit is nog geen selfserviceoptie in de app.</p>',
    ),

    'policy_s7_heading' => array('en' => 'Your rights', 'nl' => 'Jouw rechten'),
    'policy_s7_body'    => array(
        'en' => '<p>Under GDPR (and the Dutch UAVG), you have the right to:</p>
                    <ul>
                        <li>Access the personal data we hold about you</li>
                        <li>Correct inaccurate data</li>
                        <li>Request deletion of your data</li>
                        <li>Restrict or object to certain processing</li>
                        <li>Receive your data in a portable format</li>
                        <li>Withdraw consent at any time (for push notifications or location — just turn them off in Settings)</li>
                    </ul>
                    <p>To exercise any of these, contact us using the details above. If you\'re not satisfied with our response, you have the right to lodge a complaint with the Dutch data protection authority, the <strong>Autoriteit Persoonsgegevens</strong> (autoriteitpersoonsgegevens.nl).</p>',
        'nl' => '<p>Onder de AVG (en de Nederlandse UAVG) heb je het recht om:</p>
                    <ul>
                        <li>Inzage te krijgen in de persoonsgegevens die we over je hebben</li>
                        <li>Onjuiste gegevens te laten corrigeren</li>
                        <li>Verwijdering van je gegevens te verzoeken</li>
                        <li>Bepaalde verwerking te beperken of hiertegen bezwaar te maken</li>
                        <li>Je gegevens in een overdraagbaar formaat te ontvangen</li>
                        <li>Op elk moment je toestemming in te trekken (voor pushmeldingen of locatie — zet ze gewoon uit in Instellingen)</li>
                    </ul>
                    <p>Om een van deze rechten uit te oefenen, neem contact met ons op via de gegevens hierboven. Ben je niet tevreden met onze reactie, dan heb je het recht om een klacht in te dienen bij de Nederlandse toezichthouder, de <strong>Autoriteit Persoonsgegevens</strong> (autoriteitpersoonsgegevens.nl).</p>',
    ),

    'policy_s8_heading' => array('en' => 'Age requirement', 'nl' => 'Leeftijdsvereiste'),
    'policy_s8_body'    => array(
        'en' => '<p>This app is intended for people aged 16 and over, in line with the age of consent for online services under Dutch law. We don\'t knowingly collect data from anyone younger.</p>',
        'nl' => '<p>Deze app is bedoeld voor personen van 16 jaar en ouder, in lijn met de digitale meerderjarigheidsleeftijd voor onlinediensten onder Nederlands recht. We verzamelen niet bewust gegevens van jongere personen.</p>',
    ),

    'policy_s9_heading' => array('en' => 'Changes to this policy', 'nl' => 'Wijzigingen in dit beleid'),
    'policy_s9_body'    => array(
        'en' => '<p>If how we handle your data changes in a meaningful way, we\'ll update this page and change the "last updated" date at the top.</p>',
        'nl' => '<p>Als de manier waarop we met je gegevens omgaan wezenlijk verandert, werken we deze pagina bij en passen we de datum "laatst bijgewerkt" bovenaan aan.</p>',
    ),

    'restaurants_none_joined' => array('en' => 'No restaurants have joined yet.', 'nl' => 'Er hebben zich nog geen restaurants aangesloten.'),

    // Lost/reset password
    'lost_pass_title'        => array('en' => 'Reset Password', 'nl' => 'Wachtwoord resetten'),
    'lost_pass_subtitle'     => array('en' => 'Enter your email or username to get a reset link', 'nl' => 'Voer je e-mail of gebruikersnaam in om een resetlink te ontvangen'),
    'lost_pass_email_label'  => array('en' => 'Email or Username', 'nl' => 'E-mail of gebruikersnaam'),
    'lost_pass_send_button'  => array('en' => 'Send Reset Link', 'nl' => 'Resetlink verzenden'),
    'lost_pass_remember'     => array('en' => 'Remember your password?', 'nl' => 'Wachtwoord weer bekend?'),
    'lost_pass_success'      => array('en' => 'If an account exists with that email/username, a password reset link has been sent.', 'nl' => 'Als er een account bestaat met dat e-mailadres/gebruikersnaam, is er een resetlink verzonden.'),
    'reset_pass_already_logged_in' => array('en' => 'You are currently logged in. Please log out first if you wish to reset another account.', 'nl' => 'Je bent momenteel ingelogd. Log eerst uit als je een ander account wilt resetten.'),
    'reset_pass_invalid_token' => array('en' => 'Invalid or missing reset token.', 'nl' => 'Ongeldig of ontbrekend reset-token.'),
    'reset_pass_link_expired_title' => array('en' => 'Link Expired', 'nl' => 'Link verlopen'),
    'reset_pass_link_expired_body'  => array('en' => 'This password reset link is invalid or has already been used.', 'nl' => 'Deze link om het wachtwoord te resetten is ongeldig of al gebruikt.'),
    'reset_pass_request_new'  => array('en' => 'Request a new link', 'nl' => 'Vraag een nieuwe link aan'),
    'reset_pass_title'        => array('en' => 'Set New Password', 'nl' => 'Nieuw wachtwoord instellen'),
    'reset_pass_subtitle'     => array('en' => 'Enter your new password below', 'nl' => 'Voer hieronder je nieuwe wachtwoord in'),
    'reset_pass_success'      => array('en' => 'Your password has been reset successfully! You can now log in.', 'nl' => 'Je wachtwoord is succesvol gereset! Je kunt nu inloggen.'),
    'reset_pass_proceed_login'=> array('en' => 'Proceed to Login', 'nl' => 'Ga naar inloggen'),
    'reset_pass_new_label'    => array('en' => 'New Password', 'nl' => 'Nieuw wachtwoord'),
    'reset_pass_new_placeholder' => array('en' => 'At least 8 characters', 'nl' => 'Minimaal 8 tekens'),
    'reset_pass_save_button'  => array('en' => 'Save New Password', 'nl' => 'Nieuw wachtwoord opslaan'),
);
