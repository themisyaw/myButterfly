<?php

if (!defined('ABSPATH')) {
    exit;
}


class RL_Pages
{


    public static function create_pages()
    {


        $pages = array(

            'register' => array(

                'title'   => 'Register',

                'content' => '[rl_register]'

            ),



            'customer-dashboard' => array(

                'title'   => 'Customer Dashboard',

                'content' => '[rl_home]'

            ),



            'restaurant-dashboard' => array(

                'title'   => 'Restaurant Dashboard',

                'content' => '[rl_restaurant_dashboard]'

            ),


            'lost-password' => array(

                'title'   => 'Lost Password',

                'content' => '[rl_lost_password]'

            )

        );



        $created_pages = get_option(
            'rl_pages'
        );


        if(!is_array($created_pages)){

            $created_pages = array();

        }


        foreach($pages as $slug => $page){


            /*
            =========================
            CHECK EXISTING PLUGIN PAGE
            =========================
            */


            if(
                isset($created_pages[$slug])
                &&
                get_post(
                    $created_pages[$slug]
                )
            ){

                continue;

            }


            /*
            =========================
            CHECK URL EXISTS
            =========================
            */


            $existing = get_page_by_path(
                $slug
            );


            if($existing){


                $created_pages[$slug] =
                $existing->ID;


                continue;


            }


            /*
            =========================
            CREATE PAGE
            =========================
            */


            $page_id = wp_insert_post(

                array(

                    'post_title' => $page['title'],

                    'post_name' => $slug,

                    'post_content' => $page['content'],

                    'post_status' => 'publish',

                    'post_type' => 'page'

                ),

                true

            );


            if(
                !is_wp_error($page_id)
            ){


                $created_pages[$slug] =
                $page_id;


            }



        }


        update_option(

            'rl_pages',

            $created_pages

        );


    }



}