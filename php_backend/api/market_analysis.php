<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/../config/database.php";

$location = trim($_GET["location"] ?? "");
$year = isset($_GET["year"]) ? (int) $_GET["year"] : 0;

if ($location === "" || $year <= 0) {
    http_response_code(400);

    echo json_encode([
        "detail" => "location and year are required"
    ]);

    exit;
}

try {
    $sql = "
        SELECT
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
            "detail" => "No monthly data found for analysis"
        ]);

        exit;
    }

    $months = [
        "January" => "jan",
        "February" => "feb",
        "March" => "mar",
        "April" => "apr",
        "May" => "may",
        "June" => "jun",
        "July" => "jul",
        "August" => "aug",
        "September" => "sep",
        "October" => "oct",
        "November" => "nov",
        "December" => "dec"
    ];

    $availableData = [];

    foreach ($months as $monthName => $columnName) {
        $value = $record[$columnName];

        if ($value !== null && $value !== "") {
            $availableData[$monthName] = (float) $value;
        }
    }

    if (!$availableData) {
        http_response_code(404);

        echo json_encode([
            "detail" => "No monthly values available"
        ]);

        exit;
    }

    $highestMonth = array_search(max($availableData), $availableData, true);
    $lowestMonth = array_search(min($availableData), $availableData, true);

    $highestPrice = max($availableData);
    $lowestPrice = min($availableData);

    $firstMonth = array_key_first($availableData);
    $lastMonth = array_key_last($availableData);

    $firstPrice = $availableData[$firstMonth];
    $lastPrice = $availableData[$lastMonth];

    $change = $lastPrice - $firstPrice;

    if ($change > 0) {
        $trend = "rising";
        $trendText = "increased";
    } elseif ($change < 0) {
        $trend = "falling";
        $trendText = "declined";
    } else {
        $trend = "stable";
        $trendText = "remained stable";
    }

    $percentageChange = $firstPrice > 0
        ? ($change / $firstPrice) * 100
        : 0;

    $analysis = sprintf(
        "For %s in %d, the available monthly average egg price %s from ₹%.2f in %s to ₹%.2f in %s. The highest monthly average was ₹%.2f in %s, while the lowest was ₹%.2f in %s. The overall change was %.2f%%. Seasonal demand, local supply, festivals, weather and transportation conditions may have contributed to these movements.",
        $location,
        $year,
        $trendText,
        $firstPrice,
        $firstMonth,
        $lastPrice,
        $lastMonth,
        $highestPrice,
        $highestMonth,
        $lowestPrice,
        $lowestMonth,
        $percentageChange
    );

    echo json_encode([
        "location" => $location,
        "year" => $year,
        "trend" => $trend,
        "percentage_change" => round($percentageChange, 2),
        "highest_month" => $highestMonth,
        "highest_price" => round($highestPrice, 2),
        "lowest_month" => $lowestMonth,
        "lowest_price" => round($lowestPrice, 2),
        "year_average" => $record["year_average"] !== null
            ? (float) $record["year_average"]
            : null,
        "analysis" => $analysis,
        "source" => "heuristic"
    ]);

} catch (PDOException $exception) {
    http_response_code(500);

    echo json_encode([
        "detail" => "Unable to generate monthly analysis",
        "error" => $exception->getMessage()
    ]);
}