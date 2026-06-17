<?php
require 'config.php';
$stmt = $conn->prepare("SELECT id, guest_name, room_id, booking_type, status, paid_status, downpayment_amount, downpayment_cash, downpayment_status, deposit, deposit_cash, total_amount FROM bookings WHERE guest_name = :g OR room_id = :r OR status = 'Occupied' ORDER BY id DESC LIMIT 10");
$stmt->execute([':g' => 'test222', ':r' => 'Deluxe Room 106']);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
