<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

try {
    $sql = "
        SELECT DISTINCT location
        FROM egg_prices
        WHERE location IS NOT NULL
          AND location != ''
          AND location != 'Name Of Zone / Day'
        ORDER BY location
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "success" => true,
        "locations" => $locations
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to fetch locations",
        "error" => $e->getMessage()
    ]);
}