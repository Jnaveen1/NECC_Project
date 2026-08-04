<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

$location = isset($_GET["location"])
    ? trim($_GET["location"])
    : null;

$year = isset($_GET["year"])
    ? (int) $_GET["year"]
    : null;

if (!$location || !$year) {
    http_response_code(400);

    echo json_encode([
        "detail" => "location and year are required"
    ]);

    exit;
}

try {
    $sql = "
        SELECT
            location,
            year,
            jan,
            feb,
            mar,
            apr,
            may,
            jun,
            jul,
            aug,
            sep,
            oct,
            nov,
            `dec`,
            year_average
        FROM monthly_rates
        WHERE location = :location
          AND year = :year
        LIMIT 1
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location,
        "year" => $year
    ]);

    $record = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);

        echo json_encode([
            "detail" => "No official monthly average data found"
        ]);

        exit;
    }

    $monthNames = [
        1 => "January",
        2 => "February",
        3 => "March",
        4 => "April",
        5 => "May",
        6 => "June",
        7 => "July",
        8 => "August",
        9 => "September",
        10 => "October",
        11 => "November",
        12 => "December"
    ];

    $monthColumns = [
        1 => "jan",
        2 => "feb",
        3 => "mar",
        4 => "apr",
        5 => "may",
        6 => "jun",
        7 => "jul",
        8 => "aug",
        9 => "sep",
        10 => "oct",
        11 => "nov",
        12 => "dec"
    ];

    $monthlyData = [];

    foreach ($monthColumns as $monthNumber => $columnName) {
        $value = $record[$columnName];

        if ($value === null) {
            continue;
        }

        $monthlyData[] = [
            "month" => $monthNumber,
            "month_name" => $monthNames[$monthNumber],
            "average_price" => (float) $value
        ];
    }

    echo json_encode([
        "location" => $location,
        "year" => $year,
        "source_type" => "official_monthly_average_sheet",
        "total_months" => count($monthlyData),
        "monthly_data" => $monthlyData,
        "year_average" => $record["year_average"] !== null
            ? (float) $record["year_average"]
            : null
    ]);

} catch (PDOException $exception) {
    http_response_code(500);

    echo json_encode([
        "detail" => "Unable to fetch monthly summary",
        "error" => $exception->getMessage()
    ]);
}