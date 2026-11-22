# Arcane Library Club

A PHP and MySQL book-club website with account registration and login, profile editing, membership enrollment and summaries, event reservations and cancellations, member discussions, replies and likes, and administrator tables for users, events and attendees. The existing Bootstrap Agency appearance and responsive layouts are retained.

**Implementation**

PHP handles sessions, access checks, SQL queries, form validation, membership calculations, routing through individual `.php` entry points, and HTML rendering. Admin controls and their reference options are PHP-rendered templates. Discussion author dialogs are rendered with the page using the existing author joins, without an additional profile request. Account location selections are rendered on the initial response; subsequent dependent options are rendered by PHP as HTML fragments.

JavaScript remains for Bootstrap navigation, collapses and dialogs; immediate payment-field feedback; discussion likes; dependent location changes; and DataTables sorting, searching, pagination and inline admin editing. `js/locations.js` is shared by registration and account editing and prevents stale lookup responses from replacing newer selections. The original JSON location API and `get_user_info.php` remain available for compatibility.

Membership plans, prices and field rules are defined in `membership.php`. PHP uses those rules for POST validation and renders the matching native HTML constraints; `js/payment.js` retains input formatting, instant feedback, method switching and the existing loading animation. Server validation errors appear on the current response and keep the chosen plan and method without repopulating payment details. Ticket cancellation uses a PHP-rendered form per reservation with the existing confirmation prompt.

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
membership.php                    Shared PHP membership policy and form constraints
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

DataTables 2.3.2 is retained as `js/datatables.min.js` and `css/datatables.min.css`; admin tools load those distributions directly. The unused, unminified duplicate files were removed after checking tracked application and configuration references. There are no Composer/npm manifests, dependency lockfiles, frontend build configuration or generated application bundles. Existing public PHP endpoints and database-referenced assets are retained.

`.gitattributes` marks only the two retained DataTables distributions and `css/styles.css` as vendored. The latter was compared with [the pinned upstream Agency v7.0.12 stylesheet](https://raw.githubusercontent.com/StartBootstrap/startbootstrap-agency/v7.0.12/dist/css/styles.css): the only content difference is `header-bg.jpg` becoming `header-bg.png`. The upstream normalized SHA-256 is `36e30dedf27c9a8bd3db4dd71fc074f343d16ff74dfa5fc66c7340408c528fae`; the project file is `36ffba9c4b3cab88e9ae3a8c32dcae9ffbc37766cae7b2db085b5621e4d2f885` (UTF-8, LF line endings, surrounding whitespace trimmed). The minimally adapted theme is left intact; custom `css/login_style.css`, theme initialization in `js/scripts.js`, and all other application code remain counted. There were no existing attributes before this follow-up. Classification changes are separate from the PHP refactor and do not change runtime behavior.

**Verification**

Run from the project root in PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check js/scripts.js
node --check js/locations.js
node --check js/payment.js
php tests/run.php
php tests/run.php --database
php tests/membership.php
php tests/payment_flow.php gcash
php tests/payment_flow.php card
php tests/payment_flow.php invalid
php tests/form_flow.php edit
php tests/form_flow.php invalid-edit
php tests/form_flow.php registration
php tests/form_flow.php cancel-ticket
git diff --check
```

Node is optional and used only for JavaScript syntax checks. `tests/run.php` checks escaping, saved selections, unique template IDs and row structure; `--database` adds reference-data relationship checks. `tests/form_flow.php` uses an existing user with a provincial address and recognized gender, runs the selected handler inside a transaction, and rolls it back rather than saving profile or registration changes. `cancel-ticket` requires an existing reservation and tests cancellation inside a rollback transaction. `tests/membership.php` checks payment constraints, expiry boundaries and compatible POST formats without a database. `tests/payment_flow.php` tests successful card/GCash enrollment and invalid submissions using an existing inactive user, then explicitly rolls back and verifies the original database row. Run database-dependent checks against a local development database.

`php tests/render.php admintools.php` outputs an authenticated page for visual QA using an existing admin and an in-memory session. It also supports `discussions.php`, `editinfo.php` and `user_management.php` using an existing active member (preferring a member with reservations). `payment.php` uses an existing inactive member. This helper works only through the CLI; it does not enable browser authentication. Any saved HTML contains the selected database's displayed information and should be temporary.

During this refactor, the original 22 PHP files passed baseline syntax checks. Final checks covered PHP and application JavaScript syntax, helper/database checks, valid and invalid transactional form submissions, unauthenticated redirects, invalid registration HTTP submissions, both location response formats, and browser checks of mobile/desktop forms, dependent selects, admin edit/cancel/add previews and discussion profile dialogs. Successful admin writes, destructive actions, and every payment/forum interaction were not exhaustively exercised.

The follow-up passed syntax checks for all 30 PHP files and application JavaScript, 26 policy/template/reference-data checks, and seven transactional registration/profile/cancellation/payment flow modes. Browser checks confirmed identical initial payment-form geometry at desktop and mobile widths, valid card/GCash feedback, expiry rejection, field clearing when switching methods, and admin edit/cancel behavior with the retained DataTables distribution. Native confirmation-dialog dismissal could not be reliably automated; cancellation form submission and the rollback-safe server handler were checked separately. These checks do not constitute exhaustive browser or security testing.
