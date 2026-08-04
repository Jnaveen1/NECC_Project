<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

$location = isset($_GET["location"])
    ? trim($_GET["location"])
    : null;

if (!$location) {
    http_response_code(400);

    echo json_encode([
        "detail" => "location is required"
    ]);

    exit;
}

try {
    $sql = "
        SELECT
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
        ORDER BY year ASC
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location
    ]);

    $records = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (!$records) {
        http_response_code(404);

        echo json_encode([
            "detail" => "No official yearly average data found"
        ]);

        exit;
    }

    $monthColumns = [
        "jan",
        "feb",
        "mar",
        "apr",
        "may",
        "jun",
        "jul",
        "aug",
        "sep",
        "oct",
        "nov",
        "dec"
    ];

    $yearlyData = [];

    foreach ($records as $record) {
        $availableMonths = 0;

        foreach ($monthColumns as $monthColumn) {
            if (
                $record[$monthColumn] !== null &&
                $record[$monthColumn] !== ""
            ) {
                $availableMonths++;
            }
        }

        $yearlyData[] = [
            "year" => (int) $record["year"],
            "average_price" => $record["year_average"] !== null
                ? (float) $record["year_average"]
                : null,
            "available_months" => $availableMonths
        ];
    }

    echo json_encode([
        "location" => $location,
        "source_type" => "official_monthly_average_sheet",
        "total_years" => count($yearlyData),
        "yearly_data" => $yearlyData
    ]);

} catch (PDOException $exception) {
    http_response_code(500);

    echo json_encode([
        "detail" => "Unable to fetch yearly summary",
        "error" => $exception->getMessage()
    ]);
}