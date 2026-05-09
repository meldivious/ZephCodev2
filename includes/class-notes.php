<?php
class ZLS_Notes {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_notes_meta_box']);
        add_action('save_post_zls_ship', [__CLASS__, 'save_note']);
        add_action('save_post_zls_buy', [__CLASS__, 'save_note']);
        add_filter('zls_get_notes', [__CLASS__, 'get_notes'], 10, 2);
    }

    public static function add_notes_meta_box() {
        if (!current_user_can('manage_zls')) return;
        add_meta_box('zls_notes_box', '🗨️ Admin-User Communication', [__CLASS__, 'render_notes_box'], ['zls_ship', 'zls_buy'], 'normal');
    }

    public static function render_notes_box($post) {
        wp_nonce_field('zls_notes_nonce', 'zls_notes_nonce');
        $notes = get_post_meta($post->ID, '_zls_notes', true) ?: [];
        ?>
        <div id="zls-notes-container">
            <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:10px;margin-bottom:10px;background:#f9f9f9">
                <?php if (empty($notes)): ?>
                    <p class="description">No notes yet.</p>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <div style="margin-bottom:8px;padding:8px;background:#fff;border-left:3px solid #6B3AE4">
                            <strong><?php echo esc_html($note['author']); ?></strong> 
                            <small style="color:#666"><?php echo esc_html(date('M j, Y g:i a', $note['timestamp'])); ?></small>
                            <p style="margin:4px 0"><?php echo wp_kses($note['content'], 'zls_notes'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <textarea name="zls_new_note" rows="3" placeholder="Add a note for the user..." style="width:100%"></textarea>
            <button type="button" id="zls-add-note" class="button button-secondary">Add Note</button>
        </div>
        <?php
    }

    public static function save_note($post_id) {
        if (!isset($_POST['zls_notes_nonce']) || !wp_verify_nonce($_POST['zls_notes_nonce'], 'zls_notes_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $new_note = sanitize_textarea_field($_POST['zls_new_note'] ?? '');
        if (empty($new_note)) return;

        $notes = get_post_meta($post_id, '_zls_notes', true) ?: [];
        $notes[] = [
            'author' => wp_get_current_user()->display_name,
            'content' => $new_note,
            'timestamp' => time(),
            'role' => 'admin'
        ];
        update_post_meta($post_id, '_zls_notes', $notes);
        ZLS_Audit_Logger::log('NOTE_ADDED', ['post_id' => $post_id, 'author_id' => get_current_user_id()]);
    }

    public static function get_notes($notes, $post_id) {
        if (current_user_can('manage_zls')) return $notes;
        $user_id = get_current_user_id();
        $post = get_post($post_id);
        if (!$post || $post->post_author != $user_id) return []; // IDOR protection
        
        // ✅ FIX: Traditional closure for PHP 7.2
        return array_filter($notes, function($n) {
            return $n['role'] === 'admin';
        });
    }
}