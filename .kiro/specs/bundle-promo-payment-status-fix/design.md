# Design Document: Bundle/Promo Payment Status Fix

## Overview

This design addresses a critical bug in the `computeBookingTotalAmount` function where breakfast items marked with "(Promo)" suffix are incorrectly added to the total amount, even though they are already included in the promo price. This causes Bundle/Promo bookings to show as "Unpaid" even when fully paid.

The fix involves modifying the `parseBreakfastSelection` function to detect promo breakfast items and return a price of 0.0 for them, ensuring that only the promo price is counted in the total amount calculation.

## Architecture

The system uses a helper function architecture where:
- `computeBookingTotalAmount()` orchestrates the total calculation
- `parseBreakfastSelection()` extracts breakfast metadata (name and price)
- `parseCurrencyFromString()` extracts numeric prices from formatted strings

The bug exists in `parseBreakfastSelection()`, which currently extracts prices from all breakfast strings without checking for the "(Promo)" suffix that indicates the breakfast is included in the promo package.

### Current Flow (Buggy)
```
Breakfast: "2 TAPA (Promo)"
  ↓
parseBreakfastSelection()
  ↓
parseCurrencyFromString() → extracts any ₱ amount (if present)
  ↓
computeBookingTotalAmount() → adds promo price + breakfast price
  ↓
Total is inflated → Payment Status shows "Unpaid"
```

### Fixed Flow
```
Breakfast: "2 TAPA (Promo)"
  ↓
parseBreakfastSelection()
  ↓
Detects "(Promo)" suffix → returns price = 0.0
  ↓
computeBookingTotalAmount() → adds promo price + 0.0
  ↓
Total is correct → Payment Status shows "Paid"
```

## Components and Interfaces

### Modified Function: parseBreakfastSelection

**Location:** `report_helpers.php`

**Current Signature:**
```php
function parseBreakfastSelection(?string $value): array
```

**Input:** 
- `$value`: Breakfast string in various formats:
  - Promo breakfast: "2 TAPA (Promo)" or "1 LONGGANISA (Promo)"
  - Regular breakfast: "2 HOTDOG - ₱240.00" or "1 TAPA - ₱120.00"
  - Multiple items: "2 HOTDOG - ₱240.00 | 1 TAPA (Promo)"
  - None: null, "", "None", "Select Breakfast"

**Output:**
```php
[
    'name' => string,  // Breakfast item name
    'price' => float   // Price to add to total (0.0 for promo items)
]
```

**Modified Logic:**
1. Check if value is null, empty, "None", or "Select Breakfast" → return empty result
2. Check if value contains "(Promo)" suffix (case-insensitive)
   - If yes → return name with price = 0.0
   - If no → extract price using parseCurrencyFromString()
3. Handle multiple items separated by "|" by summing only non-promo prices

### Unchanged Functions

**computeBookingTotalAmount:** No changes needed. It already correctly uses the price returned by `parseBreakfastSelection()`.

**parseCurrencyFromString:** No changes needed. It correctly extracts numeric values from currency strings.

## Data Models

### Breakfast String Formats

The system supports multiple breakfast string formats:

1. **Promo Breakfast (Single Item)**
   - Format: `"{quantity} {name} (Promo)"`
   - Example: `"2 TAPA (Promo)"`
   - Price: 0.0 (included in promo)

2. **Regular Breakfast (Single Item)**
   - Format: `"{quantity} {name} - ₱{price}"`
   - Example: `"2 HOTDOG - ₱240.00"`
   - Price: Extracted from string

3. **Multiple Items (Mixed)**
   - Format: `"{item1} | {item2} | {item3}"`
   - Example: `"2 HOTDOG - ₱240.00 | 1 TAPA (Promo)"`
   - Price: Sum of non-promo item prices

4. **Empty/None**
   - Values: `null`, `""`, `"None"`, `"Select Breakfast"`
   - Price: 0.0

### Detection Logic

**Promo Breakfast Detection:**
- Check if string contains "(Promo)" using case-insensitive search
- Pattern: `stripos($value, '(Promo)') !== false`

**Regular Breakfast Detection:**
- Check if string contains "₱" followed by numbers
- Pattern: `/₱\s*([\d,]+(?:\.\d+)?)/u`

## Correctness Properties


A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property Reflection

After analyzing the acceptance criteria, I identified the following redundancies:
- Properties 4.1 and 2.1 both test promo breakfast detection → Keep 2.1 as the primary property
- Properties 4.2 and 1.2 both test regular breakfast calculation → Keep 1.2 as the primary property
- Property 1.4 is subsumed by Property 1.1 (if promo breakfast price is 0, then total = promo price only)
- Property 2.3 is subsumed by Property 1.3 (both test multi-item parsing, but 1.3 is more comprehensive)

The following properties provide unique validation value:

**Property 1: Promo Breakfast Returns Zero Price**
*For any* breakfast string containing the "(Promo)" suffix (case-insensitive), parseBreakfastSelection should return a price of 0.0
**Validates: Requirements 1.1, 2.1, 4.1**

**Property 2: Regular Breakfast Price Extraction**
*For any* breakfast string containing a price in the format "₱X.XX", parseBreakfastSelection should extract and return that exact price
**Validates: Requirements 1.2, 2.2, 4.2**

**Property 3: Mixed Breakfast Items Pricing**
*For any* breakfast string containing multiple items separated by "|", where some items have "(Promo)" suffix and others have prices, the total price should equal the sum of only the non-promo item prices
**Validates: Requirements 1.3, 2.3**

**Property 4: Promo Booking Total Excludes Breakfast**
*For any* booking with a promo price and a promo breakfast (marked with "(Promo)"), computeBookingTotalAmount should return a total equal to the promo price (not promo price + breakfast price)
**Validates: Requirements 1.4**

**Property 5: Payment Status Accuracy**
*For any* booking where payment amount is less than the calculated total amount, the payment status should be "Unpaid"
**Validates: Requirements 3.3**

### Edge Cases

The following edge cases will be handled by the property test generators:

- Empty breakfast strings (null, "", "None", "Select Breakfast") → price = 0.0
- Breakfast strings with "(Promo)" in different cases ("(promo)", "(PROMO)", "(Promo)")
- Breakfast strings with whitespace variations around "(Promo)"
- Multiple items with all promo → total price = 0.0
- Multiple items with all regular → total price = sum of all prices

## Error Handling

### Invalid Input Handling

**parseBreakfastSelection:**
- Null or empty input → Return `['name' => '', 'price' => 0.0]`
- Invalid format (no recognizable pattern) → Return name as-is with price = 0.0
- Malformed price strings → parseCurrencyFromString handles extraction gracefully

**computeBookingTotalAmount:**
- Missing breakfast data → Treat as 0.0 (no breakfast)
- Negative prices → Not possible due to regex extraction logic
- Invalid promo data → Handled by parsePromoSelection (separate function)

### Backward Compatibility

The fix maintains backward compatibility by:
1. Not changing the function signatures
2. Not requiring database schema changes
3. Working with existing breakfast string formats
4. Preserving behavior for regular (non-promo) breakfasts

## Testing Strategy

### Dual Testing Approach

This fix requires both unit tests and property-based tests:

**Unit Tests:**
- Test specific examples of promo breakfast strings
- Test specific examples of regular breakfast strings
- Test edge cases (empty, null, "None")
- Test the bug scenario: "2 TAPA (Promo)" should return price = 0.0

**Property-Based Tests:**
- Generate random breakfast strings with "(Promo)" suffix → verify price = 0.0
- Generate random breakfast strings with prices → verify correct extraction
- Generate random multi-item strings → verify correct summation
- Generate random booking data with promo → verify total excludes breakfast price
- Minimum 100 iterations per property test

### Test Configuration

Each property test must:
- Run at least 100 iterations (due to randomization)
- Reference its design document property
- Use tag format: **Feature: bundle-promo-payment-status-fix, Property {number}: {property_text}**

### Testing Balance

- Unit tests focus on specific examples and the reported bug scenario
- Property tests focus on universal correctness across all input variations
- Together they provide comprehensive coverage without excessive redundancy

## Implementation Notes

### Key Changes

The primary change is in `parseBreakfastSelection()`:

**Before:**
```php
function parseBreakfastSelection(?string $value): array
{
    if (!$value || strtolower(trim($value)) === 'none' || stripos($value, 'select breakfast') !== false) {
        return ['name' => '', 'price' => 0.0];
    }

    $name = trim($value);
    if (strpos($value, ' - ') !== false) {
        [$namePart] = explode(' - ', $value, 2);
        $name = trim($namePart);
    }

    return [
        'name' => $name,
        'price' => parseCurrencyFromString($value)  // BUG: Always extracts price
    ];
}
```

**After:**
```php
function parseBreakfastSelection(?string $value): array
{
    if (!$value || strtolower(trim($value)) === 'none' || stripos($value, 'select breakfast') !== false) {
        return ['name' => '', 'price' => 0.0];
    }

    // Check if this is a promo breakfast (included in promo package)
    if (stripos($value, '(Promo)') !== false) {
        $name = trim(str_replace('(Promo)', '', $value));
        $name = trim(preg_replace('/^\d+\s+/', '', $name)); // Remove quantity prefix
        return [
            'name' => $name,
            'price' => 0.0  // FIX: Promo breakfast is free (included in promo)
        ];
    }

    $name = trim($value);
    if (strpos($value, ' - ') !== false) {
        [$namePart] = explode(' - ', $value, 2);
        $name = trim($namePart);
    }

    return [
        'name' => $name,
        'price' => parseCurrencyFromString($value)
    ];
}
```

### Multi-Item Handling

For breakfast strings with multiple items (e.g., "2 HOTDOG - ₱240.00 | 1 TAPA (Promo)"), the current implementation in `computeBookingTotalAmount` calls `parseBreakfastSelection` once with the full string. The fix needs to handle this by:

1. Detecting if the string contains "|" separator
2. Splitting the string into individual items
3. Parsing each item separately
4. Summing only the non-promo prices

This requires a more comprehensive update to `parseBreakfastSelection` to handle multi-item strings.

### Alternative Approach

An alternative approach would be to modify `computeBookingTotalAmount` to check for "(Promo)" before adding breakfast price, but modifying `parseBreakfastSelection` is cleaner because:
- It centralizes the breakfast parsing logic
- It maintains the single responsibility principle
- It makes the fix easier to test in isolation
- It prevents future bugs if other code calls `parseBreakfastSelection`
