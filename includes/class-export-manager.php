<?php
/**
 * Zephora Logistic Systems - Export Manager
 * Export reports and analytics to CSV, Excel, and PDF
 */
class ZLS_Export_Manager {
    public static function init() {
        add_action('wp_ajax_zls_export_requests', array(__CLASS__, 'ajax_export_requests'));
        add_action('wp_ajax_zls_export_report', array(__CLASS__, 'ajax_export_report'));
    }

    /**
     * AJAX: Export requests to CSV
     */
    public static function ajax_export_requests() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        check_ajax_referer('zls_ajax', 'nonce');

        $format = sanitize_text_field($_POST['format'] ?? 'csv'); // csv or excel
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        // Fetch requests
        $args = array(
            'post_type' => array('zls_ship', 'zls_buy'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $meta_query = array('relation' => 'AND');

        if ($status) {
            $meta_query[] = array(
                'key' => '_zls_status',
                'value' => $status,
                'compare' => '='
            );
        }

        if ($type && in_array($type, array('zls_ship', 'zls_buy'))) {
            $args['post_type'] = $type;
        }

        if ($date_from) {
            $args['date_query'][] = array(
                'after' => $date_from,
                'inclusive' => true
            );
        }

        if ($date_to) {
            $args['date_query'][] = array(
                'before' => $date_to,
                'inclusive' => true
            );
        }

        if (!empty($meta_query) && count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $posts = $query->posts;

        if (empty($posts)) {
            wp_send_json_error('No requests found matching criteria');
        }

        $data = self::prepare_export_data($posts);

        if ($format === 'excel') {
            self::export_to_excel($data, 'Zephora_Logistics_Report_' . current_time('Y-m-d'));
        } else {
            self::export_to_csv($data, 'Zephora_Logistics_Report_' . current_time('Y-m-d'));
        }

        exit;
    }

    /**
     * AJAX: Export report (statistics and analytics)
     */
    public static function ajax_export_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        check_ajax_referer('zls_ajax', 'nonce');

        $format = sanitize_text_field($_POST['format'] ?? 'csv'); // csv or pdf
        $report_type = sanitize_text_field($_POST['report_type'] ?? 'summary'); // summary, status, revenue, etc.
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30'); // 7, 30, 90, 365

        $report_data = self::generate_report($report_type, intval($date_range));

        if ($format === 'pdf') {
            self::export_to_pdf($report_data, 'Zephora_Analytics_Report_' . current_time('Y-m-d'));
        } else {
            self::export_report_to_csv($report_data, 'Zephora_Analytics_Report_' . current_time('Y-m-d'));
        }

        exit;
    }

    /**
     * Prepare request data for export
     */
    private static function prepare_export_data($posts) {
        $headers = array(
            'ID',
            'Type',
            'Title',
            'Customer',
            'Status',
            'Amount (NGN)',
            'Amount (USD)',
            'Date Created',
            'Date Modified',
            'Tracking Number',
            'Payment Status'
        );

        $rows = array();

        foreach ($posts as $post) {
            $user = get_userdata($post->post_author);
            $status = get_post_meta($post->ID, '_zls_status', true) ?: 'pending';
            $quote_amount = floatval(get_post_meta($post->ID, '_zls_quote_amount', true) ?? 0);
            $quote_usd = $quote_amount / 1500;
            $tracking = get_post_meta($post->ID, '_zls_tracking_number', true) ?: 'N/A';

            $rows[] = array(
                $post->ID,
                $post->post_type === 'zls_ship' ? 'Ship' : 'Buy',
                $post->post_title,
                $user ? $user->display_name : 'N/A',
                ucfirst(str_replace('_', ' ', $status)),
                number_format($quote_amount, 2),
                number_format($quote_usd, 2),
                $post->post_date,
                $post->post_modified,
                $tracking,
                $status === 'paid' ? 'Paid' : 'Pending'
            );
        }

        return array('headers' => $headers, 'rows' => $rows);
    }

    /**
     * Generate analytics report
     */
    private static function generate_report($type, $days = 30) {
        $from_date = date('Y-m-d', strtotime("-{$days} days"));

        $query = new WP_Query(array(
            'post_type' => array('zls_ship', 'zls_buy'),
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $from_date,
                    'inclusive' => true
                )
            )
        ));

        $posts = $query->posts;

        $report = array(
            'generated_at' => current_time('Y-m-d H:i:s'),
            'period' => "{$from_date} to " . date('Y-m-d'),
            'total_requests' => count($posts),
            'total_revenue_ngn' => 0,
            'total_revenue_usd' => 0,
            'by_status' => array(),
            'by_type' => array(),
            'by_customer' => array(),
            'daily_breakdown' => array()
        );

        foreach ($posts as $post) {
            $status = get_post_meta($post->ID, '_zls_status', true) ?: 'pending';
            $amount_ngn = floatval(get_post_meta($post->ID, '_zls_quote_amount', true) ?? 0);
            $amount_usd = $amount_ngn / 1500;
            $type_label = $post->post_type === 'zls_ship' ? 'Ship' : 'Buy';
            $date = substr($post->post_date, 0, 10);

            // Totals
            $report['total_revenue_ngn'] += $amount_ngn;
            $report['total_revenue_usd'] += $amount_usd;

            // By status
            if (!isset($report['by_status'][$status])) {
                $report['by_status'][$status] = array('count' => 0, 'revenue_ngn' => 0, 'revenue_usd' => 0);
            }
            $report['by_status'][$status]['count']++;
            $report['by_status'][$status]['revenue_ngn'] += $amount_ngn;
            $report['by_status'][$status]['revenue_usd'] += $amount_usd;

            // By type
            if (!isset($report['by_type'][$type_label])) {
                $report['by_type'][$type_label] = array('count' => 0, 'revenue_ngn' => 0, 'revenue_usd' => 0);
            }
            $report['by_type'][$type_label]['count']++;
            $report['by_type'][$type_label]['revenue_ngn'] += $amount_ngn;
            $report['by_type'][$type_label]['revenue_usd'] += $amount_usd;

            // By customer
            $user = get_userdata($post->post_author);
            $customer_name = $user ? $user->display_name : 'Unknown';
            if (!isset($report['by_customer'][$customer_name])) {
                $report['by_customer'][$customer_name] = array('count' => 0, 'revenue_ngn' => 0);
            }
            $report['by_customer'][$customer_name]['count']++;
            $report['by_customer'][$customer_name]['revenue_ngn'] += $amount_ngn;

            // Daily breakdown
            if (!isset($report['daily_breakdown'][$date])) {
                $report['daily_breakdown'][$date] = array('count' => 0, 'revenue_ngn' => 0);
            }
            $report['daily_breakdown'][$date]['count']++;
            $report['daily_breakdown'][$date]['revenue_ngn'] += $amount_ngn;
        }

        return $report;
    }

    /**
     * Export to CSV
     */
    private static function export_to_csv($data, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Write headers
        fputcsv($output, $data['headers']);

        // Write rows
        foreach ($data['rows'] as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }

    /**
     * Export report to CSV
     */
    private static function export_report_to_csv($report, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Write report metadata
        fputcsv($output, array('Report Generated: ' . $report['generated_at']));
        fputcsv($output, array('Period: ' . $report['period']));
        fputcsv($output, array(''));

        // Write summary
        fputcsv($output, array('SUMMARY'));
        fputcsv($output, array('Total Requests', $report['total_requests']));
        fputcsv($output, array('Total Revenue (NGN)', number_format($report['total_revenue_ngn'], 2)));
        fputcsv($output, array('Total Revenue (USD)', number_format($report['total_revenue_usd'], 2)));
        fputcsv($output, array(''));

        // By status
        fputcsv($output, array('BY STATUS'));
        fputcsv($output, array('Status', 'Count', 'Revenue (NGN)', 'Revenue (USD)'));
        foreach ($report['by_status'] as $status => $data) {
            fputcsv($output, array(
                ucfirst(str_replace('_', ' ', $status)),
                $data['count'],
                number_format($data['revenue_ngn'], 2),
                number_format($data['revenue_usd'], 2)
            ));
        }
        fputcsv($output, array(''));

        // By type
        fputcsv($output, array('BY TYPE'));
        fputcsv($output, array('Type', 'Count', 'Revenue (NGN)', 'Revenue (USD)'));
        foreach ($report['by_type'] as $type => $data) {
            fputcsv($output, array(
                $type,
                $data['count'],
                number_format($data['revenue_ngn'], 2),
                number_format($data['revenue_usd'], 2)
            ));
        }
        fputcsv($output, array(''));

        // Top customers
        fputcsv($output, array('TOP CUSTOMERS'));
        fputcsv($output, array('Customer', 'Orders', 'Revenue (NGN)'));
        
        // Prepare customer data for sorting
        $customers_array = array();
        foreach ($report['by_customer'] as $customer => $data) {
            $customers_array[] = array(
                'name' => $customer,
                'count' => $data['count'],
                'revenue' => $data['revenue_ngn']
            );
        }
        
        // Sort by revenue descending
        usort($customers_array, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        // Output top 10
        foreach (array_slice($customers_array, 0, 10) as $customer_data) {
            fputcsv($output, array($customer_data['name'], $customer_data['count'], number_format($customer_data['revenue'], 2)));
        }

        fclose($output);
    }

    /**
     * Export to Excel (using CSV format compatible with Excel)
     * For true Excel files, would need PHPExcel or similar library
     */
    private static function export_to_excel($data, $filename) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write headers
        fputcsv($output, $data['headers']);

        // Write rows
        foreach ($data['rows'] as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }

    /**
     * Export to PDF (basic implementation)
     * For more advanced PDFs, integrate with Dompdf
     */
    private static function export_to_pdf($report, $filename) {
        // Check if Dompdf is available
        if (!class_exists('Dompdf\Dompdf')) {
            wp_die('PDF export requires Dompdf library to be installed');
        }

        $dompdf = new \Dompdf\Dompdf();

        $html = self::generate_pdf_content($report);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');

        echo $dompdf->output();
    }

    /**
     * Generate PDF content
     */
    private static function generate_pdf_content($report) {
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                h1 { color: #6B3AE4; border-bottom: 3px solid #6B3AE4; padding-bottom: 10px; }
                h2 { color: #6B3AE4; margin-top: 30px; font-size: 16px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f8f9fa; font-weight: bold; }
                .summary-item { padding: 10px; background: #f9f9f9; margin: 5px 0; border-left: 4px solid #6B3AE4; }
                .summary-item strong { color: #6B3AE4; }
            </style>
        </head>
        <body>
            <h1>Zephora Logistics Analytics Report</h1>
            <div class="summary-item">
                <p><strong>Generated:</strong> ' . $report['generated_at'] . '</p>
                <p><strong>Period:</strong> ' . $report['period'] . '</p>
            </div>

            <h2>Summary</h2>
            <div class="summary-item">
                <p><strong>Total Requests:</strong> ' . $report['total_requests'] . '</p>
                <p><strong>Total Revenue (NGN):</strong> ₦' . number_format($report['total_revenue_ngn'], 2) . '</p>
                <p><strong>Total Revenue (USD):</strong> $' . number_format($report['total_revenue_usd'], 2) . '</p>
            </div>

            <h2>By Status</h2>
            <table>
                <tr><th>Status</th><th>Count</th><th>Revenue (NGN)</th><th>Revenue (USD)</th></tr>';

        foreach ($report['by_status'] as $status => $data) {
            $html .= '<tr>
                <td>' . ucfirst(str_replace('_', ' ', $status)) . '</td>
                <td>' . $data['count'] . '</td>
                <td>₦' . number_format($data['revenue_ngn'], 2) . '</td>
                <td>$' . number_format($data['revenue_usd'], 2) . '</td>
            </tr>';
        }

        $html .= '</table>

            <h2>By Type</h2>
            <table>
                <tr><th>Type</th><th>Count</th><th>Revenue (NGN)</th><th>Revenue (USD)</th></tr>';

        foreach ($report['by_type'] as $type => $data) {
            $html .= '<tr>
                <td>' . $type . '</td>
                <td>' . $data['count'] . '</td>
                <td>₦' . number_format($data['revenue_ngn'], 2) . '</td>
                <td>$' . number_format($data['revenue_usd'], 2) . '</td>
            </tr>';
        }

        $html .= '</table>
        </body>
        </html>';

        return $html;
    }
}