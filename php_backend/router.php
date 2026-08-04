<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

$routes = [
    "/api/locations" => __DIR__ . "/api/locations.php",
    "/api/prices/daily" => __DIR__ . "/api/daily_prices.php",
    "/api/prices/summary" => __DIR__ . "/api/summary.php",
    "/api/prices/monthly-summary" => __DIR__ . "/api/monthly_summary.php",
    "/api/prices/yearly-summary" => __DIR__ . "/api/yearly_summary.php",
    "/api/analysis/market" => __DIR__ . "/api/market_analysis.php",
    "/api/analysis/monthly" => __DIR__ . "/api/monthly_analysis.php",
];

if (isset($routes[$path])) {
    require $routes[$path];
    exit;
}

http_response_code(404);

header("Content-Type: application/json");

echo json_encode([
    "detail" => "API endpoint not found"
]);