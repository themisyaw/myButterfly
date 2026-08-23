<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reward menus are per-location: each branch defines its own
 * redeemable items, even within a chain. Items can optionally be
 * grouped under a category (categories are scoped per brand).
 */
class RL_Redeem_Items
{

    public static function create($location_id, $title, $description, $points_cost, $image = '', $category_id = null)
    {
        global $wpdb;

        $location_id = absint($location_id);
        $title       = sanitize_text_field($title);
        $description = sanitize_textarea_field($description);
        $points_cost = absint($points_cost);
        $image       = esc_url_raw($image);
        $category_id = !empty($category_id) ? absint($category_id) : null;

        if (!$location_id || empty($title) || $points_cost <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_redeem_items';

        $fields = array(
            'location_id' => $location_id,
            'title'       => $title,
            'description' => $description,
            'points_cost' => $points_cost,
            'image'       => $image,
            'active'      => 1,
        );

        $formats = array('%d', '%s', '%s', '%d', '%s', '%d');

        // Only bind category_id when it's actually set — wpdb->insert()
        // can't bind a literal NULL, and the column already defaults
        // to NULL when omitted.
        if ($category_id !== null) {
            $fields['category_id'] = $category_id;
            $formats[]             = '%d';
        }

        return $wpdb->insert($table, $fields, $formats);
    }

    public static function get_all($location_id, $active_only = false)
    {
        global $wpdb;

        $location_id = absint($location_id);

        if (!$location_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_redeem_items';

        if ($active_only) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE location_id = %d AND active = 1 ORDER BY id DESC",
                    $location_id
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE location_id = %d ORDER BY id DESC",
                $location_id
            )
        );
    }

    /**
     * A location's active menu, grouped by category for display —
     * items without a category are bucketed under an 'uncategorized'
     * key at the end. Used by the public restaurant page.
     */
    public static function get_all_grouped_by_category($location_id)
    {
        $items = self::get_all($location_id, true);

        $categories = array();
        $uncategorized = array();

        foreach ($items as $item) {

            if (empty($item->category_id)) {
                $uncategorized[] = $item;
                continue;
            }

            if (!isset($categories[$item->category_id])) {
                $category = RL_Redeem_Categories::get($item->category_id);
                $categories[$item->category_id] = array(
                    'category' => $category,
                    'items'    => array(),
                );
            }

            $categories[$item->category_id]['items'][] = $item;
        }

        // Sort groups by the category's own sort_order.
        uasort($categories, function ($a, $b) {
            $a_order = $a['category'] ? intval($a['category']->sort_order) : 0;
            $b_order = $b['category'] ? intval($b['category']->sort_order) : 0;
            return $a_order <=> $b_order;
        });

        $groups = array_values($categories);

        if (!empty($uncategorized)) {
            $groups[] = array(
                'category' => null,
                'items'    => $uncategorized,
            );
        }

        return $groups;
    }

    public static function get($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_redeem_items';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
        );
    }

    public static function update($id, $data)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id || empty($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_redeem_items';

        $clean = array();

        if (isset($data['title'])) {
            $clean['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $clean['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['points_cost'])) {
            $clean['points_cost'] = absint($data['points_cost']);
        }

        if (isset($data['image'])) {
            $clean['image'] = esc_url_raw($data['image']);
        }

        if (isset($data['active'])) {
            $clean['active'] = absint($data['active']);
        }

        if (array_key_exists('category_id', $data)) {
            $category_id = !empty($data['category_id']) ? absint($data['category_id']) : null;

            // wpdb->update() can't bind a literal NULL through the
            // normal $data array, so handle that column with a
            // direct query when it needs clearing.
            if ($category_id === null) {
                $wpdb->query(
                    $wpdb->prepare("UPDATE {$table} SET category_id = NULL WHERE id = %d", $id)
                );
            } else {
                $clean['category_id'] = $category_id;
            }
        }

        if (empty($clean)) {
            return true;
        }

        return $wpdb->update($table, $clean, array('id' => $id));
    }

    public static function delete($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_redeem_items';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
}
