import re

def remove_console_logs(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace console.log, console.debug, console.warn with empty strings or comments
    # We use a regex to match console.xxx(...) including multi-line
    # This regex matches console.log, console.debug, console.info, console.warn
    # It tries to find the matching closing parenthesis.
    
    # A simpler approach: replace the lines starting with or containing console.log
    # But usually developers want them completely gone or commented.
    
    # Let's target the specific blocks the user mentioned first.
    
    # 1. PAYMENT STATUS CALCULATION block
    content = content.replace("console.log('=== PAYMENT STATUS CALCULATION (loadBookings) ===');", "// console.log('=== PAYMENT STATUS CALCULATION (loadBookings) ===');")
    content = content.replace("console.log('Booking ID:', b.booking_id);", "// console.log('Booking ID:', b.booking_id);")
    content = content.replace("console.log('Room Price:', roomPrice);", "// console.log('Room Price:', roomPrice);")
    content = content.replace("console.log('Breakfast Price:', breakfastPrice);", "// console.log('Breakfast Price:', breakfastPrice);")
    content = content.replace("console.log('Hygiene Kit Price:', hygieneKitPrice);", "// console.log('Hygiene Kit Price:', hygieneKitPrice);")
    content = content.replace("console.log('Additional Guest Charge:', additionalGuestCharge);", "// console.log('Additional Guest Charge:', additionalGuestCharge);")
    content = content.replace("console.log('Discount Amount:', discountAmount);", "// console.log('Discount Amount:', discountAmount);")
    content = content.replace("console.log('Extend Price:', extendPrice);", "// console.log('Extend Price:', extendPrice);")
    content = content.replace("console.log('Total Booking Amount:', totalBookingAmount);", "// console.log('Total Booking Amount:', totalBookingAmount);")
    content = content.replace("console.log('Total Paid (deposit + downpayment):', totalPaid);", "// console.log('Total Paid (deposit + downpayment):', totalPaid);")
    content = content.replace("console.log('=== END PAYMENT STATUS CALCULATION ===');", "// console.log('=== END PAYMENT STATUS CALCULATION ===');")
    
    # 2. Additional fields block
    content = content.replace("console.log('Booking ID:', booking.id, 'additional_food:', booking.additional_food, 'additional_items:', booking.additional_items);", "// console.log('Booking ID:', booking.id, 'additional_food:', booking.additional_food, 'additional_items:', booking.additional_items);")
    
    # 3. Other common logs
    content = re.sub(r'console\.log\(([\'"])(Promos loaded successfully:.*?)\1\);', r'// console.log(\1\2\1);', content)
    
    # General cleanup: comment out all console.log, console.debug, console.warn
    # But be careful about those inside strings (rare but possible)
    content = re.sub(r'(?<!// )(?<!//)console\.(log|debug|warn)\(', r'// console.\1(', content)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

if __name__ == "__main__":
    remove_console_logs(r'c:\xampp\htdocs\HMS\Booking.html')
