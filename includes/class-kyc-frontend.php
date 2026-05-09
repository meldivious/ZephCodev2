<?php
/**
* Zephora Logistic Systems - KYC Frontend Screens
*/
class ZLS_KYC_Frontend {
public static function init() {
add_shortcode('zls_login_register', [__CLASS__, 'render_login_register']);
add_shortcode('zls_kyc_verification', [__CLASS__, 'render_kyc_verification']);
add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);

 // ✅ NEW: Generate unique ID on first login
    add_action('wp_login', array(__CLASS__, 'generate_unique_id_on_first_login'), 10, 2);

}
public static function enqueue_styles() {
wp_enqueue_style('zls-kyc-styles', ZLS_PLUGIN_URL . 'assets/css/kyc-frontend.css', [], ZLS_VERSION);
}



/**
 * Generate a unique User ID (e.g., ZLS4F1B2A)
 */
private static function generate_unique_user_id() {
    $prefix = 'SS'; // You can change this to 'SS' or 'LP'
    $length = 6;
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $candidate_id = '';
    $exists = true;

    while ($exists) {
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $candidate_id = $prefix . $random_string;

        // Check if ID already exists in the database
        $users = get_users(array(
            'meta_key' => '_zls_unique_id',
            'meta_value' => $candidate_id,
            'number' => 1,
            'fields' => 'ID'
        ));
        $exists = !empty($users);
    }

    return $candidate_id;
}

// Add to user registration or first login
function zls_generate_unique_id($user_id) {
    $unique_id = get_user_meta($user_id, '_zls_unique_id', true);
    if (empty($unique_id)) {
        $unique_id = 'SS' . strtoupper(substr(md5($user_id . wp_salt() . time()), 0, 6));
        update_user_meta($user_id, '_zls_unique_id', $unique_id);
    }
    return $unique_id;
}


/**
 * Generate unique user ID on first login
 * @param string $user_login
 * @param WP_User $user
 */
public static function generate_unique_id_on_first_login($user_login, $user) {
    $user_id = $user->ID;
    $existing_id = get_user_meta($user_id, '_zls_unique_id', true);
    
    // Only generate if user doesn't have a unique ID yet
    if (empty($existing_id)) {
        $unique_id = self::generate_unique_user_id();
        update_user_meta($user_id, '_zls_unique_id', $unique_id);
    }
}


/**
* Screen 1: Login/Register/Forgot Password Page - All-in-One
* (Unchanged to preserve working functionality)
*/
public static function render_login_register() {
if (is_user_logged_in()) {
wp_redirect(home_url('/my-dashboard'));
exit;
}
// Initialize variables FIRST
$login_error = '';
$is_reset_mode = false;
$reset_user_id = 0;
$reset_error = '';
$reset_success = false;
$forgot_sent = false;
// Process login FIRST (before any HTML output)
if (isset($_POST['zls_login_submit'])) {
$creds = [
'user_login' => sanitize_email($_POST['zls_email']),
'user_password' => $_POST['zls_password'],
'remember' => isset($_POST['zls_remember'])
];
$user = wp_signon($creds, false);
if (is_wp_error($user)) {
// Strip HTML tags and get clean error message
$error_message = $user->get_error_message();
$login_error = wp_strip_all_tags($error_message);
} else {
wp_redirect(home_url('/my-dashboard'));
exit;
}
}
// Check if user clicked password reset link
if (isset($_GET['key'], $_GET['user_id'])) {
$reset_key = sanitize_text_field($_GET['key']);
$reset_user_id = absint($_GET['user_id']);
$stored_key = get_user_meta($reset_user_id, '_zls_password_reset_key', true);
$reset_time = get_user_meta($reset_user_id, '_zls_password_reset_time', true);
if ($reset_key === $stored_key && (time() - $reset_time) < 86400) {
$is_reset_mode = true;
// Handle password update
if (isset($_POST['zls_new_password'])) {
$new_password = $_POST['zls_new_password'];
$confirm = $_POST['zls_confirm_password'];
if ($new_password === $confirm && strlen($new_password) >= 6) {
wp_set_password($new_password, $reset_user_id);
delete_user_meta($reset_user_id, '_zls_password_reset_key');
delete_user_meta($reset_user_id, '_zls_password_reset_time');
$reset_success = true;
} else {
$reset_error = 'Passwords must match and be at least 6 characters';
}
}
}
}
// Handle forgot password submission
if (isset($_POST['zls_forgot_submit'])) {
$email = sanitize_email($_POST['zls_forgot_email']);
$user = get_user_by('email', $email);
if ($user) {
$reset_key = wp_generate_password(20, false);
update_user_meta($user->ID, '_zls_password_reset_key', $reset_key);
update_user_meta($user->ID, '_zls_password_reset_time', time());
$reset_link = add_query_arg(['key' => $reset_key, 'user_id' => $user->ID], home_url('/login'));
$subject = 'Password Reset Request';
$message = "Hello {$user->display_name},\n";
$message .= "You requested a password reset. Click the link below to reset your password:\n";
$message .= $reset_link . "\n";
$message .= "This link expires in 24 hours.\n";
$message .= "If you didn't request this, please ignore this email.\n";
wp_mail($email, $subject, $message);
}
$forgot_sent = true;
}
// NOW output HTML
ob_start();
?>
<!-- ... rest of your HTML stays the same ... -->
<style>
body { margin:0; padding:0; background:#f8f9fc; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
.zls-login-full { min-height:90vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; }
.zls-login-header { text-align:center; margin-bottom:32px; }
.zls-login-logo { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:12px; }
.zls-login-logo-icon { width:40px; height:40px; background:#6B3AE4; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; }
.zls-login-logo-text { font-size:24px; font-weight:700; color:#111; }
.zls-login-tagline { color:#6b7280; font-size:15px; max-width:320px; margin:0 auto; line-height:1.5; }
.zls-login-card { background:#fff; border-radius:16px; padding:32px; width:100%; max-width:420px; box-shadow:0 4px 24px rgba(0,0,0,0.06); }
.zls-login-card h2 { margin:0 0 8px; font-size:22px; font-weight:700; color:#111; }
.zls-login-subtitle { color:#6b7280; margin:0 0 24px; font-size:15px; }
.zls-form-group { margin-bottom:20px; position:relative; }
.zls-form-group label { display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
.zls-form-group input[type="email"], .zls-form-group input[type="password"], .zls-form-group input[type="text"], .zls-form-group input[type="tel"] { width:100%; padding:14px 16px; border:1px solid #e5e7eb; border-radius:10px; font-size:15px; background:#f9fafb; transition:all 0.2s; box-sizing:border-box; }
.zls-form-group input:focus { outline:none; border-color:#6B3AE4; background:#fff; box-shadow:0 0 0 3px rgba(107,58,228,0.1); }
.zls-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.zls-forgot-link { display:block; text-align:right; margin-bottom:16px; font-size:13px; color:#6B3AE4; text-decoration:none; font-weight:500; cursor:pointer; }
.zls-checkbox-group { display:flex; align-items:center; gap:8px; margin-bottom:24px; }
.zls-checkbox-group input { width:18px; height:18px; accent-color:#6B3AE4; }
.zls-checkbox-group label { font-size:14px; color:#4b5563; margin:0; text-transform:none; }
.zls-btn-primary { width:100%; padding:14px; background:#6B3AE4; color:#fff; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer; transition:background 0.2s; }
.zls-btn-primary:hover { background:#5a2dcf; }
.zls-btn-secondary { width:100%; padding:14px; background:#f3f4f6; color:#374151; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer; transition:background 0.2s; margin-top:8px; }
.zls-btn-secondary:hover { background:#e5e7eb; }
.zls-divider { text-align:center; margin:24px 0; position:relative; color:#9ca3af; font-size:13px; }
.zls-divider:before { content:""; position:absolute; top:50%; left:0; right:0; height:1px; background:#e5e7eb; }
.zls-divider span { background:#fff; padding:0 12px; position:relative; }
.zls-switch-link { text-align:center; margin-top:20px; font-size:14px; color:#6b7280; }
.zls-switch-link a { color:#6B3AE4; text-decoration:none; font-weight:600; cursor:pointer; }
.zls-ssl-badge { display:flex; align-items:center; justify-content:center; gap:4px; color:#9ca3af; font-size:12px; margin-top:16px; }
.zls-login-footer { margin-top:40px; text-align:center; font-size:13px; color:#9ca3af; }
.zls-login-footer nav { display:flex; justify-content:center; gap:20px; margin-top:8px; }
.zls-login-footer nav a { color:#9ca3af; text-decoration:none; }
.zls-login-footer nav a:hover { color:#6B3AE4; }
.zls-form-section { display:none; }
.zls-form-section.active { display:block; animation:fadeIn 0.3s; }
.zls-success-message { background:#d1fae5; color:#065f46; padding:16px; border-radius:10px; margin-bottom:20px; text-align:center; }
.zls-error-message { background:#fee2e2; color:#991b1b; padding:16px; border-radius:10px; margin-bottom:20px; text-align:center; }
@keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
@media (max-width:480px) { .zls-login-card { padding:24px; } .zls-form-row { grid-template-columns:1fr; } }
/* Password Toggle Styles */
.zls-password-wrapper { position: relative; }
.zls-password-wrapper input[type="password"],
.zls-password-wrapper input[type="text"] { padding-right: 48px; }
.zls-toggle-password {
position: absolute;
right: 12px;
top: 50%;
transform: translateY(-50%);
background: none;
border: none;
cursor: pointer;
padding: 4px;
color: #9ca3af;
display: flex;
align-items: center;
justify-content: center;
transition: color 0.2s;
}
.zls-toggle-password:hover { color: #6B3AE4; }
</style>
<div class="zls-login-full">

<div class="zls-login-card">
<?php if ($is_reset_mode): ?>
<!-- PASSWORD RESET MODE - ONLY SHOW THIS -->
<?php if ($reset_success): ?>
<div class="zls-success-message">✅ Password updated successfully! You can now log in.</div>
<button type="button" class="zls-btn-primary" onclick="window.location.href='<?php echo home_url('/login'); ?>'">Sign In</button>
<?php else: ?>
<h2>Set New Password</h2>
<p class="zls-login-subtitle">Enter your new password</p>
<?php if (!empty($reset_error)): ?>
<div class="zls-error-message"><?php echo esc_html($reset_error); ?></div>
<?php endif; ?>
<form method="post">
<div class="zls-form-group">
<label>New Password</label>
<div class="zls-password-wrapper">
<input type="password" name="zls_new_password" placeholder="••••••••" required>
<button type="button" class="zls-toggle-password" aria-label="Toggle password visibility">
<svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
</button>
</div>
</div>
<div class="zls-form-group">
<label>Confirm Password</label>
<div class="zls-password-wrapper">
<input type="password" name="zls_confirm_password" placeholder="••••••••" required>
<button type="button" class="zls-toggle-password" aria-label="Toggle password visibility">
<svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
</button>
</div>
</div>
<button type="submit" class="zls-btn-primary">Update Password</button>
</form>
<?php endif; ?>
<?php else: ?>
<!-- NORMAL MODE - Login/Register/Forgot -->
<!-- LOGIN FORM -->
<div id="login-form" style="display:<?php echo $forgot_sent ? 'none' : 'block'; ?>;">
<h2>Sign In</h2>
<p class="zls-login-subtitle">Enter your credentials to manage shipments</p>
<?php if (isset($_GET['registered'])): ?>
<div class="zls-success-message">Registration successful! Please log in.</div>
<?php endif; ?>
<!-- Display Login Error Inline -->
<?php if (!empty($login_error)): ?>
<div class="zls-error-message"><?php echo esc_html($login_error); ?></div>
<?php endif; ?>
<form method="post">
<div class="zls-form-group">
<label>Email Address</label>
<input type="email" name="zls_email" placeholder="name@email.com" required>
</div>
<div class="zls-form-group">
<label>Password</label>
<div class="zls-password-wrapper">
<input type="password" name="zls_password" placeholder="••••••••" required>
<button type="button" class="zls-toggle-password" aria-label="Toggle password visibility">
<svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
</button>
</div>
<span class="zls-forgot-link" onclick="showForm('forgot')">Forgot Password?</span>
</div>
<div class="zls-checkbox-group">
<input type="checkbox" name="zls_remember" id="zls_remember" value="1">
<label for="zls_remember">Remember Me</label>
</div>
<button type="submit" name="zls_login_submit" class="zls-btn-primary">Sign In to Dashboard</button>
</form>
<div class="zls-divider"><span>or</span></div>
<div class="zls-switch-link">
Don't have an account? <a onclick="showForm('register')">Register</a>
</div>
<div class="zls-ssl-badge">🔒 SSL SECURED</div>
</div>
<!-- FORGOT PASSWORD FORM -->
<div id="forgot-form" style="display:<?php echo $forgot_sent ? 'block' : 'none'; ?>;">
<h2>Reset Password</h2>
<p class="zls-login-subtitle">Enter your email to receive reset instructions</p>
<?php if ($forgot_sent): ?>
<div class="zls-success-message">✅ Your password reset link has been sent to your email address.</div>
<button type="button" class="zls-btn-secondary" onclick="showForm('login')">← Back to Login</button>
<?php else: ?>
<form method="post">
<div class="zls-form-group">
<label>Email Address</label>
<input type="email" name="zls_forgot_email" placeholder="name@email.com" required>
</div>
<button type="submit" name="zls_forgot_submit" class="zls-btn-primary">Send Reset Link</button>
</form>
<button type="button" class="zls-btn-secondary" onclick="showForm('login')">← Back to Login</button>
<?php endif; ?>
</div>
<!-- REGISTER FORM -->
<div id="register-form" style="display:none;">
<h2>Create Account</h2>
<p class="zls-login-subtitle">Register to access logistics services</p>
<?php
if (isset($_POST['zls_register_submit'])) {
$first_name = sanitize_text_field($_POST['zls_first_name']);
$last_name = sanitize_text_field($_POST['zls_last_name']);
$email = sanitize_email($_POST['zls_register_email']);
$phone = sanitize_text_field($_POST['zls_phone']);
$password = $_POST['zls_register_password'];
$confirm_password = $_POST['zls_confirm_password'];
$errors = [];
if (empty($first_name)) $errors[] = 'First name is required';
if (empty($last_name)) $errors[] = 'Last name is required';
if (empty($email) || !is_email($email)) $errors[] = 'Valid email is required';
if (email_exists($email)) $errors[] = 'Email already registered';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
if (empty($errors)) {
$user_id = wp_create_user($email, $password, $email);
if (!is_wp_error($user_id)) {
wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name]);
update_user_meta($user_id, '_zls_phone', $phone);
update_user_meta($user_id, '_zls_kyc_status', 'pending');
        // ✅ NEW: Generate and Save Unique ID
        $unique_id = self::generate_unique_user_id();
        update_user_meta($user_id, '_zls_unique_id', $unique_id);

        // Update Welcome Email to include the ID
        $subject = 'Welcome to Zephora Logistics';
        $message = "Hello {$first_name},\n";
        $message .= "Welcome to Zephora Logistics! Your account has been created.\n";
        $message .= "Your Unique ID is: {$unique_id}\n"; // Add this line
        $message .= "Please complete your KYC verification to access our services.\n";
        $message .= "Login at: " . home_url('/login') . "\n";
        $message .= "Thank you,\nZephora Logistics Team";
wp_mail($email, $subject, $message);
wp_redirect(add_query_arg('registered', '1', home_url('/login')));
exit;
} else {
echo '<div class="zls-error-message">Registration failed: ' . esc_html($user_id->get_error_message()) . '</div>';
}
} else {
echo '<div class="zls-error-message">❌ ' . esc_html(implode(', ', $errors)) . '</div>';
}
}
?>
<form method="post">
<div class="zls-form-row">
<div class="zls-form-group">
<label>First Name</label>
<input type="text" name="zls_first_name" placeholder="John" required>
</div>
<div class="zls-form-group">
<label>Last Name</label>
<input type="text" name="zls_last_name" placeholder="Doe" required>
</div>
</div>
<div class="zls-form-group">
<label>Email Address</label>
<input type="email" name="zls_register_email" placeholder="name@email.com" required>
</div>
<div class="zls-form-group">
<label>Phone Number</label>
<input type="tel" name="zls_phone" placeholder="+234 801 234 5678" required>
</div>
<div class="zls-form-group">
<label>Password</label>
<div class="zls-password-wrapper">
<input type="password" name="zls_register_password" placeholder="••••••••" required>
<button type="button" class="zls-toggle-password" aria-label="Toggle password visibility">
<svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
</button>
</div>
</div>
<div class="zls-form-group">
<label>Confirm Password</label>
<div class="zls-password-wrapper">
<input type="password" name="zls_confirm_password" placeholder="••••••••" required>
<button type="button" class="zls-toggle-password" aria-label="Toggle password visibility">
<svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
</button>
</div>
</div>
<button type="submit" name="zls_register_submit" class="zls-btn-primary">Create Account</button>
</form>
<div class="zls-switch-link">
Already have an account? <a onclick="showForm('login')">Sign In</a>
</div>
</div>
<?php endif; ?>
</div>

</div>
<script>
function showForm(formName) {
document.getElementById('login-form').style.display = 'none';
document.getElementById('forgot-form').style.display = 'none';
document.getElementById('register-form').style.display = 'none';
var targetForm = document.getElementById(formName + '-form');
if (targetForm) {
targetForm.style.display = 'block';
}
}
// Password Toggle Handler
document.addEventListener('click', function(e) {
const toggleBtn = e.target.closest('.zls-toggle-password');
if (!toggleBtn) return;
const wrapper = toggleBtn.closest('.zls-password-wrapper');
const input = wrapper.querySelector('input');
const eyeOpen = toggleBtn.querySelector('.eye-open');
const eyeClosed = toggleBtn.querySelector('.eye-closed');
const isHidden = input.type === 'password';
input.type = isHidden ? 'text' : 'password';
eyeOpen.style.display = isHidden ? 'none' : 'block';
eyeClosed.style.display = isHidden ? 'block' : 'none';
});
</script>
<?php
// Handle login submission
if (isset($_POST['zls_login_submit'])) {
$creds = [
'user_login' => sanitize_email($_POST['zls_email']),
'user_password' => $_POST['zls_password'],
'remember' => isset($_POST['zls_remember'])
];
$user = wp_signon($creds, false);
if (is_wp_error($user)) {
// Strip HTML tags and get clean error message
$error_message = $user->get_error_message();
$login_error = wp_strip_all_tags($error_message);
} else {
wp_redirect(home_url('/my-dashboard'));
exit;
}
}
return ob_get_clean();
}
/**
* Screen 2: KYC Verification Status & Upload (New Design)
*/
public static function render_kyc_verification() {
if (!is_user_logged_in()) {
wp_redirect(home_url('/login'));
exit;
}
$user_id = get_current_user_id();
$kyc_status = get_user_meta($user_id, '_zls_kyc_status', true) ?: 'pending';
$kyc_data = get_user_meta($user_id, '_zls_kyc_data', true);
// Redirect approved users to dashboard
if ($kyc_status === 'approved') {
wp_redirect(home_url('/my-dashboard'));
exit;
}
$kyc_note = get_user_meta($user_id, '_zls_kyc_note', true);
// ✅ NEW: Get attempt count
$attempts = (int) get_user_meta($user_id, '_zls_kyc_attempts', true) ?: 0;
$max_attempts = 3;
$remaining = max(0, $max_attempts - $attempts);
// Check if documents have been submitted
$documents_submitted = !empty($kyc_data) && !empty($kyc_data['submitted_at']);
ob_start();
?>
<style>
.zls-kyc-container { max-width: 800px; margin: 40px auto; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, sans-serif; color: #111; }
.zls-stepper { display: flex; align-items: center; justify-content: center; margin-bottom: 40px; }
.zls-step { display: flex; align-items: center; gap: 8px; }
.zls-step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; border: 2px solid #e5e7eb; color: #9ca3af; background: #fff; }
.zls-step-circle.active { border-color: #6B3AE4; color: #6B3AE4; background: #f3f4f6; }
.zls-step-circle.done { border-color: #6B3AE4; color: #fff; background: #6B3AE4; }
.zls-step-label { font-size: 14px; font-weight: 600; color: #9ca3af; }
.zls-step-label.active { color: #111; }
.zls-step-line { width: 60px; height: 2px; background: #e5e7eb; margin: 0 16px; }
.zls-kyc-header { text-align: center; margin-bottom: 32px; }
.zls-kyc-header h1 { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
.zls-kyc-header p { color: #6b7280; margin: 0; font-size: 15px; }
.zls-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.zls-info-card { background: #fff; padding: 16px; border-radius: 12px; border: 1px solid #f3f4f6; display: flex; gap: 12px; align-items: flex-start; }
.zls-info-icon { width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.zls-info-icon.success { background: #d1fae5; color: #059669; }
.zls-info-icon.info { background: #fef3c7; color: #d97706; }
.zls-info-content h4 { margin: 0 0 4px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.zls-info-content p { margin: 0; font-size: 14px; color: #4b5563; line-height: 1.5; }
.zls-upload-section { margin-bottom: 24px; background: #fff; border-radius: 16px; border: 1px solid #f3f4f6; overflow: hidden; }
.zls-upload-header { padding: 20px 24px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
.zls-upload-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
.zls-upload-header p { margin: 4px 0 0; font-size: 13px; color: #6b7280; }
.zls-drop-zone { padding: 32px; text-align: center; cursor: pointer; transition: all 0.2s; border: 2px dashed #e5e7eb; margin: 20px; border-radius: 12px; background: #f9fafb; position: relative; }
.zls-drop-zone:hover, .zls-drop-zone.dragover { border-color: #6B3AE4; background: #f5f3ff; }
.zls-drop-zone.has-file { border-color: #10b981; background: #d1fae5; border-style: solid; }
.zls-drop-icon { width: 48px; height: 48px; background: #fff; border-radius: 50%; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); color: #6B3AE4; font-size: 20px; }
.zls-drop-title { font-weight: 600; margin-bottom: 4px; }
.zls-drop-hint { font-size: 13px; color: #6b7280; }
.zls-file-input { display: none; }
.zls-file-name { font-size: 14px; color: #059669; margin-top: 8px; font-weight: 500; display: none; }
.zls-submit-area { text-align: center; margin-top: 32px; padding-bottom: 40px; }
.zls-btn-verify { background: #6B3AE4; color: #fff; border: none; padding: 16px 48px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; width: 100%; max-width: 400px; }
.zls-btn-verify:hover { background: #5a2dcf; }
.zls-btn-verify:disabled { background: #d1d5db; cursor: not-allowed; }
.zls-policy-text { margin-top: 16px; font-size: 13px; color: #6b7280; }
.zls-encryption-footer { margin-top: 24px; text-align: center; font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.zls-status-card { background: #fff; padding: 32px; border-radius: 16px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 24px; }
.zls-status-icon { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; }
.zls-status-icon.pending { background: #fef3c7; color: #d97706; }
.zls-status-icon.denied { background: #fee2e2; color: #dc2626; }
.zls-status-icon.banned { background: #1f2937; color: #fff; }
.zls-alert { padding: 16px; border-radius: 10px; margin: 20px 0; text-align: center; }
.zls-alert.success { background: #d1fae5; color: #065f46; }
.zls-alert.error { background: #fee2e2; color: #991b1b; }
/* ✅ NEW: Attempt counter badge */
.zls-attempt-badge { margin-top: 12px; display: inline-block; background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; }
@media (max-width: 600px) { .zls-info-grid { grid-template-columns: 1fr; } .zls-stepper { flex-direction: column; gap: 12px; } .zls-step-line { width: 2px; height: 20px; margin: 0; } }
</style>
<div class="zls-kyc-container">
<!-- Stepper -->
<div class="zls-stepper">
<div class="zls-step">
<div class="zls-step-circle done">✓</div>
<span class="zls-step-label">Account</span>
</div>
<div class="zls-step-line"></div>
<div class="zls-step">
<div class="zls-step-circle <?php echo $documents_submitted ? 'active' : 'done'; ?>">2</div>
<span class="zls-step-label <?php echo $documents_submitted ? 'active' : ''; ?>">Documents</span>
</div>
<div class="zls-step-line"></div>
<div class="zls-step">
<div class="zls-step-circle">3</div>
<span class="zls-step-label">Verification</span>
</div>
</div>
<div class="zls-kyc-header">
<h1>Identity Verification</h1>
<p>To ensure security on our platform, please upload high-quality photos of your documents.</p>
</div>
<?php if ($kyc_status === 'denied'): ?>
<div class="zls-status-card">
<div class="zls-status-icon denied">!</div>
<h2 style="margin:0 0 8px;">KYC Denied</h2>
<p style="color:#666;max-width:500px;margin:0 auto 20px;"><?php echo esc_html($kyc_note ?: 'Documents did not meet requirements. Please resubmit.'); ?></p>
<!-- ✅ NEW: Display attempt counter -->
<div class="zls-attempt-badge">
⚠️ Attempts Remaining: <?php echo $remaining; ?> of <?php echo $max_attempts; ?>
</div>
<?php if ($remaining > 0): ?>
<!-- ✅ Show resubmit button only if attempts remain -->
<div style="margin-top: 20px;">
<h4>Resubmit Documents Below</h4>
</div>
<?php else: ?>
<!-- ✅ Show max attempts message -->
<p style="color: #dc2626; margin-top: 15px; font-weight: bold;">
Max attempts reached. Your account has been restricted. Please contact support.
</p>
<?php endif; ?>
</div>
<?php elseif ($kyc_status === 'pending' && $documents_submitted): ?>
<div class="zls-status-card">
<div class="zls-status-icon pending">⏳</div>
<h2 style="margin:0 0 8px;">KYC Pending</h2>
<p style="color:#666;max-width:500px;margin:0 auto 20px;">Our team is currently reviewing your documents. This typically takes 24-48 hours.</p>
</div>
<?php elseif ($kyc_status === 'banned'): ?>
<div class="zls-status-card" style="background:#1f2937;color:#fff;">
<h2 style="color:#fff;">KYC Banned</h2>
<p style="color:#d1d5db;"><?php echo esc_html($kyc_note ?: 'Your account has been suspended.'); ?></p>
<button class="zls-btn-verify" style="background:#fff;color:#1f2937;margin-top:16px;" onclick="window.location.href='mailto:support@zephora.logistics'">Contact Support</button>
</div>
<?php endif; ?>
<?php if ($kyc_status !== 'banned' && ($kyc_status !== 'pending' || !$documents_submitted)): ?>
<!-- Upload Form -->
<div id="upload-section">
<form method="post" enctype="multipart/form-data" id="zls-kyc-form">
<?php wp_nonce_field('zls_kyc_upload', 'zls_kyc_nonce'); ?>
<div class="zls-info-grid">
<div class="zls-info-card">
<div class="zls-info-icon success">✓</div>
<div class="zls-info-content">
<h4>Requirements</h4>
<p>All four corners visible<br>No glare or reflections<br>Text must be legible</p>
</div>
</div>
<div class="zls-info-card">
<div class="zls-info-icon info">i</div>
<div class="zls-info-content">
<h4>Format</h4>
<p>File types: JPG, PNG, PDF<br>Max size: 10MB<br>Valid documents only</p>
</div>
</div>
</div>
<!-- Government ID Upload -->
<div class="zls-upload-section">
<div class="zls-upload-header">
<div>
<h3>Government ID</h3>
<p>Passport, Driver's License or National Identity Card</p>
</div>
</div>
<div class="zls-drop-zone" id="drop-zone-id" onclick="document.getElementById('zls_gov_id').click()">
<div class="zls-drop-icon">📷</div>
<div class="zls-drop-title">Click to upload or drag and drop</div>
<div class="zls-drop-hint">Front side of document</div>
<input type="file" name="zls_gov_id" id="zls_gov_id" class="zls-file-input" accept="image/*,.pdf" required onchange="handleFileSelect(this, 'drop-zone-id')">
<div class="zls-file-name" id="name-zone-id"></div>
</div>
</div>
<!-- Proof of Address Upload -->
<div class="zls-upload-section">
<div class="zls-upload-header">
<div>
<h3>Proof of Address</h3>
<p>Utility Bill, Bank Statement (issued within last 3 months)</p>
</div>
</div>
<div class="zls-drop-zone" id="drop-zone-address" onclick="document.getElementById('zls_proof_address').click()">
<div class="zls-drop-icon">📄</div>
<div class="zls-drop-title">Click to upload or drag and drop</div>
<div class="zls-drop-hint">Document must show your full name</div>
<input type="file" name="zls_proof_address" id="zls_proof_address" class="zls-file-input" accept="image/*,.pdf" required onchange="handleFileSelect(this, 'drop-zone-address')">
<div class="zls-file-name" id="name-zone-address"></div>
</div>
</div>
<div class="zls-submit-area">
<button type="submit" name="zls_submit_kyc" class="zls-btn-verify" id="zls-submit-btn" disabled>Submit for Verification →</button>
<p class="zls-policy-text">By clicking submit, you agree to our <a href="#" style="color:#6B3AE4;">Identity Data Policy</a>.</p>
<div class="zls-encryption-footer">🔒 End-to-end encrypted verification</div>
</div>
</form>
</div>
<?php endif; ?>
</div>
<script>
function handleFileSelect(input, zoneId) {
var zone = document.getElementById(zoneId);
var nameDiv = document.getElementById(zoneId.replace('drop-zone', 'name-zone'));
var btn = document.getElementById('zls-submit-btn');
if (input.files && input.files[0]) {
zone.classList.add('has-file');
var file = input.files[0];
zone.querySelector('.zls-drop-title').textContent = file.name;
zone.querySelector('.zls-drop-hint').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
zone.querySelector('.zls-drop-icon').textContent = '✓';
nameDiv.style.display = 'block';
nameDiv.textContent = '✓ File selected: ' + file.name;
checkFormValidity();
}
}
function checkFormValidity() {
var idFile = document.getElementById('zls_gov_id').files.length > 0;
var addrFile = document.getElementById('zls_proof_address').files.length > 0;
var btn = document.getElementById('zls-submit-btn');
if (idFile && addrFile) {
btn.disabled = false;
} else {
btn.disabled = true;
}
}
// Drag and Drop Logic
['drop-zone-id', 'drop-zone-address'].forEach(function(id) {
var zone = document.getElementById(id);
zone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
zone.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
zone.addEventListener('drop', function(e) {
e.preventDefault();
this.classList.remove('dragover');
var input = this.querySelector('input[type="file"]');
input.files = e.dataTransfer.files;
handleFileSelect(input, id);
});
});
</script>
<?php
// Handle Upload Submission
if (isset($_POST['zls_submit_kyc']) && isset($_POST['zls_kyc_nonce']) && wp_verify_nonce($_POST['zls_kyc_nonce'], 'zls_kyc_upload')) {
if (isset($_FILES['zls_gov_id']) && isset($_FILES['zls_proof_address'])) {
// Validation logic
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 10 * 1024 * 1024; // 10MB
if (!in_array($_FILES['zls_gov_id']['type'], $allowed) || $_FILES['zls_gov_id']['size'] > $max_size) {
echo '<div class="zls-alert error">Invalid Government ID file type or size (Max 10MB).</div>';
} elseif (!in_array($_FILES['zls_proof_address']['type'], $allowed) || $_FILES['zls_proof_address']['size'] > $max_size) {
echo '<div class="zls-alert error">Invalid Proof of Address file type or size (Max 10MB).</div>';
} else {
// Secure Upload
$upload_dir = wp_upload_dir();
$kyc_dir = $upload_dir['basedir'] . '/zls-kyc-documents/' . $user_id;
if (!file_exists($kyc_dir)) {
wp_mkdir_p($kyc_dir);
file_put_contents($kyc_dir . '/.htaccess', "Deny from all");
}
$gov_name = 'gov_id_' . time() . '_' . wp_generate_password(8, false) . '.' . pathinfo($_FILES['zls_gov_id']['name'], PATHINFO_EXTENSION);
$addr_name = 'addr_' . time() . '_' . wp_generate_password(8, false) . '.' . pathinfo($_FILES['zls_proof_address']['name'], PATHINFO_EXTENSION);
if (move_uploaded_file($_FILES['zls_gov_id']['tmp_name'], $kyc_dir . '/' . $gov_name) &&
move_uploaded_file($_FILES['zls_proof_address']['tmp_name'], $kyc_dir . '/' . $addr_name)) {
update_user_meta($user_id, '_zls_kyc_data', [
'gov_id_file' => $gov_name,
'proof_address_file' => $addr_name,
'submitted_at' => current_time('mysql')
]);
update_user_meta($user_id, '_zls_kyc_status', 'pending');
echo '<script>document.getElementById("zls-kyc-form").style.display="none"; document.querySelector(".zls-kyc-header h1").textContent="Submission Successful!"; document.querySelector(".zls-kyc-header p").textContent="Your documents have been received. You will be notified once reviewed."; document.querySelector(".zls-stepper .zls-step-circle.active").classList.add("done"); document.querySelector(".zls-stepper .zls-step-circle.active").textContent="✓"; </script>';
} else {
echo '<div class="zls-alert error">Failed to upload files. Please try again.</div>';
}
}
}
}
return ob_get_clean();
}
}