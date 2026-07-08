<?php
/**
 * Plugin Name: Simple Store Security
 * Plugin URI:  https://example.com
 * Description: یک پلاگین امنیتی ساده و کاربردی برای فروشگاه‌های وردپرسی. شامل محدودسازی تلاش ورود، هدرهای امنیتی، مخفی‌سازی نسخه وردپرس، غیرفعال‌سازی XML-RPC، تغییر آدرس صفحه ورود، جلوگیری از افشای کاربران و فایروال ساده در برابر درخواست‌های مشکوک.
 * Version:     1.4.0
 * Author:      اسماعیل
 * Text Domain: simple-store-security
 * Domain Path: /languages
 *
 * Changelog:
 * 1.4.0 - ادغام با منوی مدیریت قالب رستین: در صورت فعال بودن قالب، صفحات
 *         «امنیت فروشگاه» و «گزارش امنیتی» زیرِ منوی واحد «قالب رستین» ثبت
 *         می‌شوند (از طریق ثابت RASTIN_ADMIN_MENU_SLUG). در نبود قالب، افزونه
 *         مثل قبل یک منوی سطح‌بالای مستقل می‌سازد.
 * 1.3.0 - بهینه‌سازی ثبت گزارش: جلوگیری از انفجار تعداد ردیف‌های جدول
 *         زیر حمله بات (debounce ۶۰ ثانیه‌ای + شمارنده تکرار)
 *       - جلوگیری از شمارش/لاگ/ایمیل مجدد تلاش ورود وقتی IP از قبل قفل است
 * 1.2.0 - افزودن گزارش امنیتی (ثبت تلاش‌های ناموفق، قفل‌شدن IP و درخواست‌های بلاک‌شده در جدول دیتابیس)
 *       - افزودن صفحه «گزارش امنیتی» در پیشخوان با امکان پاک کردن گزارش
 *       - پاکسازی خودکار روزانه رکوردهای قدیمی گزارش
 * 1.1.0 - افزودن قابلیت تغییر آدرس صفحه ورود (Custom Login URL)
 *       - جلوگیری از افشای لیست کاربران از طریق REST API
 *       - ارسال ایمیل هشدار به مدیر هنگام قفل شدن یک IP
 * 1.0.0 - نسخه اولیه
 */

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Store_Security {

    /**
     * نام آپشن‌های ذخیره شده در دیتابیس
     */
    const OPTION_KEY = 'sss_settings';

    /**
     * تعداد نهایی تلاش‌های مجاز ورود
     */
    const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * مدت زمان قفل شدن (ثانیه) - ۱۵ دقیقه
     */
    const LOCKOUT_DURATION = 900;

    /**
     * نام رویداد زمان‌بندی‌شده برای پاکسازی روزانه گزارش
     */
    const CRON_HOOK = 'sss_daily_cleanup';

    public function __construct() {
        // تنظیمات پیش‌فرض هنگام فعال‌سازی
        register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );

        add_action( self::CRON_HOOK, array( $this, 'cleanup_old_logs' ) );

        // مدیریت آدرس سفارشی صفحه ورود - باید خیلی زود اجرا بشه
        add_action( 'plugins_loaded', array( $this, 'handle_custom_login_url' ), 1 );

        // صفحه تنظیمات در پیشخوان
        // اولویت ۲۰ تا مطمئن شویم منوی سطح‌بالای قالب رستین (اولویت ۱۰) قبلاً ثبت شده است.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // اجرای قابلیت‌ها بر اساس تنظیمات فعال
        add_action( 'init', array( $this, 'apply_protections' ) );
    }

    /**
     * مقادیر پیش‌فرض هنگام نصب پلاگین
     */
    public function on_activate() {
        $defaults = $this->default_settings();
        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, $defaults );
        }

        $this->create_log_table();

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * هنگام غیرفعال‌سازی پلاگین، رویداد زمان‌بندی‌شده حذف می‌شود
     */
    public function on_deactivate() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * نام جدول گزارش امنیتی به همراه پیشوند دیتابیس
     */
    private function log_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sss_security_log';
    }

    /**
     * ساخت جدول گزارش امنیتی (در صورت عدم وجود)
     */
    private function create_log_table() {
        global $wpdb;
        $table_name      = $this->log_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(30) NOT NULL,
            ip_address VARCHAR(100) NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * ثبت یک رویداد امنیتی در جدول گزارش
     *
     * برای جلوگیری از پر شدن جدول زیر حملات بات (که ممکنه صدها درخواست
     * مشابه در دقیقه بفرستن)، اگه رویداد مشابهی از همون IP طی ۶۰ ثانیه
     * اخیر ثبت شده باشه، به‌جای درج ردیف جدید، همون ردیف آپدیت می‌شود
     * و شمارنده تکرار افزایش پیدا می‌کند.
     */
    private function log_event( $event_type, $ip_address, $details = '' ) {
        $settings = $this->get_settings();
        if ( empty( $settings['enable_logging'] ) ) {
            return;
        }

        global $wpdb;
        $table        = $this->log_table_name();
        $window       = 60; // ثانیه
        $debounce_key = 'sss_log_debounce_' . md5( $event_type . '|' . $ip_address );
        $existing     = get_transient( $debounce_key );

        $clean_details = sanitize_text_field( $details );

        if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
            $count = (int) $existing['count'] + 1;

            $updated_details = sprintf(
                /* translators: 1: جزئیات رویداد, 2: تعداد تکرار */
                __( '%1$s (تکرار شده: %2$d بار طی ۱ دقیقه اخیر)', 'simple-store-security' ),
                $clean_details,
                $count
            );

            $wpdb->update(
                $table,
                array(
                    'details'    => $updated_details,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $existing['id'] ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            set_transient( $debounce_key, array( 'id' => $existing['id'], 'count' => $count ), $window );
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'event_type' => sanitize_key( $event_type ),
                'ip_address' => sanitize_text_field( $ip_address ),
                'details'    => $clean_details,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        set_transient( $debounce_key, array( 'id' => $wpdb->insert_id, 'count' => 1 ), $window );
    }

    /**
     * پاکسازی روزانه رکوردهای قدیمی‌تر از مدت نگهداری تنظیم‌شده
     */
    public function cleanup_old_logs() {
        $settings = $this->get_settings();
        $days     = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 30;
        if ( $days < 1 ) {
            $days = 30;
        }

        global $wpdb;
        $table = $this->log_table_name();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
            )
        );
    }

    private function default_settings() {
        return array(
            'hide_wp_version'      => 1,
            'disable_xmlrpc'       => 1,
            'limit_login_attempts' => 1,
            'security_headers'     => 1,
            'disable_author_scan'  => 1,
            'block_suspicious_req' => 1,
            'disable_file_edit'    => 1,
            'generic_login_error'  => 1,
            'disable_rest_users'   => 1,
            'email_alert_lockout'  => 0,
            'login_slug'           => '',
            'enable_logging'       => 1,
            'log_retention_days'   => 30,
        );
    }

    /**
     * خواندن تنظیمات فعلی
     */
    private function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, $this->default_settings() );
    }

    /**
     * اعمال قابلیت‌های امنیتی بر اساس تنظیمات
     */
    public function apply_protections() {
        $settings = $this->get_settings();

        if ( ! empty( $settings['hide_wp_version'] ) ) {
            $this->hide_wp_version();
        }

        if ( ! empty( $settings['disable_xmlrpc'] ) ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
            add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
        }

        if ( ! empty( $settings['limit_login_attempts'] ) ) {
            add_filter( 'authenticate', array( $this, 'check_login_lockout' ), 30, 3 );
            add_action( 'wp_login_failed', array( $this, 'record_failed_login' ) );
            add_action( 'wp_login', array( $this, 'clear_failed_login' ), 10, 1 );
        }

        if ( ! empty( $settings['security_headers'] ) ) {
            add_action( 'send_headers', array( $this, 'add_security_headers' ) );
        }

        if ( ! empty( $settings['disable_author_scan'] ) ) {
            add_action( 'template_redirect', array( $this, 'block_author_scan' ) );
        }

        if ( ! empty( $settings['block_suspicious_req'] ) ) {
            add_action( 'init', array( $this, 'basic_firewall' ), 1 );
        }

        if ( ! empty( $settings['disable_file_edit'] ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', true );
        }

        if ( ! empty( $settings['generic_login_error'] ) ) {
            add_filter( 'login_errors', array( $this, 'generic_login_error_message' ) );
        }

        if ( ! empty( $settings['disable_rest_users'] ) ) {
            add_filter( 'rest_endpoints', array( $this, 'remove_users_rest_endpoint' ) );
        }
    }

    /* ---------------------------------------------------
     * ۱) مخفی‌سازی نسخه وردپرس از خروجی سایت
     * ------------------------------------------------- */
    private function hide_wp_version() {
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );

        // حذف نسخه از فایل‌های استایل و اسکریپت (جلوگیری از fingerprinting)
        add_filter( 'style_loader_src', array( $this, 'remove_version_query' ), 15, 1 );
        add_filter( 'script_loader_src', array( $this, 'remove_version_query' ), 15, 1 );
    }

    public function remove_version_query( $src ) {
        if ( strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    public function remove_pingback_header( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /* ---------------------------------------------------
     * ۲) محدودسازی تلاش‌های ناموفق ورود (Brute Force Protection)
     * ------------------------------------------------- */
    private function get_client_ip() {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '0.0.0.0';
    }

    public function check_login_lockout( $user, $username, $password ) {
        if ( empty( $username ) ) {
            return $user;
        }
        $ip  = $this->get_client_ip();
        $key = 'sss_lockout_' . md5( $ip );

        if ( get_transient( $key ) ) {
            return new WP_Error(
                'too_many_attempts',
                __( 'تعداد تلاش‌های ناموفق ورود شما بیش از حد مجاز بوده است. لطفاً ۱۵ دقیقه دیگر دوباره تلاش کنید.', 'simple-store-security' )
            );
        }
        return $user;
    }

    public function record_failed_login( $username ) {
        $ip           = $this->get_client_ip();
        $attempts_key = 'sss_attempts_' . md5( $ip );
        $lockout_key  = 'sss_lockout_' . md5( $ip );

        // اگه IP از قبل قفله، دیگه لازم نیست دوباره شمارش یا لاگ اضافه بشه
        // (کاربر همین الان با پیام قفل بودن مواجه شده - این فقط از رشد
        // بی‌رویه گزارش و ایمیل‌های تکراری هنگام حمله مداوم بات جلوگیری می‌کنه)
        if ( get_transient( $lockout_key ) ) {
            return;
        }

        $attempts = (int) get_transient( $attempts_key );
        $attempts++;

        if ( $attempts >= self::MAX_LOGIN_ATTEMPTS ) {
            set_transient( $lockout_key, 1, self::LOCKOUT_DURATION );
            delete_transient( $attempts_key );
            $this->log_event( 'lockout', $ip, $username );
            $this->maybe_email_lockout_alert( $ip, $username );
        } else {
            set_transient( $attempts_key, $attempts, self::LOCKOUT_DURATION );
            $this->log_event( 'failed_login', $ip, $username );
        }
    }

    public function clear_failed_login( $user_login ) {
        $ip = $this->get_client_ip();
        delete_transient( 'sss_attempts_' . md5( $ip ) );
        delete_transient( 'sss_lockout_' . md5( $ip ) );
    }

    /**
     * ارسال ایمیل هشدار به مدیر سایت هنگام قفل شدن یک IP
     */
    private function maybe_email_lockout_alert( $ip, $username ) {
        $settings = $this->get_settings();
        if ( empty( $settings['email_alert_lockout'] ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: نام سایت */
            __( '[%s] هشدار امنیتی: قفل شدن IP به دلیل تلاش‌های ناموفق ورود', 'simple-store-security' ),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: آی‌پی, 2: نام کاربری تلاش شده */
            __( "یک آی‌پی به دلیل تلاش‌های ناموفق مکرر برای ورود، به مدت ۱۵ دقیقه مسدود شد.\n\nآدرس IP: %1\$s\nنام کاربری واردشده: %2\$s\n\nاین یک ایمیل خودکار از پلاگین Simple Store Security است.", 'simple-store-security' ),
            $ip,
            $username
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * پیام عمومی خطای ورود (بدون افشای اینکه نام کاربری درست بوده یا رمز عبور)
     */
    public function generic_login_error_message() {
        return __( 'نام کاربری یا رمز عبور نادرست است.', 'simple-store-security' );
    }

    /* ---------------------------------------------------
     * ۳) هدرهای امنیتی HTTP
     * ------------------------------------------------- */
    public function add_security_headers() {
        if ( headers_sent() ) {
            return;
        }
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
    }

    /* ---------------------------------------------------
     * ۴) جلوگیری از شناسایی نام کاربری از طریق آرشیو نویسنده (?author=N)
     * ------------------------------------------------- */
    public function block_author_scan() {
        if ( is_author() && ! is_user_logged_in() ) {
            if ( isset( $_GET['author'] ) ) {
                wp_safe_redirect( home_url( '/', 'relative' ), 301 );
                exit;
            }
        }
    }

    /* ---------------------------------------------------
     * ۵) جلوگیری از افشای لیست کاربران از طریق REST API
     * ------------------------------------------------- */
    public function remove_users_rest_endpoint( $endpoints ) {
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
            unset( $endpoints['/wp/v2/users'] );
        }
        if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    }

    /* ---------------------------------------------------
     * ۶) فایروال ساده در برابر رشته‌های درخواست مشکوک
     * ------------------------------------------------- */
    public function basic_firewall() {
        // اجازه عبور به درخواست‌های پیشخوان و ووکامرس REST API
        if ( is_admin() ) {
            return;
        }

        $suspicious_patterns = array(
            'union select',
            'base64_decode',
            '<script',
            '../../',
            'eval(',
            'etc/passwd',
            'wp-config.php',
        );

        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $query_string = isset( $_SERVER['QUERY_STRING'] ) ? strtolower( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
        $combined     = $request_uri . ' ' . $query_string;

        foreach ( $suspicious_patterns as $pattern ) {
            if ( strpos( $combined, $pattern ) !== false ) {
                $this->log_event( 'blocked_request', $this->get_client_ip(), $request_uri );
                wp_die(
                    esc_html__( 'درخواست شما به دلایل امنیتی مسدود شد.', 'simple-store-security' ),
                    esc_html__( 'درخواست مسدود شد', 'simple-store-security' ),
                    array( 'response' => 403 )
                );
            }
        }
    }

    /* ---------------------------------------------------
     * ۷) تغییر آدرس صفحه ورود (Custom Login URL)
     * ------------------------------------------------- */
    public function handle_custom_login_url() {
        $settings = $this->get_settings();
        $slug     = isset( $settings['login_slug'] ) ? trim( $settings['login_slug'] ) : '';

        // اگه اسلاگ سفارشی تنظیم نشده، این قابلیت غیرفعاله
        if ( empty( $slug ) ) {
            return;
        }

        // اجازه به درخواست‌های ضروری وردپرس (کرون، اجاکس و REST) بدون تغییر
        if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
             ( defined( 'DOING_CRON' ) && DOING_CRON ) ||
             ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $request_path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

        // اگه کاربر آدرس اختصاصی رو باز کرده، صفحه ورود واقعی رو نمایش بده
        if ( $request_path === $slug ) {
            define( 'SSS_CUSTOM_LOGIN_ALLOWED', true );
            require ABSPATH . 'wp-login.php';
            exit;
        }

        // مسدودسازی دسترسی مستقیم به wp-login.php برای کاربران وارد نشده
        $is_login_file = ( false !== strpos( $request_path, 'wp-login.php' ) );
        if ( $is_login_file && ! defined( 'SSS_CUSTOM_LOGIN_ALLOWED' ) ) {
            // اگه کاربر از قبل وارد شده (مثلاً برای خروج)، اجازه بده
            if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
                return;
            }
            status_header( 404 );
            nocache_headers();
            wp_die(
                esc_html__( 'صفحه مورد نظر یافت نشد.', 'simple-store-security' ),
                esc_html__( 'یافت نشد', 'simple-store-security' ),
                array( 'response' => 404 )
            );
        }
    }

    /* ---------------------------------------------------
     * صفحه تنظیمات در پیشخوان وردپرس
     * ------------------------------------------------- */
    /**
     * تعیین منوی والد برای صفحات افزونه.
     *
     * اگر قالب رستین فعال باشد، صفحات زیرِ منوی واحد قالب
     * (RASTIN_ADMIN_MENU_SLUG) ثبت می‌شوند؛ در غیر این صورت افزونه یک منوی
     * سطح‌بالای مستقل برای خودش می‌سازد تا مستقل هم کار کند.
     *
     * @return string اسلاگ منوی والد.
     */
    private function get_parent_menu_slug() {
        if ( defined( 'RASTIN_ADMIN_MENU_SLUG' ) ) {
            return RASTIN_ADMIN_MENU_SLUG;
        }

        // حالت مستقل: ساخت منوی سطح‌بالای اختصاصی افزونه (فقط یک‌بار).
        static $registered = false;
        if ( ! $registered ) {
            add_menu_page(
                __( 'امنیت فروشگاه', 'simple-store-security' ),
                __( 'امنیت فروشگاه', 'simple-store-security' ),
                'manage_options',
                'simple-store-security',
                array( $this, 'render_settings_page' ),
                'dashicons-shield',
                59
            );
            $registered = true;
        }
        return 'simple-store-security';
    }

    public function add_settings_page() {
        $parent = $this->get_parent_menu_slug();

        add_submenu_page(
            $parent,
            __( 'امنیت فروشگاه', 'simple-store-security' ),
            __( 'امنیت فروشگاه', 'simple-store-security' ),
            'manage_options',
            'simple-store-security',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $parent,
            __( 'گزارش امنیتی', 'simple-store-security' ),
            __( 'گزارش امنیتی', 'simple-store-security' ),
            'manage_options',
            'simple-store-security-log',
            array( $this, 'render_log_page' )
        );
    }

    public function register_settings() {
        register_setting( 'sss_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $clean = array();
        $checkbox_fields = array(
            'hide_wp_version',
            'disable_xmlrpc',
            'limit_login_attempts',
            'security_headers',
            'disable_author_scan',
            'block_suspicious_req',
            'disable_file_edit',
            'generic_login_error',
            'disable_rest_users',
            'email_alert_lockout',
            'enable_logging',
        );
        foreach ( $checkbox_fields as $field ) {
            $clean[ $field ] = ! empty( $input[ $field ] ) ? 1 : 0;
        }

        // پاکسازی اسلاگ سفارشی صفحه ورود
        $slug = isset( $input['login_slug'] ) ? sanitize_title( $input['login_slug'] ) : '';
        // جلوگیری از استفاده از کلمات رزرو شده که با هسته وردپرس تداخل داره
        $reserved = array( 'wp-admin', 'wp-login', 'wp-login.php', 'admin', 'login' );
        if ( in_array( $slug, $reserved, true ) ) {
            $slug = '';
        }
        $clean['login_slug'] = $slug;

        // مدت نگهداری گزارش (روز) - حداقل ۱ و حداکثر ۳۶۵ روز
        $retention = isset( $input['log_retention_days'] ) ? (int) $input['log_retention_days'] : 30;
        $clean['log_retention_days'] = max( 1, min( 365, $retention ) );

        return $clean;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings  = $this->get_settings();
        $login_url = ! empty( $settings['login_slug'] ) ? home_url( '/' . $settings['login_slug'] ) : '';
        ?>
        <div class="wrap" dir="rtl">
            <h1><?php esc_html_e( 'تنظیمات امنیت فروشگاه', 'simple-store-security' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'sss_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'آدرس سفارشی صفحه ورود', 'simple-store-security' ); ?></th>
                        <td>
                            <input type="text" dir="ltr" style="direction:ltr;text-align:left;" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[login_slug]" value="<?php echo esc_attr( $settings['login_slug'] ); ?>" placeholder="my-secret-login" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e( 'اگه پر بشه، آدرس اصلی wp-login.php غیرفعال می‌شه و فقط از این آدرس می‌شه وارد شد. مثال: my-secret-login', 'simple-store-security' ); ?>
                                <?php if ( $login_url ) : ?>
                                    <br /><strong><?php esc_html_e( 'آدرس فعلی ورود:', 'simple-store-security' ); ?></strong>
                                    <code dir="ltr"><?php echo esc_html( $login_url ); ?></code>
                                <?php endif; ?>
                                <br /><span style="color:#b32d2e;"><?php esc_html_e( 'هشدار: قبل از ذخیره، آدرس جدید رو جایی یادداشت کن تا از پیشخوان قفل نشی.', 'simple-store-security' ); ?></span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'مخفی‌سازی نسخه وردپرس', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hide_wp_version]" value="1" <?php checked( $settings['hide_wp_version'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'غیرفعال‌سازی XML-RPC', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_xmlrpc]" value="1" <?php checked( $settings['disable_xmlrpc'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'محدودسازی تلاش ورود (ضد Brute Force)', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[limit_login_attempts]" value="1" <?php checked( $settings['limit_login_attempts'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'پس از ۵ تلاش ناموفق، آی‌پی به مدت ۱۵ دقیقه قفل می‌شود.', 'simple-store-security' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'ایمیل هشدار هنگام قفل شدن IP', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_alert_lockout]" value="1" <?php checked( $settings['email_alert_lockout'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'به ایمیل مدیر سایت اطلاع داده می‌شود.', 'simple-store-security' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'پیام عمومی خطای ورود', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[generic_login_error]" value="1" <?php checked( $settings['generic_login_error'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'هدرهای امنیتی HTTP', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[security_headers]" value="1" <?php checked( $settings['security_headers'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'جلوگیری از شناسایی نام کاربری (Author Scan)', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_author_scan]" value="1" <?php checked( $settings['disable_author_scan'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'جلوگیری از افشای کاربران از طریق REST API', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_rest_users]" value="1" <?php checked( $settings['disable_rest_users'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'مسیر /wp-json/wp/v2/users/ که لیست کاربران و نام‌های کاربری رو نشون می‌ده، غیرفعال می‌شه.', 'simple-store-security' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'فایروال ساده (بلاک درخواست‌های مشکوک)', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[block_suspicious_req]" value="1" <?php checked( $settings['block_suspicious_req'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'غیرفعال‌سازی ویرایشگر فایل در پیشخوان', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_file_edit]" value="1" <?php checked( $settings['disable_file_edit'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'جلوگیری از ویرایش مستقیم فایل‌های افزونه و قالب از داخل پیشخوان.', 'simple-store-security' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'ثبت گزارش امنیتی', 'simple-store-security' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_logging]" value="1" <?php checked( $settings['enable_logging'], 1 ); ?> />
                                <?php esc_html_e( 'فعال', 'simple-store-security' ); ?>
                            </label>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: لینک صفحه گزارش امنیتی */
                                    esc_html__( 'تلاش‌های ناموفق ورود، قفل‌شدن IP و درخواست‌های بلاک‌شده ثبت می‌شوند. مشاهده در %s.', 'simple-store-security' ),
                                    '<a href="' . esc_url( admin_url( 'options-general.php?page=simple-store-security-log' ) ) . '">' . esc_html__( 'صفحه گزارش امنیتی', 'simple-store-security' ) . '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'مدت نگهداری گزارش (روز)', 'simple-store-security' ); ?></th>
                        <td>
                            <input type="number" min="1" max="365" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[log_retention_days]" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" class="small-text" />
                            <p class="description"><?php esc_html_e( 'رکوردهای قدیمی‌تر از این مدت هر روز به‌طور خودکار حذف می‌شوند.', 'simple-store-security' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'ذخیره تنظیمات', 'simple-store-security' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * نمایش صفحه گزارش امنیتی در پیشخوان
     */
    public function render_log_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table = $this->log_table_name();

        // پاک کردن کل گزارش در صورت درخواست مدیر
        if ( isset( $_POST['sss_clear_log'] ) && check_admin_referer( 'sss_clear_log_action', 'sss_clear_log_nonce' ) ) {
            $wpdb->query( "TRUNCATE TABLE {$table}" );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'گزارش امنیتی با موفقیت پاک شد.', 'simple-store-security' ) . '</p></div>';
        }

        $labels = array(
            'failed_login'    => __( 'تلاش ناموفق ورود', 'simple-store-security' ),
            'lockout'         => __( 'قفل شدن IP', 'simple-store-security' ),
            'blocked_request' => __( 'درخواست بلاک‌شده (فایروال)', 'simple-store-security' ),
        );

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
        ?>
        <div class="wrap" dir="rtl">
            <h1><?php esc_html_e( 'گزارش امنیتی', 'simple-store-security' ); ?></h1>
            <p><?php esc_html_e( 'آخرین ۲۰۰ رویداد امنیتی ثبت‌شده در سایت.', 'simple-store-security' ); ?></p>

            <form method="post" style="margin-bottom:15px;" onsubmit="return confirm('<?php echo esc_js( __( 'آیا از پاک کردن کامل گزارش مطمئن هستید؟', 'simple-store-security' ) ); ?>');">
                <?php wp_nonce_field( 'sss_clear_log_action', 'sss_clear_log_nonce' ); ?>
                <button type="submit" name="sss_clear_log" value="1" class="button button-secondary">
                    <?php esc_html_e( 'پاک کردن کل گزارش', 'simple-store-security' ); ?>
                </button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'زمان', 'simple-store-security' ); ?></th>
                        <th><?php esc_html_e( 'نوع رویداد', 'simple-store-security' ); ?></th>
                        <th><?php esc_html_e( 'آدرس IP', 'simple-store-security' ); ?></th>
                        <th><?php esc_html_e( 'جزئیات', 'simple-store-security' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'هنوز هیچ رویدادی ثبت نشده است.', 'simple-store-security' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td dir="ltr" style="text-align:right;"><?php echo esc_html( $row->created_at ); ?></td>
                                <td>
                                    <?php
                                    $label = isset( $labels[ $row->event_type ] ) ? $labels[ $row->event_type ] : $row->event_type;
                                    echo esc_html( $label );
                                    ?>
                                </td>
                                <td dir="ltr" style="text-align:right;"><?php echo esc_html( $row->ip_address ); ?></td>
                                <td><?php echo esc_html( $row->details ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new Simple_Store_Security();