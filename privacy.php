<?php
$lastUpdated = 'November 19, 2025';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taster - Privacy Policy</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 48px 16px;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.7;
        }
        .container {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 48px 48px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 32px;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #1e293b;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 4px;
            color: #1e293b;
        }
        .meta {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 40px;
        }
        h2 {
            font-size: 1.1rem;
            font-weight: 650;
            color: #1e293b;
            margin: 36px 0 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        p, li {
            font-size: 0.95rem;
            color: #475569;
            margin: 0 0 12px;
        }
        ul {
            padding-left: 20px;
            margin: 0 0 12px;
        }
        li {
            margin-bottom: 6px;
        }
        a {
            color: #2563eb;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .footer {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.8rem;
            color: #94a3b8;
            text-align: center;
        }
        @media (max-width: 600px) {
            .container {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/" class="back-link">← Back to Taster</a>

        <div class="logo">🍺 Taster</div>
        <h1>Privacy Policy</h1>
        <p class="meta">Last updated: <?= htmlspecialchars($lastUpdated) ?></p>

        <p>
            Taster ("we", "our", or "us") is a beer tasting event platform. This Privacy Policy explains what
            information we collect when you use Taster, how we use it, and your rights around that data.
        </p>

        <h2>1. Information We Collect</h2>
        <p>When you create an account or sign in with Google, we receive the following from Google via Auth0:</p>
        <ul>
            <li><strong>Name</strong> — used to personalize your experience within the app</li>
            <li><strong>Email address</strong> — used to identify your account</li>
        </ul>
        <p>We do not collect passwords. Authentication is handled securely by <a href="https://auth0.com" target="_blank">Auth0</a>.</p>

        <h2>2. How We Use Your Information</h2>
        <ul>
            <li>To create and manage your Taster account</li>
            <li>To associate your beer reviews, ratings, and event participation with your account</li>
            <li>To display your name within events you host or attend</li>
        </ul>
        <p>We do not use your information for advertising or marketing purposes.</p>

        <h2>3. Data Sharing</h2>
        <p>We do not sell, rent, or share your personal information with third parties, except:</p>
        <ul>
            <li><strong>Auth0</strong> — our authentication provider, which handles sign-in securely. See <a href="https://auth0.com/privacy" target="_blank">Auth0's Privacy Policy</a>.</li>
            <li>When required by law or to protect the rights and safety of our users.</li>
        </ul>

        <h2>4. Data Retention</h2>
        <p>
            Your account data is retained for as long as your account is active. If you'd like your data
            removed, please contact us at the email below and we will delete your account and associated data.
        </p>

        <h2>5. Security</h2>
        <p>
            We use industry-standard practices to protect your data, including secure HTTPS connections
            and delegating authentication to Auth0. No method of transmission over the internet is 100%
            secure, but we take reasonable steps to protect your information.
        </p>

        <h2>6. Children's Privacy</h2>
        <p>
            Taster is intended for users who are <strong>21 years of age or older</strong>. We do not
            knowingly collect information from anyone under 21. If you believe a minor has provided us
            with their information, please contact us so we can remove it.
        </p>

        <h2>7. Changes to This Policy</h2>
        <p>
            We may update this Privacy Policy from time to time. Changes will be posted on this page
            with an updated date at the top.
        </p>

        <h2>8. Contact Us</h2>
        <p>
            If you have any questions about this Privacy Policy, please contact us at:<br>
            <a href="mailto:samuelhernandez010904@gmail.com">samuelhernandez010904@gmail.com</a>
        </p>

        <div class="footer">
            &copy; <?= date('Y') ?> Taster &mdash; <a href="/">Home</a>
        </div>
    </div>
</body>
</html>
