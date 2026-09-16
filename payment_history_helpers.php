<?php
/**
 * Payment History Helper Functions
 * 
 * These functions help manage multiple payment timestamps stored in payment_date_time column
 * Format: "2026-01-18 10:10:00|2026-01-18 23:04:00|2026-01-19 14:30:00"
 */

/**
 * Parse payment_date_time string into an array of timestamps
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return array Array of DateTime objects
 */
function parsePaymentHistory($payment_date_time)
{
    if (empty($payment_date_time)) {
        return [];
    }

    $timestamps = explode('|', $payment_date_time);
    $payments = [];

    foreach ($timestamps as $timestamp) {
        $timestamp = trim($timestamp);
        if (!empty($timestamp)) {
            try {
                $payments[] = new DateTime($timestamp);
            } catch (Exception $e) {
                error_log("Invalid payment timestamp: $timestamp");
            }
        }
    }

    return $payments;
}

/**
 * Format payment history for display
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @param string $format Date format (default: 'm/d/Y h:i A')
 * @param string $separator Separator between payments (default: '<br>')
 * @return string Formatted payment history
 */
function formatPaymentHistory($payment_date_time, $format = 'm/d/Y h:i A', $separator = '<br>')
{
    $payments = parsePaymentHistory($payment_date_time);

    if (empty($payments)) {
        return '-';
    }

    $formatted = [];
    foreach ($payments as $index => $payment) {
        $formatted[] = ($index + 1) . '. ' . $payment->format($format);
    }

    return implode($separator, $formatted);
}

/**
 * Get the count of payments made
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return int Number of payments
 */
function getPaymentCount($payment_date_time)
{
    return count(parsePaymentHistory($payment_date_time));
}

/**
 * Get the first payment date
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return DateTime|null First payment date or null
 */
function getFirstPaymentDate($payment_date_time)
{
    $payments = parsePaymentHistory($payment_date_time);
    return !empty($payments) ? $payments[0] : null;
}

/**
 * Get the last (most recent) payment date
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return DateTime|null Last payment date or null
 */
function getLastPaymentDate($payment_date_time)
{
    $payments = parsePaymentHistory($payment_date_time);
    return !empty($payments) ? end($payments) : null;
}

/**
 * Append a new payment timestamp to existing payment history
 * 
 * @param string|null $existing_payment_date_time Current payment_date_time value
 * @param string|null $new_timestamp New timestamp to append (default: current time)
 * @return string Updated payment_date_time string
 */
function appendPaymentTimestamp($existing_payment_date_time, $new_timestamp = null)
{
    if ($new_timestamp === null) {
        $new_timestamp = date('Y-m-d H:i:s');
    }

    if (empty($existing_payment_date_time)) {
        return $new_timestamp;
    }

    return $existing_payment_date_time . '|' . $new_timestamp;
}

/**
 * Format payment history for export/reports with payment numbers
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return string Formatted payment history for export
 */
function formatPaymentHistoryForExport($payment_date_time)
{
    $payments = parsePaymentHistory($payment_date_time);

    if (empty($payments)) {
        return 'No payments recorded';
    }

    $formatted = [];
    foreach ($payments as $index => $payment) {
        $formatted[] = 'Payment ' . ($index + 1) . ': ' . $payment->format('m/d/Y h:i A');
    }

    return implode('; ', $formatted);
}

/**
 * Get payment history as JSON array
 * 
 * @param string|null $payment_date_time The payment_date_time value from database
 * @return string JSON array of payment timestamps
 */
function getPaymentHistoryJSON($payment_date_time)
{
    $payments = parsePaymentHistory($payment_date_time);

    $timestamps = [];
    foreach ($payments as $payment) {
        $timestamps[] = $payment->format('Y-m-d H:i:s');
    }

    return json_encode($timestamps);
}
?>