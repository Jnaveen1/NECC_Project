<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$path = parse_url(
    $_SERVER["REQUEST_URI"],
    PHP_URL_PATH
);

/*
|--------------------------------------------------------------------------
| Serve static files
|--------------------------------------------------------------------------
| Example:
| /assets/css/style.css
*/
$filePath = __DIR__ . $path;

if ($path !== "/" && is_file($filePath)) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        "css" => "text/css; charset=utf-8",
        "js" => "application/javascript; charset=utf-8",
        "json" => "application/json; charset=utf-8",
        "png" => "image/png",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "svg" => "image/svg+xml",
        "gif" => "image/gif",
        "ico" => "image/x-icon",
        "html" => "text/html; charset=utf-8",
        "txt" => "text/plain; charset=utf-8",
    ];

    if (isset($mimeTypes[$extension])) {
        header("Content-Type: " . $mimeTypes[$extension]);
    }

    readfile($filePath);
    exit;
}

/*
|--------------------------------------------------------------------------
| Main PHP dashboard page
|--------------------------------------------------------------------------
*/
if ($path === "/") {
    header("Location: /dashboard");
    exit;
}

if ($path === "/dashboard") {
    require __DIR__ . "/pages/dashboard.php";
    exit;
}

if ($path === "/daily") {
    require __DIR__ . "/pages/daily.php";
    exit;
}

if ($path === "/monthly") {
    require __DIR__ . "/pages/monthly.php";
    exit;
}

if ($path === "/analysis") {
    require __DIR__ . "/pages/analysis.php";
    exit;
}

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
*/
$routes = [
    "/api/locations" =>
        __DIR__ . "/api/locations.php",

    "/api/prices/daily" =>
        __DIR__ . "/api/daily_prices.php",

    "/api/prices/summary" =>
        __DIR__ . "/api/summary.php",

    "/api/prices/monthly-summary" =>
        __DIR__ . "/api/monthly_summary.php",

    "/api/prices/yearly-summary" =>
        __DIR__ . "/api/yearly_summary.php",

    "/api/analysis/market" =>
        __DIR__ . "/api/market_analysis.php",

    "/api/analysis/monthly" =>
        __DIR__ . "/api/monthly_analysis.php",
];

if (isset($routes[$path])) {
    require $routes[$path];
    exit;
}

/*
|--------------------------------------------------------------------------
| Not found
|--------------------------------------------------------------------------
*/
http_response_code(404);

header("Content-Type: application/json");

echo json_encode([
    "detail" => "Page or API endpoint not found"
]);