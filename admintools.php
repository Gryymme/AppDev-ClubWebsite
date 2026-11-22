<?php
session_start();
require_once 'database/db_connection.php';

// Handles POST requests for update, delete, and add actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Re-check admin session for security
    if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin' || $_SESSION['email'] !== 'admin@email.com') {
        echo json_encode(['success' => false, 'message' => 'Authentication error. Please log in again.']);
        exit();
    }

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }
    $conn->set_charset("utf8mb4");

    $action = $_POST['action'];
    $table_name = $_POST['table'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0; 

    // DELETE ACTION 
    if ($action === 'delete') {
        if (empty($table_name) || empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters for delete.']);
            $conn->close();
            exit();
        }

        $db_table = '';
        if ($table_name === 'users') $db_table = 'users';
        if ($table_name === 'events') $db_table = 'events';
        if ($table_name === 'attendees') $db_table = 'signed_up_events';

        if (empty($db_table)) {
            echo json_encode(['success' => false, 'message' => 'Invalid table specified for deletion.']);
            $conn->close();
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM $db_table WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete record: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
    // ADD ACTION
    if ($action === 'add') {
        if (empty($table_name)) {
            echo json_encode(['success' => false, 'message' => 'Missing table parameter for add.']);
            $conn->close();
            exit();
        }

        $response_data = [];
        switch ($table_name) {
            case 'users':
                $stmt = $conn->prepare("INSERT INTO users (username, email, name, address, region_id, province_id, city_municipality_id, contact, dob, gender, member_status, membership_plan, membership_amount_paid, membership_start_date, membership_end_date, membership_payment_method, membership_transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $province_id = ($_POST['province_id'] === 'NULL' || $_POST['province_id'] === '') ? null : $_POST['province_id'];
                $stmt->bind_param("ssssiiissssssssss", $_POST['username'], $_POST['email'], $_POST['name'], $_POST['address'], $_POST['region_id'], $province_id, $_POST['city_municipality_id'], $_POST['contact'], $_POST['dob'], $_POST['gender'], $_POST['member_status'], $_POST['membership_plan'], $_POST['membership_amount_paid'], $_POST['membership_start_date'], $_POST['membership_end_date'], $_POST['membership_payment_method'], $_POST['membership_transaction_id']);
                break;
            case 'attendees':
                // Fetch event details
                $event_stmt = $conn->prepare("SELECT title, event_date, event_time FROM events WHERE id = ?");
                $event_stmt->bind_param("i", $_POST['event_id']);
                $event_stmt->execute();
                $event_result = $event_stmt->get_result()->fetch_assoc();
                $event_stmt->close();

                if (!$event_result) {
                    echo json_encode(['success' => false, 'message' => 'Invalid Event ID.']);
                    $conn->close();
                    exit();
                }
                
                $stmt = $conn->prepare("INSERT INTO signed_up_events (user_id, event_id, event_title, event_date, event_time) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisss", $_POST['user_id'], $_POST['event_id'], $event_result['title'], $event_result['event_date'], $event_result['event_time']);
                $response_data = $event_result; // Send event data back to fill the row
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid table for add operation.']);
                $conn->close();
                exit();
        }

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(['success' => true, 'id' => $new_id, 'data' => $response_data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add record: ' . $stmt->error]);
        }
        $stmt->close();
    }

    // UPDATE ACTION
    if ($action === 'update') {
        // ID must be a positive integer for an update
        if (empty($table_name) || $id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing or invalid ID for update.']);
            $conn->close();
            exit();
        }

        $db_table = '';
        $allowed_fields = [];
        
        switch ($table_name) {
            case 'users':
                $db_table = 'users';
                $allowed_fields = ['username', 'email', 'name', 'address', 'region_id', 'province_id', 'city_municipality_id', 'contact', 'dob', 'gender', 'member_status', 'membership_plan', 'membership_amount_paid', 'membership_start_date', 'membership_end_date', 'membership_payment_method', 'membership_transaction_id'];
                break;
            case 'events':
                $db_table = 'events';
                $allowed_fields = ['title', 'subtitle', 'description', 'event_date', 'event_time', 'image_path'];
                break;
            case 'attendees':
                $db_table = 'signed_up_events';
                $allowed_fields = ['user_id', 'event_id']; // Only allow direct edit of these
                
                // If event_id is being updated, fetch new event details to update related fields
                if (isset($_POST['event_id'])) {
                    $event_stmt = $conn->prepare("SELECT title, event_date, event_time FROM events WHERE id = ?");
                    $event_stmt->bind_param("i", $_POST['event_id']);
                    $event_stmt->execute();
                    $event_result = $event_stmt->get_result()->fetch_assoc();
                    $event_stmt->close();

                    if ($event_result) {
                        $_POST['event_title'] = $event_result['title'];
                        $_POST['event_date'] = $event_result['event_date'];
                        $_POST['event_time'] = $event_result['event_time'];
                        $allowed_fields[] = 'event_title';
                        $allowed_fields[] = 'event_date';
                        $allowed_fields[] = 'event_time';
                    }
                }
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid table specified for update.']);
                $conn->close();
                exit();
        }

        $fields_to_update = [];
        $param_values = [];
        $types = '';

        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                if ($field === 'province_id' && $_POST[$field] === 'NULL') {
                    $fields_to_update[] = "province_id = NULL";
                } else {
                    $fields_to_update[] = "$field = ?";
                    $param_values[] = $_POST[$field];
                    // Simple type guessing for bind_param
                    $types .= (strpos($field, '_id') !== false || in_array($field, ['user_id', 'event_id'])) ? 'i' : 's';
                }
            }
        }

        if (empty($fields_to_update)) {
            echo json_encode(['success' => false, 'message' => 'No valid fields to update.']);
            $conn->close();
            exit();
        }

        $sql = "UPDATE $db_table SET " . implode(', ', $fields_to_update) . " WHERE id = ?";
        $types .= 'i';
        $param_values[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$param_values);

        if ($stmt->execute()) {
            // If an event was updated, also update its attendees
            if ($table_name === 'events' && (isset($_POST['event_date']) || isset($_POST['event_time']))) {
                $update_attendees_stmt = $conn->prepare("UPDATE signed_up_events SET event_date = ?, event_time = ? WHERE event_id = ?");
                $update_attendees_stmt->bind_param("ssi", $_POST['event_date'], $_POST['event_time'], $id);
                $update_attendees_stmt->execute();
                $update_attendees_stmt->close();
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update record: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
    $conn->close();
    exit();
}

// PAGE LOAD LOGIC, runs when the page is initially loaded.

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin' || $_SESSION['email'] !== 'admin@email.com') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Fetch all users for 'Manage Users'
$users = [];
$sql_users = "
    SELECT u.id, u.username, u.name, u.address, u.region_id, r.region_name AS region_name_display,
           u.province_id, p.province_name AS province_name_display, u.city_municipality_id, 
           cm.city_municipality_name AS city_municipality_name_display, u.email, u.contact, u.dob, 
           u.gender, u.member_status, u.created_at, u.membership_plan, u.membership_amount_paid,
           u.membership_start_date, u.membership_end_date, u.membership_payment_method, u.membership_transaction_id
    FROM users u
    LEFT JOIN regions r ON u.region_id = r.id
    LEFT JOIN provinces p ON u.province_id = p.id
    LEFT JOIN cities_municipalities cm ON u.city_municipality_id = cm.id
    ORDER BY u.id ASC
";
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $row['region_name_display'] = $row['region_name_display'] ?? 'N/A';
        $row['province_name_display'] = $row['province_name_display'] ?? 'N/A';
        $row['city_municipality_name_display'] = $row['city_municipality_name_display'] ?? 'N/A';
        $users[] = $row;
    }
}

// For JS dropdowns
$users_for_js = array_map(function($user) {
    return ['id' => $user['id'], 'username' => $user['username']];
}, $users);


// Fetch all events for 'Manage Events' and the dropdown
$events = [];
$sql_events = "SELECT id, title, subtitle, description, event_date, event_time, image_path FROM events ORDER BY event_date ASC";
$result_events = $conn->query($sql_events);
if ($result_events) {
    while ($row = $result_events->fetch_assoc()) {
        $events[] = $row;
    }
}

// Fetch all attendees for 'Manage Attendees'
$attendees = [];
$sql_attendees = "SELECT id, user_id, event_id, event_title, event_date, event_time, signup_date FROM signed_up_events ORDER BY id ASC";
$result_attendees = $conn->query($sql_attendees);
if ($result_attendees) {
    while ($row = $result_attendees->fetch_assoc()) {
        $attendees[] = $row;
    }
}


// Fetch regions, provinces, and cities for JavaScript dropdowns
$all_regions = [];
$stmt_regions = $conn->query("SELECT id, region_name, region_code FROM regions ORDER BY region_name ASC");
if ($stmt_regions) {
    while ($row = $stmt_regions->fetch_assoc()) $all_regions[] = $row;
}

$all_provinces_js = [];
$stmt_provinces = $conn->query("SELECT id, province_name, region_id FROM provinces ORDER BY province_name ASC");
if ($stmt_provinces) {
    while ($row = $stmt_provinces->fetch_assoc()) $all_provinces_js[] = $row;
}

$all_cities_municipalities_js = [];
$stmt_cities = $conn->query("SELECT id, city_municipality_name, province_id, region_id FROM cities_municipalities ORDER BY city_municipality_name ASC");
if ($stmt_cities) {
    while ($row = $stmt_cities->fetch_assoc()) $all_cities_municipalities_js[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/datatables.min.css"/>
    <style>
        body { background-color: #FFFFFF !important; color: #343a40; font-family: 'Roboto Slab', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .container-fluid { padding-top: 20px; padding-bottom: 20px; flex-grow: 1; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 15px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .header-section h1 { font-size: 2.5rem; color: #343a40; margin: 0; font-weight: bold; }
        .section-heading { margin-top: 40px; margin-bottom: 20px; color: #343a40; text-align: left; }
        .table-responsive { background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 40px; overflow-x: auto; }

        table.dataTable { width: 100% !important; }
        
        /* DataTables Styling */
        table.dataTable thead th { background-color: #343a40; color: white; white-space: nowrap; }
        table.dataTable tbody td { vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .action-buttons button { margin-right: 5px; margin-bottom: 5px; }
        .dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select { border: 1px solid #ced4da; padding: 0.375rem 0.75rem; border-radius: 0.25rem; }
        
        /* Editable Cell Styling */
        .editable-cell input, .editable-cell select, .editable-cell textarea {
            width: 100%;
            padding: 0.375rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-sizing: border-box;
            background-color: #fff;
            min-width: 150px;
        }
        .editable-cell textarea {
            resize: vertical;
            min-height: 80px; 
        }
        .temp-id { font-weight: bold; color: #0d6efd; }
        .new-row-form td { padding: 5px !important; }
    </style>
</head>
<body>
    <div class="container-fluid py-5">
        <div class="header-section">
            <h1><b>Admin Tools</b></h1>
            <a href="main.php" class="btn btn-secondary btn-lg">Exit Admin Tools</a>
        </div>

        <h2 class="section-heading fw-bold">Manage Users</h2>
        <div class="table-responsive mb-5">
            <table id="usersTable" class="table table-striped table-bordered display compact cell-border" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Address</th><th>Region</th><th>Province</th><th>City/Municipality</th><th>Contact</th><th>DOB</th><th>Gender</th><th>Member Status</th><th>Created At</th><th>Plan</th><th>Paid</th><th>Start</th><th>End</th><th>Pay Method</th><th>Trans. ID</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-id="<?php echo htmlspecialchars($user['id']); ?>">
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td data-field="username"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td data-field="email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-field="name"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td data-field="address"><?php echo htmlspecialchars($user['address']); ?></td>
                            <td data-field="region_id" data-field-id="<?php echo htmlspecialchars($user['region_id'] ?? ''); ?>"><?php echo htmlspecialchars($user['region_name_display']); ?></td>
                            <td data-field="province_id" data-field-id="<?php echo htmlspecialchars($user['province_id'] ?? ''); ?>"><?php echo htmlspecialchars($user['province_name_display']); ?></td>
                            <td data-field="city_municipality_id" data-field-id="<?php echo htmlspecialchars($user['city_municipality_id'] ?? ''); ?>"><?php echo htmlspecialchars($user['city_municipality_name_display']); ?></td>
                            <td data-field="contact"><?php echo htmlspecialchars($user['contact']); ?></td>
                            <td data-field="dob"><?php echo htmlspecialchars($user['dob']); ?></td>
                            <td data-field="gender"><?php echo htmlspecialchars($user['gender']); ?></td>
                            <td data-field="member_status"><?php echo htmlspecialchars($user['member_status']); ?></td>
                            <td data-field="created_at" data-editable="false"><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td data-field="membership_plan"><?php echo htmlspecialchars($user['membership_plan']); ?></td>
                            <td data-field="membership_amount_paid"><?php echo htmlspecialchars($user['membership_amount_paid']); ?></td>
                            <td data-field="membership_start_date"><?php echo htmlspecialchars($user['membership_start_date']); ?></td>
                            <td data-field="membership_end_date"><?php echo htmlspecialchars($user['membership_end_date']); ?></td>
                            <td data-field="membership_payment_method"><?php echo htmlspecialchars($user['membership_payment_method']); ?></td>
                            <td data-field="membership_transaction_id" data-editable="false"><?php echo htmlspecialchars($user['membership_transaction_id']); ?></td>
                            <td class="action-buttons"><button type="button" class="btn btn-warning btn-sm edit-btn">Edit</button><button type="button" class="btn btn-danger btn-sm delete-btn">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-success mt-3 add-user-btn">Add User</button>
        </div>
        
        <hr>

        <h2 class="section-heading fw-bold">Manage Events</h2>
        <div class="table-responsive mb-5">
            <table id="eventsTable" class="table table-striped table-bordered display compact cell-border" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th><th>Title</th><th>Subtitle</th><th>Description</th><th>Date</th><th>Time</th><th>Image Path</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr data-id="<?php echo htmlspecialchars($event['id']); ?>">
                            <td><?php echo htmlspecialchars($event['id']); ?></td>
                            <td data-field="title"><?php echo htmlspecialchars($event['title']); ?></td>
                            <td data-field="subtitle"><?php echo htmlspecialchars($event['subtitle']); ?></td>
                            <td data-field="description"><?php echo htmlspecialchars($event['description']); ?></td>
                            <td data-field="event_date"><?php echo htmlspecialchars($event['event_date']); ?></td>
                            <td data-field="event_time"><?php echo htmlspecialchars($event['event_time']); ?></td>
                            <td data-field="image_path"><?php echo htmlspecialchars($event['image_path']); ?></td>
                            <td class="action-buttons"><button type="button" class="btn btn-warning btn-sm edit-btn">Edit</button><button type="button" class="btn btn-danger btn-sm delete-btn">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr>

        <h2 class="section-heading fw-bold">Manage Attendees</h2>
        <div class="mb-3">
            <label for="eventFilter" class="form-label"><b>Select an Event to View Attendees:</b></label>
            <select id="eventFilter" class="form-select">
                <option value="">-- All Events --</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo htmlspecialchars($event['id']); ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="table-responsive">
            <table id="attendeesTable" class="table table-striped table-bordered display compact cell-border" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th><th>User ID</th><th>Event ID</th><th>Event Title</th><th>Event Date</th><th>Event Time</th><th>Signup Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendees as $attendee): ?>
                         <tr data-id="<?php echo htmlspecialchars($attendee['id']); ?>" data-event-id="<?php echo htmlspecialchars($attendee['event_id']); ?>">
                            <td><?php echo htmlspecialchars($attendee['id']); ?></td>
                            <td data-field="user_id"><?php echo htmlspecialchars($attendee['user_id']); ?></td>
                            <td data-field="event_id"><?php echo htmlspecialchars($attendee['event_id']); ?></td>
                            <td data-field="event_title"><?php echo htmlspecialchars($attendee['event_title']); ?></td>
                            <td data-field="event_date" data-editable="false"><?php echo htmlspecialchars($attendee['event_date']); ?></td>
                            <td data-field="event_time" data-editable="false"><?php echo htmlspecialchars($attendee['event_time']); ?></td>
                            <td data-field="signup_date" data-editable="false"><?php echo htmlspecialchars($attendee['signup_date']); ?></td>
                            <td class="action-buttons"><button type="button" class="btn btn-warning btn-sm edit-btn">Edit</button><button type="button" class="btn btn-danger btn-sm delete-btn">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-success mt-3 add-attendee-btn">Add Attendee</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="js/datatables.min.js"></script>

    <script>
    $(document).ready(function() {
        // DATA FROM PHP
        const allRegions = <?php echo json_encode($all_regions); ?>;
        const allProvinces = <?php echo json_encode($all_provinces_js); ?>;
        const allCitiesMunicipalities = <?php echo json_encode($all_cities_municipalities_js); ?>;
        const allEvents = <?php echo json_encode($events); ?>;
        const allUsers = <?php echo json_encode($users_for_js); ?>;


        // DATATABLE INITIALIZATION 
        var usersTable = $('#usersTable').DataTable({
            "scrollX": true,
            "responsive": true, 
            "columnDefs": [{ "orderable": false, "targets": -1 }]
        });
        var eventsTable = $('#eventsTable').DataTable({
            "scrollX": true,
            "responsive": true,
            "columnDefs": [{ "orderable": false, "targets": -1 }]
        });
        var attendeesTable = $('#attendeesTable').DataTable({
            "scrollX": true,
            "responsive": true,
            "columnDefs": [{ "orderable": false, "targets": -1 }]
        });
        
        // MANAGE ATTENDEES FILTER
        $('#eventFilter').on('change', function() {
            var selectedEventId = $(this).val();
            if (!selectedEventId) {
                attendeesTable.column(2).search('').draw();
            } else {
                attendeesTable.column(2).search('^' + selectedEventId + '$', true, false).draw();
            }
        });

        // EDIT/CANCEL/SAVE LOGIC
        function enterEditMode(row) {
            if (row.hasClass('editing')) return;
            row.addClass('editing');

            row.children('td:not(.action-buttons)').each(function() {
                $(this).data('original-html', $(this).html());
            });

            row.find('.action-buttons').html('<button type="button" class="btn btn-primary btn-sm save-btn">Save</button> <button type="button" class="btn btn-secondary btn-sm cancel-btn">Cancel</button>');

            row.children('td:not(.action-buttons)').each(function() {
                const cell = $(this);
                const field = cell.data('field');
                if (!field || cell.data('editable') === false) return;
                
                cell.addClass('editable-cell');
                const originalContent = cell.text();
                let inputElement;

                switch (field) {
                    case 'subtitle':
                    case 'description':
                        inputElement = `<textarea class="form-control" data-field="${field}">${originalContent}</textarea>`;
                        break;
                    case 'event_date':
                    case 'dob':
                    case 'membership_start_date':
                    case 'membership_end_date':
                        inputElement = `<input type="date" value="${originalContent}" class="form-control" data-field="${field}"/>`;
                        break;
                    case 'event_time':
                        inputElement = `<input type="text" value="${originalContent}" class="form-control" data-field="${field}"/>`;
                        break;
                    case 'gender':
                        inputElement = `<select class="form-control" data-field="${field}"><option value="Male" ${originalContent === 'Male' ? 'selected' : ''}>Male</option><option value="Female" ${originalContent === 'Female' ? 'selected' : ''}>Female</option><option value="Other" ${originalContent === 'Other' ? 'selected' : ''}>Other</option></select>`;
                        break;
                    case 'member_status':
                        inputElement = `<select class="form-control" data-field="${field}"><option value="Active" ${originalContent === 'Active' ? 'selected' : ''}>Active</option><option value="Inactive" ${originalContent === 'Inactive' ? 'selected' : ''}>Inactive</option><option value="Pending" ${originalContent === 'Pending' ? 'selected' : ''}>Pending</option></select>`;
                        break;
                    case 'region_id':
                    case 'province_id':
                    case 'city_municipality_id':
                        inputElement = `<select class="form-control" data-field="${field}"></select>`; break;
                    case 'event_title': // For Manage Attendees table
                        const originalEventId = row.data('event-id');
                        let eventOptions = allEvents.map(e => `<option value="${e.id}" ${e.id == originalEventId ? 'selected' : ''}>${e.title}</option>`).join('');
                        inputElement = `<select class="form-control" data-field="event_id">${eventOptions}</select>`;
                        break;
                    default:
                        inputElement = `<input type="text" value="${originalContent}" class="form-control" data-field="${field}"/>`;
                }
                cell.html(inputElement);
            });

            if (row.closest('table').is('#usersTable')) {
                const regionSelect = row.find('select[data-field="region_id"]');
                const provinceSelect = row.find('select[data-field="province_id"]');
                const citySelect = row.find('select[data-field="city_municipality_id"]');
                
                const originalRegionId = row.find('td[data-field="region_id"]').data('field-id') || null;
                const originalProvinceId = row.find('td[data-field="province_id"]').data('field-id') || null;
                const originalCityId = row.find('td[data-field="city_municipality_id"]').data('field-id') || null;

                populateRegions(regionSelect, originalRegionId);
                populateProvinces(provinceSelect, originalRegionId, originalProvinceId);
                populateCities(citySelect, originalRegionId, originalProvinceId, originalCityId);
            }
            
            $(row.closest('table')).DataTable().columns.adjust();
        }

        function exitEditMode(row, isNew) {
            if (isNew) {
                row.closest('table').DataTable().row(row).remove().draw();
                return;
            }
            if (!row.hasClass('editing')) return;
            row.removeClass('editing');
            row.children('td:not(.action-buttons)').each(function() {
                $(this).html($(this).data('original-html')).removeClass('editable-cell');
            });
            row.find('.action-buttons').html('<button type="button" class="btn btn-warning btn-sm edit-btn">Edit</button> <button type="button" class="btn btn-danger btn-sm delete-btn">Delete</button>');
        }
        
        // EVENT HANDLERS FOR ALL TABLES
        $('table').on('click', '.edit-btn', function() {
            enterEditMode($(this).closest('tr'));
        });

        $('table').on('click', '.cancel-btn', function() {
            var row = $(this).closest('tr');
            var isNew = row.hasClass('new-row-form');
            exitEditMode(row, isNew);
        });

        // SAVE BUTTON HANDLER (Handles both Add and Update)
        $('table').on('click', '.save-btn', function() {
            var row = $(this).closest('tr');
            var tableId = row.closest('table').attr('id');
            var isNew = row.hasClass('new-row-form');
            
            var data = {
                id: isNew ? 0 : row.data('id'),
                table: tableId.replace('Table', ''),
                action: isNew ? 'add' : 'update'
            };
            
            var hasChanges = false;
            row.find('input, select, textarea').each(function() {
                var field = $(this).data('field');
                if(field) {
                    data[field] = $(this).val();
                    hasChanges = true;
                }
            });

            if (!hasChanges && !isNew) {
                exitEditMode(row, false);
                return;
            }
            
            $.ajax({
                url: 'admintools.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Refresh the page to show all changes
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error.'));
                        if(!isNew) exitEditMode(row, false); // Revert changes on failure for existing rows
                    }
                },
                error: function(xhr, status, error) {
                    alert('An AJAX error occurred: ' + error);
                    if(!isNew) exitEditMode(row, false);
                }
            });
        });

        // DELETE BUTTON HANDLER
        $('table').on('click', '.delete-btn', function() {
            if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) return;
            
            var row = $(this).closest('tr');
            var id = row.data('id');
            var tableId = row.closest('table').attr('id');
            var data = { 
                id: id, 
                table: tableId.replace('Table', ''),
                action: 'delete'
            };

            $.ajax({
                url: 'admintools.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        row.closest('table').DataTable().row(row).remove().draw();
                    } else {
                        alert('Error deleting record: ' + (response.message || 'Unknown error.'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('An AJAX error occurred while deleting: ' + error);
                }
            });
        });

        // ADD USER BUTTON
        $('.add-user-btn').click(function() {
            var newRow = usersTable.row.add([
                '<span class="temp-id">NEW</span>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="username"></td>',
                '<td class="editable-cell"><input type="email" class="form-control" data-field="email"></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="name"></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="address"></td>',
                '<td class="editable-cell"><select class="form-control" data-field="region_id"></select></td>',
                '<td class="editable-cell"><select class="form-control" data-field="province_id"></select></td>',
                '<td class="editable-cell"><select class="form-control" data-field="city_municipality_id"></select></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="contact"></td>',
                '<td class="editable-cell"><input type="date" class="form-control" data-field="dob"></td>',
                '<td class="editable-cell"><select class="form-control" data-field="gender"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></td>',
                '<td class="editable-cell"><select class="form-control" data-field="member_status"><option value="Pending">Pending</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></td>',
                '', // Created At (not editable)
                '<td class="editable-cell"><input type="text" class="form-control" data-field="membership_plan"></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="membership_amount_paid"></td>',
                '<td class="editable-cell"><input type="date" class="form-control" data-field="membership_start_date"></td>',
                '<td class="editable-cell"><input type="date" class="form-control" data-field="membership_end_date"></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="membership_payment_method"></td>',
                '<td class="editable-cell"><input type="text" class="form-control" data-field="membership_transaction_id"></td>',
                '<td class="action-buttons"><button type="button" class="btn btn-primary btn-sm save-btn">Save</button> <button type="button" class="btn btn-secondary btn-sm cancel-btn">Cancel</button></td>'
            ]).draw(false).node();
            
            $(newRow).addClass('new-row-form').find('td').addClass('editable-cell');
            populateRegions($(newRow).find('select[data-field="region_id"]'));
        });

        // ADD ATTENDEE BUTTON
        $('.add-attendee-btn').click(function() {
            var userOptions = allUsers.map(u => `<option value="${u.id}">${u.username} (ID: ${u.id})</option>`).join('');
            var eventOptions = allEvents.map(e => `<option value="${e.id}">${e.title}</option>`).join('');

            var newRow = attendeesTable.row.add([
                '<span class="temp-id">NEW</span>',
                `<select class="form-control" data-field="user_id"><option value="">Select User</option>${userOptions}</select>`,
                `<select class="form-control" data-field="event_id"><option value="">Select Event</option>${eventOptions}</select>`,
                '<span></span><input type="hidden" data-field="event_title" />',
                '<span></span><input type="hidden" data-field="event_date" />',
                '<span></span><input type="hidden" data-field="event_time" />',
                '', 
                '<button type="button" class="btn btn-primary btn-sm save-btn">Save</button> <button type="button" class="btn btn-secondary btn-sm cancel-btn">Cancel</button>'
            ]).draw(false).node();
            
            $(newRow).addClass('new-row-form');
            // Add the 'action-buttons' class to the last cell
            $(newRow).find('td:last-child').addClass('action-buttons');
        });
        
        // Auto-populate event details when adding an attendee
        $('#attendeesTable tbody').on('change', 'tr.new-row-form select[data-field="event_id"]', function() {
            var row = $(this).closest('tr');
            var eventId = $(this).val();
            var event = allEvents.find(e => e.id == eventId);

            // Find cells by their index in the row
            var titleCell = row.find('td').eq(3);
            var dateCell = row.find('td').eq(4);
            var timeCell = row.find('td').eq(5);

            if (event) {
                titleCell.find('span').text(event.title);
                titleCell.find('input').val(event.title);

                dateCell.find('span').text(event.event_date);
                dateCell.find('input').val(event.event_date);

                timeCell.find('span').text(event.event_time);
                timeCell.find('input').val(event.event_time);
            } else {
                // Clear other values if "Select Event" is chosen
                titleCell.find('span').text('');
                titleCell.find('input').val('');
                dateCell.find('span').text('');
                dateCell.find('input').val('');
                timeCell.find('span').text('');
                timeCell.find('input').val('');
            }
        });


        // LOCATION DROPDOWN LOGIC
        function populateRegions(selectElement, selectedRegionId = null) {
            selectElement.empty().append('<option value="">Select Region</option>');
            allRegions.forEach(region => {
                const selectedAttr = (selectedRegionId !== null && region.id == selectedRegionId) ? 'selected' : '';
                selectElement.append(`<option value="${region.id}" ${selectedAttr}>${region.region_name}</option>`);
            });
        }

        function populateProvinces(selectElement, regionId, selectedProvinceId = null) {
            selectElement.empty().append('<option value="">Select Province</option>');
            selectElement.prop('disabled', true);
            const numericRegionId = parseInt(regionId);
            const regionObj = allRegions.find(r => r.id == numericRegionId);
            
            if (regionObj) {
                if (regionObj.region_code === "NCR") {
                    selectElement.append('<option value="NULL" selected>N/A (NCR)</option>');
                } else {
                    selectElement.prop('disabled', false);
                    let filteredProvinces = allProvinces.filter(p => p.region_id == numericRegionId);
                    filteredProvinces.forEach(function(province) {
                        const selectedAttr = (selectedProvinceId !== null && province.id == selectedProvinceId) ? 'selected' : '';
                        selectElement.append(`<option value="${province.id}" ${selectedAttr}>${province.province_name}</option>`);
                    });
                }
            }
        }
        
        function populateCities(selectElement, regionId, provinceId, selectedCityId = null) {
            selectElement.empty().append('<option value="">Select City/Municipality</option>');
            selectElement.prop('disabled', true);
            const numericRegionId = parseInt(regionId);
            const numericProvinceId = (provinceId === 'NULL' || provinceId === null || provinceId === '') ? null : parseInt(provinceId);
            const regionObj = allRegions.find(r => r.id == numericRegionId);

            if (regionObj) {
                let filteredCities = [];
                if (regionObj.region_code === "NCR") {
                    selectElement.prop('disabled', false);
                    filteredCities = allCitiesMunicipalities.filter(c => c.region_id == numericRegionId && c.province_id === null);
                } else if (numericProvinceId) {
                    selectElement.prop('disabled', false);
                    filteredCities = allCitiesMunicipalities.filter(c => c.province_id == numericProvinceId);
                }
                
                filteredCities.forEach(function(city) {
                    const selectedAttr = (selectedCityId !== null && city.id == selectedCityId) ? 'selected' : '';
                    selectElement.append(`<option value="${city.id}" ${selectedAttr}>${city.city_municipality_name}</option>`);
                });
            }
        }
        
        // DELEGATED EVENT HANDLERS FOR DYNAMIC DROPDOWNS
        $('#usersTable tbody').on('change', 'select[data-field="region_id"]', function() {
            const row = $(this).closest('tr');
            if (!row.hasClass('editing') && !row.hasClass('new-row-form')) return;

            const provinceSelect = row.find('select[data-field="province_id"]');
            const citySelect = row.find('select[data-field="city_municipality_id"]');
            const selectedRegionId = $(this).val();

            populateProvinces(provinceSelect, selectedRegionId);
            provinceSelect.trigger('change');
        });

        $('#usersTable tbody').on('change', 'select[data-field="province_id"]', function() {
            const row = $(this).closest('tr');
            if (!row.hasClass('editing') && !row.hasClass('new-row-form')) return;

            const regionSelect = row.find('select[data-field="region_id"]');
            const citySelect = row.find('select[data-field="city_municipality_id"]');
            const selectedRegionId = regionSelect.val();
            const selectedProvinceId = $(this).val();
            
            populateCities(citySelect, selectedRegionId, selectedProvinceId);
        });

    });
    </script>
</body>
</html>