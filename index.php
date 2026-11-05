<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['account_id']) && file_exists(__DIR__ . '/config/auth.php')) {
    try {
        require_once __DIR__ . '/config/auth.php';
        $credentials = $auth0->getCredentials();
        if ($credentials) {
            $_SESSION['account_id'] = $credentials->user['sub'];
            $_SESSION['display_name'] = $credentials->user['name'] ?? $credentials->user['nickname'] ?? 'User';
            $_SESSION['email'] = $credentials->user['email'] ?? '';
        }
    } catch (Throwable $e) {
        // Not logged in or Auth0 session expired
    }
}

$isLoggedIn = !empty($_SESSION['account_id']);
$displayName = $_SESSION['display_name'] ?? 'Account';

$prefillCode = strtoupper(trim((string) ($_GET['code'] ?? '')));
if (!preg_match('/^[A-Z0-9]{6}$/', $prefillCode)) {
    $prefillCode = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taster - Home</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 80vh;
            gap: 1rem;
            margin: 0;
            position: relative;
        }
        .top-nav {
            position: absolute;
            top: 20px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        .pill-btn:hover {
            color: #1e293b;
            border-color: #cbd5e1;
            background: #f8fafc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        .pill-btn-login {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            font-weight: 600;
        }
        .pill-btn-login:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            border-color: #1d4ed8;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }
        .logout-link {
            color: #94a3b8;
            background: transparent;
            border-color: transparent;
            box-shadow: none;
            font-size: 0.825rem;
            padding: 6px 10px;
        }
        .logout-link:hover {
            color: #ef4444;
            background: #fef2f2;
            border-color: #fecaca;
            box-shadow: none;
        }
        .btn-group { display: flex; flex-direction: column; gap: 12px; width: 260px; }
        form.btn-group { width: 100%; margin-top: 6px; }
        form label {
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            color: #333;
        }
        #event_code {
            text-align: center;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 3px;
            padding: 14px 10px;
            border: 2px solid #ccc;
            border-radius: 6px;
            background-color: #fff;
            color: #222;
            width: 100%;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        #event_code:focus {
            border-color: #333;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
        }
        #event_code::placeholder {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            color: #9ca3af;
        }
        button {
            padding: 13px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 6px;
            border: 1px solid #ccc;
            background-color: #f7f7f7;
            transition: all 0.15s ease;
        }
        button:hover:not(:disabled) {
            background-color: #ebebeb;
        }
        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .kahoot-btn {
            background-color: #333333;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            padding: 14px 12px;
            border: none;
            border-radius: 6px;
            box-shadow: 0 4px 0 #1a1a1a;
            cursor: pointer;
            width: 100%;
            box-sizing: border-box;
            transition: background-color 0.15s ease, transform 0.1s ease, box-shadow 0.1s ease;
            letter-spacing: 0.5px;
        }
        .kahoot-btn:hover:not(:disabled) {
            background-color: #222222;
            box-shadow: 0 4px 0 #000000;
        }
        .kahoot-btn:active:not(:disabled) {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #000000;
        }
        .kahoot-btn:disabled {
            background-color: #94a3b8;
            box-shadow: none;
            opacity: 0.6;
            cursor: not-allowed;
            color: #ffffff;
        }
        .code-status {
            min-height: 1.4em;
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
        }
        .code-status.ok { color: #16a34a; }
        .code-status.bad { color: #dc2626; }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="about.php" class="pill-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>About</span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="account.php" class="pill-btn" title="View Account Details">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a href="logout.php" class="pill-btn logout-link" title="Log out">
                <span>Log out</span>
            </a>
        <?php else: ?>
            <a href="login.php?role=home" class="pill-btn pill-btn-login">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Log in</span>
            </a>
        <?php endif; ?>
    </div>

    <h1>Taster</h1>
    <p>Beer Tasting Event Platform</p>

    <div class="btn-group">
        <!-- 1. Host route (Auth0) -->
        <button onclick="window.location.href='login.php?role=host'">Host Event</button>

        <form method="post" action="join.php" class="btn-group">
            <label for="event_code">Join an Event</label>

            <input
                type="text"
                id="event_code"
                name="event_code"
                placeholder="Event PIN"
                maxlength="6"
                minlength="6"
                pattern="[A-Za-z0-9]{6}"
                autocomplete="off"
                required
                oninput="this.value = this.value
                    .replace(/[^A-Za-z0-9]/g, '')
                    .toUpperCase()"
                value="<?= htmlspecialchars($prefillCode, ENT_QUOTES, 'UTF-8') ?>"
            >

            <p id="code-status" class="code-status" role="status"></p>

            <button
                type="submit"
                name="action"
                value="<?= $isLoggedIn ? 'attend' : 'anonymous' ?>"
                class="kahoot-btn"
                <?= !$isLoggedIn ? 'onclick="return confirm(\'Please confirm you are 21 years of age or older to enter.\')"' : '' ?>
            >
                Enter
            </button>
        </form>
    </div>

    <script>
        function joinAnonymous() {
            if (confirm("Please confirm you are 21 years of age or older to enter.")) {
                window.location.href = "anonymous.php";
            } else {
                alert("Access Denied: You must be 21 or older.");
            }
        }
    </script>
    <script>
        (function () {
            const input = document.getElementById('event_code');
            const status = document.getElementById('code-status');
            const joinButtons = document.querySelectorAll(
                'button[name="action"]'
            );

            let timer = null;
            let controller = null;
            let codeIsValid = false;

            function showStatus(message, className = '') {
                status.textContent = message;
                status.className =
                    'code-status' + (className ? ' ' + className : '');
            }

            function setButtonsEnabled(enabled) {
                joinButtons.forEach(function (button) {
                    button.disabled = !enabled;
                });
            }

            setButtonsEnabled(false);

            input.addEventListener('input', function () {
                const code = input.value
                    .replace(/[^A-Za-z0-9]/g, '')
                    .toUpperCase();

                input.value = code;
                codeIsValid = false;
                setButtonsEnabled(false);

                clearTimeout(timer);

                if (controller) {
                    controller.abort();
                }

                if (!/^[A-Z0-9]{6}$/.test(code)) {
                    showStatus('');
                    return;
                }

                showStatus('Checking…');

                timer = setTimeout(function () {
                    controller = new AbortController();

                    fetch('join.php?check=' + encodeURIComponent(code), {
                        signal: controller.signal,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Server error');
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        // Ignore an old response if the user changed the code.
                        if (input.value !== code) {
                            return;
                        }

                        codeIsValid = data.valid === true;

                        if (codeIsValid) {
                            showStatus('✓ Code found', 'ok');
                            setButtonsEnabled(true);
                        } else {
                            showStatus('✗ Code not found', 'bad');
                            setButtonsEnabled(false);
                        }
                    })
                    .catch(function (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        showStatus('Could not check the code right now.');
                        setButtonsEnabled(false);
                    });
                }, 250);
            });

            // Prevent submission unless the server has confirmed the code.
            input.form.addEventListener('submit', function (event) {
                if (!codeIsValid) {
                    event.preventDefault();
                    showStatus('Please enter a valid event code.', 'bad');
                }
            });
        
        if (input.value) {
            input.dispatchEvent(new Event('input'));
        }
        })();
    </script>
</body>
</html>
