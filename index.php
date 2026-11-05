<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['account_id']) && file_exists(__DIR__ . '/config/auth.php')) {
    try {
        require_once __DIR__ . '/config/auth.php';
        $credentials = $auth0->getCredentials();
        if ($credentials) {
            $user = $credentials->user;
            $_SESSION['account_id'] = $user['sub'];
            $given = $user['given_name'] ?? $user['user_metadata']['given_name'] ?? $user['user_metadata']['first_name'] ?? null;
            if (!empty($given)) {
                $name = trim((string) $given);
            } else {
                $name = trim((string) ($user['name'] ?? $user['nickname'] ?? ''));
                if ($name === '' && !empty($user['email'])) {
                    $name = (string) $user['email'];
                }
                if (str_contains($name, '@')) {
                    $local = explode('@', $name)[0];
                    $name = preg_split('/[._-]/', $local)[0] ?? $local;
                }
                $name = explode(' ', trim($name))[0];
            }
            $_SESSION['display_name'] = !empty($name) ? ucfirst(strtolower($name)) : 'User';
            $_SESSION['email'] = $user['email'] ?? '';
        }
    } catch (Throwable $e) {
        // Not logged in or Auth0 session expired
    }
}

$isLoggedIn = !empty($_SESSION['account_id']);
$rawName = $_SESSION['display_name'] ?? 'Account';
if (str_contains($rawName, '@')) {
    $local = explode('@', $rawName)[0];
    $rawName = preg_split('/[._-]/', $local)[0] ?? $local;
}
$firstWord = explode(' ', trim($rawName))[0];
$firstWord = preg_replace('/\d+$/', '', $firstWord);
$displayName = !empty($firstWord) ? ucfirst(strtolower($firstWord)) : 'Account';

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
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            padding: 24px 16px;
            position: relative;
            box-sizing: border-box;
        }
        .top-nav {
            position: absolute;
            top: 20px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: calc(100% - 48px);
        }
        .pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .pill-btn:hover {
            color: #1e293b;
            border-color: #cbd5e1;
            background: #f8fafc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }
        .user-pill span {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
        .pill-btn-logout {
            background-color: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            font-weight: 600;
        }
        .pill-btn-logout:hover {
            background-color: #b91c1c;
            color: #ffffff;
            border-color: #b91c1c;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        .center-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 320px;
        }
        .header-hero {
            text-align: center;
            margin: 0 0 1.25rem 0;
        }
        .brand-title {
            margin: 0 0 6px 0;
            font-size: 2.5rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.5px;
        }
        .brand-subtitle {
            margin: 0;
            font-size: 1.05rem;
            color: #4b5563;
            font-weight: 400;
        }

        .btn-group { display: flex; flex-direction: column; gap: 12px; width: 260px; }
        form.btn-group { width: 100%; margin-top: 6px; }
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
        #event_code:focus::placeholder {
            color: transparent;
        }
        #event_code::placeholder {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            color: #9ca3af;
            transition: color 0.15s ease;
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
            background-color: #cbd5e1;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.15rem;
            padding: 14px 12px;
            border: none;
            border-radius: 6px;
            box-shadow: none;
            cursor: not-allowed;
            width: 100%;
            box-sizing: border-box;
            transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            letter-spacing: 0.5px;
        }
        .kahoot-btn:not(:disabled) {
            background-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 4px 0 #1d4ed8;
            cursor: pointer;
        }
        .kahoot-btn:not(:disabled):hover {
            background-color: #1d4ed8;
            box-shadow: 0 4px 0 #1e40af;
        }
        .kahoot-btn:not(:disabled):active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #1e40af;
        }

        /* Mobile Optimization (iPhone 16, SE, Android) */
        @media (max-width: 480px) {
            body {
                padding-top: 56px;
            }
            .top-nav {
                top: 14px;
                right: 12px;
                left: 12px;
                max-width: calc(100% - 24px);
                justify-content: flex-end;
                gap: 6px;
            }
            .pill-btn {
                padding: 6px 9px;
                font-size: 0.8rem;
                gap: 4px;
            }
            .pill-btn svg {
                width: 13px;
                height: 13px;
            }
            .user-pill span {
                max-width: 80px;
            }
            .header-hero {
                margin-bottom: 1rem;
            }
            .brand-title {
                font-size: 2.15rem;
            }
            .brand-subtitle {
                font-size: 0.95rem;
            }
        }
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
            <a href="account.php" class="pill-btn user-pill" title="View Account Details">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a href="logout.php" class="pill-btn pill-btn-logout" title="Log out">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
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

    <main class="center-content">
        <div class="header-hero">
            <h1 class="brand-title">Taster</h1>
            <p class="brand-subtitle">Beer Tasting Event Platform</p>
        </div>

        <div class="btn-group">
            <!-- 1. Host route (Auth0) -->
            <button onclick="window.location.href='login.php?role=host'">Host Event</button>

            <form method="post" action="join.php" class="btn-group">
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

                <button
                    type="submit"
                    name="action"
                    value="<?= $isLoggedIn ? 'attend' : 'anonymous' ?>"
                    class="kahoot-btn"
                    id="enter-btn"
                    disabled
                    <?= !$isLoggedIn ? 'onclick="return confirm(\'Please confirm you are 21 years of age or older to enter.\')"' : '' ?>
                >
                    Enter
                </button>
            </form>
        </div>
    </main>

    <script>
        (function () {
            const input = document.getElementById('event_code');
            const enterButton = document.getElementById('enter-btn');

            let timer = null;
            let controller = null;
            let codeIsValid = false;

            // Instantly clear placeholder on click / focus, restore on blur if empty
            input.addEventListener('focus', function () {
                this.placeholder = '';
            });

            input.addEventListener('blur', function () {
                if (!this.value) {
                    this.placeholder = 'Event PIN';
                }
            });

            function setButtonEnabled(enabled) {
                codeIsValid = enabled;
                enterButton.disabled = !enabled;
            }

            setButtonEnabled(false);

            input.addEventListener('input', function () {
                const code = input.value
                    .replace(/[^A-Za-z0-9]/g, '')
                    .toUpperCase();

                input.value = code;
                setButtonEnabled(false);

                clearTimeout(timer);

                if (controller) {
                    controller.abort();
                }

                if (!/^[A-Z0-9]{6}$/.test(code)) {
                    return;
                }

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
                        if (input.value !== code) {
                            return;
                        }
                        setButtonEnabled(data.valid === true);
                    })
                    .catch(function (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }
                        setButtonEnabled(false);
                    });
                }, 150);
            });

            // Prevent submission unless the server has confirmed the code is valid.
            input.form.addEventListener('submit', function (event) {
                if (!codeIsValid) {
                    event.preventDefault();
                }
            });

            if (input.value) {
                input.dispatchEvent(new Event('input'));
            }
        })();
    </script>
</body>
</html>
