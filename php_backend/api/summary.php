<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

$location = trim($_GET["location"] ?? "");
$startDate = trim($_GET["start_date"] ?? "");
$endDate = trim($_GET["end_date"] ?? "");

if ($location === "" || $startDate === "" || $endDate === "") {
    http_response_code(400);

    echo json_encode([
        "detail" => "location, start_date and end_date are required"
    ]);

    exit;
}

if ($startDate > $endDate) {
    http_response_code(400);

    echo json_encode([
        "detail" => "start_date cannot be greater than end_date"
    ]);

    exit;
}

try {
    $sql = "
        SELECT
            COUNT(*) AS total_records,
            AVG(price) AS average_price,
            MIN(price) AS minimum_price,
            MAX(price) AS maximum_price,
            MIN(price_date) AS first_date,
            MAX(price_date) AS last_date,
            (
                SELECT price
                FROM egg_prices
                WHERE location = :location
                  AND price_date BETWEEN :start_date AND :end_date
                ORDER BY price_date DESC, id DESC
                LIMIT 1
            ) AS latest_price,
            (
                SELECT price_date
                FROM egg_prices
                WHERE location = :location
                  AND price_date BETWEEN :start_date AND :end_date
                ORDER BY price_date DESC, id DESC
                LIMIT 1
            ) AS latest_price_date
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $summary = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$summary || (int) $summary["total_records"] === 0) {
        http_response_code(404);

        echo json_encode([
            "detail" => "No price data found"
        ]);

        exit;
    }

    echo json_encode([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate,
        "total_records" => (int) $summary["total_records"],
        "current_price" => (float) $summary["latest_price"],
        "current_date" => $summary["latest_price_date"],
        "average_price" => round((float) $summary["average_price"], 2),
        "minimum_price" => (float) $summary["minimum_price"],
        "maximum_price" => (float) $summary["maximum_price"],
        "first_date" => $summary["first_date"],
        "last_date" => $summary["last_date"]
    ]);

} catch (PDOException $exception) {
    http_response_code(500);

    echo json_encode([
        "detail" => "Unable to fetch price summary",
        "error" => $exception->getMessage()
    ]);
}