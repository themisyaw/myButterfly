<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-day opening hours for a brand. Stored as one row per day of
 * the week (0 = Sunday .. 6 = Saturday, matching PHP's date('w')),
 * so a restaurant can have different hours each day, or be closed
 * entirely on specific days — a single flat opening/closing pair
 * couldn't express either.
 */
class RL_Brand_Hours
{
    const DAY_LABELS = array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    );

    // Display order for admin forms and the public "Hours" list —
    // Monday first, matching how most people think about a business
    // week. Storage itself uses PHP's date('w') convention (0=Sunday).
    const DISPLAY_ORDER = array(1, 2, 3, 4, 5, 6, 0);

    private static function sanitize_time($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value)) {
            return substr($value, 0, 5) . ':00';
        }

        return null;
    }

    /**
     * Always returns exactly 7 rows keyed by day_of_week (0-6) — a
     * day the brand hasn't configured yet comes back as a default
     * "open, no hours set" row so callers/forms never have to treat
     * a missing day as a special case.
     */
    public static function get_all_for_brand($brand_id)
    {
        global $wpdb;

        $brand_id = absint($brand_id);
        $by_day   = array();

        if ($brand_id) {

            $table = $wpdb->prefix . 'rl_brand_hours';

            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE brand_id = %d", $brand_id)
            );

            foreach ($rows as $row) {
                $by_day[intval($row->day_of_week)] = $row;
            }
        }

        $result = array();

        for ($day = 0; $day <= 6; $day++) {

            if (isset($by_day[$day])) {
                $result[$day] = $by_day[$day];
                continue;
            }

            $result[$day] = (object) array(
                'brand_id'     => $brand_id,
                'day_of_week'  => $day,
                'is_closed'    => 0,
                'opening_time' => null,
                'closing_time' => null,
            );
        }

        return $result;
    }

    public static function get_hours_for_day($brand_id, $day_of_week)
    {
        $all         = self::get_all_for_brand($brand_id);
        $day_of_week = intval($day_of_week);

        return $all[$day_of_week] ?? null;
    }

    /**
     * Whether any day has been configured at all — lets callers skip
     * showing an "Hours" section entirely for a brand that's never
     * set any.
     */
    public static function has_any_hours($brand_id)
    {
        foreach (self::get_all_for_brand($brand_id) as $row) {
            if (!empty($row->is_closed) || !empty($row->opening_time) || !empty($row->closing_time)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replaces a brand's whole week in one go. $days is keyed by
     * day_of_week (0-6), each value an array with 'is_closed'
     * (bool-ish), 'opening_time' and 'closing_time' ("HH:MM" or '').
     * A day left fully blank and not marked closed is simply not
     * stored, so it falls back to the "unconfigured" default.
     */
    public static function save_for_brand($brand_id, $days)
    {
        global $wpdb;

        $brand_id = absint($brand_id);

        if (!$brand_id || !is_array($days)) {
            return false;
        }

        $table = $wpdb->prefix . 'rl_brand_hours';

        $wpdb->delete($table, array('brand_id' => $brand_id), array('%d'));

        for ($day = 0; $day <= 6; $day++) {

            $entry     = $days[$day] ?? array();
            $is_closed = !empty($entry['is_closed']);

            // A closed day never carries stale times.
            $opening_time = $is_closed ? null : self::sanitize_time($entry['opening_time'] ?? '');
            $closing_time = $is_closed ? null : self::sanitize_time($entry['closing_time'] ?? '');

            if (!$is_closed && $opening_time === null && $closing_time === null) {
                continue;
            }

            $fields = array(
                'brand_id'    => $brand_id,
                'day_of_week' => $day,
                'is_closed'   => $is_closed ? 1 : 0,
            );

            $formats = array('%d', '%d', '%d');

            if ($opening_time !== null) {
                $fields['opening_time'] = $opening_time;
                $formats[]              = '%s';
            }

            if ($closing_time !== null) {
                $fields['closing_time'] = $closing_time;
                $formats[]              = '%s';
            }

            $wpdb->insert($table, $fields, $formats);
        }

        return true;
    }

    /**
     * "9:00 AM – 10:00 PM", or '' if either side is missing.
     */
    public static function format_range($opening_time, $closing_time)
    {
        if (empty($opening_time) || empty($closing_time)) {
            return '';
        }

        $opening = strtotime($opening_time);
        $closing = strtotime($closing_time);

        if ($opening === false || $closing === false) {
            return '';
        }

        return date('g:i A', $opening) . ' – ' . date('g:i A', $closing);
    }

    /**
     * Whether the brand is open right now, based on today's row.
     * Handles hours that cross midnight (e.g. 18:00–02:00) by
     * treating "now" as open if it's on either side of midnight
     * within the window.
     */
    public static function is_open_now($brand_id)
    {
        $today = self::get_hours_for_day($brand_id, intval(current_time('w')));

        if (!$today || !empty($today->is_closed) || empty($today->opening_time) || empty($today->closing_time)) {
            return false;
        }

        $now   = current_time('H:i:s');
        $open  = $today->opening_time;
        $close = $today->closing_time;

        if ($close > $open) {
            return ($now >= $open && $now <= $close);
        }

        return ($now >= $open || $now <= $close);
    }

    /**
     * A short status for today, for the "Open now" / "Closed" badge:
     * array('label' => ..., 'closed' => bool). Distinguishes a full
     * day off ("Closed today") from simply being outside today's
     * window right now ("Closed now").
     */
    public static function get_today_status($brand_id)
    {
        $today = self::get_hours_for_day($brand_id, intval(current_time('w')));

        if (!$today || !empty($today->is_closed)) {
            return array('label' => rl_t('hours_closed_today'), 'closed' => true);
        }

        if (empty($today->opening_time) || empty($today->closing_time)) {
            return array('label' => '', 'closed' => false);
        }

        $range = self::format_range($today->opening_time, $today->closing_time);

        if (self::is_open_now($brand_id)) {
            return array('label' => rl_t('hours_open_now') . ' · ' . $range, 'closed' => false);
        }

        return array('label' => rl_t('hours_closed_now') . ' · ' . $range, 'closed' => true);
    }
}
