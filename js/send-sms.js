jQuery(document).ready(function($) {
    $('#ippanel-sms-form').on('submit', function(e) {
        e.preventDefault(); // جلوگیری از ارسال عادی فرم و رفرش صفحه

        var phoneInput = $('#ippanel-phone-number');
        var sendButton = $('#ippanel-send-btn');
        var messageDiv = $('#ippanel-message');
        var phoneNumber = phoneInput.val();

        // نمایش پیام در حال ارسال
        messageDiv.css('color', 'orange').text('در حال ارسال...');
        sendButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: ippanel_ajax_obj.ajax_url,
            data: {
                action: 'send_ippanel_sms', // نام اکشنی که در PHP تعریف کردیم
                nonce: ippanel_ajax_obj.nonce, // امنیت
                phone_number: phoneNumber
            },
            success: function(response) {
                if (response.success) {
                    // موفقیت آمیز بود
                    messageDiv.css('color', 'green').text(response.data);
                    phoneInput.val(''); // خالی کردن اینپوت
                } else {
                    // خطا از سمت سرور
                    messageDiv.css('color', 'red').text(response.data);
                }
            },
            error: function(xhr, status, error) {
                // خطا در ارتباط AJAX
                messageDiv.css('color', 'red').text('خطا در ارتباط با سرور. لطفاً بعداً تلاش کنید.');
                console.error(xhr.responseText);
            },
            complete: function() {
                // در هر صورت (موفق یا ناموفق) دکمه را فعال کن
                sendButton.prop('disabled', false);
            }
        });
    });
});