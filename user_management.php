<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'database/db_connection.php';

// User details
$userId = $_SESSION['user_id'];

// Fetch user's full name and other details directly from the database
$name = 'N/A';
$username = 'Guest';
$email = 'N/A';
$member_status = 'Not a member';

// Execute query to get user details
$userStmt = $conn->prepare("SELECT name, username, email, member_status FROM users WHERE id = ?");
if ($userStmt) {
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($user = $userResult->fetch_assoc()) {
        // Assign fetched details to variables
        $name = $user['name'];
        $username = $user['username'];
        $email = $user['email'];
        $member_status = $user['member_status'];
    }
    $userStmt->close();
} else {
    error_log("Error preparing user details query: " . $conn->error);
}

// Fetch signed-up events with full details
$signed_up_events = [];
$query = "
    SELECT
        se.event_id,
        e.title AS event_title,
        e.subtitle,
        e.description,
        e.image_path,
        e.event_date,
        e.event_time
    FROM
        signed_up_events se
    JOIN
        events e ON se.event_id = e.id
    WHERE
        se.user_id = ?
    ORDER BY
        e.event_date ASC
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $signed_up_events[] = $row;
    }
    $stmt->close();
} else {
    error_log("Error preparing signed-up events query: " . $conn->error);
}
$conn->close(); 

// Display message after a ticket deletion
$message = $_SESSION['message'] ?? '';
$msg_type = $_SESSION['msg_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['msg_type']);

// Define the condition for displaying the admin button
$isAdmin = ($username === 'admin' && $email === 'admin@email.com');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <link rel="icon" type="image/x-icon" href="assets/arcane.ico" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
    <link href="css/styles.css" rel="stylesheet" />
    <link href="css/login_style.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-body { padding: 2rem; }
        .account-details ul { list-style: none; padding: 0; }
        .account-details li { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .account-details li span { font-weight: bold; }
        .event-item { border: 1px solid #dee2e6; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .event-item h5 { margin-bottom: 5px; }
        .event-item p { margin-bottom: 5px; font-size: 0.9em; }
        .event-item .btn-group { margin-top: 10px; display: flex; gap: 5px; }
        .modal-body .list-unstyled li { margin-bottom: 0.5rem; }
        .modal-body .text-left { text-align: left; }

        .event-item .btn-group .btn {
            flex-grow: 1; 
            border-radius: 0.375rem !important; 
            font-weight: bold; 
        }
        .event-item .btn-group .btn.btn-warning {
            color: #fff; 
        }
        
    </style>
</head>
<body class="bg-primary">
    <?php include 'navbar.php'; ?>
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center align-items-center h-100">
            <div class="col-12 col-md-10 col-lg-8 col-xl-7">
                <div class="card shadow-2-strong" style="border-radius: 1rem;">
                    <div class="card-body p-5">
                        <h1 class="text-center mb-4">Account Management</h1>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h2 class="h4 mb-3">Reserved Tickets:</h2>
                                <?php if (!empty($signed_up_events)): ?>
                                    <?php foreach ($signed_up_events as $event): ?>
                                        <div class="event-item">
                                            <h5 class="text-uppercase"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                            <p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($event['event_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo htmlspecialchars($event['event_time']); ?></p>
                                            <div class="btn-group" role="group" aria-label="Event actions">
                                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#eventInfoModal<?php echo htmlspecialchars($event['event_id']); ?>">
                                                    EVENT INFO
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm cancel-ticket-btn" data-eventid="<?php echo htmlspecialchars($event['event_id']); ?>">
                                                    CANCEL TICKET
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>You have no reserved tickets for any club events.</p>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 ps-md-4 account-details">
                                <h2 class="h4 mb-3">Account Details:</h2>
                                <ul class="mb-4">
                                    <li><span>Name:</span> <?php echo htmlspecialchars($name); ?></li>
                                    <li><span>Username:</span> <?php echo htmlspecialchars($username); ?></li>
                                    <li><span>Email:</span> <?php echo htmlspecialchars($email); ?></li>
                                    <li><span>Member Status:</span> <?php echo htmlspecialchars($member_status); ?></li>
                                </ul>

                                <?php if ($isAdmin): ?>
                                    <a href="admintools.php" class="btn btn-dark btn-lg w-100 mt-3">LAUNCH ADMIN TOOLS</a>
                                <?php endif; ?>

                                <a href="editinfo.php" class="btn btn-dark btn-lg w-100 mt-3">VIEW / EDIT INFO</a>
                                <a href="logout.php" class="btn btn-dark btn-lg w-100 mt-3">LOG OUT</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteTicketForm" action="process_delete_ticket.php" method="POST" style="display: none;">
        <input type="hidden" id="eventIdToDelete" name="event_id_to_delete" value="">
    </form>

    <?php foreach ($signed_up_events as $event): ?>
    <div class="modal fade" id="eventInfoModal<?php echo htmlspecialchars($event['event_id']); ?>" tabindex="-1" aria-labelledby="eventInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventInfoModalLabel"><b>About The Event</b></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <img class="img-fluid d-block mx-auto" src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="<?php echo htmlspecialchars($event['event_title']); ?>" />
                            </div>
                            <div class="col-md-6 text-left">
                                <h2 class="text-uppercase"><?php echo htmlspecialchars($event['event_title']); ?></h2>
                                <p class="item-intro text-muted"><?php echo htmlspecialchars($event['subtitle']); ?></p>
                                <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                <ul class="list-unstyled mt-4">
                                    <li><strong>Date:</strong> <?php echo date("F j, Y", strtotime($event['event_date'])); ?></li>
                                    <li><strong>Time:</strong> <?php echo htmlspecialchars($event['event_time']); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cancelButtons = document.querySelectorAll('.cancel-ticket-btn');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('You are about to cancel your reserved ticket. Click OK to proceed.')) {
                    const eventId = this.getAttribute('data-eventid');
                    const deleteForm = document.getElementById('deleteTicketForm');
                    const eventIdInput = document.getElementById('eventIdToDelete');
                    eventIdInput.value = eventId;
                    deleteForm.submit();
                }
            });
        });
    });
    </script>
</body>
</html>