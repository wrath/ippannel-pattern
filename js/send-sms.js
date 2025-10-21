jQuery(document).ready(function($) {
    $('#ippanel-sms-form').on('submit', function(e) {
        e.preventDefault();

        var phoneInput = $('#ippanel-phone-number');
        var sendButton = $('#ippanel-send-btn');
        var messageDiv = $('#ippanel-message');
        var phoneNumber = phoneInput.val();

        messageDiv.css('color', 'orange').text('در حال ارسال...');
        sendButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: ippanel_ajax_obj.ajax_url,
            data: {
                action: 'send_ippanel_sms',
                nonce: ippanel_ajax_obj.nonce,
                phone_number: phoneNumber
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.css('color', 'green').text(response.data);
                    phoneInput.val('');
                } else {
                    messageDiv.css('color', 'red').text(response.data);
                }
            },
            error: function(xhr, status, error) {
                messageDiv.css('color', 'red').text('خطا در ارتباط با سرور. لطفاً بعداً تلاش کنید.');
                console.error(xhr.responseText);
            },
            complete: function() {
                sendButton.prop('disabled', false);
            }
        });
    });
});