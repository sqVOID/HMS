import json
import re

file_path = 'c:/xampp/htdocs/HMS/Booking.html'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Fix isCashLikeMethod to NOT include Instapay, Online Banking, and Airbnb
content = content.replace(
    "return method === 'Cash' || method === 'Instapay' || method === 'Online Banking' || method === 'Airbnb';",
    "return method === 'Cash';"
)

# 2. Fix the editBookingForm payment loop inside "hasEditPaymentData"
# Let's use regex to replace it
target1 = r'''                    depositCash = 0;
                    depositGCash = 0;
                    depositMaya = 0;

                    payments.forEach\(p => \{
                        if \(isCashLikeMethod\(p\.method\)\) \{
                            depositCash \+= p\.amount;
                        \} else if \(p\.method === 'G-cash'\) \{
                            depositGCash \+= p\.amount;
                        \} else if \(p\.method === 'Maya'\) \{
                            depositMaya \+= p\.amount;
                        \}
                    \}\);'''

replacement1 = '''                    depositCash = 0;
                    depositGCash = 0;
                    depositMaya = 0;
                    let depositInstapay = 0;
                    let depositOnlineBanking = 0;
                    let depositAirbnb = 0;

                    payments.forEach(p => {
                        if (p.method === 'Instapay') {
                            depositInstapay += p.amount;
                        } else if (p.method === 'Online Banking') {
                            depositOnlineBanking += p.amount;
                        } else if (p.method === 'Airbnb') {
                            depositAirbnb += p.amount;
                        } else if (isCashLikeMethod(p.method)) {
                            depositCash += p.amount;
                        } else if (p.method === 'G-cash') {
                            depositGCash += p.amount;
                        } else if (p.method === 'Maya') {
                            depositMaya += p.amount;
                        }
                    });'''

content = re.sub(target1, replacement1, content)

# 3. Add to depositRatio block
target2 = r'''                    const actualDepositCash = depositCash \* depositRatio;
                    const actualDepositGCash = depositGCash \* depositRatio;
                    const actualDepositMaya = depositMaya \* depositRatio;

                    // Set cumulative deposit totals \(existing \+ newly applied payment\)\.
                    // update_booking\.php stores running totals in deposit/deposit_\* fields\.
                    actualDeposit = existingDepositTotal \+ actualPaymentApplied;
                    depositCash = existingDepositCash \+ actualDepositCash;
                    depositGCash = existingDepositGCash \+ actualDepositGCash;
                    depositMaya = existingDepositMaya \+ actualDepositMaya;'''

replacement2 = '''                    const actualDepositCash = depositCash * depositRatio;
                    const actualDepositGCash = depositGCash * depositRatio;
                    const actualDepositMaya = depositMaya * depositRatio;
                    const actualDepositInstapay = depositInstapay * depositRatio;
                    const actualDepositOnlineBanking = depositOnlineBanking * depositRatio;
                    const actualDepositAirbnb = depositAirbnb * depositRatio;

                    // Set cumulative deposit totals (existing + newly applied payment).
                    // update_booking.php stores running totals in deposit/deposit_* fields.
                    const existingDepositInstapay = booking ? (parseFloat(booking.deposit_instapay) || 0) : 0;
                    const existingDepositOnlineBanking = booking ? (parseFloat(booking.deposit_online_banking) || 0) : 0;
                    const existingDepositAirbnb = booking ? (parseFloat(booking.deposit_airbnb) || 0) : 0;
                    
                    actualDeposit = existingDepositTotal + existingDepositInstapay + existingDepositOnlineBanking + existingDepositAirbnb + actualPaymentApplied;
                    depositCash = existingDepositCash + actualDepositCash;
                    depositGCash = existingDepositGCash + actualDepositGCash;
                    depositMaya = existingDepositMaya + actualDepositMaya;
                    let depositInstapayCombined = existingDepositInstapay + actualDepositInstapay;
                    let depositOnlineBankingCombined = existingDepositOnlineBanking + actualDepositOnlineBanking;
                    let depositAirbnbCombined = existingDepositAirbnb + actualDepositAirbnb;'''

content = re.sub(target2, replacement2, content)

# 4. Fix fallback deposit data extraction
target3 = r'''                if \(booking && \(booking\.deposit_cash \|\| booking\.deposit_g_cash \|\| booking\.deposit_maya\)\) \{
                    // CRITICAL FIX: Get deposit data from booking object if hidden fields are empty
                    console\.log\('No depositDataStr found, extracting from booking object'\);
                    depositCash = parseFloat\(booking\.deposit_cash\) \|\| 0;
                    depositGCash = parseFloat\(booking\.deposit_g_cash\) \|\| 0;
                    depositMaya = parseFloat\(booking\.deposit_maya\) \|\| 0;
                    gcashRef = booking\.deposit_gcash_ref \|\| '';
                    mayaRef = booking\.deposit_maya_ref \|\| '';
                    actualDeposit = depositCash \+ depositGCash \+ depositMaya;'''

replacement3 = '''                if (booking && (booking.deposit_cash || booking.deposit_g_cash || booking.deposit_maya || booking.deposit_instapay || booking.deposit_online_banking || booking.deposit_airbnb)) {
                    console.log('No depositDataStr found, extracting from booking object');
                    depositCash = parseFloat(booking.deposit_cash) || 0;
                    depositGCash = parseFloat(booking.deposit_g_cash) || 0;
                    depositMaya = parseFloat(booking.deposit_maya) || 0;
                    depositInstapayCombined = parseFloat(booking.deposit_instapay) || 0;
                    depositOnlineBankingCombined = parseFloat(booking.deposit_online_banking) || 0;
                    depositAirbnbCombined = parseFloat(booking.deposit_airbnb) || 0;
                    gcashRef = booking.deposit_gcash_ref || '';
                    mayaRef = booking.deposit_maya_ref || '';
                    referenceNoInstapay = booking.deposit_instapay_ref || '';
                    referenceNoOnlineBanking = booking.deposit_online_banking_ref || '';
                    referenceNoAirbnb = booking.deposit_airbnb_ref || '';
                    actualDeposit = depositCash + depositGCash + depositMaya + depositInstapayCombined + depositOnlineBankingCombined + depositAirbnbCombined;'''

content = re.sub(target3, replacement3, content)

# 5. Fix recalculating deposit from breakdown and setting formdata
target4 = r'''            // CRITICAL FIX: Recalculate actualDeposit from breakdown fields to ensure accuracy
            // This prevents issues where raw payment amount differs from actual deposit after discount
            actualDeposit = depositCash \+ depositGCash \+ depositMaya;
            console\.log\('actualDeposit \(recalculated from breakdown\):', actualDeposit\);

            formData\.set\('deposit', actualDeposit\);

            console\.log\('=== PAYMENT DATA BEING SENT TO BACKEND ==='\);
            console\.log\('actualDeposit:', actualDeposit\);
            console\.log\('depositCash:', depositCash\);
            console\.log\('depositGCash:', depositGCash\);
            console\.log\('depositMaya:', depositMaya\);
            console\.log\('paid_status:', formData\.get\('paid_status'\)\);
            console\.log\('=== END PAYMENT DATA ==='\);

            // Build deposit_details string
            let depositDetailsParts = \[\];
            if \(depositCash > 0\) \{
                depositDetailsParts\.push\(depositCash\.toFixed\(2\) \+ ' Cash'\);
            \}
            if \(depositGCash > 0\) \{
                let gcashPart = depositGCash\.toFixed\(2\) \+ ' G-cash';
                if \(gcashRef\) \{
                    gcashPart \+= ' \(Ref: ' \+ gcashRef \+ '\)';
                \}
                depositDetailsParts\.push\(gcashPart\);
            \}
            if \(depositMaya > 0\) \{
                let mayaPart = depositMaya\.toFixed\(2\) \+ ' Maya';
                if \(mayaRef\) \{
                    mayaPart \+= ' \(Ref: ' \+ mayaRef \+ '\)';
                \}
                depositDetailsParts\.push\(mayaPart\);
            \}
            const depositDetails = depositDetailsParts\.join\(', '\);
            formData\.set\('deposit_details', depositDetails\);

            // Set individual deposit method amounts
            formData\.set\('deposit_cash', depositCash\);
            formData\.set\('deposit_g_cash', depositGCash\);
            formData\.set\('deposit_maya', depositMaya\);
            formData\.set\('deposit_gcash_ref', gcashRef\);
            formData\.set\('deposit_maya_ref', mayaRef\);'''

replacement4 = '''            // CRITICAL FIX: Recalculate actualDeposit from breakdown fields to ensure accuracy
            let depInsta = typeof depositInstapayCombined !== 'undefined' ? depositInstapayCombined : 0;
            let depOnline = typeof depositOnlineBankingCombined !== 'undefined' ? depositOnlineBankingCombined : 0;
            let depAir = typeof depositAirbnbCombined !== 'undefined' ? depositAirbnbCombined : 0;
            
            actualDeposit = depositCash + depositGCash + depositMaya + depInsta + depOnline + depAir;
            console.log('actualDeposit (recalculated from breakdown):', actualDeposit);

            formData.set('deposit', actualDeposit);

            console.log('=== PAYMENT DATA BEING SENT TO BACKEND ===');
            console.log('actualDeposit:', actualDeposit);
            console.log('depositCash:', depositCash);
            console.log('depositGCash:', depositGCash);
            console.log('depositMaya:', depositMaya);
            console.log('depositInstapay:', depInsta);
            console.log('depositOnlineBanking:', depOnline);
            console.log('depositAirbnb:', depAir);
            console.log('paid_status:', formData.get('paid_status'));
            console.log('=== END PAYMENT DATA ===');

            // Build deposit_details string
            let depositDetailsParts = [];
            if (depositCash > 0) {
                depositDetailsParts.push(depositCash.toFixed(2) + ' Cash');
            }
            if (depositGCash > 0) {
                let gcashPart = depositGCash.toFixed(2) + ' G-cash';
                if (gcashRef) gcashPart += ' (Ref: ' + gcashRef + ')';
                depositDetailsParts.push(gcashPart);
            }
            if (depositMaya > 0) {
                let mayaPart = depositMaya.toFixed(2) + ' Maya';
                if (mayaRef) mayaPart += ' (Ref: ' + mayaRef + ')';
                depositDetailsParts.push(mayaPart);
            }
            if (depInsta > 0) {
                let p = depInsta.toFixed(2) + ' Instapay';
                depositDetailsParts.push(p);
            }
            if (depOnline > 0) depositDetailsParts.push(depOnline.toFixed(2) + ' Online Banking');
            if (depAir > 0) depositDetailsParts.push(depAir.toFixed(2) + ' Airbnb');
            
            const depositDetails = depositDetailsParts.join(', ');
            formData.set('deposit_details', depositDetails);

            // Set individual deposit method amounts
            formData.set('deposit_cash', depositCash);
            formData.set('deposit_g_cash', depositGCash);
            formData.set('deposit_maya', depositMaya);
            formData.set('deposit_instapay', depInsta);
            formData.set('deposit_online_banking', depOnline);
            formData.set('deposit_airbnb', depAir);
            formData.set('deposit_gcash_ref', gcashRef);
            formData.set('deposit_maya_ref', mayaRef);'''

content = re.sub(target4, replacement4, content)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print('Replaced')
