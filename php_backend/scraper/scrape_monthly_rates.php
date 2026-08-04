<?php

require_once __DIR__ . "/../config/database.php";

const NECC_MONTHLY_URL = "https://e2necc.com/home/eggprice";

function fetchMonthlyReportPage(int $year): string
{
    $formData = [
        "ddlYear" => (string) $year,
        "rblReportType" => "MonthlyReport",
        "btnReport" => "Get Sheet"
    ];

    $curl = curl_init(NECC_MONTHLY_URL);

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

        throw new Exception("Monthly report request failed: " . $error);
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

function parseMonthlyValue(string $value): ?float
{
    $cleaned = trim(str_replace(",", "", $value));

    if (
        $cleaned === "" ||
        $cleaned === "-" ||
        strtoupper($cleaned) === "NA" ||
        strtoupper($cleaned) === "N/A"
    ) {
        return null;
    }

    if (!is_numeric($cleaned)) {
        return null;
    }

    return (float) $cleaned;
}

function isValidMonthlyLocation(string $location): bool
{
    $invalidLocations = [
        "",
        "location",
        "centre",
        "center",
        "name of zone / month",
        "name of zone/month",
        "name of zone / day",
        "name of zone/day"
    ];

    return !in_array(
        strtolower(trim($location)),
        $invalidLocations,
        true
    );
}

function scrapeMonthlyRates(int $year): array
{
    $html = fetchMonthlyReportPage($year);

    $document = new DOMDocument();

    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);

    $tables = $xpath->query("//table");
    $selectedTable = null;

    foreach ($tables as $table) {
        $rows = $xpath->query(".//tr", $table);

        foreach ($rows as $row) {
            $cells = $xpath->query("./th|./td", $row);

            if ($cells->length >= 13) {
                $selectedTable = $table;
                break 2;
            }
        }
    }

    if ($selectedTable === null) {
        throw new Exception("Monthly rates table was not found");
    }

    $records = [];
    $rows = $xpath->query(".//tr", $selectedTable);

    foreach ($rows as $row) {
        $cells = $xpath->query("./th|./td", $row);

        if ($cells->length < 13) {
            continue;
        }

        $values = [];

        foreach ($cells as $cell) {
            $values[] = trim(
                preg_replace("/\s+/", " ", $cell->textContent)
            );
        }

        $location = $values[0] ?? "";

        if (!isValidMonthlyLocation($location)) {
            continue;
        }

        $records[] = [
            "location" => $location,
            "year" => $year,
            "jan" => parseMonthlyValue($values[1] ?? ""),
            "feb" => parseMonthlyValue($values[2] ?? ""),
            "mar" => parseMonthlyValue($values[3] ?? ""),
            "apr" => parseMonthlyValue($values[4] ?? ""),
            "may" => parseMonthlyValue($values[5] ?? ""),
            "jun" => parseMonthlyValue($values[6] ?? ""),
            "jul" => parseMonthlyValue($values[7] ?? ""),
            "aug" => parseMonthlyValue($values[8] ?? ""),
            "sep" => parseMonthlyValue($values[9] ?? ""),
            "oct" => parseMonthlyValue($values[10] ?? ""),
            "nov" => parseMonthlyValue($values[11] ?? ""),
            "dec" => parseMonthlyValue($values[12] ?? ""),
            "year_average" => parseMonthlyValue($values[13] ?? ""),
            "source" => NECC_MONTHLY_URL
        ];
    }

    if (!$records) {
        throw new Exception(
            "No monthly rates were parsed for year {$year}"
        );
    }

    return $records;
}

function saveMonthlyRates(PDO $conn, array $records): int
{
    $sql = "
        INSERT INTO monthly_rates (
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
            year_average,
            source
        )
        VALUES (
            :location,
            :year,
            :jan,
            :feb,
            :mar,
            :apr,
            :may,
            :jun,
            :jul,
            :aug,
            :sep,
            :oct,
            :nov,
            :dec,
            :year_average,
            :source
        )
        ON DUPLICATE KEY UPDATE
            jan = VALUES(jan),
            feb = VALUES(feb),
            mar = VALUES(mar),
            apr = VALUES(apr),
            may = VALUES(may),
            jun = VALUES(jun),
            jul = VALUES(jul),
            aug = VALUES(aug),
            sep = VALUES(sep),
            oct = VALUES(oct),
            nov = VALUES(nov),
            `dec` = VALUES(`dec`),
            year_average = VALUES(year_average),
            source = VALUES(source)
    ";

    $statement = $conn->prepare($sql);

    $affectedRows = 0;

    $conn->beginTransaction();

    try {
        foreach ($records as $record) {
            $statement->execute($record);
            $affectedRows += $statement->rowCount();
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollBack();
        throw $error;
    }

    return $affectedRows;
}

if (
    PHP_SAPI === "cli" &&
    realpath($_SERVER["SCRIPT_FILENAME"]) === realpath(__FILE__)
) {
    $year = isset($argv[1])
        ? (int) $argv[1]
        : (int) date("Y");

    try {
        echo "Scraping monthly rates for {$year}...\n";

        $records = scrapeMonthlyRates($year);

        echo "Scraped locations: " . count($records) . "\n";

        $affectedRows = saveMonthlyRates($conn, $records);

        echo "Database rows affected: {$affectedRows}\n";
        echo "Monthly rates saved successfully\n";
    } catch (Throwable $error) {
        echo "Error: " . $error->getMessage() . "\n";
        exit(1);
    }
}