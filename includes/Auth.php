<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Auth
{
    const OPTION_KEY = 'sg_current_user'; // stores array: ['name'=>..., 'email'=>..., 'picture'=>...]

    public static function boot()
    {
        // listen for login/logout actions coming from admin page
        \add_action('admin_init', [__CLASS__, 'maybe_handle_auth_actions']);
        // OAuth callback endpoints (works for logged-in or not)
        \add_action('admin_post_sg_oauth_callback', [__CLASS__, 'oauth_callback']);
        \add_action('admin_post_nopriv_sg_oauth_callback', [__CLASS__, 'oauth_callback']);
    }

    public static function is_logged_in(): bool
    {
        $user = \get_option(self::OPTION_KEY);
        return is_array($user) && !empty($user['email']);
    }

    public static function current_user(): ?array
    {
        $user = \get_option(self::OPTION_KEY);
        return is_array($user) ? $user : null;
    }

    public static function maybe_handle_auth_actions()
    {
        if (!is_admin()) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'scripgrab') return;

        // logout
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'logout') {
            \delete_option(self::OPTION_KEY);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // switch account = same as logout for now
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'switch') {
            \delete_option(self::OPTION_KEY);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // start Google OAuth
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'oauth_start') {
            \check_admin_referer('sg_oauth_start');
            self::oauth_start();
            exit;
        }

        // fake login (this is what we'll replace with real Google OAuth later)
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'mock_login') {
            $fake = [
                'name'    => 'ScrinGrab User',
                'email'   => 'user@example.com',
                'picture' => 'https://www.gravatar.com/avatar/' . md5('user@example.com') . '?s=80&d=identicon',
            ];
            \update_option(self::OPTION_KEY, $fake);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // later:
        // if sg_action === 'oauth_callback' -> verify Google token -> save -> redirect
    }

    protected static function oauth_start(): void
    {
        $client_id = defined('SG_GOOGLE_CLIENT_ID') ? constant('SG_GOOGLE_CLIENT_ID') : '';
        $client_secret = defined('SG_GOOGLE_CLIENT_SECRET') ? constant('SG_GOOGLE_CLIENT_SECRET') : '';
        if (!$client_id || !$client_secret) {
            \wp_safe_redirect(\add_query_arg([
                'page' => 'scripgrab',
                'sg_notice' => 'oauth_missing_config',
            ], \admin_url('admin.php')));
            exit;
        }

        $redirect_uri = \admin_url('admin-post.php?action=sg_oauth_callback');
        $state = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(substr(md5(uniqid('', true)), 0, 16));
        $nonce = function_exists('random_bytes') ? bin2hex(random_bytes(12)) : wp_generate_password(24, false, false);
        set_transient('sg_oauth_state_' . $state, [
            'nonce' => $nonce,
            'time'  => time(),
        ], 10 * MINUTE_IN_SECONDS);

        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'include_granted_scopes' => 'true',
            'prompt'        => 'select_account',
            'state'         => $state,
            'nonce'         => $nonce,
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        \wp_redirect($url);
        exit;
    }

    public static function oauth_callback(): void
    {
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        if ($error) {
            self::end_with_notice('oauth_error_' . $error);
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        if (!$code || !$state) {
            self::end_with_notice('oauth_invalid_callback');
        }

        $stored = get_transient('sg_oauth_state_' . $state);
        if (!$stored || empty($stored['nonce'])) {
            self::end_with_notice('oauth_state_mismatch');
        }
        delete_transient('sg_oauth_state_' . $state);

        $client_id = defined('SG_GOOGLE_CLIENT_ID') ? constant('SG_GOOGLE_CLIENT_ID') : '';
        $client_secret = defined('SG_GOOGLE_CLIENT_SECRET') ? constant('SG_GOOGLE_CLIENT_SECRET') : '';
        $redirect_uri = \admin_url('admin-post.php?action=sg_oauth_callback');

        $resp = \wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
        ]);
        if (\is_wp_error($resp)) {
            self::end_with_notice('oauth_http_error');
        }

        $data = json_decode((string) \wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || empty($data['id_token'])) {
            self::end_with_notice('oauth_no_id_token');
        }

        $claims = self::decode_jwt_claims($data['id_token']);
        if (!$claims) {
            self::end_with_notice('oauth_bad_id_token');
        }

        $iss = isset($claims['iss']) ? (string) $claims['iss'] : '';
        $aud = isset($claims['aud']) ? (string) $claims['aud'] : '';
        $exp = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        $nonce = isset($claims['nonce']) ? (string) $claims['nonce'] : '';

        if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            self::end_with_notice('oauth_bad_issuer');
        }
        if ($aud !== $client_id) {
            self::end_with_notice('oauth_bad_audience');
        }
        if ($exp && $exp < time() - 60) {
            self::end_with_notice('oauth_token_expired');
        }
        if ($nonce !== (string) $stored['nonce']) {
            self::end_with_notice('oauth_nonce_mismatch');
        }

        $email = isset($claims['email']) ? sanitize_email($claims['email']) : '';
        $name = isset($claims['name']) ? sanitize_text_field($claims['name']) : '';
        $picture = isset($claims['picture']) ? esc_url_raw($claims['picture']) : '';
        if (!$name && $email) $name = $email;
        if (!$email) {
            self::end_with_notice('oauth_no_email');
        }

        \update_option(self::OPTION_KEY, [
            'name'    => $name,
            'email'   => $email,
            'picture' => $picture,
        ]);

        \wp_safe_redirect(\admin_url('admin.php?page=scripgrab'));
        exit;
    }

    protected static function decode_jwt_claims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        $payload = strtr($parts[1], '-_', '+/');
        $pad = strlen($payload) % 4;
        if ($pad) $payload .= str_repeat('=', 4 - $pad);
        $json = base64_decode($payload);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    protected static function end_with_notice(string $key): void
    {
        $url = \add_query_arg([
            'page'      => 'scripgrab',
            'sg_notice' => $key,
        ], \admin_url('admin.php'));
        \wp_safe_redirect($url);
        exit;
    }
}


