<?php
// Membership policy is shared by POST validation and the rendered form.
function membershipPlans(): array
{
    return [
        'monthly' => ['amount' => 20, 'duration' => '+1 month', 'billing' => 'Monthly Billing', 'features' => ['First month free', 'Access to Club Newsletter', 'Access to Exclusive Events']],
        'annual' => ['amount' => 200, 'duration' => '+1 year', 'billing' => 'Annual Billing', 'features' => ['Access to Club Newsletter', 'Access to Exclusive Events']],
    ];
}

function paymentFieldRules(): array
{
    return [
        'credit_debit' => [
            'cardName' => ['pattern' => '[a-zA-Z\s.\-]+', 'required' => 'Card Name is required.', 'invalid' => 'Card Name can only contain letters, spaces, hyphens, and periods.', 'server' => 'Card Name must contain only letters, spaces, hyphens, and periods.'],
            'cardNumber' => ['pattern' => '[0-9]{13,19}', 'maxlength' => 19, 'required' => 'Card Number is required.', 'invalid' => 'Invalid card number length (13-19 digits).', 'server' => 'Invalid Card Number length (13-19 digits) or characters.'],
            'expiryDate' => ['pattern' => '(0[1-9]|1[0-2])/[0-9]{2}', 'maxlength' => 5, 'required' => 'Expiry Date is required.', 'invalid' => 'Invalid month (MM).', 'server' => 'Invalid Expiry Date format (MM/YY).'],
            'cvv' => ['pattern' => '[0-9]{3,4}', 'maxlength' => 4, 'required' => 'CVV is required.', 'invalid' => 'CVV must be 3 or 4 digits.', 'server' => 'CVV must be 3 or 4 digits.'],
        ],
        'gcash' => [
            'gcashPhoneNumber' => ['pattern' => '[0-9]{10,11}', 'maxlength' => 11, 'required' => 'Mobile Number is required.', 'invalid' => 'Phone number must be 10 or 11 digits.', 'server' => 'Mobile Number must be 10 or 11 digits (numerical only).'],
        ],
    ];
}

function paymentScalar(array $data, string $key): string
{
    return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
}

function validateMembershipPayment(array $data, ?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    $errors = [];
    if (!isset(membershipPlans()[paymentScalar($data, 'selected_plan')])) {
        $errors[] = 'Please select a membership plan.';
    }
    $rules = paymentFieldRules()[paymentScalar($data, 'paymentMethod')] ?? null;
    if ($rules === null) {
        $errors[] = 'Please select a valid payment method.';
        return $errors;
    }
    foreach ($rules as $id => $rule) {
        $value = paymentScalar($data, $id);
        if ($id === 'cardNumber') $value = str_replace(' ', '', $value);
        // Preserve the existing server API's acceptance of both MMYY and MM/YY.
        if ($id === 'expiryDate') $value = preg_replace('~^([0-9]{2})([0-9]{2})$~', '$1/$2', $value);
        if ($value === '' || !preg_match('~\A(?:' . $rule['pattern'] . ')\z~', $value)) {
            $errors[] = $rule['server'];
        } elseif ($id === 'expiryDate') {
            [$month, $year] = array_map('intval', explode('/', $value));
            if (2000 + $year < (int)$today->format('Y') ||
                (2000 + $year === (int)$today->format('Y') && $month < (int)$today->format('m'))) {
                $errors[] = 'Credit Card has expired.';
            }
        }
    }
    return $errors;
}

function paymentInputAttributes(string $id, array $rule): string
{
    $attributes = [
        'type' => 'text', 'id' => $id, 'name' => $id, 'pattern' => $rule['pattern'],
        'data-payment-field' => '', 'data-required-message' => $rule['required'],
        'data-invalid-message' => $rule['invalid'], 'aria-describedby' => $id . 'Error',
    ];
    if (isset($rule['maxlength'])) $attributes['maxlength'] = $rule['maxlength'];
    $html = [];
    foreach ($attributes as $key => $value) {
        $html[] = $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
    }
    return implode(' ', $html);
}
