<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taster - Home</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 80vh; gap: 1rem; margin: 0; position: relative; }
        .top-nav { position: absolute; top: 20px; right: 24px; }
        .top-nav a { text-decoration: none; color: #333; font-size: 0.95rem; padding: 8px 16px; border-radius: 6px; border: 1px solid #ccc; background-color: #f7f7f7; display: inline-block; }
        .top-nav a:hover { background-color: #ebebeb; }
        .btn-group { display: flex; flex-direction: column; gap: 12px; width: 220px; }
        button { padding: 12px; font-size: 1rem; cursor: pointer; border-radius: 6px; border: 1px solid #ccc; background-color: #f7f7f7; }
        button:hover { background-color: #ebebeb; }
        .code-status { min-height: 1.4em; margin: 0; font-size: 0.9rem; }
        .code-status.ok { color: #2e7d32; }
        .code-status.bad { color: #b00020; }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="about.php">About</a>
    </div>

    <?php
        $prefillCode = strtoupper(trim((string) ($_GET['code'] ?? '')));

        if (!preg_match('/^[A-Z0-9]{6}$/', $prefillCode)) {
            $prefillCode = '';
        }
    ?>
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
                placeholder="Enter six-character code"
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

            <button type="submit" name="action" value="attend">
                Log In and Attend
            </button>

            <button
                type="submit"
                name="action"
                value="anonymous"
                onclick="return confirm(
                    'Please confirm you are 21 years of age or older.'
                )"
            >
                Attend Anonymously
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
