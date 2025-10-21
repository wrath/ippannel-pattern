<?php
/**
 * Plugin Name: ippanel Pattern SMS (No Variable)
 * Description: ارسال پیامک پترن (الگو) بدون متغیر از طریق ippanel.com.
 * Version: 1.1
 * Author: Your Name
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// ========== بخش ۱: ایجاد صفحه تنظیمات در پیشخوان وردپرس ==========

add_action('admin_menu', 'ippanel_add_admin_menu');
add_action('admin_init', 'ippanel_settings_init');

function ippanel_add_admin_menu() {
    add_options_page(
        'تنظیمات پیامک ippanel',      // عنوان صفحه
        'پیامک ippanel',              // عنوان منو
        'manage_options',             // سطح دسترسی
        'ippanel-pattern-sms',        // شناسه صفحه
        'ippanel_options_page_html'   // تابع نمایش محتوای صفحه
    );
}

function ippanel_settings_init() {
    register_setting('ippanel_settings_group', 'ippanel_settings');

    add_settings_section(
        'ippanel_settings_section',
        'تنظیمات API پنل پیامک',
        null,
        'ippanel-pattern-sms'
    );

    add_settings_field(
        'ippanel_api_key',
        'کلید API (AccessKey)',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'api_key']
    );

    add_settings_field(
        'ippanel_originator',
        'شماره ارسال کننده (Originator)',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'originator']
    );

    add_settings_field(
        'ippanel_pattern_code',
        'کد الگو (Pattern Code)',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'pattern_code']
    );
    
    // فیلد متغیر الگو حذف شد چون پیامک ما متغیری ندارد.
}

function ippanel_text_field_render($args) {
    $options = get_option('ippanel_settings');
    $field_name = $args['name'];
    $field_value = isset($options[$field_name]) ? $options[$field_name] : '';
    ?>
    <input type="text" name="ippanel_settings[<?php echo $field_name; ?>]" value="<?php echo esc_attr($field_value); ?>" style="width: 400px;">
    <?php
}

function ippanel_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ippanel_settings_group');
            do_settings_sections('ippanel-pattern-sms');
            submit_button('ذخیره تنظیمات');
            ?>
        </form>
    </div>
    <?php
}


// ========== بخش ۲: ایجاد شورت‌کد برای نمایش فرم ==========

add_shortcode('ippanel_sms_form', 'ippanel_render_sms_form_shortcode');

function ippanel_render_sms_form_shortcode() {
    ob_start(); // برای اینکه خروجی در یک بافر ذخیره شود
    ?>
    <div id="ippanel-sms-container">
        <form id="ippanel-sms-form">
            <p>
                <label for="ippanel-phone-number">شماره تلفن:</label><br>
                <input type="tel" id="ippanel-phone-number" name="phone_number" placeholder="مثلاً: 09123456789" required style="width: 250px; padding: 8px;">
            </p>
            <p>
                <button type="submit" id="ippanel-send-btn" class="button button-primary">ارسال پیامک</button>
            </p>
            <div id="ippanel-message" style="margin-top: 15px; font-weight: bold;"></div>
            <?php wp_nonce_field('ippanel_sms_nonce_action', 'ippanel_sms_nonce'); ?>
        </form>
    </div>
    <?php
    return ob_get_clean(); // برگرداندن محتوای بافر
}


// ========== بخش ۳: ثبت و اجرای فایل JavaScript برای ارسال AJAX ==========

add_action('wp_enqueue_scripts', 'ippanel_enqueue_scripts');

function ippanel_enqueue_scripts() {
    // فقط زمانی اسکریپت را لود کن که شورت‌کد در صفحه وجود داشته باشد
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ippanel_sms_form')) {
        wp_register_script(
            'ippanel-send-sms-js',
            plugin_dir_url(__FILE__) . 'js/send-sms.js',
            array('jquery'), // وابستگی به جی‌کوئری
            '1.1',
            true // لود در فوتر
        );

        // ارسال آدرس AJAX و nonce به فایل جاوااسکریپت
        wp_localize_script(
            'ippanel-send-sms-js',
            'ippanel_ajax_obj',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ippanel_sms_nonce_action')
            )
        );

        wp_enqueue_script('ippanel-send-sms-js');
    }
}


// ========== بخش ۴: پردازش درخواست AJAX در سمت سرور (اصلاح‌شده) ==========

add_action('wp_ajax_send_ippanel_sms', 'ippanel_send_sms_callback');
add_action('wp_ajax_nopriv_send_ippanel_sms', 'ippanel_send_sms_callback');

function ippanel_send_sms_callback() {
    // ۱. بررسی امنیتی nonce
    if (!check_ajax_referer('ippanel_sms_nonce_action', 'nonce', false)) {
        wp_send_json_error('خطای امنیتی: لطفاً صفحه را رفرش کنید و مجددا تلاش نمایید.');
        wp_die();
    }

    // ۲. دریافت و پاک‌سازی شماره تلفن
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    if (empty($phone_number)) {
        wp_send_json_error('شماره تلفن نمی‌تواند خالی باشد.');
        wp_die();
    }

    // ۳. دریافت تنظیمات ذخیره شده
    $settings = get_option('ippanel_settings');
    $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
    $originator = isset($settings['originator']) ? $settings['originator'] : '';
    $pattern_code = isset($settings['pattern_code']) ? $settings['pattern_code'] : '';

    // بررسی کامل بودن تنظیمات (فیلد متغیر حذف شد)
    if (empty($api_key) || empty($originator) || empty($pattern_code)) {
        wp_send_json_error('تنظیمات پلاگین به درستی تکمیل نشده است. لطفاً به بخش تنظیمات مراجعه کنید.');
        wp_die();
    }

    // ۴. آماده‌سازی مقادیر برای ارسال به الگو
    // چون پیامک ما متغیری ندارد، آرایه مقادیر خالی است.
    $pattern_values = [];

    // ۵. ارسال درخواست به API ippanel
    $api_url = 'https://rest.ippanel.com/v1/messages/patterns';

    $body = json_encode([
        'pattern_code' => $pattern_code,
        'originator'   => $originator,
        'recipient'    => $phone_number,
        'values'       => $pattern_values // اینجا یک آرایه خالی ارسال می‌شود
    ]);

    $headers = [
        'Content-Type'  => 'application/json',
        'Authorization' => 'AccessKey ' . $api_key
    ];

    $response = wp_remote_post($api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 30
    ]);

    // ۶. بررسی پاسخ دریافتی
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error('خطا در ارتباط با سرور پیامک: ' . $error_message);
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code == 200 && isset($response_body['status']) && $response_body['status'] === 'OK') {
            // پیام موفقیت آمیز بدون اشاره به کد
            wp_send_json_success("پیامک با موفقیت ارسال شد.");
        } else {
            $error_msg = isset($response_body['message']) ? $response_body['message'] : 'خطای ناشناخته';
            wp_send_json_error('خطا در ارسال پیامک: ' . $error_msg);
        }
    }

    wp_die();
}