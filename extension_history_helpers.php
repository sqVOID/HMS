<?php
/**
 * Extension History Helper Functions
 * 
 * These functions help manage multiple extension timestamps stored in extension_time_at column
 * Format: "2026-01-18 10:10:00|2026-01-18 23:04:00|2026-01-19 14:30:00"
 */

/**
 * Parse extension_time_at string into an array of timestamps
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return array Array of DateTime objects
 */
function parseExtensionHistory($extension_time_at) {
    if (empty($extension_time_at)) {
        return [];
    }
    
    $timestamps = explode('|', $extension_time_at);
    $extensions = [];
    
    foreach ($timestamps as $timestamp) {
        $timestamp = trim($timestamp);
        if (!empty($timestamp)) {
            try {
                $extensions[] = new DateTime($timestamp);
            } catch (Exception $e) {
                error_log("Invalid extension timestamp: $timestamp");
            }
        }
    }
    
    return $extensions;
}

/**
 * Format extension history for display
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @param string $format Date format (default: 'm/d/Y h:i A')
 * @param string $separator Separator between extensions (default: '<br>')
 * @return string Formatted extension history
 */
function formatExtensionHistory($extension_time_at, $format = 'm/d/Y h:i A', $separator = '<br>') {
    $extensions = parseExtensionHistory($extension_time_at);
    
    if (empty($extensions)) {
        return '-';
    }
    
    $formatted = [];
    foreach ($extensions as $index => $extension) {
        $formatted[] = ($index + 1) . '. ' . $extension->format($format);
    }
    
    return implode($separator, $formatted);
}

/**
 * Get the count of extensions made
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return int Number of extensions
 */
function getExtensionCount($extension_time_at) {
    return count(parseExtensionHistory($extension_time_at));
}

/**
 * Get the first extension date
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return DateTime|null First extension date or null
 */
function getFirstExtensionDate($extension_time_at) {
    $extensions = parseExtensionHistory($extension_time_at);
    return !empty($extensions) ? $extensions[0] : null;
}

/**
 * Get the last (most recent) extension date
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return DateTime|null Last extension date or null
 */
function getLastExtensionDate($extension_time_at) {
    $extensions = parseExtensionHistory($extension_time_at);
    return !empty($extensions) ? end($extensions) : null;
}

/**
 * Append a new extension timestamp to existing extension history
 * 
 * @param string|null $existing_extension_time_at Current extension_time_at value
 * @param string|null $new_timestamp New timestamp to append (default: current time)
 * @return string Updated extension_time_at string
 */
function appendExtensionTimestamp($existing_extension_time_at, $new_timestamp = null) {
    if ($new_timestamp === null) {
        $new_timestamp = date('Y-m-d H:i:s');
    }
    
    if (empty($existing_extension_time_at)) {
        return $new_timestamp;
    }
    
    return $existing_extension_time_at . '|' . $new_timestamp;
}

/**
 * Format extension history for export/reports with extension numbers
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return string Formatted extension history for export
 */
function formatExtensionHistoryForExport($extension_time_at) {
    $extensions = parseExtensionHistory($extension_time_at);
    
    if (empty($extensions)) {
        return 'No extensions recorded';
    }
    
    $formatted = [];
    foreach ($extensions as $index => $extension) {
        $formatted[] = 'Extension ' . ($index + 1) . ': ' . $extension->format('m/d/Y h:i A');
    }
    
    return implode('; ', $formatted);
}

/**
 * Get extension history as JSON array
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return string JSON array of extension timestamps
 */
function getExtensionHistoryJSON($extension_time_at) {
    $extensions = parseExtensionHistory($extension_time_at);
    
    $timestamps = [];
    foreach ($extensions as $extension) {
        $timestamps[] = $extension->format('Y-m-d H:i:s');
    }
    
    return json_encode($timestamps);
}

/**
 * Format extension history with duration details
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @param array $extension_details Array of extension details (hours, minutes, price)
 * @param string $format Date format (default: 'm/d/Y h:i A')
 * @param string $separator Separator between extensions (default: '<br>')
 * @return string Formatted extension history with details
 */
function formatExtensionHistoryWithDetails($extension_time_at, $extension_details = [], $format = 'm/d/Y h:i A', $separator = '<br>') {
    $extensions = parseExtensionHistory($extension_time_at);
    
    if (empty($extensions)) {
        return '-';
    }
    
    $formatted = [];
    foreach ($extensions as $index => $extension) {
        $detail = '';
        if (isset($extension_details[$index])) {
            $hours = $extension_details[$index]['hours'] ?? 0;
            $minutes = $extension_details[$index]['minutes'] ?? 0;
            $price = $extension_details[$index]['price'] ?? 0;
            
            $duration = '';
            if ($hours > 0) $duration .= $hours . 'h ';
            if ($minutes > 0) $duration .= $minutes . 'm';
            $duration = trim($duration);
            
            if ($duration && $price > 0) {
                $detail = " (+{$duration}, ₱" . number_format($price, 2) . ")";
            } elseif ($duration) {
                $detail = " (+{$duration})";
            } elseif ($price > 0) {
                $detail = " (₱" . number_format($price, 2) . ")";
            }
        }
        
        $formatted[] = ($index + 1) . '. ' . $extension->format($format) . $detail;
    }
    
    return implode($separator, $formatted);
}

/**
 * Get time between extensions
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @return array Array of intervals between extensions
 */
function getExtensionIntervals($extension_time_at) {
    $extensions = parseExtensionHistory($extension_time_at);
    
    if (count($extensions) < 2) {
        return [];
    }
    
    $intervals = [];
    for ($i = 1; $i < count($extensions); $i++) {
        $interval = $extensions[$i-1]->diff($extensions[$i]);
        $intervals[] = $interval;
    }
    
    return $intervals;
}

/**
 * Check if extension was made within a time period
 * 
 * @param string|null $extension_time_at The extension_time_at value from database
 * @param string $period Period to check (e.g., '1 hour', '30 minutes', '1 day')
 * @return bool True if extension was made within the period
 */
function hasRecentExtension($extension_time_at, $period = '1 hour') {
    $lastExtension = getLastExtensionDate($extension_time_at);
    
    if (!$lastExtension) {
        return false;
    }
    
    $cutoff = new DateTime();
    $cutoff->modify("-{$period}");
    
    return $lastExtension > $cutoff;
}
?>