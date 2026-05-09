<?php
$post = get_post($request_id);
$meta = get_post_meta($request_id);
$addr = $meta['_zls_address_line1'][0] ?? '';
$city = $meta['_zls_city'][0] ?? '';
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
body { font-family: Arial, sans-serif; padding: 20px; }
.header { text-align: center; border-bottom: 2px solid #6B3AE4; padding-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
.total { background: #f9f9f9; font-weight: bold; }
</style></head>
<body>
<div class="header"><h2>Zephora Logistics Invoice</h2><p>Request #<?php echo $request_id; ?></p></div>
<p><strong>Customer:</strong> <?php echo get_the_author_meta('display_name', $post->post_author); ?></p>
<p><strong>Delivery Address:</strong> <?php echo $addr; ?>, <?php echo $city; ?></p>
<table>
    <tr><th>Item</th><td><?php echo $post->post_title; ?></td></tr>
    <tr><th>Service Fee</th><td>₦<?php echo number_format($meta['_zls_quote_amount'][0] ?? 0); ?></td></tr>
    <tr class="total"><td colspan="2">Total Due: ₦<?php echo number_format($meta['_zls_quote_amount'][0] ?? 0); ?></td></tr>
</table>
<p style="margin-top:20px; color:#888;">Generated on <?php echo date('F j, Y'); ?> | Paid: <?php echo $meta['_zls_status'][0] === 'paid' ? 'Yes' : 'Pending'; ?></p>
</body>
</html>