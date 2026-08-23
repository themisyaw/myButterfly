<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RL_Deactivator {

    public static function deactivate() {

        flush_rewrite_rules();

    }

}