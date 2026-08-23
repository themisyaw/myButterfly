<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A physical branch belonging to a brand. The brand's manager scans/
 * adds points at any of the brand's locations; the wallet scope that
 * gets credited (brand-wide or location-only) is resolved via the
 * parent brand's pool_points/pool_entries flags — see get_with_brand().
 */
class RL_Locations
{

    public static function create($data)
    {
        global $wpdb;

        $brand_id = absint($data['brand_id'] ?? 0);
        $name     = sanitize_text_field($data['name'] ?? '');
        $address  = sanitize_text_field($data['address'] ?? '');
        $lat      = isset($data['lat']) && $data['lat'] !== '' ? floatval($data['lat']) : null;
        $lng      = isset($data['lng']) && $data['lng'] !== '' ? floatval($data['lng']) : null;

        if (!$brand_id || empty($name)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_locations';

        $inserted = $wpdb->insert(
            $table,
            array(
                'brand_id' => $brand_id,
                'name'     => $name,
                'address'  => $address,
                'lat'      => $lat,
                'lng'      => $lng,
                'status'   => 'active',
            ),
            array('%d', '%s', '%s', '%f', '%f', '%s')
        );

        if (!$inserted) {
            return false;
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id || empty($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_locations';

        $clean  = array();
        $format = array();

        if (isset($data['name'])) {
            $clean['name'] = sanitize_text_field($data['name']);
            $format[]       = '%s';
        }

        if (isset($data['address'])) {
            $clean['address'] = sanitize_text_field($data['address']);
            $format[]           = '%s';
        }

        if (isset($data['lat'])) {
            $clean['lat'] = $data['lat'] === '' ? null : floatval($data['lat']);
            $format[]      = '%f';
        }

        if (isset($data['lng'])) {
            $clean['lng'] = $data['lng'] === '' ? null : floatval($data['lng']);
            $format[]      = '%f';
        }

        if (isset($data['status'])) {
            $clean['status'] = sanitize_text_field($data['status']);
            $format[]          = '%s';
        }

        if (empty($clean)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $clean,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    /**
     * Free geocoding via OpenStreetMap's Nominatim — no API key needed.
     * Returns array('lat'=>..,'lng'=>..) or false. The manager can
     * still override the result manually in the location form.
     */
    public static function geocode_address($address)
    {
        $address = trim(sanitize_text_field($address));

        if (empty($address)) {
            return false;
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($address);

        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'headers' => array(
                'User-Agent' => 'ButterflyLoyaltyPlugin/1.0 (' . home_url() . ')',
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !isset($body[0]['lat'], $body[0]['lon'])) {
            return false;
        }

        return array(
            'lat' => floatval($body[0]['lat']),
            'lng' => floatval($body[0]['lon']),
        );
    }

    public static function get($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $table = $wpdb->prefix . 'rl_locations';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            )
        );
    }

    /**
     * Location row joined with its brand's pooling flags — the
     * combination everything else in the plugin actually needs.
     */
    public static function get_with_brand($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $locations = $wpdb->prefix . 'rl_locations';
        $brands    = $wpdb->prefix . 'rl_brands';

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT
                    l.*,
                    b.name AS brand_name,
                    b.logo AS brand_logo,
                    b.pool_points,
                    b.pool_entries,
                    b.status AS brand_status
                FROM {$locations} l
                INNER JOIN {$brands} b ON b.id = l.brand_id
                WHERE l.id = %d
                LIMIT 1
                ",
                $id
            )
        );
    }

    public static function get_all_by_brand($brand_id, $active_only = false)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id) {
            return array();
        }

        $table = $wpdb->prefix . 'rl_locations';

        if ($active_only) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE brand_id = %d AND status = 'active' ORDER BY name ASC",
                    $brand_id
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE brand_id = %d ORDER BY name ASC",
                $brand_id
            )
        );
    }

    /**
     * All active locations across all active brands, for the public
     * map/directory.
     */
    public static function get_all_public()
    {
        global $wpdb;

        $locations = $wpdb->prefix . 'rl_locations';
        $brands    = $wpdb->prefix . 'rl_brands';

        return $wpdb->get_results(
            "
            SELECT
                l.*,
                b.name AS brand_name,
                b.logo AS brand_logo,
                b.description AS brand_description
            FROM {$locations} l
            INNER JOIN {$brands} b ON b.id = l.brand_id
            WHERE l.status = 'active' AND b.status = 'active'
            ORDER BY b.name ASC, l.name ASC
            "
        );
    }

    public static function delete($id)
    {
        global $wpdb;

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_locations';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
}
