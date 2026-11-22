# Arcane Library Club

A PHP and MySQL book-club website with account registration and login, profile editing, membership enrollment and summaries, event reservations and cancellations, member discussions, replies and likes, and administrator tables for users, events and attendees. The existing Bootstrap Agency appearance and responsive layouts are retained.

**Implementation**

PHP handles sessions, access checks, SQL queries, form validation, membership calculations, routing through individual `.php` entry points, and HTML rendering. Admin controls and their reference options are PHP-rendered templates. Discussion author dialogs are rendered with the page using the existing author joins, without an additional profile request. Account location selections are rendered on the initial response; subsequent dependent options are rendered by PHP as HTML fragments.

JavaScript remains for Bootstrap navigation, collapses and dialogs; immediate payment-field feedback; discussion likes; dependent location changes; and DataTables sorting, searching, pagination and inline admin editing. `js/locations.js` is shared by registration and account editing and prevents stale lookup responses from replacing newer selections. The original JSON location API and `get_user_info.php` remain available for compatibility.

The membership form is a demonstration: it validates card/GCash-shaped inputs and updates membership records locally. It does not call a payment gateway or charge money. Use synthetic payment details for local testing.

**Requirements and local setup**

- PHP with `mysqli` and mysqlnd (`get_result()` is used), sessions and password hashing. Verified locally with PHP 8.2.12.
- MySQL or MariaDB and a matching project database; XAMPP is suitable.
- A modern browser and network access for the existing Bootstrap, jQuery, Font Awesome and Google Fonts CDN resources.

1. Place this folder under the web server's document root, or run the PHP development server below from this folder.
2. Restore a matching database dump. The original repository contains no schema, migrations or reference-data import, so a fresh database cannot be provisioned from this checkout alone. Obtain the project's database export before first-time setup. Required tables are `users`, `events`, `signed_up_events`, `regions`, `provinces`, `cities_municipalities`, `threads`, `replies` and `likes`. Location reference rows and their relationships must also be present, including NCR cities with a null province.
3. Set the host, database name, username and password in `database/db_connection.php` for your local database. Keep real deployment credentials out of version control.
4. Start the database service and Apache in XAMPP, then open [the local home page](http://localhost/projects/club_website/main.php) when using that folder path. Alternatively:

```powershell
php -S 127.0.0.1:8000 -t .
```

Open [main.php on the development server](http://127.0.0.1:8000/main.php). There is no root `index.php` or frontend build step. No Composer or npm installation is needed.

No database migration is required for this refactor. The existing admin checks depend on the session username `admin` and email `admin@email.com`; there is no account-seeding command or documented admin password. Existing authentication and membership rules remain in place. This refactor is not a complete security review of the legacy application.

**Project structure**

```text
main.php, navbar.php, footer.php    Home page and shared layout
signup.php, registration_result.php Registration and result
login.php, logout.php              Session authentication
editinfo.php, process_edit_info.php Profile form and updates
payment.php, afterpayment.php      Demo membership enrollment and details
events.php, process_*ticket.php,
process_event_signup.php           Events and reservations
discussions.php, get_user_info.php Discussions and compatibility profile API
admintools.php                     Admin tables and action handler
admin/                            PHP control templates and existing JSON handlers
database/                         Database connection and shared location queries/rendering/validation
js/                               Browser interactions and bundled DataTables
css/                              Agency/Bootstrap theme, account styles and DataTables styles
assets/                           Existing logos, event/perk images and icons
tests/                            CLI checks and read-only rendering helper
```

The DataTables `.js`/`.min.js` files are third-party distribution source and its minified counterpart, not application code. The minified file is loaded by admin tools; the unminified distribution is retained. The same applies to the two DataTables stylesheets. `css/styles.css` includes Bootstrap and the Agency theme. There are no dependency manifests, generated application builds or language-statistics overrides.

**Verification**

Run from the project root in PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check js/scripts.js
node --check js/locations.js
php tests/run.php
php tests/run.php --database
php tests/form_flow.php edit
php tests/form_flow.php invalid-edit
php tests/form_flow.php registration
git diff --check
```

Node is optional and used only for JavaScript syntax checks. `tests/run.php` checks escaping, saved selections, unique template IDs and row structure; `--database` adds reference-data relationship checks. `tests/form_flow.php` uses an existing user with a provincial address and recognized gender, runs the selected handler inside a transaction, and rolls it back rather than saving profile or registration changes. Run these database-dependent checks against a local development database.

`php tests/render.php admintools.php` outputs an authenticated page for visual QA using an existing admin and an in-memory session. It also supports `discussions.php`, `editinfo.php` and `user_management.php` using an existing active member. This helper works only through the CLI; it does not enable browser authentication. Any saved HTML contains the selected database's displayed information and should be temporary.

During this refactor, the original 22 PHP files passed baseline syntax checks. Final checks covered PHP and application JavaScript syntax, helper/database checks, valid and invalid transactional form submissions, unauthenticated redirects, invalid registration HTTP submissions, both location response formats, and browser checks of mobile/desktop forms, dependent selects, admin edit/cancel/add previews and discussion profile dialogs. Successful admin writes, destructive actions, and every payment/forum interaction were not exhaustively exercised.

**Language estimate**

GitHub Linguist was unavailable. By raw source-file bytes across PHP, JavaScript and CSS, including bundled libraries and tests, the approximation changed from PHP 22.2% / JavaScript 46.4% / CSS 31.5% to roughly PHP 21.3% / JavaScript 47.0% / CSS 31.7%. Entire PHP files count as PHP in this approximation, including their HTML and remaining inline scripts; images and documentation are excluded.

Counting application inline script bodies plus the project's own `.js` files, JavaScript decreased from about 48.3 KB to 30.1 KB (38%). The two retained DataTables distributions account for 490,833 JavaScript bytes. PHP files became smaller as duplicated client rendering was replaced with shared server code. These estimates do not establish GitHub's primary language.
