<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'database/db_connection.php'; 

$user_id = $_SESSION['user_id'];
$user_data = [];
$message = $_SESSION['message'] ?? '';
$msg_type = $_SESSION['msg_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['msg_type']);


if ($user_id) {
    // Fetch user's current data using correct column names
    $stmt = $conn->prepare("SELECT username, email, name, address, contact, dob, gender, region_id, province_id, city_municipality_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch regions for the dropdown
$regions = [];
$sql_regions = "SELECT id, region_name, region_code FROM regions ORDER BY region_name ASC";
$result_regions = $conn->query($sql_regions);
if ($result_regions->num_rows > 0) {
    while($row = $result_regions->fetch_assoc()) {
        $regions[] = $row;
    }
}

// Fetch provinces based on user's current region_id, if available
$provinces = [];
if (!empty($user_data['region_id'])) {
    $stmt_provinces = $conn->prepare("SELECT id, province_name FROM provinces WHERE region_id = ? ORDER BY province_name ASC");
    $stmt_provinces->bind_param("i", $user_data['region_id']);
    $stmt_provinces->execute();
    $result_provinces = $stmt_provinces->get_result();
    if ($result_provinces->num_rows > 0) {
        while($row = $result_provinces->fetch_assoc()) {
            $provinces[] = $row;
        }
    }
    $stmt_provinces->close();
}

// Fetch cities/municipalities based on user's current province_id or region_id (for NCR), if available
$cities_municipalities_data = []; 
if (!empty($user_data['province_id'])) {
    $stmt_cities = $conn->prepare("SELECT id, city_municipality_name FROM cities_municipalities WHERE province_id = ? ORDER BY city_municipality_name ASC");
    $stmt_cities->bind_param("i", $user_data['province_id']);
    $stmt_cities->execute();
    $result_cities = $stmt_cities->get_result();
    if ($result_cities->num_rows > 0) {
        while($row = $result_cities->fetch_assoc()) {
            $cities_municipalities_data[] = $row;
        }
    }
    $stmt_cities->close();
} else if (!empty($user_data['region_id'])) { // Handle NCR case if province_id is null for NCR
    // Find region code for the selected region
    $ncr_region_code = '';
    foreach($regions as $region) {
        if ($region['id'] == $user_data['region_id']) {
            $ncr_region_code = $region['region_code'];
            break;
        }
    }

    if ($ncr_region_code === 'NCR') {
        $stmt_cities_ncr = $conn->prepare("SELECT id, city_municipality_name FROM cities_municipalities WHERE region_id = ? ORDER BY city_municipality_name ASC");
        $stmt_cities_ncr->bind_param("i", $user_data['region_id']);
        $stmt_cities_ncr->execute();
        $result_cities_ncr = $stmt_cities_ncr->get_result();
        if ($result_cities_ncr->num_rows > 0) {
            while($row = $result_cities_ncr->fetch_assoc()) {
                $cities_municipalities_data[] = $row;
            }
        }
        $stmt_cities_ncr->close();
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Edit Account Information</title>

    <link rel="icon" type="image/x-xicon" href="assets/arcane.ico" />

    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" />

    <link href="css/styles.css" rel="stylesheet" />

    <link href="css/login_style.css" rel="stylesheet" />

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-wrapper">
        <main class="main-content">

            <div class="login-container">
                <form action="process_edit_info.php" method="post" class="form-box">
                    <h1>Edit Information</h1>
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="signup-columns">
                        <div class="left-column">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Enter your preferred username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>

                            <label>Name</label>
                            <input type="text" name="name" placeholder="e.g. Juan Dela Cruz" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>

                            <label>Address</label>
                            <input type="text" name="address" placeholder="Street/Barangay" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" required>

                            <label for="region">Region</label>
                            <select name="region_id" id="region" required>
                                <option value="">Select Region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo htmlspecialchars($region['id']); ?>" data-region-code="<?php echo htmlspecialchars($region['region_code']); ?>" <?php echo (isset($user_data['region_id']) && $user_data['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region['region_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="province">Province</label>
                            <select name="province_id" id="province" required <?php echo empty($provinces) ? 'disabled' : ''; ?>>
                                <option value="">Select Province</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo htmlspecialchars($province['id']); ?>" <?php echo (isset($user_data['province_id']) && $user_data['province_id'] == $province['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province['province_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="city_municipality">City/Municipality</label>
                            <select name="city_municipality_id" id="city_municipality" required <?php echo empty($cities_municipalities_data) ? 'disabled' : ''; ?>>
                                <option value="">Select City/Municipality</option>
                                <?php foreach ($cities_municipalities_data as $city): // Changed variable name here ?>
                                    <option value="<?php echo htmlspecialchars($city['id']); ?>" <?php echo (isset($user_data['city_municipality_id']) && $user_data['city_municipality_id'] == $city['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['city_municipality_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        </div>

                        <div class="right-column">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="e.g. example@email.com" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            
                            <label>Contact Number</label>
                            <input type="text" name="contact" placeholder="e.g. 09xx-xxx-xxxx" pattern="[0-9]+" value="<?php echo htmlspecialchars($user_data['contact'] ?? ''); ?>" required>

                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo htmlspecialchars($user_data['dob'] ?? ''); ?>" required class="uniform-input">

                            <label for="gender">Gender</label>
                            <select name="gender" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Non-Binary" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Non-Binary') ? 'selected' : ''; ?>>Non-Binary</option>
                            </select>

                            <label>New Password (leave blank if not changing)</label>
                            <input type="password" name="password" placeholder="Enter new password">

                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-enter new password">
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit">Save Changes</button>
                    </div>

                    <p class="text-link">Go back to <a href="user_management.php">Account Management</a></p>
                </form>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        $(document).ready(function() {
            var $regionSelect = $('#region');
            var $provinceSelect = $('#province');
            var $cityMunicipalitySelect = $('#city_municipality');

            function resetProvinceAndCity() {
                $provinceSelect.html('<option value="">Select Province</option>').prop('disabled', true);
                $cityMunicipalitySelect.html('<option value="">Select City/Municipality</option>').prop('disabled', true);
            }

            // Function to load provinces
            function loadProvinces(regionId, selectedProvinceId = null) {
                if (regionId) {
                    var regionCode = $regionSelect.find(':selected').data('region-code');
                    if (regionCode === 'NCR') {
                        $provinceSelect.html('<option value="NA">Not Applicable</option>').prop('disabled', true);
                        loadCities(regionId, selectedProvinceId, 'cities_by_region'); // For NCR, pass regionId
                    } else {
                        $.ajax({
                            url: 'database/fetch_locations.php',
                            type: 'GET',
                            dataType: 'json',
                            data: { type: 'provinces', id: regionId },
                            success: function(provinces) {
                                $provinceSelect.empty().append('<option value="">Select Province</option>');
                                $.each(provinces, function(i, province) {
                                    $provinceSelect.append($('<option>', {
                                        value: province.id,
                                        text: province.province_name,
                                        selected: (selectedProvinceId && selectedProvinceId == province.id)
                                    }));
                                });
                                $provinceSelect.prop('disabled', false);
                                // If a province was selected, load its cities
                                if (selectedProvinceId) {
                                    loadCities(selectedProvinceId, '<?php echo htmlspecialchars($user_data['city_municipality_id'] ?? ''); ?>');
                                }
                            },
                            error: function() {
                                console.log('Error fetching provinces.');
                            }
                        });
                    }
                } else {
                    resetProvinceAndCity();
                }
            }

            // Function to load cities/municipalities
            function loadCities(parentId, selectedCityId = null, type = 'cities') {
                if (parentId) {
                    $.ajax({
                        url: 'database/fetch_locations.php',
                        type: 'GET',
                        dataType: 'json',
                        data: { type: type, id: parentId }, // Use 'cities_by_region' for NCR
                        success: function(cities) {
                            $cityMunicipalitySelect.empty().append('<option value="">Select City/Municipality</option>');
                            $.each(cities, function(i, city) {
                                $cityMunicipalitySelect.append($('<option>', {
                                    value: city.id,
                                    text: city.city_municipality_name,
                                    selected: (selectedCityId && selectedCityId == city.id)
                                }));
                            });
                            $cityMunicipalitySelect.prop('disabled', false);
                        },
                        error: function() {
                            console.log('Error fetching cities.');
                        }
                    });
                } else {
                    $cityMunicipalitySelect.html('<option value="">Select City/Municipality</option>').prop('disabled', true);
                }
            }

            // Initial load for provinces and cities if user data exists
            var initialRegionId = '<?php echo htmlspecialchars($user_data['region_id'] ?? ''); ?>';
            var initialProvinceId = '<?php echo htmlspecialchars($user_data['province_id'] ?? ''); ?>';
            var initialCityId = '<?php echo htmlspecialchars($user_data['city_municipality_id'] ?? ''); ?>';

            if (initialRegionId) {
                loadProvinces(initialRegionId, initialProvinceId);
                // For NCR, if region is selected and no province, load cities by region
                var initialRegionCode = $regionSelect.find('option:selected').data('region-code');
                if (initialRegionCode === 'NCR' && !initialProvinceId) {
                     loadCities(initialRegionId, initialCityId, 'cities_by_region');
                } else if (initialProvinceId) {
                    loadCities(initialProvinceId, initialCityId);
                }
            }


            // Region change event
            $regionSelect.on('change', function() {
                var regionId = $(this).val();
                resetProvinceAndCity();
                loadProvinces(regionId);
            });

            // Province change event
            $provinceSelect.on('change', function() {
                var provinceId = $(this).val();
                $cityMunicipalitySelect.html('<option value="">Select City/Municipality</option>').prop('disabled', true);
                loadCities(provinceId);
            });
        });
    </script>
</body>
</html>