<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']); // It's better to check for 'user_id' for login status

// Check if the user has an active membership
$hasMembership = ($isLoggedIn && isset($_SESSION['member_status']) && $_SESSION['member_status'] === 'Active');

// Membership link URL
$membershipLink = $hasMembership ? 'afterpayment.php' : 'payment.php';

// Login/Account Management link URL
$loginRedirectPage = $isLoggedIn ? 'user_management.php' : 'login.php';

// Logged in as ' ' text
$loginLinkText = $isLoggedIn ? 'Logged in as ' . htmlspecialchars($_SESSION['username']) : 'Login';

// Exclusive Events and Discussions links
$exclusiveEventsLink = '#'; 
$discussionsLink = '#';     

if ($isLoggedIn) {
    if ($hasMembership) {
        $exclusiveEventsLink = 'events.php';
        $discussionsLink = 'discussions.php';
    } else {
        // Logged in but not a member
        $exclusiveEventsLink = 'payment.php';
        $discussionsLink = 'payment.php';
    }
} else {
    // Not logged in
    $exclusiveEventsLink = 'login.php';
    $discussionsLink = 'login.php';
}

?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-shrink" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="main.php#page-top"><img src="assets/img/navbar-logo.png" alt="..." /></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
            Menu
            <i class="fas fa-bars ms-1"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarResponsive">
            <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                <li class="nav-item"><a class="nav-link" href="main.php#about">About</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($exclusiveEventsLink) ?>">Exclusive Events</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($discussionsLink) ?>">Discussions</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($membershipLink) ?>">Membership</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($loginRedirectPage) ?>"><?= $loginLinkText ?></a>
                </li>
            </ul>
        </div>
    </div>
</nav>