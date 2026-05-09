/**
 * Zephora Logistics - Settings Page JavaScript
 * PHP 7.4+ Compatible | No arrow functions in global scope
 */
jQuery(document).ready(function($) {
    
    // Tab switching
    $('.zls-settings-tabs button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Update active tab button
        $('.zls-settings-tabs button').removeClass('active');
        $(this).addClass('active');
        
        // Show selected tab content
        $('.zls-settings-tab').removeClass('active').hide();
        $('#' + tabId).addClass('active').show();
    });
    
    // Show/hide address form
    $('#zls-show-address-form').on('click', function() {
        $('.zls-address-form').slideToggle();
        $(this).text(function(i, text) {
            return text === '+ Add New Address' ? '− Cancel' : '+ Add New Address';
        });
    });
    
    // Add new address via AJAX
    $('#zls-add-address-btn').on('click', function() {
        var form = $('.zls-address-form');
        var data = {
            action: 'zls_add_address',
            nonce: zlsSettings.nonce,
            address: {
                label: form.find('[name="zls_us_addresses[new][label]"]').val(),
                address_line1: form.find('[name="zls_us_addresses[new][address_line1]"]').val(),
                address_line2: form.find('[name="zls_us_addresses[new][address_line2]"]').val(),
                city: form.find('[name="zls_us_addresses[new][city]"]').val(),
                state: form.find('[name="zls_us_addresses[new][state]"]').val(),
                postal_code: form.find('[name="zls_us_addresses[new][postal_code]"]').val(),
                country: form.find('[name="zls_us_addresses[new][country]"]').val(),
                contact_phone: form.find('[name="zls_us_addresses[new][contact_phone]"]').val(),
            }
        };
        
        $.post(zlsSettings.ajax_url, data, function(response) {
            if (response.success) {
                // Add new address card to list
                $('#zls-addresses-list').append(response.data.html);
                // Reset form
                form.find('input').val('');
                form.slideUp();
                $('#zls-show-address-form').text('+ Add New Address');
                // Show success message
                alert('Address added successfully!');
            } else {
                alert('Error: ' + (response.data.message || 'Unknown error'));
            }
        });
    });
    
    // Remove address via AJAX (event delegation for dynamic elements)
    $(document).on('click', '.zls-btn-remove', function() {
        if (!confirm('Remove this address? Customers will no longer see it.')) {
            return;
        }
        
        var id = $(this).data('id');
        var card = $(this).closest('.zls-address-card');
        
        $.post(zlsSettings.ajax_url, {
            action: 'zls_remove_address',
            nonce: zlsSettings.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                card.fadeOut(function() { $(this).remove(); });
            } else {
                alert('Error: ' + (response.data.message || 'Unknown error'));
            }
        });
    });
    
    // Toggle password visibility for API keys
    $('.zls-settings-wrap').on('click', '.toggle-password', function() {
        var input = $(this).prev('input');
        var type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).text(type === 'password' ? '👁️ Show' : '🙈 Hide');
    });
    
    // Add toggle buttons next to password fields
    $('input[type="password"][name*="key"]').each(function() {
        $(this).after(' <button type="button" class="toggle-password" style="margin-left:5px">👁️ Show</button>');
    });
});