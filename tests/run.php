<?php
// Run from the project root: php tests/run.php [--database]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/locations.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

ob_start();
renderLocationOptions([['id' => 5, 'province_name' => '<Example & "Province">']], 'provinces', 5);
$html = ob_get_clean();
check(strpos($html, 'value="5" selected') !== false, 'Saved location is selected by PHP');
check(strpos($html, '&lt;Example &amp; &quot;Province&quot;&gt;') !== false, 'Location labels are escaped');

$unsafe = '"><img src=x onerror=alert(1)>';
$users = [['id' => 1, 'username' => $unsafe]];
$events = [['id' => 2, 'title' => $unsafe, 'event_date' => '2030-01-01', 'event_time' => '10:00']];
$all_regions = [['id' => 3, 'region_name' => $unsafe, 'region_code' => 'NCR']];
$all_provinces = [];
$all_cities_municipalities = [];
ob_start();
include __DIR__ . '/../admin/form_templates.php';
$html = ob_get_clean();
check(strpos($html, '<img') === false && strpos($html, '&lt;img') !== false, 'Admin option labels cannot create HTML');
check(strpos($html, 'data-event-date="2030-01-01"') !== false, 'Attendee preview receives server-rendered event details');
preg_match_all('/id="([^"]+)"/', $html, $ids);
check(count($ids[1]) === count(array_unique($ids[1])), 'Admin templates have unique IDs');
check(substr_count($html, '<td') === 28, 'New user and attendee rows match their table column counts');

if (in_array('--database', $argv, true)) {
    require __DIR__ . '/../database/db_connection.php';
    $ncr = $conn->query("SELECT id FROM regions WHERE region_code = 'NCR'")->fetch_assoc();
    check((bool)$ncr, 'NCR reference region is available');
    $cities = locationRows($conn, 'cities_by_region', (int)$ncr['id']);
    check(count($cities) > 0 && validLocation($conn, $ncr['id'], null, $cities[0]['id']), 'NCR accepts a city without a province');
    check(!validLocation($conn, $ncr['id'], 999999, $cities[0]['id']), 'NCR rejects an unrelated province');
    $province = $conn->query('SELECT id, region_id FROM provinces WHERE id IN (SELECT province_id FROM cities_municipalities) LIMIT 1')->fetch_assoc();
    check((bool)$province, 'Provincial reference data is available');
    $cities = locationRows($conn, 'cities', (int)$province['id']);
    check(validLocation($conn, $province['region_id'], $province['id'], $cities[0]['id']), 'Provincial addresses validate');
    check(!validLocation($conn, $ncr['id'], null, $cities[0]['id']), 'A city from another region is rejected');
    check(!validLocation($conn, [], null, []), 'Malformed location identifiers are rejected');
    check(locationRows($conn, 'invalid', 1) === [], 'Unknown lookup type returns no rows');
    $conn->close();
}
