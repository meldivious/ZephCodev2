jQuery(document).ready(function($) {
    // Tabs
    $('.zls-tabs button').click(function() {
        $('.zls-tabs button').removeClass('active');
        $(this).addClass('active');
        $('.zls-tab-content').removeClass('active').hide();
        $('#' + $(this).data('tab')).addClass('active').show();
    });

    // Confirm payment AJAX
    $('.zls-btn-sm').click(function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if(!confirm('Confirm you have paid? Admin will verify.')) return;
        $.post(url, function() { location.reload(); });
    });

    // KYC auto-save note
    $('[name="zls_kyc_submit"]').click(function() {
        $(this).text('Saving...').prop('disabled', true);
    });
});