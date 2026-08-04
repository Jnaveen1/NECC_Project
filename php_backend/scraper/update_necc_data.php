<?php

require_once __DIR__ . "/scrape_one_month.php";
require_once __DIR__ . "/scrape_monthly_rates.php";

$currentMonth = (int) date("n");
$currentYear = (int) date("Y");

echo "Starting NECC data update...\n\n";

try {
    echo "Updating daily prices for {$currentMonth}/{$currentYear}...\n";

    $dailyRecords = scrapeMonthPrices(
        $currentMonth,
        $currentYear
    );

    $dailyAffected = saveEggPrices(
        $conn,
        $dailyRecords
    );

    echo "Daily records scraped: " . count($dailyRecords) . "\n";
    echo "Daily rows affected: {$dailyAffected}\n\n";

    echo "Updating monthly rates for {$currentYear}...\n";

    $monthlyRecords = scrapeMonthlyRates(
        $currentYear
    );

    $monthlyAffected = saveMonthlyRates(
        $conn,
        $monthlyRecords
    );

    echo "Monthly locations scraped: ";
    echo count($monthlyRecords) . "\n";

    echo "Monthly rows affected: {$monthlyAffected}\n\n";

    echo "NECC data update completed successfully\n";
} catch (Throwable $error) {
    echo "Update failed: ";
    echo $error->getMessage() . "\n";

    exit(1);
}