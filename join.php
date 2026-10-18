<?php
/**
 * Thinking about integrating this with index.php and/or making this the landing
 * page so that users can immediately slam a code in and get started.
 */

session_start();

//echo '<pre>';
//var_dump($_SESSION);
//echo '</pre>';

require_once __DIR__ . '/config/event-helpers.php';

function event_code_exists(PDO $ece_pdo, string $ece_code): bool {
    $ece_stmt = $ece_pdo->prepare('SELECT 1 FROM Events WHERE JoinCode = ? LIMIT 1');
    $ece_stmt->execute([$ece_code]);
    return (bool) $ece_stmt->fetchColumn();
}

function account_exists(PDO $ae_pdo, string $ae_acc): bool {
    $ae_stmt = $ae_pdo->prepare('SELECT 1 FROM Accounts WHERE JoinCode = ? LIMIT 1');
    $ae_stmt->execute([$ae_acc]);
    return (bool) $ae_stmt->fetchColumn();
}

/* ---------- PAGE SETTINGS ---------- */
$page_title   = 'Join an Event';
$page_heading = 'Join an Event';
$body_class   = 'centered';

// $login_url    = 'login.php?role=attendee'; // Maybe go back to this unless we flesh out lobby.php more/combine the two
$login_url    = 'lobby.php';

// Names for anonymous
$animals = [
    'Alligator', 'Anteater', 'Armadillo', 'Badger', 'Beaver', 'Buffalo', 'Camel',
    'Capybara', 'Chameleon', 'Cheetah', 'Chinchilla', 'Chipmunk', 'Cormorant',
    'Coyote', 'Crow', 'Dingo', 'Dinosaur', 'Dolphin', 'Duck', 'Elephant', 'Ferret',
    'Fox', 'Frog', 'Giraffe', 'Gopher', 'Grizzly', 'Hedgehog', 'Hippo', 'Hyena',
    'Ibex', 'Ifrit', 'Iguana', 'Jackal', 'Kangaroo', 'Koala', 'Kraken', 'Lemur',
    'Leopard', 'Liger', 'Llama', 'Manatee', 'Mink', 'Monkey', 'Moose', 'Narwhal',
    'Nyan Cat', 'Orangutan', 'Otter', 'Panda', 'Penguin', 'Platypus', 'Pumpkin',
    'Python', 'Quagga', 'Rabbit', 'Raccoon', 'Rhino', 'Sheep', 'Shrew', 'Skunk',
    'Squirrel', 'Tiger', 'Turtle', 'Walrus', 'Wolf', 'Wolverine', 'Wombat',
];

/* ---------- FORM HANDLING ---------- */
$errors     = [];
$event_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    $code  = strtoupper(trim((string) $_GET['check']));
    $valid = false;
 
    if (preg_match('/^[A-Z0-9]{6}$/', $code)) {
        try {
            $valid = event_code_exists($pdo, $code);
        } catch (PDOException $e) {
            error_log('event-login check error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'server_error']);
            exit;
        }
    }
    echo json_encode(['valid' => $valid]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $event_code  = strtoupper(trim($_POST['event_code'] ?? ''));

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            // Check if event_id is in the database
            if (!event_code_exists($pdo, $event_code)) {
                $errors[] = 'That Event Code was not found.';
            }
            // TODO: Check if the event is open
            // Check if the account exists (don't include for anon)
            $account_id = 'dev-account-001'; // Placeholder for testing, remove after account integration
            if (!account_exists($pdo, $account_id)) {
                $errors[] = 'That Event Code was not found.';
            }
            if ($action === 'attend') {
                $_SESSION['event_code'] = $event_code;
                unset($_SESSION['anonymous']);
                $_SESSION['account_id'] = 'dev-account-001'; // TODO: set to actual account, probably during the redirect to login/check if logged in already
                header('Location: ' . $lobby_url); // TODO: make this redirect to login page if not logged in
                exit;
            } elseif ($action === 'anonymous') {
                $_SESSION['event_code'] = $event_code;
                $_SESSION['anonymous'] = true;
                $_SESSION['account_id'] = 'Anonymous' . $animals[random_int(0, count($animals) - 1)];
                header('Location: ' . $lobby_url);
                exit;
            } else {
                $errors[] = 'Please choose one of the options.';
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}

/* ---------- PAGE CONTENT ---------- */
ob_start();
?>
<form method="post" action="" class="btn-group" novalidate>
    <label for="event_code">Event Code</label>
    <input type="text" id="event_code" name="event_code"
           value="<?= htmlspecialchars($event_code, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Enter your six character code"
           oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase()"
           title="6 letters or numbers"
           maxlength="6" minlength="6" pattern="[A-Za-z0-9]{6}"
           autocomplete="off" autocapitalize="characters" spellcheck="false"
           inputmode="text" required>
    <p id="code-status" class="code-status" role="status"></p>
    <button type="submit" name="action" value="attend" class="btn">Login and Attend</button>
    <button type="submit" name="action" value="anonymous" class="btn"
            onclick="return confirm('Please confirm you are 21 years of age or older to enter.')">
        Attend as Anonymous
    </button>
</form>

<script>
// Fun script for live event code checking
(function () {
    var input  = document.getElementById('event_code');
    var status = document.getElementById('code-status');
    var timer  = null;
    var controller = null;
 
    function show(text, cls) {
        status.textContent = text;
        status.className = 'code-status' + (cls ? ' ' + cls : '');
    }
 
    input.addEventListener('input', function () {
        var code = input.value.toUpperCase();
 
        clearTimeout(timer);
        if (controller) { controller.abort(); }
 
        if (!/^[A-Z0-9]{6}$/.test(code)) { show('', ''); return; }
 
        show('Checking\u2026', '');
        timer = setTimeout(function () {
            controller = new AbortController();
            fetch(window.location.pathname + '?check=' + encodeURIComponent(code), {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
            .then(function (res) {
                if (!res.ok) { throw new Error('server'); }
                return res.json();
            })
            .then(function (data) {
                if (input.value.toUpperCase() !== code) { return; }   // user kept typing
                if (data.valid) { show('\u2713 Code found', 'ok'); }
                else            { show('\u2717 Code not found', 'bad'); }
            })
            .catch(function (err) {
                if (err.name === 'AbortError') { return; }
                show('Could not check the code right now.', '');
            });
        }, 250);
    });
 
    // Rechecks after failed submit
    if (input.value) { input.dispatchEvent(new Event('input')); }
})();
</script>

<?php
$page_content = ob_get_clean();

require __DIR__ . '/layout.php';
