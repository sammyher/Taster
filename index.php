<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taster - Home</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 80vh; gap: 1rem; }
        .btn-group { display: flex; flex-direction: column; gap: 12px; width: 220px; }
        button { padding: 12px; font-size: 1rem; cursor: pointer; border-radius: 6px; border: 1px solid #ccc; background-color: #f7f7f7; }
        button:hover { background-color: #ebebeb; }
    </style>
</head>
<body>
    <h1>Taster</h1>
    <p>Beer Tasting Event Platform</p>

    <div class="btn-group">
        <!-- 1. About section -->
        <button onclick="window.location.href='about.php'">About</button>

        <!-- 2. Host route (Auth0) -->
        <button onclick="window.location.href='login.php?role=host'">Host</button>

        <!-- 3. Registered Attendee route (Auth0) -->
        <button onclick="window.location.href='login.php?role=attendee'">Attendee</button>

        <!-- 4. Anonymous Attendee route with 21+ gate -->
        <button onclick="joinAnonymous()">Attendee (Anonymous)</button>

        <!-- 5. Account Details route (Auth0) -->
        <button onclick="window.location.href='login.php'" >Account Details</button>
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
</body>
</html>
