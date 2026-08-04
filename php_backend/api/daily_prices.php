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
        SELECT price_date, price
        FROM egg_prices
        WHERE location = :location
          AND price_date >= :start_date
          AND price_date <= :end_date
        ORDER BY price_date ASC
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $records = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (!$records) {
        http_response_code(404);

        echo json_encode([
            "detail" => "No daily price data found"
        ]);

        exit;
    }

    $prices = [];

    foreach ($records as $record) {
        $prices[] = [
            "date" => $record["price_date"],
            "price" => (float) $record["price"]
        ];
    }

    echo json_encode([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate,
        "total_records" => count($prices),
        "prices" => $prices
    ]);

} catch (PDOException $exception) {
    http_response_code(500);

    echo json_encode([
        "detail" => "Unable to fetch daily prices",
        "error" => $exception->getMessage()
    ]);
}