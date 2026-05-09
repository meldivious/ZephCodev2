<?php
/**
 * Zephora Logistic Systems - PDF Invoice
 * Requires: composer require dompdf/dompdf
 * Auto-saves to wp-content/uploads/zls-invoices/
 */
class ZLS_PDF {
    public static function init() {
        // Auto-create upload dir
        $dir = wp_upload_dir()['basedir'] . '/zls-invoices';
        if (!file_exists($dir)) wp_mkdir_p($dir);
    }

    public static function generate_invoice($request_id, $output = 'download') {
        if (!class_exists('Dompdf\Dompdf')) {
            if (class_exists('ZLS_Error_Handler')) {
                ZLS_Error_Handler::log_app_error(
                    'Dompdf library not installed',
                    array(
                        'request_id' => $request_id,
                        'solution' => 'Run: composer require dompdf/dompdf in plugin root'
                    ),
                    'error'
                );
            }
            return false;
        }
        $post = get_post($request_id);
        $user = get_userdata($post->post_author);
        $meta = get_post_meta($request_id);
        $quote = $meta['_zls_quote_amount'][0] ?? 0;
        $status = $meta['_zls_status'][0] ?? 'pending';
        $ref = $meta['_zls_payment_ref'][0] ?? '';
        
        ob_start(); ?>
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8">
        <style>
            body{font-family:system-ui,-apple-system,sans-serif;padding:40px;color:#111;line-height:1.5}
            .header{border-bottom:3px solid #6B3AE4;padding-bottom:20px;margin-bottom:30px}
            .header h1{margin:0;color:#6B3AE4;font-size:28px}
            .meta{display:flex;justify-content:space-between;margin-bottom:30px}
            .meta div{font-size:14px}
            table{width:100%;border-collapse:collapse;margin:20px 0}
            th,td{padding:12px;text-align:left;border-bottom:1px solid #eee}
            th{background:#f8f9fa;font-weight:600}
            .total{text-align:right;font-size:20px;font-weight:700;margin-top:20px;color:#6B3AE4}
            .footer{margin-top:50px;padding-top:20px;border-top:1px solid #ddd;font-size:12px;color:#666;text-align:center}
        </style></head><body>
        <div class="header"><h1>Zephora Logistics</h1><p>Official Invoice</p></div>
        <div class="meta">
            <div><strong>Invoice To:</strong><br><?php echo esc_html($user->display_name); ?><br><?php echo esc_html($meta['_zls_kyc_data'][0]['address'] ?? ''); ?></div>
            <div><strong>Invoice #:</strong> ZLS-<?php echo absint($request_id); ?><br><strong>Date:</strong> <?php echo date('F j, Y'); ?><br><strong>Status:</strong> <?php echo ucfirst(str_replace('_',' ',$status)); ?></div>
        </div>
        <table>
            <tr><th>Description</th><th>Amount</th></tr>
            <tr><td>Logistics & Delivery Service for: <?php echo esc_html($post->post_title); ?></td><td>₦<?php echo number_format($quote, 2); ?></td></tr>
        </table>
        <div class="total">Total: ₦<?php echo number_format($quote, 2); ?></div>
        <?php if($ref): ?><p style="margin-top:20px"><strong>Payment Ref:</strong> <?php echo esc_html($ref); ?></p><?php endif; ?>
        <div class="footer">Generated automatically by Zephora Logistics | support@zephora.logistics</div>
        </body></html><?php
        $html = ob_get_clean();
        
        $dompdf = new Dompdf\Dompdf(array('defaultPaperSize' => 'A4', 'defaultFont' => 'system-ui'));
        $dompdf->loadHtml($html);
        $dompdf->render();
        
        $filename = 'ZLS-Invoice-' . $request_id . '-' . date('Y-m-d') . '.pdf';
        $filepath = wp_upload_dir()['basedir'] . '/zls-invoices/' . $filename;
        file_put_contents($filepath, $dompdf->output());
        
        if ($output === 'download') {
            $dompdf->stream($filename, array('Attachment' => true));
            exit;
        }
        return $filepath;
    }
}