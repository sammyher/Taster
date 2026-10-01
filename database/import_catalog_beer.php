<?php

$env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);

$apiKey = trim($env['CATALOG_BEER_API_KEY'], " \t\n\r\0\x0B'\"");
$dbHost = $env['DB_HOST'];
$dbName = $env['DB_NAME'];
$dbUser = $env['DB_USER'];
$dbPass = $env['DB_PASS'];

$pdo = new PDO(
    "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

function apiRequest(string $url, string $apiKey): array
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_HTTPHEADER => ['Accept: application/json'],
    	CURLOPT_USERPWD => $apiKey . ':',
    	CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    	CURLOPT_CONNECTTIMEOUT => 10,
    	CURLOPT_TIMEOUT => 45
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        throw new Exception(curl_error($curl));
    }

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status !== 200) {
        throw new Exception("API returned HTTP status $status");
    }

    return json_decode($response, true);
}

$insert = $pdo->prepare("
    INSERT INTO BeerCatalog
        (ID, Name, Brewery, Type, ABV, IBU, Description, AccountID)
    VALUES
        (:id, :name, :brewery, :type, :abv, :ibu, :description, NULL)
    ON DUPLICATE KEY UPDATE
        Name = VALUES(Name),
        Brewery = VALUES(Brewery),
        Type = VALUES(Type),
        ABV = VALUES(ABV),
        IBU = VALUES(IBU),
        Description = VALUES(Description)
");

$cursor = null;
$page = 1;

do {
    $url = 'https://api.catalog.beer/beer?count=25';

    if ($cursor !== null) {
        $url .= '&cursor=' . rawurlencode($cursor);
    }

    echo "Loading page $page...\n";

    $list = apiRequest($url, $apiKey);

    foreach ($list['data'] as $summary) {
        $beer = apiRequest(
            'https://api.catalog.beer/beer/' . $summary['id'],
            $apiKey
        );

        $name = substr($beer['name'] ?? 'Unknown Beer', 0, 100);
        $brewery = substr(
            $beer['brewer']['name'] ?? 'Unknown Brewery',
            0,
            100
        );
        $type = substr(
            $beer['style'] ?? $beer['beverage_type'] ?? 'Beer',
            0,
            100
        );
        $description = substr($beer['description'] ?? '', 0, 500);

        $insert->execute([
            ':id' => $beer['id'],
            ':name' => $name,
            ':brewery' => $brewery,
            ':type' => $type,
            ':abv' => $beer['abv'] ?? null,
            ':ibu' => $beer['ibu'] ?? null,
            ':description' => $description
        ]);
    }

    echo "Page $page imported.\n";

    $cursor = $list['has_more']
        ? $list['next_cursor']
        : null;

    $page++;

} while ($cursor !== null);

echo "Import complete.\n";
