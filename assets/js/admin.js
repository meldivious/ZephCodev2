jQuery(document).ready(function($) {
    $('#zls_status').change(function() {
        const note = prompt('Optional: Add internal note for status change');
        if(note) $('#zls_notes_box textarea[name="zls_new_note"]').val(note);
    });
});