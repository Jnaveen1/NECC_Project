<?php

require_once __DIR__ . "/../config/database.php";

const NECC_URL = "https://e2necc.com/home/eggprice";

/**
 * Send request to NECC and return the HTML page.
 */
function fetchMonthPage(int $month, int $year): string
{
    $formData = [
        "ddlMonth" => str_pad((string) $month, 2, "0", STR_PAD_LEFT),
        "ddlYear" => (string) $year,
        "rblReportType" => "DailyReport",
        "btnReport" => "Get Sheet"
    ];

    $curl = curl_init(NECC_URL);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($formData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);

    $html = curl_exec($curl);

    if ($html === false) {
        $error = curl_error($curl);
        curl_close($curl);

        throw new Exception("NECC request failed: " . $error);
    }

    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception(
            "NECC returned HTTP status " . $statusCode
        );
    }

    return $html;
}

/**
 * Convert a table value into a valid price.
 */
function parsePrice(string $value): ?float
{
    $cleaned = trim(str_replace(",", "", $value));

    $invalidValues = ["", "-", "NA", "N/A", "NULL"];

    if (in_array(strtoupper($cleaned), $invalidValues, true)) {
        return null;
    }

    if (!is_numeric($cleaned)) {
        return null;
    }

    return (float) $cleaned;
}

/**
 * Check whether the first cell is a real location.
 */
function isValidLocation(string $location): bool
{
    $location = trim($location);

    $invalidLocations = [
        "",
        "location",
        "centre",
        "center",
        "name of zone / day",
        "name of zone/day"
    ];

    return !in_array(strtolower($location), $invalidLocations, true);
}

/**
 * Extract daily prices from the NECC HTML table.
 */
function scrapeMonthPrices(int $month, int $year): array
{
    $html = fetchMonthPage($month, $year);

    $document = new DOMDocument();

    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);

    $tables = $xpath->query("//table");

    $selectedTable = null;
    $largestColumnCount = 0;

    foreach ($tables as $table) {
        $rows = $xpath->query(".//tr", $table);
        $maximumColumns = 0;

        foreach ($rows as $row) {
            $cells = $xpath->query("./th|./td", $row);
            $maximumColumns = max($maximumColumns, $cells->length);
        }

        if ($maximumColumns >= 10 && $maximumColumns > $largestColumnCount) {
            $selectedTable = $table;
            $largestColumnCount = $maximumColumns;
        }
    }

    if ($selectedTable === null) {
        throw new Exception("NECC daily price table was not found");
    }

    $records = [];
    $rows = $xpath->query(".//tr", $selectedTable);
    $today = new DateTimeImmutable("today");

    foreach ($rows as $row) {
        $cells = $xpath->query("./th|./td", $row);

        if ($cells->length < 2) {
            continue;
        }

        $values = [];

        foreach ($cells as $cell) {
            $values[] = trim(
                preg_replace("/\s+/", " ", $cell->textContent)
            );
        }

        $location = $values[0] ?? "";

        if (!isValidLocation($location)) {
            continue;
        }

        // Values 1 to 31 represent day prices.
        for ($day = 1; $day <= 31; $day++) {
            $priceText = $values[$day] ?? "";
            $price = parsePrice($priceText);

            if ($price === null) {
                continue;
            }

            if (!checkdate($month, $day, $year)) {
                continue;
            }

            $priceDate = new DateTimeImmutable(
                sprintf("%04d-%02d-%02d", $year, $month, $day)
            );

            // Do not save future dates.
            if ($priceDate > $today) {
                continue;
            }

            $records[] = [
                "location" => $location,
                "price_date" => $priceDate->format("Y-m-d"),
                "price" => $price,
                "source" => NECC_URL
            ];
        }
    }

    if (count($records) === 0) {
        throw new Exception(
            "No daily prices were parsed for {$month}/{$year}"
        );
    }

    return $records;
}

/**
 * Insert new records or update existing records.
 */
function saveEggPrices(PDO $conn, array $records): int
{
    $sql = "
        INSERT INTO egg_prices (
            location,
            price_date,
            price,
            source
        )
        VALUES (
            :location,
            :price_date,
            :price,
            :source
        )
        ON DUPLICATE KEY UPDATE
            price = VALUES(price),
            source = VALUES(source)
    ";

    $statement = $conn->prepare($sql);

    $affectedRows = 0;

    $conn->beginTransaction();

    try {
        foreach ($records as $record) {
            $statement->execute([
                "location" => $record["location"],
                "price_date" => $record["price_date"],
                "price" => $record["price"],
                "source" => $record["source"]
            ]);

            $affectedRows += $statement->rowCount();
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollBack();
        throw $error;
    }

    return $affectedRows;
}

if (PHP_SAPI === "cli" &&
    realpath($_SERVER["SCRIPT_FILENAME"]) === realpath(__FILE__)) {
    $month = isset($argv[1])
        ? (int) $argv[1]
        : (int) date("n");

    $year = isset($argv[2])
        ? (int) $argv[2]
        : (int) date("Y");

    if ($month < 1 || $month > 12) {
        exit("Invalid month. Use a value between 1 and 12.\n");
    }

    try {
        echo "Scraping {$month}/{$year}...\n";

        $records = scrapeMonthPrices($month, $year);

        echo "Scraped records: " . count($records) . "\n";

        $affectedRows = saveEggPrices($conn, $records);

        echo "Database rows affected: {$affectedRows}\n";
        echo "Daily NECC data saved successfully\n";
    } catch (Throwable $error) {
        echo "Error: " . $error->getMessage() . "\n";
        exit(1);
    }
}