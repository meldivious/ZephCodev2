<?php
/**
 * Zephora Logistic Systems - Buy For Me Handler
 */
class ZLS_Buy_For_Me extends ZLS_Request_Base {
    
    /**
     * Submit purchase request
     */
    public static function submit() {
        // Verify nonce
        if (!isset($_POST['zls_buy_nonce']) || !wp_verify_nonce($_POST['zls_buy_nonce'], 'zls_buy')) {
            return new WP_Error('invalid_nonce', 'Security check failed');
        }

        // Get form data
        $data = $_POST['bfm'] ?? [];
        
        // Handle submission via parent class
        $result = self::handle_submission('zls_buy', $data);
        return $result;
    }
    
    
    /**
     * Render Buy For Me form
     */
    public static function render() {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login'));
            exit;
        }

        $user_id = get_current_user_id();
        
        // Check for success message
        $show_success = isset($_GET['bfm_ok']) && $_GET['bfm_ok'] === '1';
        
        // Check for error message
        $error_message = get_transient('zls_buy_error_' . $user_id);
        delete_transient('zls_buy_error_' . $user_id);

        ob_start();
        ?>
        <style>
            .zls-buy-for-me-container {
                max-width: 1220px;
                margin: 0px auto;
                padding: 40px 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .zls-buy-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 32px;
            }
            
            .zls-buy-title {
                font-size: 28px;
                font-weight: 700;
                color: #111;
                margin: 0;
            }
            
            .zls-btn-back {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                color: #059669;
                font-weight: 600;
                font-size: 14px;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .zls-btn-back:hover {
                background: #f9fafb;
                border-color: #059669;
            }
            
            .zls-btn-back svg {
                width: 16px;
                height: 16px;
            }
            
            .zls-buy-card {
                background: #fff;
                border-radius: 16px;
                padding: 32px;
                margin-bottom: 24px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.04);
                border: 1px solid #f3f4f6;
            }
            
            .zls-section-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 28px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f3f4f6;
            }
            
            .zls-section-icon {
                width: 32px;
                height: 32px;
                background: #d1fae5;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #059669;
                font-size: 18px;
            }
            
            .zls-section-title {
                font-size: 18px;
                font-weight: 700;
                color: #111;
                margin: 0;
            }
            
            .zls-form-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .zls-form-group {
                margin-bottom: 20px;
            }
            
            .zls-form-group.full-width {
                grid-column: 1 / -1;
            }
            
            .zls-form-group label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #6b7280;
                margin-bottom: 8px;
            }
            
            .zls-form-group input[type="text"],
            .zls-form-group input[type="email"],
            .zls-form-group input[type="tel"],
            .zls-form-group input[type="url"],
            .zls-form-group input[type="number"],
            .zls-form-group textarea {
                width: 100%;
                padding: 14px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                font-size: 15px;
                background: #f9fafb;
                transition: all 0.2s;
                box-sizing: border-box;
                font-family: inherit;
            }
            
            .zls-form-group input:focus,
            .zls-form-group textarea:focus {
                outline: none;
                border-color: #059669;
                background: #fff;
                box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
            }
            
            .zls-form-group input::placeholder,
            .zls-form-group textarea::placeholder {
                color: #9ca3af;
            }
            
            .zls-input-with-label {
                position: relative;
            }
            
            .zls-input-suffix {
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #9ca3af;
                font-size: 14px;
                pointer-events: none;
            }
            
            .zls-form-group textarea {
                resize: vertical;
                min-height: 100px;
            }
            
            .zls-submit-section {
                margin-top: 32px;
                padding-top: 24px;
                border-top: 1px solid #f3f4f6;
            }
            
            .zls-btn-submit {
                width: 100%;
                padding: 16px;
                background: #059669;
                color: #fff;
                border: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.2s;
            }
            
            .zls-btn-submit:hover {
                background: #047857;
            }
            
            .zls-success-message {
                background: #d1fae5;
                color: #065f46;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 24px;
                text-align: center;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            }
            
            .zls-error-message {
                background: #fee2e2;
                color: #991b1b;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 24px;
                text-align: center;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @media (max-width: 768px) {
                .zls-form-grid { grid-template-columns: 1fr; }
                .zls-buy-card { padding: 24px; }
                .zls-buy-header { flex-direction: column; gap: 16px; align-items: flex-start; }
            }
        </style>
        
        <div class="zls-buy-for-me-container">
            <!-- Header with Back Button -->
            <div class="zls-buy-header">
                <h1 class="zls-buy-title">Buy For Me</h1>
                <a href="<?php echo home_url('/my-dashboard'); ?>" class="zls-btn-back">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Success Message -->
            <?php if ($show_success): ?>
            <div class="zls-success-message">
                ✅ Purchase request submitted successfully! We'll source your item and send a quote.
            </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
            <div class="zls-error-message">
                ❌ <?php echo esc_html($error_message); ?>
            </div>
            <?php endif; ?>
            
            <form method="post" class="zls-buy-form">
                <?php wp_nonce_field('zls_buy', 'zls_buy_nonce'); ?>
                
                <!-- Product Information Section -->
                <div class="zls-buy-card">
                    <div class="zls-section-header">
                        
                        <h2 class="zls-section-title">Product Details</h2>
                    </div>
                    
                    <div class="zls-form-grid">
                        <div class="zls-form-group full-width">
                            <label for="bfm_product_name">Product Name</label>
                            <input type="text" id="bfm_product_name" name="bfm[product_name]" placeholder="e.g., MacBook Air M2" required>
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="bfm_product_url">Product URL</label>
                            <input type="url" id="bfm_product_url" name="bfm[product_url]" placeholder="https://amazon.com/item...">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_quantity">Quantity</label>
                            <input type="number" id="bfm_quantity" name="bfm[quantity]" min="1" value="1">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_budget_usd">Amount ($)</label>
                            <div class="zls-input-with-label">
                                <input type="number" id="bfm_budget_usd" name="bfm[budget_usd]" step="0.01" min="0" placeholder="999.00">
                                <span class="zls-input-suffix">$</span>
                            </div>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_preferred_store">Preferred Store</label>
                            <input type="text" id="bfm_preferred_store" name="bfm[preferred_store]" placeholder="Amazon, BestBuy...">
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="bfm_specs">Specifications</label>
                            <textarea id="bfm_specs" name="bfm[specs]" placeholder="Color, size, model, storage..." rows="3"></textarea>
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="bfm_content_desc">Content Description</label>
                            <textarea id="bfm_content_desc" name="bfm[content_desc]" placeholder="For customs clearance..." required rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Nigeria Delivery Address Section -->
                <div class="zls-buy-card">
                    <div class="zls-section-header">
                       
                        <h2 class="zls-section-title">Nigeria Delivery Address</h2>
                    </div>
                    
                    <div class="zls-form-grid">
                        <div class="zls-form-group">
                            <label for="bfm_recipient_name">Full Name</label>
                            <input type="text" id="bfm_recipient_name" name="bfm[recipient_name]" placeholder="Recipient Name" required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_recipient_phone">Phone</label>
                            <input type="tel" id="bfm_recipient_phone" name="bfm[recipient_phone]" placeholder="+234" required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_recipient_email">Email</label>
                            <input type="email" id="bfm_recipient_email" name="bfm[recipient_email]" placeholder="email@example.com">
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="bfm_address_line1">Street Address</label>
                            <input type="text" id="bfm_address_line1" name="bfm[address_line1]" placeholder="123 Example Street" required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_city">City</label>
                            <input type="text" id="bfm_city" name="bfm[city]" placeholder="Ikeja, Lekki..." required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_state">State</label>
                            <input type="text" id="bfm_state" name="bfm[state]" value="Lagos State" required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_postal_code">Postal Code</label>
                            <input type="text" id="bfm_postal_code" name="bfm[postal_code]" placeholder="100001">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="bfm_landmark">Landmark</label>
                            <input type="text" id="bfm_landmark" name="bfm[landmark]" placeholder="Near Shoprite Mall">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="zls-submit-section">
                        <button type="submit" name="bfm_submit" class="zls-btn-submit">
                            Submit Purchase Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}