<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database/db_connection.php';

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_username = $_SESSION['username'] ?? ''; // Get current username for admin check

// Check if the user has an active membership
$hasMembership = ($isLoggedIn && isset($_SESSION['member_status']) && $_SESSION['member_status'] === 'Active');

// ACCESS CONTROL
if (!$isLoggedIn) {
    header("Location: login.php");
    exit();
}
if (!$hasMembership) {
    header("Location: payment.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An error occurred.'];
    $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : null;
    $reply_id = isset($_POST['reply_id']) ? (int)$_POST['reply_id'] : null;
    $item_id = $thread_id ?: $reply_id;
    $is_thread = (bool)$thread_id;

    if ($current_user_id > 0 && $item_id > 0) {
        if ($is_thread) {
            $check_stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND thread_id = ?");
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND reply_id = ?");
        }
        $check_stmt->bind_param("ii", $current_user_id, $item_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            if ($is_thread) {
                $toggle_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND thread_id = ?");
            } else {
                $toggle_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND reply_id = ?");
            }
            $response['user_liked'] = false;
        } else {
            if ($is_thread) {
                $toggle_stmt = $conn->prepare("INSERT INTO likes (user_id, thread_id) VALUES (?, ?)");
            } else {
                $toggle_stmt = $conn->prepare("INSERT INTO likes (user_id, reply_id) VALUES (?, ?)");
            }
            $response['user_liked'] = true;
        }
        $toggle_stmt->bind_param("ii", $current_user_id, $item_id);
        $toggle_stmt->execute();

        if ($is_thread) {
            $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE thread_id = ?");
        } else {
            $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE reply_id = ?");
        }
        $count_stmt->bind_param("i", $item_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $response['new_like_count'] = $count_result['count'];
        $response['success'] = true;
    }

    echo json_encode($response);
    exit();
}

// HANDLE STANDARD FORM SUBMISSIONS (Threads, Replies, and Deletes)

// Handle creating a new thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_thread'])) {
    $content = trim($_POST['thread_content']);
    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO threads (user_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $current_user_id, $content);
        $stmt->execute();
        $stmt->close();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }
}

// Handle posting a reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_reply'])) {
    $content = trim($_POST['reply_content']);
    $thread_id = $_POST['thread_id'];
    if (!empty($content) && !empty($thread_id)) {
        $stmt = $conn->prepare("INSERT INTO replies (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $thread_id, $current_user_id, $content);
        $stmt->execute();
        $stmt->close();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "#thread-" . $thread_id);
        exit();
    }
}

// Handle deleting a thread or reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $thread_id_to_delete = $_POST['thread_id'] ?? null;
    $reply_id_to_delete = $_POST['reply_id'] ?? null;

    if ($thread_id_to_delete) {
        $stmt = $conn->prepare("SELECT user_id FROM threads WHERE id = ?");
        $stmt->bind_param("i", $thread_id_to_delete);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['user_id'] == $current_user_id || $current_username === 'admin') {
                $delete_stmt = $conn->prepare("DELETE FROM threads WHERE id = ?");
                $delete_stmt->bind_param("i", $thread_id_to_delete);
                $delete_stmt->execute();
            }
        }
    } elseif ($reply_id_to_delete) {
        $stmt = $conn->prepare("SELECT user_id FROM replies WHERE id = ?");
        $stmt->bind_param("i", $reply_id_to_delete);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['user_id'] == $current_user_id || $current_username === 'admin') {
                $delete_stmt = $conn->prepare("DELETE FROM replies WHERE id = ?");
                $delete_stmt->bind_param("i", $reply_id_to_delete);
                $delete_stmt->execute();
            }
        }
    }
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// SORTING LOGIC
$sort_option = $_GET['sort'] ?? 'newest'; // Default to 'newest'
$order_by_sql = "t.created_at DESC";      // Default ORDER BY clause

if ($sort_option === 'top') {
    $order_by_sql = "like_count DESC, t.created_at DESC";
} elseif ($sort_option === 'oldest') {
    $order_by_sql = "t.created_at ASC";
}

// PHP LOGIC FOR DATA FETCHING
$sql_threads = "
    SELECT
        t.id,
        t.content,
        t.created_at,
        t.user_id,
        u.username,
        (SELECT COUNT(*) FROM likes WHERE thread_id = t.id) AS like_count,
        (SELECT COUNT(*) FROM replies WHERE thread_id = t.id) AS reply_count,
        (SELECT COUNT(*) FROM likes WHERE thread_id = t.id AND user_id = ?) AS user_liked
    FROM
        threads t
    JOIN
        users u ON t.user_id = u.id
    ORDER BY
        $order_by_sql";
$threads_stmt = $conn->prepare($sql_threads);
$threads_stmt->bind_param("i", $current_user_id);
$threads_stmt->execute();
$threads_result = $threads_stmt->get_result();


// NAVIGATION BAR LOGIC
$membershipLink = 'afterpayment.php';
$loginRedirectPage = 'user_management.php';
$loginLinkText = 'Logged in as ' . htmlspecialchars($_SESSION['username']);
$exclusiveEventsLink = 'events.php';
$discussionLink = 'discussions.php';

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Arcane Discussions</title>
        <link rel="icon" type="image/x-icon" href="assets/arcane.ico" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
        <link href="css/styles.css" rel="stylesheet" />
        <style>
            .post-container, .create-thread-container {
                background-color: #fff;
                border: 1px solid #dee2e6;
                border-radius: 0.75rem;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            }
            .reply-container {
                border-top: 1px solid #e9ecef;
                padding-top: 1rem;
                margin-top: 1rem;
                margin-left: 2rem;
            }
            .username { 
                font-weight: bold; 
                color: #343a40; 
                cursor: pointer;
                text-decoration: none;
            }
            .username:hover {
                text-decoration: underline;
            }
            .post-date { font-size: 0.8em; color: #6c757d; }
            .post-content { margin-top: 1rem; margin-bottom: 1rem; }
            .delete-form {
                display: inline-block;
                margin: 0;
            }

            .user-modal-name {
                font-size: 1.75rem !important; 
                font-weight: bold !important;  
                color: black !important;
            }
            .user-modal-username {
                color: black !important;
            }
            .user-modal-member-info, .user-modal-member-info span {
                color: gray !important;      
                font-size: 0.9rem !important;
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
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($discussionLink) ?>">Discussions</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($membershipLink) ?>">Membership</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($loginRedirectPage) ?>"><?= $loginLinkText ?></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading">Arcane Library Club</div>
                <div class="masthead-heading text-uppercase">Member Discussions</div>
                <a class="btn btn-primary btn-xl text-uppercase" href="#discussions">Start Talking</a>
            </div>
        </header>

        <section class="page-section bg-light" id="discussions">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Community Forum</h2>
                    <h3 class="section-subheading text-muted">Share your thoughts with fellow members.</h3>
                </div>

                <div class="d-flex justify-content-center align-items-center mb-4">
                    <div class="btn-group me-3" role="group" aria-label="Sort threads">
                        <a href="?sort=newest" class="btn <?= $sort_option === 'newest' ? 'btn-primary' : 'btn-outline-primary' ?>">Newest Threads</a>
                        <a href="?sort=top" class="btn <?= $sort_option === 'top' ? 'btn-primary' : 'btn-outline-primary' ?>">Top Threads</a>
                        <a href="?sort=oldest" class="btn <?= $sort_option === 'oldest' ? 'btn-primary' : 'btn-outline-primary' ?>">Oldest Threads</a>
                    </div>
                    
                    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createThreadContainer" aria-expanded="false" aria-controls="createThreadContainer">
                        <i class="fas fa-plus me-1"></i> Create Thread
                    </button>
                </div>

                <div class="collapse" id="createThreadContainer">
                    <div class="create-thread-container col-lg-8 mx-auto">
                        <form action="<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')) ?>" method="POST">
                            <div class="mb-3">
                                <textarea class="form-control" name="thread_content" rows="4" placeholder="What's on your mind, <?= htmlspecialchars($_SESSION['username']) ?>?" required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="create_thread" class="btn btn-success">Create Thread</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php if ($threads_result->num_rows > 0): ?>
                            <?php while($thread = $threads_result->fetch_assoc()): ?>
                                <div class="post-container" id="thread-<?= $thread['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="#" class="username" data-user-id="<?= $thread['user_id'] ?>"><?= htmlspecialchars($thread['username']) ?></a>
                                        <div class="post-date"><?= date('F j, Y, g:i a', strtotime($thread['created_at'])) ?></div>
                                    </div>
                                    <div class="post-content">
                                        <p><?= nl2br(htmlspecialchars($thread['content'])) ?></p>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-sm <?= $thread['user_liked'] ? 'btn-primary' : 'btn-outline-primary' ?> like-btn" data-thread-id="<?= $thread['id'] ?>">
                                            <i class="fas fa-thumbs-up"></i> <span class="like-count"><?= $thread['like_count'] ?></span> Likes
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#replies-<?= $thread['id'] ?>" aria-expanded="false">
                                            <i class="fas fa-comment-alt"></i> 
                                            <?= $thread['reply_count'] == 0 ? 'Reply' : ($thread['reply_count'] . ' ' . ($thread['reply_count'] == 1 ? 'Reply' : 'Replies')) ?>
                                        </button>

                                        <?php if ($current_user_id == $thread['user_id'] || $current_username === 'admin'): ?>
                                            <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')) ?>" class="delete-form ms-2" onsubmit="return confirm('Are you sure you want to delete this thread? This will also delete all replies.');">
                                                <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
                                                <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <div class="collapse" id="replies-<?= $thread['id'] ?>">
                                        <hr class="my-3">
                                        <?php
                                        $reply_stmt = $conn->prepare("
                                            SELECT r.id, r.content, r.created_at, r.user_id, u.username,
                                            (SELECT COUNT(*) FROM likes WHERE reply_id = r.id) AS like_count,
                                            (SELECT COUNT(*) FROM likes WHERE reply_id = r.id AND user_id = ?) AS user_liked
                                            FROM replies r
                                            JOIN users u ON r.user_id = u.id
                                            WHERE r.thread_id = ?
                                            ORDER BY r.created_at ASC
                                        ");
                                        $reply_stmt->bind_param("ii", $current_user_id, $thread['id']);
                                        $reply_stmt->execute();
                                        $replies_result = $reply_stmt->get_result();
                                        
                                        if ($replies_result->num_rows > 0) {
                                            while($reply = $replies_result->fetch_assoc()): ?>
                                            <div class="reply-container" id="reply-<?= $reply['id'] ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="#" class="username" data-user-id="<?= $reply['user_id'] ?>"><?= htmlspecialchars($reply['username']) ?></a>
                                                    <div class="post-date"><?= date('F j, Y, g:i a', strtotime($reply['created_at'])) ?></div>
                                                </div>
                                                <div class="post-content">
                                                    <p><?= nl2br(htmlspecialchars($reply['content'])) ?></p>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <button class="btn btn-sm <?= $reply['user_liked'] ? 'btn-primary' : 'btn-outline-primary' ?> like-btn" data-reply-id="<?= $reply['id'] ?>">
                                                        <i class="fas fa-thumbs-up"></i> <span class="like-count"><?= $reply['like_count'] ?></span> Likes
                                                    </button>
                                                    <?php if ($current_user_id == $reply['user_id'] || $current_username === 'admin'): ?>
                                                        <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')) ?>" class="delete-form ms-2" onsubmit="return confirm('Are you sure you want to delete this reply?');">
                                                            <input type="hidden" name="reply_id" value="<?= $reply['id'] ?>">
                                                            <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endwhile;
                                        } else {
                                            echo '<p class="text-muted ms-4 small">No replies yet. Be the first to reply!</p>';
                                        }
                                        $reply_stmt->close(); ?>

                                        <div class="reply-form mt-3 ms-4">
                                            <form action="<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')) ?>" method="POST">
                                                <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
                                                <div class="input-group">
                                                    <input type="text" class="form-control form-control-sm" name="reply_content" placeholder="Write a reply..." required>
                                                    <button class="btn btn-sm btn-success" type="submit" name="post_reply">Post Reply</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center text-muted mt-5">
                                <p>No discussions yet. Be the first to start one!</p>
                            </div>
                        <?php endif; $threads_stmt->close(); ?>
                    </div>
                </div>
            </div>
        </section>

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

        <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center">
                    <div class="modal-header border-0">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <h5 class="modal-title w-100" id="userModalLabel">About the User</h5>
                        <hr>
                        <p id="modalUserName" class="user-modal-name mb-0">User Name</p>
                        <p id="modalUserUsername" class="user-modal-username mb-3">@username</p>
                        <p class="user-modal-member-info mb-0">
                            Has been a member since<br>
                            <span id="modalUserMemberSince">Membership Date</span>
                        </p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>


        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/scripts.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // LIKE BUTTON LOGIC
            const likeButtons = document.querySelectorAll('.like-btn');
            likeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const threadId = this.dataset.threadId;
                    const replyId = this.dataset.replyId;
                    const likeCountSpan = this.querySelector('.like-count');
                    const formData = new FormData();
                    formData.append('like_action', '1');
                    if (threadId) formData.append('thread_id', threadId);
                    else if (replyId) formData.append('reply_id', replyId);

                    fetch('discussions.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            likeCountSpan.textContent = data.new_like_count;
                            if (data.user_liked) {
                                this.classList.remove('btn-outline-primary');
                                this.classList.add('btn-primary');
                            } else {
                                this.classList.remove('btn-primary');
                                this.classList.add('btn-outline-primary');
                            }
                        } else {
                            console.error('Like action failed:', data.message);
                        }
                    })
                    .catch(error => console.error('Fetch Error:', error));
                });
            });

            // USER INFO POP-UP
            const userModalElement = document.getElementById('userModal');
            const userModal = new bootstrap.Modal(userModalElement);
            
            const modalUserName = document.getElementById('modalUserName');
            const modalUserUsername = document.getElementById('modalUserUsername');
            const modalUserMemberSince = document.getElementById('modalUserMemberSince');

            document.querySelectorAll('.username').forEach(usernameLink => {
                usernameLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.dataset.userId;

                    fetch(`get_user_info.php?user_id=${userId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Populate modal with data
                                modalUserName.textContent = data.user.name;
                                modalUserUsername.textContent = `@${data.user.username}`;
                                const memberDate = new Date(data.user.created_at);
                                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                modalUserMemberSince.textContent = memberDate.toLocaleDateString('en-US', options);

                                // Show the modal using Bootstrap's API
                                userModal.show();
                            } else {
                                alert('Could not retrieve user information.');
                                console.error('Error:', data.message);
                            }
                        })
                        .catch(error => {
                            alert('An error occurred while fetching user data.');
                            console.error('Fetch error:', error);
                        });
                });
            });
        });
        </script>
    </body>
</html>