<?php
// Shared by initial rendering, the location endpoint, and form validation.
function locationRows(mysqli $conn, string $type, int $id): array
{
    $queries = [
        'provinces' => 'SELECT id, province_name FROM provinces WHERE region_id = ? ORDER BY province_name ASC',
        'cities' => 'SELECT id, city_municipality_name FROM cities_municipalities WHERE province_id = ? ORDER BY city_municipality_name ASC',
        'cities_by_region' => 'SELECT id, city_municipality_name FROM cities_municipalities WHERE region_id = ? AND province_id IS NULL ORDER BY city_municipality_name ASC',
    ];
    if ($id <= 0 || !isset($queries[$type])) return [];
    $stmt = $conn->prepare($queries[$type]);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function renderLocationOptions(array $rows, string $type, $selected = null): void
{
    $isProvince = $type === 'provinces';
    echo '<option value="">Select ' . ($isProvince ? 'Province' : 'City/Municipality') . '</option>';
    foreach ($rows as $row) {
        $label = $row[$isProvince ? 'province_name' : 'city_municipality_name'];
        echo '<option value="' . (int)$row['id'] . '"' . ((string)$selected === (string)$row['id'] ? ' selected' : '') . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

function validLocation(mysqli $conn, $region, $province, $city): bool
{
    $region = filter_var($region, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $city = filter_var($city, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$region || !$city) return false;
    $stmt = $conn->prepare('SELECT region_code FROM regions WHERE id = ?');
    $stmt->bind_param('i', $region);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    if ($row['region_code'] === 'NCR') {
        if ($province !== null) return false;
        $cities = locationRows($conn, 'cities_by_region', $region);
    } else {
        $province = filter_var($province, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$province || !in_array($province, array_column(locationRows($conn, 'provinces', $region), 'id'))) return false;
        $cities = locationRows($conn, 'cities', $province);
    }
    return in_array($city, array_column($cities, 'id'));
}
