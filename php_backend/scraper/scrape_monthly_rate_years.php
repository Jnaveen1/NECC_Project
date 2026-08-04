<?php

require_once __DIR__ . "/scrape_monthly_rates.php";

$startYear = isset($argv[1])
    ? (int) $argv[1]
    : (int) date("Y") - 2;

$endYear = isset($argv[2])
    ? (int) $argv[2]
    : (int) date("Y");

if ($startYear > $endYear) {
    exit("Start year cannot be greater than end year.\n");
}

$totalLocations = 0;
$totalAffectedRows = 0;

for ($year = $startYear; $year <= $endYear; $year++) {
    try {
        echo "Scraping monthly rates for {$year}...\n";

        $records = scrapeMonthlyRates($year);
        $affectedRows = saveMonthlyRates($conn, $records);

        $totalLocations += count($records);
        $totalAffectedRows += $affectedRows;

        echo "Scraped locations: " . count($records) . "\n";
        echo "Affected rows: {$affectedRows}\n\n";
    } catch (Throwable $error) {
        echo "Failed for {$year}: ";
        echo $error->getMessage() . "\n\n";
    }
}

echo "Monthly rate scraping completed\n";
echo "Total scraped location records: {$totalLocations}\n";
echo "Total affected rows: {$totalAffectedRows}\n";