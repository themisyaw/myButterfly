<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends Web Push notifications directly against the browser push
 * protocol — VAPID application-server identification (RFC 8292) and
 * aes128gcm message encryption (RFC 8291 / RFC 8188) — using only
 * PHP's built-in openssl extension. No Composer / third-party
 * library, so it works unmodified on shared hosting (Hostinger)
 * exactly as it does in LocalWP.
 *
 * Requires PHP 8.1+ (openssl_pkey_derive(), used for the ECDH step,
 * was added in 8.1). is_supported() checks this before anything
 * tries to send, so an older host degrades gracefully to "push
 * unavailable" instead of a fatal error.
 *
 * This is a hand-rolled implementation of a real crypto protocol —
 * it has been written carefully against the RFCs, but it has NOT
 * been exercised against a live push service by an automated test.
 * Use the "send test push" action in the admin Notifications screen
 * to confirm delivery to your own browser before trusting this for
 * a real broadcast or a winner pick.
 */
class RL_Web_Push
{

    /**
     * Set whenever generate_vapid_keys() or send() fails, so the
     * admin UI can show the real reason instead of a generic
     * "didn't work" — openssl failures are otherwise silent.
     */
    private static $last_error = '';

    public static function last_error()
    {
        return self::$last_error;
    }

    /**
     * A one-line environment snapshot appended to failure messages —
     * PHP/OpenSSL library versions and whether our bundled config
     * file is actually where the code thinks it is — so the next
     * failure (if there is one) points at the real cause instead of
     * requiring another guess-and-check round trip.
     */
    public static function diagnostic_info()
    {
        $bundled = RL_PLUGIN_PATH . 'includes/openssl.cnf';

        return sprintf(
            'PHP %s | OpenSSL %s | bundled config: %s (%s) | OPENSSL_CONF env: %s | modules dir: %s',
            PHP_VERSION,
            defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'unknown',
            $bundled,
            file_exists($bundled) ? 'found, ' . filesize($bundled) . ' bytes' : 'MISSING',
            getenv('OPENSSL_CONF') ?: '(not set)',
            getenv('OPENSSL_MODULES') ?: '(not set)'
        );
    }

    private static function capture_openssl_error($fallback)
    {
        $messages = array();

        while ($msg = openssl_error_string()) {
            $messages[] = $msg;
        }

        self::$last_error = !empty($messages) ? implode(' | ', $messages) : $fallback;
    }

    /**
     * Some openssl_* functions (openssl_sign(), openssl_pkey_derive())
     * don't accept a per-call 'config' option the way openssl_pkey_new()
     * does — the only way to point THOSE at a valid config file is the
     * OPENSSL_CONF environment variable, which OpenSSL's library reads
     * directly. Set once per request, before any openssl_* call, so
     * every call in this class benefits, not just the ones that take
     * an explicit 'config' array key.
     */
    private static function ensure_openssl_env()
    {
        static $checked = false;

        if ($checked) {
            return;
        }

        $checked = true;

        $existing = getenv('OPENSSL_CONF');

        if ($existing && file_exists($existing)) {
            return;
        }

        $bundled = RL_PLUGIN_PATH . 'includes/openssl.cnf';

        if (file_exists($bundled)) {
            putenv('OPENSSL_CONF=' . $bundled);
        }
    }

    /*
    =========================
    SUPPORT / KEY STATE
    =========================
    */

    public static function is_supported()
    {
        return extension_loaded('openssl') && function_exists('openssl_pkey_derive');
    }

    public static function has_keys()
    {
        return (bool) get_option('rl_vapid_public_key') && (bool) get_option('rl_vapid_private_key');
    }

    public static function public_key()
    {
        return get_option('rl_vapid_public_key', '');
    }

    /**
     * Generates a fresh VAPID (application server identification)
     * EC keypair on the P-256 curve and stores it as base64url
     * options. VAPID keys identify the SERVER, not the subscriber —
     * existing subscriptions keep working if these are regenerated,
     * though that's not something to do casually once real
     * subscribers exist.
     */
    public static function generate_vapid_keys()
    {
        self::$last_error = '';
        self::ensure_openssl_env();

        if (!self::is_supported()) {
            self::$last_error = 'PHP 8.1+ with the openssl extension is required.';
            return false;
        }

        // Some local PHP/OpenSSL setups (this is a known issue on
        // Windows dev environments, which is what LocalWP runs on)
        // can't find a valid openssl.cnf and fail EC key generation
        // as a result. Explicitly pointing at PHP's own configured
        // openssl.cafile/capath — or, failing that, common bundled
        // config locations — works around it without needing the
        // exact path hardcoded.
        $config_args = self::openssl_config_args();

        $res = openssl_pkey_new($config_args + array(
            'curve_name'        => 'prime256v1',
            'private_key_type'  => OPENSSL_KEYTYPE_EC,
            // Meaningless for an EC key (the curve alone determines
            // key strength), but PHP validates this field regardless
            // of key type and rejects the unset default of 0 — a
            // known PHP bug (php-src #13214 / #21083). Any value at
            // or above the "must be at least 384 bits" floor from
            // that validation satisfies it without affecting the
            // actual generated key.
            'private_key_bits'  => 384,
        ));

        if (!$res) {
            self::capture_openssl_error('openssl_pkey_new() failed for an unknown reason — often means PHP/OpenSSL on this server can\'t locate a valid openssl.cnf (common on Windows local dev environments like LocalWP).');
            self::$last_error .= ' [' . self::diagnostic_info() . ']';
            return false;
        }

        $details = openssl_pkey_get_details($res);

        if (empty($details['ec']['x']) || empty($details['ec']['y']) || empty($details['ec']['d'])) {
            self::capture_openssl_error('The generated key is missing its EC point/private scalar details.');
            self::$last_error .= ' [' . self::diagnostic_info() . ']';
            return false;
        }

        $public_raw  = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $private_raw = $details['ec']['d'];

        update_option('rl_vapid_public_key', self::base64url_encode($public_raw));
        update_option('rl_vapid_private_key', self::base64url_encode($private_raw));

        return true;
    }

    /**
     * openssl.cnf location for openssl_pkey_new()'s 'config' option.
     * Prefers the host's own OPENSSL_CONF if set, but always falls
     * back to a minimal config bundled directly with this plugin
     * (includes/openssl.cnf) — confirmed necessary on this project's
     * LocalWP/Windows environment, which errors with "configuration
     * file routines::no such file" otherwise. Bundling our own means
     * this doesn't depend on guessing where the host's config lives,
     * and works the same way on Hostinger at deploy time.
     */
    private static function openssl_config_args()
    {
        $env = getenv('OPENSSL_CONF');

        if ($env && file_exists($env)) {
            return array('config' => $env);
        }

        $bundled = RL_PLUGIN_PATH . 'includes/openssl.cnf';

        if (file_exists($bundled)) {
            return array('config' => $bundled);
        }

        return array();
    }

    /*
    =========================
    SEND
    =========================
    */

    /**
     * Sends one push message to one subscription row (needs
     * ->endpoint, ->p256dh, ->auth — the shape returned by
     * RL_Push_Subscriptions). $payload is a plain array, JSON
     * encoded and encrypted here before it ever leaves the server.
     *
     * Returns true on success, false on a local/config failure, or
     * the HTTP status code (int) the push service responded with —
     * callers use that to prune subscriptions that come back 404/410
     * (the browser has unsubscribed / the endpoint is dead).
     */
    public static function send($subscription, $payload)
    {
        self::$last_error = '';
        self::ensure_openssl_env();

        if (!self::is_supported() || !self::has_keys()) {
            return false;
        }

        $endpoint = $subscription->endpoint;

        $ua_public_raw = self::base64url_decode($subscription->p256dh);
        $auth_secret   = self::base64url_decode($subscription->auth);

        if (strlen($ua_public_raw) !== 65 || strlen($auth_secret) !== 16) {
            return false;
        }

        $body = self::encrypt_payload(wp_json_encode($payload), $ua_public_raw, $auth_secret);

        if (!$body) {
            return false;
        }

        $audience = wp_parse_url($endpoint, PHP_URL_SCHEME) . '://' . wp_parse_url($endpoint, PHP_URL_HOST);

        $jwt = self::build_vapid_jwt($audience);

        if (!$jwt) {
            return false;
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization'    => 'vapid t=' . $jwt . ', k=' . self::public_key(),
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => '86400',
            ),
            'body'    => $body,
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status = intval(wp_remote_retrieve_response_code($response));

        return ($status >= 200 && $status < 300) ? true : $status;
    }

    /*
    =========================
    VAPID JWT (RFC 8292)
    Proves to the push service which application server is sending —
    a JWT signed with our VAPID private key, ES256 (ECDSA P-256 /
    SHA-256), separate from the per-message encryption keys below.
    =========================
    */

    private static function build_vapid_jwt($audience)
    {
        $private_raw = self::base64url_decode(get_option('rl_vapid_private_key', ''));
        $public_raw  = self::base64url_decode(get_option('rl_vapid_public_key', ''));

        if (strlen($private_raw) !== 32) {
            return false;
        }

        $subject = get_option('rl_vapid_subject');

        if (empty($subject)) {
            $subject = 'mailto:' . get_option('admin_email');
        }

        $header  = self::base64url_encode(wp_json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));
        $payload = self::base64url_encode(wp_json_encode(array(
            'aud' => $audience,
            'exp' => time() + (12 * HOUR_IN_SECONDS),
            'sub' => $subject,
        )));

        $signing_input = $header . '.' . $payload;

        $pem = self::ec_private_key_to_pem($private_raw, $public_raw ?: null);

        $der_signature = '';
        $signed = openssl_sign($signing_input, $der_signature, $pem, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            return false;
        }

        $raw_signature = self::der_to_raw_signature($der_signature);

        if (!$raw_signature) {
            return false;
        }

        return $signing_input . '.' . self::base64url_encode($raw_signature);
    }

    /*
    =========================
    PAYLOAD ENCRYPTION (RFC 8291 message encryption over the
    RFC 8188 "aes128gcm" content-coding)
    =========================
    */

    private static function encrypt_payload($plaintext, $ua_public_raw, $auth_secret)
    {
        // A fresh, one-time EC keypair for THIS message — distinct
        // from the VAPID identity key above.
        $res = openssl_pkey_new(self::openssl_config_args() + array(
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ));

        if (!$res) {
            self::capture_openssl_error('openssl_pkey_new() failed while encrypting a message.');
            return false;
        }

        $details = openssl_pkey_get_details($res);

        if (empty($details['ec']['x']) || empty($details['ec']['y'])) {
            return false;
        }

        $as_public_raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        $shared_secret = openssl_pkey_derive(self::ec_public_key_to_pem($ua_public_raw), $res, 32);

        if (!$shared_secret) {
            return false;
        }

        $salt = random_bytes(16);

        // HKDF (RFC 5869), split across two extract/expand passes
        // per RFC 8291 §3.4: first derive an "IKM" bound to both the
        // ECDH secret and the subscription's private auth_secret,
        // then run a normal HKDF over that IKM with the per-message
        // salt to get the actual content-encryption key and nonce.
        $prk_key  = hash_hmac('sha256', $shared_secret, $auth_secret, true);
        $key_info = "WebPush: info\x00" . $ua_public_raw . $as_public_raw;
        $ikm      = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);

        $prk = hash_hmac('sha256', $ikm, $salt, true);

        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);

        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        // Single-record body: payload + one 0x02 delimiter byte
        // (RFC 8188 padding delimiter for "no padding, last record").
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext . "\x02",
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            return false;
        }

        // aes128gcm record header (RFC 8188 §2.1): salt(16) +
        // record_size(4, big-endian) + key_id length(1) + key_id
        // (our ephemeral public key, so the receiver can redo ECDH).
        $header = $salt . pack('N', 4096) . chr(strlen($as_public_raw)) . $as_public_raw;

        return $header . $ciphertext . $tag;
    }

    /*
    =========================
    RAW EC KEY <-> PEM
    openssl_sign()/openssl_pkey_derive() need PEM (or a key
    resource), not the raw fixed-width points/scalars this protocol
    passes around everywhere else — these wrap/unwrap the minimal
    DER structures by hand so no other library is needed.
    =========================
    */

    private static function ec_public_key_to_pem($raw_point)
    {
        // Fixed 21-byte AlgorithmIdentifier DER for
        // {id-ecPublicKey, prime256v1} — identical for every P-256
        // key, only the point itself (below) ever changes.
        $alg_id = hex2bin('301306072a8648ce3d020106082a8648ce3d030107');

        $bit_string = "\x03" . self::der_length(strlen($raw_point) + 1) . "\x00" . $raw_point;

        $body = $alg_id . $bit_string;
        $der  = "\x30" . self::der_length(strlen($body)) . $body;

        return self::der_to_pem($der, 'PUBLIC KEY');
    }

    private static function ec_private_key_to_pem($raw_d, $raw_public = null)
    {
        // RFC 5915 ECPrivateKey structure. The [1] publicKey field is
        // technically OPTIONAL, but some OpenSSL builds parse EC
        // private keys more reliably with it present, so it's
        // included whenever the caller has the public point handy.
        $version    = "\x02\x01\x01";
        $priv_octet = "\x04" . self::der_length(strlen($raw_d)) . $raw_d;
        $params     = "\xa0" . self::der_length(10) . hex2bin('06082a8648ce3d030107');

        $body = $version . $priv_octet . $params;

        if ($raw_public) {
            $bit_string = "\x03" . self::der_length(strlen($raw_public) + 1) . "\x00" . $raw_public;
            $body .= "\xa1" . self::der_length(strlen($bit_string)) . $bit_string;
        }

        $der = "\x30" . self::der_length(strlen($body)) . $body;

        return self::der_to_pem($der, 'EC PRIVATE KEY');
    }

    private static function der_length($len)
    {
        if ($len < 128) {
            return chr($len);
        }

        $bytes = ltrim(pack('N', $len), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function der_to_pem($der, $label)
    {
        return "-----BEGIN {$label}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$label}-----\n";
    }

    /**
     * openssl_sign() on an EC key returns a DER-encoded ECDSA
     * signature (SEQUENCE of two INTEGERs, r and s). JWS's ES256
     * instead needs the raw fixed-width r||s (32 bytes each,
     * zero-padded / stripped of DER's sign-guard byte) — this
     * converts between the two.
     */
    private static function der_to_raw_signature($der)
    {
        if (strlen($der) < 8 || ord($der[0]) !== 0x30) {
            return false;
        }

        $offset = 2;

        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += (ord($der[1]) & 0x7f);
        }

        if (!isset($der[$offset]) || ord($der[$offset]) !== 0x02) {
            return false;
        }

        $offset++;
        $r_len = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $r_len);
        $offset += $r_len;

        if (!isset($der[$offset]) || ord($der[$offset]) !== 0x02) {
            return false;
        }

        $offset++;
        $s_len = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $s_len);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /*
    =========================
    BASE64URL
    =========================
    */

    public static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode($data)
    {
        $data = strtr($data, '-_', '+/');

        $pad = strlen($data) % 4;

        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($data);
    }
}
