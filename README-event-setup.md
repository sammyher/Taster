# Taster event-setup draft

## Assumptions

- `config/db.php` either sets `$pdo` or returns a `PDO` connection.
- The login callback stores the Taster account ID in `$_SESSION['account_id']`.
- During development, `dev-login.php` stores `dev-account-001` in that session key.
- `Events.HostID` permits `NULL` while a new event and its host participant are created.
- The project uses the table name `EventParticipants`.
- `Events` contains nullable `JoinCode` with a unique constraint.
- `BeerContainer` contains `Name` and `Type`.

## Files

- `create-event.php`: creates the event, host participant, and host relationship.
- `edit-small-event.php`: assigns beers directly to a small event.
- `edit-containers.php`: lists festival stands or tour locations.
- `create-container.php`: creates a stand/location and redirects to its beer list.
- `edit-container-beers.php`: assigns beers to one container.
- `open-event.php`: validates the draft, generates the join code, and opens it.
- `event-lobby.php`: displays the code and future participant invite URL.
- `config/event-helpers.php`: shared session, authorization, CSRF, and display helpers.
- `dev-login.php` and `dev-logout.php`: localhost-only simulated authentication.
- `event-setup-schema-additions.sql`: columns/indexes expected by these drafts.

## Test path

1. Run `schema.sql` and `seed-development.sql` locally.
2. Incorporate the required schema additions.
3. Copy `event-helpers.php` beside the existing `config/db.php`.
4. Start the PHP development server from the project root.
5. Visit `dev-login.php`.
6. Create a small, festival, and tour event and inspect the resulting rows in Workbench.

These are intentionally plain development pages. Their purpose is to prove the
database workflow before the team integrates final styling, Auth0, Catalog.beer,
participant joining, and QR-code generation.
