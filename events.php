<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database/db_connection.php';

// Check for Admin User
$isAdmin = (isset($_SESSION['username']) && $_SESSION['username'] === 'admin' && isset($_SESSION['email']) && $_SESSION['email'] === 'admin@email.com');

// Handle Edit Event Form Submission (for Admin only)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $eventId = $_POST['event_id'];
    $title = $_POST['event_title'];
    $subtitle = $_POST['event_subtitle'];
    $description = $_POST['event_description'];
    $date = $_POST['event_date'];
    $time = $_POST['event_time'];
    
    // Update the main 'events' table
    $stmt = $conn->prepare("UPDATE events SET title = ?, subtitle = ?, description = ?, event_date = ?, event_time = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $title, $subtitle, $description, $date, $time, $eventId);
    $stmt->execute();
    $stmt->close();
    
    // Update the 'signed_up_events' table 
    $eventDateFormatted = date("F j, Y", strtotime($date));
    $stmt_signed_up = $conn->prepare("UPDATE signed_up_events SET event_title = ?, event_date = ?, event_time = ? WHERE event_id = ?");
    $stmt_signed_up->bind_param("sssi", $title, $eventDateFormatted, $time, $eventId);
    $stmt_signed_up->execute();
    $stmt_signed_up->close();
    
    $_SESSION['message'] = "Event '" . htmlspecialchars($title) . "' updated successfully!";
    $_SESSION['msg_type'] = "success";

    header("Location: events.php");
    exit();
}

// Fetch all events from the database
$today = date('Y-m-d');
$result = $conn->query("SELECT * FROM events WHERE event_date >= '$today' ORDER BY event_date ASC");
$events = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Navigation Bar Logic
$isLoggedIn = isset($_SESSION['user_id']);
$hasMembership = ($isLoggedIn && isset($_SESSION['member_status']) && $_SESSION['member_status'] === 'Active');
$membershipLink = $hasMembership ? 'afterpayment.php' : 'payment.php';
$loginRedirectPage = $isLoggedIn ? 'user_management.php' : 'login.php';
$loginLinkText = $isLoggedIn ? 'Logged in as ' . htmlspecialchars($_SESSION['username']) : 'Login';
$exclusiveEventsLink = ($isLoggedIn && $hasMembership) ? 'events.php' : ($isLoggedIn ? 'payment.php' : 'login.php');
$newsletterLink = ($isLoggedIn && $hasMembership) ? 'discussions.php' : ($isLoggedIn ? 'payment.php' : 'login.php');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Events at Arcane Library</title>
        <link rel="icon" type="image/x-icon" href="assets/arcane.ico" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
        <link href="css/styles.css" rel="stylesheet" />
        <style>
            .edit-form-container { display: none; margin-top: 2rem; border-top: 1px solid #dee2e6; padding-top: 2rem; }
            .modal-button-container {
                max-width: 300px; /* Constrains the horizontal length of the buttons */
                margin-left: auto;
                margin-right: auto;
            }
            .portfolio-modal .btn {
                font-family: 'Montserrat', sans-serif !important;
                font-weight: bold !important;
            }
        </style>
    </head>
    <body id="page-top">
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-shrink" id="mainNav">
            <div class="container">
                <a class="navbar-brand" href="main.php#page-top"><img src="assets/img/navbar-logo.png" alt="..." /></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu <i class="fas fa-bars ms-1"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link" href="main.php#about">About</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($exclusiveEventsLink) ?>">Exclusive Events</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($newsletterLink) ?>">Discussions</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($membershipLink) ?>">Membership</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($loginRedirectPage) ?>"><?= $loginLinkText ?></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading">Arcane Library Club</div>
                <div class="masthead-heading text-uppercase">Exclusive Events</div>
                <a class="btn btn-primary btn-xl text-uppercase" href="#portfolio">Browse Events</a>
            </div>
        </header>

        <section class="page-section bg-light" id="portfolio">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Upcoming Events</h2>
                    <h3 class="section-subheading text-muted">Browse upcoming events organized by Arcane Library</h3>
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?= $_SESSION['msg_type'] == 'danger' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['msg_type']); endif; ?>
                </div>
                <div class="row">
                    <?php foreach ($events as $event): ?>
                    <div class="col-lg-4 col-sm-6 mb-4">
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal<?= $event['id'] ?>">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="<?= htmlspecialchars($event['image_path']) ?>" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading"><?= htmlspecialchars($event['title']) ?></div>
                                <div class="portfolio-caption-subheading text-muted"><?= date("F j, Y", strtotime($event['event_date'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($events)): ?>
                        <div class="col-12 text-center">
                            <p class="text-muted">No upcoming events scheduled at this time. Please check back later!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <footer class="footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-lg-start">Copyright &copy; Arcane Library Club 2025</div>
                    <div class="col-lg-4 my-3 my-lg-0"></div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="link-dark text-decoration-none me-3" href="#!">Privacy Policy</a>
                        <a class="link-dark text-decoration-none" href="#!">Terms of Use</a>
                    </div>
                </div>
            </div>
        </footer>

        <?php foreach ($events as $event): ?>
        <div class="portfolio-modal modal fade" id="portfolioModal<?= $event['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <img class="img-fluid d-block mx-auto" src="<?= htmlspecialchars($event['image_path']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" />
                                        </div>
                                        <div class="col-md-6 text-left">
                                            <h2 class="text-uppercase"><?= htmlspecialchars($event['title']) ?></h2>
                                            <p class="item-intro text-muted"><?= htmlspecialchars($event['subtitle']) ?></p>
                                            <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                            <ul class="list-unstyled">
                                                <li><strong>Date:</strong> <?= date("F j, Y", strtotime($event['event_date'])) ?></li>
                                                <li><strong>Time:</strong> <?= htmlspecialchars($event['event_time']) ?></li>
                                            </ul>
                                            
                                            <div class="d-grid gap-2 modal-button-container">
                                                <form action="process_event_signup.php" method="POST" class="d-grid">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    <button class="btn btn-primary text-uppercase" type="submit">Reserve Ticket</button>
                                                </form>

                                                <?php if ($isAdmin): ?>
                                                    <button class="btn btn-warning text-uppercase edit-event-btn" type="button" data-form-id="edit-form-<?= $event['id'] ?>">Edit Event</button>
                                                    <a href="admintools.php" class="btn btn-secondary text-uppercase">Edit Using Admin Tools</a>
                                                <?php endif; ?>

                                                <button class="btn btn-secondary text-uppercase" data-bs-dismiss="modal" type="button">
                                                    <i class="fas fa-xmark me-1"></i> Close
                                                </button>
                                            </div>
                                            <?php if ($isAdmin): ?>
                                            <div class="edit-form-container" id="edit-form-<?= $event['id'] ?>">
                                                <h4 class="text-uppercase">Edit Event Details</h4>
                                                <form action="events.php" method="POST">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    <div class="form-group mb-3"><label for="event_title_<?= $event['id'] ?>">Title</label><input type="text" class="form-control" name="event_title" id="event_title_<?= $event['id'] ?>" value="<?= htmlspecialchars($event['title']) ?>" required></div>
                                                    <div class="form-group mb-3"><label for="event_subtitle_<?= $event['id'] ?>">Subtitle</label><input type="text" class="form-control" name="event_subtitle" id="event_subtitle_<?= $event['id'] ?>" value="<?= htmlspecialchars($event['subtitle']) ?>" required></div>
                                                    <div class="form-group mb-3"><label for="event_description_<?= $event['id'] ?>">Description</label><textarea class="form-control" name="event_description" id="event_description_<?= $event['id'] ?>" rows="4" required><?= htmlspecialchars($event['description']) ?></textarea></div>
                                                    <div class="form-group mb-3"><label for="event_date_<?= $event['id'] ?>">Date</label><input type="date" class="form-control" name="event_date" id="event_date_<?= $event['id'] ?>" value="<?= htmlspecialchars($event['event_date']) ?>" required></div>
                                                    <div class="form-group mb-3"><label for="event_time_<?= $event['id'] ?>">Time</label><input type="text" class="form-control" name="event_time" id="event_time_<?= $event['id'] ?>" value="<?= htmlspecialchars($event['event_time']) ?>" required></div>
                                                    <button type="submit" name="update_event" class="btn btn-success">Update Event</button>
                                                    <button type="button" class="btn btn-danger cancel-edit-btn" data-form-id="edit-form-<?= $event['id'] ?>">Cancel</button>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/scripts.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const editButtons = document.querySelectorAll('.edit-event-btn');
                const cancelButtons = document.querySelectorAll('.cancel-edit-btn');

                editButtons.forEach(button => {
                    button.addEventListener('click', function () {
                        const formId = this.getAttribute('data-form-id');
                        const formContainer = document.getElementById(formId);
                        if (formContainer) {
                            formContainer.style.display = 'block';
                        }
                    });
                });

                cancelButtons.forEach(button => {
                    button.addEventListener('click', function () {
                        const formId = this.getAttribute('data-form-id');
                        const formContainer = document.getElementById(formId);
                        if (formContainer) {
                            formContainer.style.display = 'none';
                        }
                    });
                });
            });
        </script>
    </body>
</html>