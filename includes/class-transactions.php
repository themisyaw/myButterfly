<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Transactions
{


    /*
    =========================
    GET CUSTOMER TRANSACTIONS
    =========================
    */


    public static function get_customer_transactions(
        $customer_id,
        $limit = 20
    )
    {


        global $wpdb;



        $customer_id = absint(
            $customer_id
        );



        $limit = absint(
            $limit
        );



        if ($limit <= 0) {

            $limit = 20;

        }



        if ($limit > 100) {

            $limit = 100;

        }





        if (!$customer_id) {

            return array();

        }





        $table     = $wpdb->prefix . 'rl_transactions';
        $brands    = $wpdb->prefix . 'rl_brands';
        $locations = $wpdb->prefix . 'rl_locations';





        return $wpdb->get_results(

            $wpdb->prepare(

                "
                SELECT
                    t.id,
                    t.customer_id,
                    t.staff_id,
                    t.transaction_type,
                    t.points,
                    t.note,
                    t.created_at,
                    b.name AS brand_name,
                    l.name AS location_name

                FROM {$table} t
                LEFT JOIN {$brands} b ON b.id = t.brand_id
                LEFT JOIN {$locations} l ON l.id = t.location_id

                WHERE t.customer_id = %d

                ORDER BY t.created_at DESC

                LIMIT %d

                ",

                $customer_id,

                $limit

            )

        );


    }

    /**
     * Every location id this customer has at least one transaction
     * at — no row limit, since this only needs to answer "has this
     * customer been here before?" for the Restaurants directory's
     * "Visited" filter, not render anything itself.
     */
    public static function get_visited_location_ids($customer_id)
    {
        global $wpdb;

        $customer_id = absint($customer_id);

        if (!$customer_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_transactions';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT DISTINCT t.location_id
                FROM {$table} t
                WHERE t.customer_id = %d AND t.location_id IS NOT NULL
                ",
                $customer_id
            )
        );

        return array_map('intval', $ids);
    }

}