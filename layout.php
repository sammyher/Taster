<?php
/**
 * Shared page layout.
 *
 * Expected variables:
 *   $page_title    (string)  required  <title> text
 *   $page_heading  (string)  required  <h1> text
 *   $page_content  (string)  required  HTML for the <main> area
 *   $page_aside    (string)  optional  tagline under the heading
 *   $body_class    (string)  optional  centers content horizontally (more mobile friendly)
 */
$centered     = $body_class ?? '';
$lang         = $lang ?? 'en';
$page_aside   = $page_aside ?? '';
$footer_text  = $footer_text ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <style type="text/css">
        body {
            margin: 40px auto;
            max-width: 650px;
            line-height: 1.6;
            font-size: 18px;
            color: #444;
            padding: 0 10px;
        }
        h1, h2, h3 { line-height: 1.2; }

        .centered { text-align: center; }
        .centered .btn-group { margin-left: auto; margin-right: auto }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 300px;
            max-width: 100%;
        }
        .btn-group input[type="text"]::placeholder {
            font-family: inherit;
            font-size: 0.8rem;
            letter-spacing: normal;
            color: #777;
            opacity: 1;
        }
        .btn-group input[type="text"]:focus::placeholder { color: transparent; }
        .btn-group input[type="text"] {
            padding: 12px;
            font-size: 1.25rem;
            letter-spacing: 0.2em;
            text-align: center;
            text-transform: uppercase;
            font-family: monospace;
            color: inherit;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .btn {
            display: block;
            padding: 12px;
            font-size: 1rem;
            font-family: inherit;
            text-align: center;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #f7f7f7;
        }
        .btn:hover { background-color: #ebebeb; }
        .btn:focus-visible,
        .btn-group input[type="text"]:focus-visible { outline: 2px solid #444; outline-offset: 2px; }
        .code-status { min-height: 1.6em; margin 0; font-size: 0.9rem; }
        .code-status.ok { color: #2e7d32; }
        .code-status.ng { color: #b00020; }
    </style>
</head>
<body>
    <header>
        <h1><?= htmlspecialchars($page_heading, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($page_aside !== ''): ?>
            <aside><?= htmlspecialchars($page_aside, ENT_QUOTES, 'UTF-8') ?></aside>
        <?php endif; ?>
    </header>

    <main>
        <?= $page_content ?>
    </main>

    <?php if ($footer_text !== ''): ?>
        <footer>
            <p><?= $footer_text ?></p>
        </footer>
    <?php endif; ?>
</body>
</html>
