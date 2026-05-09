<?php
/**
 * Zephora Logistic Systems - Ship For Me Handler
 */
class ZLS_Ship_For_Me extends ZLS_Request_Base {
    
    /**
     * Submit shipping request
     */
    public static function submit() {
        // Verify nonce
        if (!isset($_POST['zls_ship_nonce']) || !wp_verify_nonce($_POST['zls_ship_nonce'], 'zls_ship')) {
            return new WP_Error('invalid_nonce', 'Security check failed');
        }

        // Get form data
        $data = $_POST['sfm'] ?? [];
        
        // Handle submission via parent class
        $result = self::handle_submission('zls_ship', $data);
        return $result;
    }
    
    
    
    /**
     * Render Ship For Me form
     */
    public static function render() {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login'));
            exit;
        }

        // Check for success message
        $show_success = isset($_GET['sfm_ok']) && $_GET['sfm_ok'] === '1';
        
        // Get error if any
        global $ship_error;
        $error_message = $ship_error ?? '';

        ob_start();
        ?>
        <style>
            .zls-ship-for-me-container {
                max-width: 1220px;
                margin: 0px auto;
                padding: 40px 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .zls-ship-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 32px;
            }
            
            .zls-ship-title {
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
                color: #6B3AE4;
                font-weight: 600;
                font-size: 14px;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .zls-btn-back:hover {
                background: #f9fafb;
                border-color: #6B3AE4;
            }
            
            .zls-btn-back svg {
                width: 16px;
                height: 16px;
            }
            
            .zls-ship-card {
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
                background: #f3f0ff;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6B3AE4;
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
                border-color: #6B3AE4;
                background: #fff;
                box-shadow: 0 0 0 3px rgba(107,58,228,0.1);
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
                background: #6B3AE4;
                color: #fff;
                border: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.2s;
            }
            
            .zls-btn-submit:hover {
                background: #5a2dcf;
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
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 768px) {
                .zls-form-grid {
                    grid-template-columns: 1fr;
                }
                
                .zls-ship-card {
                    padding: 24px;
                }
                
                .zls-ship-header {
                    flex-direction: column;
                    gap: 16px;
                    align-items: flex-start;
                }
            }
        </style>
        
        <div class="zls-ship-for-me-container">
            <!-- Header with Back Button -->
            <div class="zls-ship-header">
                <h1 class="zls-ship-title">Ship For Me</h1>
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
                ✅ Shipping request submitted successfully! We'll review and send you a quote soon.
            </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
            <div class="zls-error-message">
                ❌ <?php echo esc_html($error_message); ?>
            </div>
            <?php endif; ?>
            
            <form method="post" class="zls-ship-form">
                <?php wp_nonce_field('zls_ship', 'zls_ship_nonce'); ?>
                
                <!-- Product Information Section -->
                <div class="zls-ship-card">
                    <div class="zls-section-header">
                        
                        <h2 class="zls-section-title">Product Information</h2>
                    </div>
                    
                    <div class="zls-form-grid">
                        <div class="zls-form-group">
                            <label for="sfm_product_name">Product Name</label>
                            <input type="text" 
                                   id="sfm_product_name" 
                                   name="sfm[product_name]" 
                                   placeholder="e.g., MacBook Pro 14-inch" 
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_tracking_number">Tracking / Order #</label>
                            <input type="text" 
                                   id="sfm_tracking_number" 
                                   name="sfm[tracking_number]" 
                                   placeholder="e.g., UPS-123456789">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_product_url">Product URL</label>
                            <input type="url" 
                                   id="sfm_product_url" 
                                   name="sfm[product_url]" 
                                   placeholder="https://amazon.com/item...">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_packages">Packages</label>
                            <div class="zls-input-with-label">
                                <input type="number" 
                                       id="sfm_packages" 
                                       name="sfm[packages]" 
                                       min="1" 
                                       value="1"
                                       placeholder="1">
                                <span class="zls-input-suffix">Qty</span>
                            </div>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_weight_kg">Weight (KG)</label>
                            <div class="zls-input-with-label">
                                <input type="number" 
                                       id="sfm_weight_kg" 
                                       name="sfm[weight_kg]" 
                                       step="0.1" 
                                       min="0"
                                       placeholder="0.00"
                                       required>
                                <span class="zls-input-suffix">KG</span>
                            </div>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_value_usd">Amount/Value ($)</label>
                            <div class="zls-input-with-label">
                                <input type="number" 
                                       id="sfm_value_usd" 
                                       name="sfm[value_usd]" 
                                       step="0.01" 
                                       min="0"
                                       placeholder="0.00"
                                       required>
                                <span class="zls-input-suffix">$</span>
                            </div>
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="sfm_content_desc">Content Description</label>
                            <textarea id="sfm_content_desc" 
                                      name="sfm[content_desc]" 
                                      placeholder="Briefly describe the contents of the package..."
                                      required
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Nigeria Delivery Address Section -->
                <div class="zls-ship-card">
                    <div class="zls-section-header">
                        
                        <h2 class="zls-section-title">Nigeria Delivery Address</h2>
                    </div>
                    
                    <div class="zls-form-grid">
                        <div class="zls-form-group">
                            <label for="sfm_recipient_name">Full Name</label>
                            <input type="text" 
                                   id="sfm_recipient_name" 
                                   name="sfm[recipient_name]" 
                                   placeholder="Recipient Name"
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_recipient_phone">Phone</label>
                            <input type="tel" 
                                   id="sfm_recipient_phone" 
                                   name="sfm[recipient_phone]" 
                                   placeholder="+234"
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_recipient_email">Email</label>
                            <input type="email" 
                                   id="sfm_recipient_email" 
                                   name="sfm[recipient_email]" 
                                   placeholder="email@example.com">
                        </div>
                        
                        <div class="zls-form-group full-width">
                            <label for="sfm_address_line1">Street Address</label>
                            <input type="text" 
                                   id="sfm_address_line1" 
                                   name="sfm[address_line1]" 
                                   placeholder="123 Example Street"
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_city">City</label>
                            <input type="text" 
                                   id="sfm_city" 
                                   name="sfm[city]" 
                                   placeholder="Ikeja, Lekki..."
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_state">State</label>
                            <input type="text" 
                                   id="sfm_state" 
                                   name="sfm[state]" 
                                   value="Lagos State"
                                   required>
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_postal_code">Postal Code</label>
                            <input type="text" 
                                   id="sfm_postal_code" 
                                   name="sfm[postal_code]" 
                                   placeholder="100001">
                        </div>
                        
                        <div class="zls-form-group">
                            <label for="sfm_landmark">Landmark</label>
                            <input type="text" 
                                   id="sfm_landmark" 
                                   name="sfm[landmark]" 
                                   placeholder="Near Shoprite Mall">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="zls-submit-section">
                        <button type="submit" name="sfm_submit" class="zls-btn-submit">
                            Submit Shipping Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}