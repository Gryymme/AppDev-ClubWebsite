<?php
// Controls are rendered once by PHP and cloned when an admin edits a row.
$userFields = ['username', 'email', 'name', 'address', 'region_id', 'province_id', 'city_municipality_id', 'contact', 'dob', 'gender', 'member_status', 'created_at', 'membership_plan', 'membership_amount_paid', 'membership_start_date', 'membership_end_date', 'membership_payment_method', 'membership_transaction_id'];
$eventFields = ['title', 'subtitle', 'description', 'event_date', 'event_time', 'image_path'];
$controlFields = array_unique(array_merge($userFields, $eventFields, ['user_id', 'event_id']));
$choices = [
    'gender' => ['Male', 'Female', 'Other', 'Non-Binary'],
    'member_status' => ['Pending', 'Active', 'Inactive'],
];
$optionSources = [
    'region_id' => [$all_regions, 'region_name'],
    'province_id' => [$all_provinces, 'province_name'],
    'city_municipality_id' => [$all_cities_municipalities, 'city_municipality_name'],
    'user_id' => [$users, 'username'],
    'event_id' => [$events, 'title'],
];
function adminEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function adminOptionAttributes(array $option): string
{
    $attributes = [];
    foreach (['region_id', 'province_id', 'region_code', 'event_date', 'event_time'] as $attribute) {
        if (array_key_exists($attribute, $option)) {
            $attributes[] = 'data-' . str_replace('_', '-', $attribute) . '="' . adminEscape($option[$attribute]) . '"';
        }
    }
    return implode(' ', $attributes);
}
foreach ($controlFields as $field):
    if ($field === 'created_at') continue;
?>
<template id="control-<?= $field ?>">
    <?php if (isset($choices[$field]) || isset($optionSources[$field])): ?>
        <select class="form-control" data-field="<?= $field ?>" aria-label="<?= adminEscape(str_replace('_', ' ', $field)) ?>">
            <?php if (isset($choices[$field])): foreach ($choices[$field] as $choice): ?>
                <option value="<?= adminEscape($choice) ?>"><?= adminEscape($choice) ?></option>
            <?php endforeach; else: [$rows, $label] = $optionSources[$field]; ?>
                <option value="">Select <?= adminEscape(str_replace('_', ' ', $field)) ?></option>
                <?php foreach ($rows as $option): ?>
                    <option value="<?= (int)$option['id'] ?>" <?= adminOptionAttributes($option) ?>><?= adminEscape($option[$label]) ?><?= $field === 'user_id' ? ' (ID: ' . (int)$option['id'] . ')' : '' ?></option>
                <?php endforeach; ?>
                <?php if ($field === 'province_id'): ?><option value="NULL">N/A (NCR)</option><?php endif; ?>
            <?php endif; ?>
        </select>
    <?php elseif (in_array($field, ['subtitle', 'description'], true)): ?>
        <textarea class="form-control" data-field="<?= $field ?>" aria-label="<?= $field ?>"></textarea>
    <?php else:
        $type = in_array($field, ['dob', 'event_date', 'membership_start_date', 'membership_end_date'], true) ? 'date' : ($field === 'email' ? 'email' : 'text'); ?>
        <input type="<?= $type ?>" class="form-control" data-field="<?= $field ?>" aria-label="<?= adminEscape(str_replace('_', ' ', $field)) ?>">
    <?php endif; ?>
</template>
<?php endforeach; ?>
<template id="save-buttons">
    <button type="button" class="btn btn-primary btn-sm save-btn">Save</button>
    <button type="button" class="btn btn-secondary btn-sm cancel-btn">Cancel</button>
</template>
<template id="edit-buttons">
    <button type="button" class="btn btn-warning btn-sm edit-btn">Edit</button>
    <button type="button" class="btn btn-danger btn-sm delete-btn">Delete</button>
</template>
<template id="new-user-row">
    <tr class="new-row-form">
        <td><span class="temp-id">NEW</span></td>
        <?php foreach ($userFields as $field): ?>
            <td class="editable-cell" data-new-field="<?= $field === 'created_at' ? '' : $field ?>"></td>
        <?php endforeach; ?>
        <td class="action-buttons"></td>
    </tr>
</template>
<template id="new-attendee-row">
    <tr class="new-row-form">
        <td><span class="temp-id">NEW</span></td>
        <td data-new-field="user_id"></td><td data-new-field="event_id"></td>
        <td data-event-detail="title"></td><td data-event-detail="date"></td><td data-event-detail="time"></td>
        <td></td><td class="action-buttons"></td>
    </tr>
</template>
