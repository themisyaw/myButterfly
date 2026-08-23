<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menu categories are scoped per brand (not per location) — a chain's
 * manager defines a category set once ("Drinks", "Mains", ...) and
 * every location's own reward menu can reuse it.
 */
class RL_Redeem_Categories
{

    public static function create($brand_id, $name, $sort_order = 0)
    {
        global $wpdb;

        $brand_id   = absint($brand_id);
        $name       = sanitize_text_field($name);
        $sort_order = intval($sort_order);

        if (!$brand_id || empty($name)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_redeem_categories';

        $inserted = $wpdb->insert(
            $table,
            array(
                'brand_id'   => $brand_id,
                'name'       => $name,
                'sort_order' => $sort_order,
            ),
            array('%d', '%s', '%d')
        );

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;
    }

    public static function get($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_redeem_categories';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
        );
    }

    public static function get_all_by_brand($brand_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_redeem_categories';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE brand_id = %d ORDER BY sort_order ASC, name ASC",
                $brand_id
            )
        );
    }

    public static function update($id, $data)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id || empty($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_redeem_categories';

        $clean  = array();
        $format = array();

        if (isset($data['name'])) {
            $clean['name'] = sanitize_text_field($data['name']);
            $format[]      = '%s';
        }

        if (isset($data['sort_order'])) {
            $clean['sort_order'] = intval($data['sort_order']);
            $format[]            = '%d';
        }

        if (empty($clean)) {
            return false;
        }

        return $wpdb->update($table, $clean, array('id' => $id), $format, array('%d'));
    }

    public static function delete($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $categories_table = $wpdb->prefix . 'rl_redeem_categories';
        $items_table       = $wpdb->prefix . 'rl_redeem_items';

        // Uncategorize any items pointing at this category rather than
        // leaving a dangling category_id behind. wpdb->update() can't
        // bind a literal NULL, so this needs a direct query.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$items_table} SET category_id = NULL WHERE category_id = %d",
                $id
            )
        );

        return $wpdb->delete($categories_table, array('id' => $id), array('%d'));
    }
}
