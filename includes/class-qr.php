<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_QR
{


    /*
    =========================
    GENERATE SECURE TOKEN
    =========================
    */


    public static function generate_token()
    {


        return wp_generate_password(

            48,

            false,

            false

        );


    }







    /*
    =========================
    VALIDATE TOKEN FORMAT
    =========================
    */


    public static function validate_token(
        $token
    )
    {


        $token = sanitize_text_field(
            $token
        );



        if (empty($token)) {


            return false;


        }





        if (strlen($token) < 32) {


            return false;


        }





        return true;


    }







    /*
    =========================
    GET QR IMAGE URL
    UNUSED as of the client-side QR rendering change — the customer's
    QR tab now renders the code in-browser via qrcodejs (see
    assets/js/qr-display.js) instead of loading an <img> from this
    URL, so the token is never sent to a third-party service. Left
    here rather than deleted in case anything external still expects
    it, but nothing in this plugin calls it anymore — don't
    reintroduce a call to this without good reason.
    =========================
    */


    public static function get_qr_url(
        $token
    )
    {


        if (!self::validate_token($token)) {


            return false;


        }





        $data = urlencode(
            $token
        );



        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $data;


    }




    /*
    =========================
    GENERATE REFERRAL CODE
    Short, shareable — unlike the QR token this is meant to be
    typed or dropped in a link (?ref=CODE), not scanned.
    =========================
    */


    public static function generate_referral_code()
    {

        // Unambiguous charset — no 0/O/1/I confusion when read aloud or typed.
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 7;
        $code   = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[wp_rand(0, strlen($chars) - 1)];
        }

        return $code;
    }



}