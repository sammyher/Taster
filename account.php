<?php
session_start();

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

$credentials = $auth0->getCredentials();

if (!$credentials) {
    // Not logged in yet, send through Auth0 and back here afterward
    $_SESSION['target_role'] = 'account';
    header('Location: ' . $auth0->login());
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM Accounts WHERE ID = :id");
$stmt->execute([':id' => $credentials->user['sub']]);
$userProfile = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Details - Taster</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="account-container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($userProfile['DisplayName'] ?? 'User'); ?></h1>
            <p>Manage your favorite brews and past tasting events.</p>
        </div>

        <!-- Favorite Beers Section -->
        <div class="section-card">
            <h2>My Favorite Beers</h2>
            
            <div class="search-bar">
                <input type="text" id="beerSearchInput" placeholder="Search for a beer to add...">
                <button class="btn-add" onclick="addBeer()">Add</button>
            </div>

            <ul class="beer-list" id="favoriteBeersList">
            </ul>
            <div id="emptyListMessage" class="empty-state">No favorite beers added yet.</div>
        </div>

        <!-- Archived Events Section -->
        <div class="section-card">
            <h2>Archived Events</h2>
            <p class="empty-state">No past events found.</p>
        </div>
        
        <a href="index.php" class="nav-link">&larr; Back to Home</a>
    </div>

    <script>
        function updateEmptyState() {
            const list = document.getElementById('favoriteBeersList');
            const message = document.getElementById('emptyListMessage');
            if (list.children.length === 0) {
                message.style.display = 'block';
            } else {
                message.style.display = 'none';
            }
        }

        function addBeer() {
            const searchInput = document.getElementById('beerSearchInput');
            const beerName = searchInput.value.trim();
            
            if (beerName === "") return;

            const list = document.getElementById('favoriteBeersList');
            const li = document.createElement('li');
            li.className = 'beer-item';
            li.innerHTML = `
                <span>${beerName}</span>
                <button class="btn-delete" onclick="removeBeer(this)">Delete</button>
            `;
            
            list.appendChild(li);
            searchInput.value = ""; 
            updateEmptyState();
        }

        function removeBeer(buttonElement) {
            const listItem = buttonElement.parentElement;
            listItem.remove();
            updateEmptyState();
        }

        updateEmptyState();
    </script>
</body>
</html>
