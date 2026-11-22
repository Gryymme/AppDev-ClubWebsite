<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../membership.php';

function paymentCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

$today = new DateTimeImmutable('2026-11-23');
$card = ['selected_plan' => 'annual', 'paymentMethod' => 'credit_debit', 'cardName' => 'Test Person', 'cardNumber' => '4111111111111111', 'expiryDate' => '11/26', 'cvv' => '123'];
paymentCheck(validateMembershipPayment($card, $today) === [], 'A card remains valid through its expiry month');
paymentCheck(validateMembershipPayment(array_replace($card, ['expiryDate' => '10/26']), $today) === ['Credit Card has expired.'], 'Expired cards are rejected by PHP');
paymentCheck(validateMembershipPayment(array_replace($card, ['expiryDate' => '13/26']), $today) !== [], 'Invalid expiry months are rejected');
paymentCheck(validateMembershipPayment(array_replace($card, ['expiryDate' => '1126', 'cardNumber' => '4111 1111 1111 1111']), $today) === [], 'Existing compact expiry and spaced card-number POST formats remain accepted');
foreach ([12, 20] as $length) {
    paymentCheck(validateMembershipPayment(array_replace($card, ['cardNumber' => str_repeat('1', $length)]), $today) !== [], "Card number length $length is rejected");
}
paymentCheck(validateMembershipPayment(array_replace($card, ['cvv' => '12x']), $today) !== [], 'Non-numeric CVV is rejected');
$gcash = ['selected_plan' => 'monthly', 'paymentMethod' => 'gcash', 'gcashPhoneNumber' => '9171234567'];
paymentCheck(validateMembershipPayment($gcash, $today) === [], 'GCash does not require hidden card fields');
paymentCheck(validateMembershipPayment(array_replace($gcash, ['gcashPhoneNumber' => '91712']), $today) !== [], 'Short mobile numbers are rejected');
paymentCheck(count(validateMembershipPayment(['selected_plan' => [], 'paymentMethod' => []], $today)) === 2, 'Malformed plan and method values are rejected');
$rule = paymentFieldRules()['credit_debit']['cardName'];
$rule['invalid'] = '"><img src=x onerror=alert(1)>';
$attributes = paymentInputAttributes('cardName', $rule);
paymentCheck(strpos($attributes, '<img') === false && strpos($attributes, '&lt;img') !== false, 'Payment constraint attributes escape messages');
paymentCheck(membershipPlans()['monthly']['amount'] === 20 && membershipPlans()['annual']['amount'] === 200, 'Existing membership prices remain unchanged');
