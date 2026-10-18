<?php

session_start();

require_once __DIR__ . '/config/event-helpers.php';

/* ---------- PAGE SETTINGS ---------- */
$page_title   = 'Host Homepage';
$page_heading = 'Welcome, '; // TODO: Add hostname
$body_class   = 'centered';

/* ---------- FORM HANDLING ---------- */
$errors     = [];
$event_code = '';

/* ---------- PAGE CONTENT ----------- */
ob_start();
?>
<h2>Hosting Dashboard</h2>
<p>Show QR Code / Event ID</p>
<p>Manage participants</p>
<p>Manage beer list</p>
<p>Manage vendors</p>
<a class="btn" href=create-event.php>Start an Event</a>
<a class="btn" href=join.php>Join an Event</a>
<a class="btn" href=index.php>Return to homepage</a>
</form>

<?php
$page_content = ob_get_clean();

// needs to be at the bottom
require __DIR__ . '/layout.php';
