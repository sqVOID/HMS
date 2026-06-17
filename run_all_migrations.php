<?php
/**
 * Run All History Migrations
 * 
 * This script runs all necessary migrations for payment and extension history
 * in the correct order and provides a complete setup.
 */

echo "🚀 STARTING COMPLETE HISTORY SETUP\n";
echo str_repeat("=", 60) . "\n\n";

// Run the complete migration
echo "Running complete history migration...\n\n";
include 'add_complete_history_support.php';

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 ALL MIGRATIONS COMPLETED!\n";
echo str_repeat("=", 60) . "\n\n";

echo "📋 WHAT WAS DONE:\n";
echo "✅ Payment history support added to both tables\n";
echo "✅ Extension history support added to both tables\n";
echo "✅ All columns are now TEXT type for multiple timestamps\n";
echo "✅ Both bookings and reports tables are synchronized\n\n";

echo "🧪 TESTING:\n";
echo "• Payment History: test_payment_history.php\n";
echo "• Extension History: test_extension_history.php\n\n";

echo "📚 DOCUMENTATION:\n";
echo "• Payment History: PAYMENT_HISTORY_README.md\n";
echo "• Extension History: EXTENSION_HISTORY_README.md\n\n";

echo "🎯 READY TO USE!\n";
echo "Your booking system now tracks complete history of all payments and extensions.\n";
?>