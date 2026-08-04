<?php

require_once __DIR__ . "/scrape_one_month.php";

$endYear = (int) date("Y");
$startYear = $endYear - 3;

$totalScraped = 0;
$totalAffected = 0;

for ($year = $startYear; $year <= $endYear; $year++) {
    for ($month = 1; $month <= 12; $month++) {
        $currentYear = (int) date("Y");
        $currentMonth = (int) date("n");

        if (
            $year > $currentYear ||
            ($year === $currentYear && $month > $currentMonth)
        ) {
            continue;
        }

        try {
            echo "Scraping {$month}/{$year}...\n";

            $records = scrapeMonthPrices($month, $year);
            $affectedRows = saveEggPrices($conn, $records);

            $totalScraped += count($records);
            $totalAffected += $affectedRows;

            echo "Scraped: " . count($records) . "\n";
            echo "Affected: {$affectedRows}\n\n";
        } catch (Throwable $error) {
            echo "Failed {$month}/{$year}: ";
            echo $error->getMessage() . "\n\n";
        }
    }
}

echo "Three-year scraping completed\n";
echo "Total scraped records: {$totalScraped}\n";
echo "Total affected rows: {$totalAffected}\n";