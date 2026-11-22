<?php
// Directory: database/fetch_locations.php
// This file handles fetching provinces and cities/municipalities to the dropdown menu based on the type and id parameters.
header('Content-Type: application/json'); 

require_once 'db_connection.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

$response = [];

if ($type === 'provinces' && $id > 0) {
    // Fetch provinces for a given region_id
    $stmt = $conn->prepare("SELECT id, province_name FROM provinces WHERE region_id = ? ORDER BY province_name ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $provinces = [];
    while ($row = $result->fetch_assoc()) {
        $provinces[] = $row;
    }
    $response = $provinces;
    $stmt->close();
} elseif ($type === 'cities' && $id > 0) {
    // Fetch cities/municipalities for a given province_id
    $stmt = $conn->prepare("SELECT id, city_municipality_name FROM cities_municipalities WHERE province_id = ? ORDER BY city_municipality_name ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    $response = $cities;
    $stmt->close();
} elseif ($type === 'cities_by_region' && $id > 0) {
    // Special case: Fetch cities/municipalities directly by region_id (for NCR)
    $stmt = $conn->prepare("SELECT id, city_municipality_name FROM cities_municipalities WHERE region_id = ? AND province_id IS NULL ORDER BY city_municipality_name ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    $response = $cities;
    $stmt->close();
}

$conn->close();
echo json_encode($response);
?>