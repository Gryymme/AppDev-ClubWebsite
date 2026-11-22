<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']); 

// Check if the user has an active membership
$hasMembership = ($isLoggedIn && isset($_SESSION['member_status']) && $_SESSION['member_status'] === 'Active');

// Determine the Membership link URL
$membershipLink = $hasMembership ? 'afterpayment.php' : 'payment.php';

// Determine the Login/Account Management link URL
$loginRedirectPage = $isLoggedIn ? 'user_management.php' : 'login.php';

// Determine the text for the Login/Account Management link
$loginLinkText = $isLoggedIn ? 'Logged in as ' . htmlspecialchars($_SESSION['username']) : 'Login';

// Logic for Exclusive Events and Newsletter links
$exclusiveEventsLink = '#'; // Default to a placeholder if no access
$discussionsLink = '#';     // Default to a placeholder if no access

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

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- REQUIRED META TAGS -->
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Arcane Library</title>
        <!-- Website Tab Icon -->
        <link rel="icon" type="image/x-icon" href="assets/arcane.ico" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
        <!-- Core theme CSS (includes Bootstrap) -->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body id="page-top">
        <!-- HEADER (NAVIGATION BAR) -->
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

        <!-- MASTHEAD (PAGE HEADER) -->
        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading">Where forgotten knowledge awakens</div>
                <div class="masthead-heading text-uppercase">Arcane Library Club</div>
                <a class="btn btn-primary btn-xl text-uppercase" href="#membership_perks">Explore Arcane Library</a>
            </div>
        </header>

        <!-- ABOUT SECTION -->
        <section class="page-section" id="about">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">About Arcane Library</h2>
                    <h3 class="section-subheading text-muted">Arcane Library is a book club devoted to uncovering the overlooked, the obscure, and the nearly lost. We explore texts that slip through the cracks of conventional reading lists—forgotten histories, suppressed ideas, neglected thinkers, and the hidden currents that shape our world.</h3>
                    <h3 class="section-subheading text-muted">Each month, we delve into a carefully selected work that invites reflection, discussion, and discovery. From obscure historical accounts and banned books to underappreciated essays and rediscovered classics, our reading list is a journey into the shadowed corners of knowledge.</h3>
                    <h3 class="section-subheading text-muted">Arcane Library is a space for readers who ask deeper questions, who enjoy peeling back layers, and who believe that the past holds keys to understanding the present—and perhaps the future.</h3>
                    <h3 class="section-subheading text-muted">Join us as we read between the lines, dust off forgotten pages, and awaken the knowledge time tried to bury.</h3>
                </div>
            </div>
        </section>

        <!-- WHY JOIN SECTION -->
        <section class="page-section bg-light" id="membership_perks">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Why Join Arcane Library?</h2>
                    <h3 class="section-subheading text-muted">Step into a world where stories come to life and ideas spark endless conversations. Arcane Library is more than a book club, it’s a community of passionate readers, explorers of literature, and seekers of knowledge. Whether you love diving into fantasy realms, unraveling mysteries, or discussing thought, provoking works, you’ll find a place here. Join us and turn every page into an adventure. Let the reading begin!</h3>
                </div>
            </div>
        </section>

        <!-- WHY JOIN ITEMS -->
        <section class="page-section" id="why-join">
            <div class="container">
                
                <div class="row align-items-center mb-6">
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/1.png" alt="Curated Collection" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">Access to Exclusive Events</h2>
                        <p class="text-muted">From midnight readings and banned book nights to live debates and curated guest lectures, Arcane Library offers members access to events you won’t find anywhere else. These gatherings, both online and in select physical locations, bring together thinkers, authors, and fellow readers to celebrate the obscure, the controversial, and the deeply thought-provoking. It's more than attendance, it's participation in a living, breathing exploration of forgotten knowledge.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section bg-light" id="why-join">
            <div class="container">
                <div class="row align-items-center flex-row-reverse mb-6">
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/2.png" alt="Exclusive Member Perks" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">Author Q&A Sessions</h2>
                        <p class="text-muted">Author Q&A session is where our members can interact with writers in real time, asking questions, gaining deeper insights into the book, and hearing stories from behind the scenes. These conversations allow authors to open up about their inspirations, writing challenges, and creative decisions. Do you want to know what real-life events inspired a character? You’ll get your answers from the source.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section" id="why-join">
            <div class="container">
                <div class="row align-items-center mb-6">
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/3.png" alt="Community and Events" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">To Seek What Was Forgotten</h2>
                        <p class="text-muted">Because it’s not just a club, it’s a sanctuary for the curious, the imaginative, and the brave-hearted. Here, you don’t just read books, you uncover lost knowledge, revive ancient truths, and share stories powerful enough to reshape minds.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section bg-light" id="why-join">
            <div class="container">
                <div class="row align-items-center flex-row-reverse mb-6">
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/4.png" alt="Comfortable Reading Spaces" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">Discover Books You Won’t Find Anywhere Else</h2>
                        <p class="text-muted">Arcane Library curates titles that go far beyond mainstream book clubs. We focus on underappreciated works, forgotten histories, and texts that challenge conventional thinking. If you're tired of repetitive reading lists and want to explore ideas that are rarely discussed, this is the place. Each selection is chosen to spark fresh insight, not just follow trends.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section" id="why-join">
            <div class="container">
                <div class="row align-items-center mb-6">
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/5.png" alt="Digital Resources" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">Thoughtful Discussions</h2>
                        <p class="text-muted">Whether you’re someone who loves analyzing character arcs and literary devices or you want to talk about how a story made you feel, there’s a place for you in our discussions. We create a welcoming space for all kinds of readers, whether you speak up or simply listen, you’ll walk away from each session with fresh insights and a deeper appreciation for the books we read.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-section bg-light" id="why-join">
            <div class="container">
                <div class="row align-items-center flex-row-reverse mb-6"> 
                    <div class="col-lg-6">
                        <img class="img-fluid rounded mb-3 mb-lg-0" src="assets/img/perks/6.png" alt="Personalized Recommendations" />
                    </div>
                    <div class="col-lg-6">
                        <h2 class="section-heading">A Community of Inquisitive Minds</h2>
                        <p class="text-muted">At Arcane Library, you’ll find a community bound by curiosity and a hunger for knowledge. Our members come from diverse backgrounds but share one thing in common: a desire to ask deeper questions and explore the unseen layers of history, literature, and philosophy. Whether you’re a seasoned academic or a passionate autodidact, you’ll feel at home among kindred spirits who challenge, inspire, and expand your worldview.</p>
                    </div>
                </div>
            </div>
        </section>
   
        <!-- FOOTER (BOTTOM OF THE PAGE) -->
        <footer class="footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-lg-start">Copyright &copy; Arcane Library Club 2025</div>
                    <div class="col-lg-4 my-3 my-lg-0">
                        <a class="btn btn-dark btn-social mx-2" href="https://x.com/home" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a class="btn btn-dark btn-social mx-2" href="https://www.facebook.com/" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-dark btn-social mx-2" href="https://www.linkedin.com/" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="link-dark text-decoration-none me-3" href="#!">Privacy Policy</a>
                        <a class="link-dark text-decoration-none" href="#!">Terms of Use</a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Bootstrap core JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS -->
        <script src="js/scripts.js"></script>
    </body>
</html>
