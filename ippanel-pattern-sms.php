<?php
/**
 * Plugin Name: ippanel Pattern SMS 
 * Description: ارسال پیامک پترن (الگو) بدون متغیر از طریق ippanel.com با API نهایی.
 * Version: 3.2
 * Author: حمیدرضا خسروآبادی
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
        'ippanel_settings_section_callback',
        'ippanel-pattern-sms'
    );

    add_settings_field(
        'ippanel_api_key',
        'توکن API (Token)',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'api_key']
    );

    add_settings_field(
        'ippanel_from_number',
        'شماره ارسال کننده (from_number) با فرمت E.164',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'from_number']
    );

    add_settings_field(
        'ippanel_pattern_code',
        'کد الگو (code)',
        'ippanel_text_field_render',
        'ippanel-pattern-sms',
        'ippanel_settings_section',
        ['name' => 'pattern_code']
    );
}

function ippanel_settings_section_callback() {
    echo '<p>لطفاً اطلاعات دریافتی از پنل ippanel.com را با دقت وارد کنید. شماره ارسال کننده باید با فرمت E.164 باشد (مثال: +983000505).</p>';
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
    ob_start();
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
    return ob_get_clean();
}


// ========== بخش ۳: ثبت و اجرای فایل JavaScript برای ارسال AJAX ==========

add_action('wp_enqueue_scripts', 'ippanel_enqueue_scripts');

function ippanel_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ippanel_sms_form')) {
        wp_register_script(
            'ippanel-send-sms-js',
            plugin_dir_url(__FILE__) . 'js/send-sms.js',
            array('jquery'),
            '3.2',
            true
        );

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


// ========== بخش ۴: پردازش درخواست AJAX در سمت سرور (نسخه نهایی و اصلاح‌شده بر اساس پاسخ ساختاریافته) ==========

add_action('wp_ajax_send_ippanel_sms', 'ippanel_send_sms_callback');
add_action('wp_ajax_nopriv_send_ippanel_sms', 'ippanel_send_sms_callback');

function ippanel_send_sms_callback() {
    if (!check_ajax_referer('ippanel_sms_nonce_action', 'nonce', false)) {
        wp_send_json_error('خطای امنیتی: لطفاً صفحه را رفرش کنید و مجددا تلاش نمایید.');
        wp_die();
    }

    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    if (empty($phone_number)) {
        wp_send_json_error('شماره تلفن نمی‌تواند خالی باشد.');
        wp_die();
    }

    $settings = get_option('ippanel_settings');
    $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
    $from_number = isset($settings['from_number']) ? $settings['from_number'] : '';
    $pattern_code = isset($settings['pattern_code']) ? $settings['pattern_code'] : '';

    if (empty($api_key) || empty($from_number) || empty($pattern_code)) {
        wp_send_json_error('تنظیمات پلاگین به درستی تکمیل نشده است. لطفاً به بخش تنظیمات مراجعه کنید.');
        wp_die();
    }

    $recipient_e164 = ippanel_convert_to_e164($phone_number);

    $api_url = 'https://edge.ippanel.com/v1/api/send';

    $body = json_encode([
        'sending_type' => 'pattern',
        'from_number'  => $from_number,
        'code'         => $pattern_code,
        'recipients'   => [$recipient_e164],
        'params'       => (object)[]
    ]);

    $headers = [
        'Content-Type'  => 'application/json',
        'Authorization' => $api_key
    ];

    $response = wp_remote_post($api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('ippanel Connection Error: ' . $error_message);
        wp_send_json_error('خطا در ارتباط با سرور پیامک: ' . $error_message);
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        error_log('=== ippanel API Debug Info ===');
        error_log('Request URL: ' . $api_url);
        error_log('Request Body: ' . $body);
        error_log('Response Code: ' . $response_code);
        error_log('Response Body: ' . print_r($response_body, true));
        error_log('==============================');

        // --- شرط موفقیت اصلاح‌شده بر اساس پاسخ ساختاریافته جدید ---
        if ($response_code == 200 && isset($response_body['meta']['status']) && $response_body['meta']['status'] === true) {
            
            $success_message = isset($response_body['meta']['message']) ? $response_body['meta']['message'] : 'پیامک با موفقیت ارسال شد.';
            $message_id = 'نامشخص';

            if (isset($response_body['data']['message_outbox_ids']) && !empty($response_body['data']['message_outbox_ids'])) {
                $message_id = $response_body['data']['message_outbox_ids'][0];
            }
            
            wp_send_json_success("✅ {$success_message} کد پیگیری: {$message_id}");

        } else {
            // این بخش فقط برای خطاهای واقعی اجرا می‌شود
            $error_msg = isset($response_body['meta']['message']) ? $response_body['meta']['message'] : 'خطای ناشناخته از سمت سرور';
            
            // نمایش کل بدنه پاسخ به عنوان جزئیات خطا برای دیباگ بهتر
            $error_details = json_encode($response_body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $error_msg .= ' - جزئیات: ' . $error_details;
            
            wp_send_json_error('خطا در ارسال پیامک: ' . $error_msg);
        }
    }

    wp_die();
}

// --- تابع کمکی برای تبدیل شماره تلفن به فرمت E.164 ---
function ippanel_convert_to_e164($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($phone, '0098') === 0) {
        return '+' . substr($phone, 2);
    } elseif (strpos($phone, '09') === 0) {
        return '+98' . substr($phone, 1);
    } elseif (strpos($phone, '98') === 0) {
        return '+' . $phone;
    }
    return $phone;
}