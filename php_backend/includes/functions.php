<?php

require_once __DIR__ . "/../config/database.php";

function getLocations(PDO $conn): array
{
    $sql = "
        SELECT DISTINCT location
        FROM egg_prices
        WHERE location IS NOT NULL
          AND location != ''
          AND location != 'Name Of Zone / Day'
        ORDER BY location
    ";

    $statement = $conn->query($sql);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function getPriceSummary(
    PDO $conn,
    string $location,
    string $startDate,
    string $endDate
): ?array {

    $sql = "
        SELECT
            COUNT(*) AS total_records,
            ROUND(AVG(price), 2) AS average_price,
            MIN(price) AS minimum_price,
            MAX(price) AS maximum_price
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $summary = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$summary || (int)$summary["total_records"] === 0) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Current Price
    |--------------------------------------------------------------------------
    */

    $latestSql = "
        SELECT
            price AS current_price,
            price_date AS latest_date
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
        ORDER BY price_date DESC, id DESC
        LIMIT 1
    ";

    $latestStatement = $conn->prepare($latestSql);

    $latestStatement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $latest = $latestStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Minimum Price
    |--------------------------------------------------------------------------
    */

    $minimumSql = "
        SELECT
            price,
            price_date
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
        ORDER BY price ASC, price_date ASC
        LIMIT 1
    ";

    $minimumStatement = $conn->prepare($minimumSql);

    $minimumStatement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $minimum = $minimumStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Maximum Price
    |--------------------------------------------------------------------------
    */

    $maximumSql = "
        SELECT
            price,
            price_date
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
        ORDER BY price DESC, price_date ASC
        LIMIT 1
    ";

    $maximumStatement = $conn->prepare($maximumSql);

    $maximumStatement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $maximum = $maximumStatement->fetch(PDO::FETCH_ASSOC);

    return [
        "current_price" => $latest["current_price"] ?? null,
        "current_date" => $latest["latest_date"] ?? null,

        "average_price" => $summary["average_price"],

        "minimum_price" => $minimum["price"] ?? null,
        "minimum_date" => $minimum["price_date"] ?? null,

        "maximum_price" => $maximum["price"] ?? null,
        "maximum_date" => $maximum["price_date"] ?? null,

        "total_records" => $summary["total_records"]
    ];
}

function getDailyPrices(
    PDO $conn,
    string $location,
    string $startDate,
    string $endDate
): array {
    $sql = "
        SELECT price_date, price
        FROM egg_prices
        WHERE location = :location
          AND price_date BETWEEN :start_date AND :end_date
        ORDER BY price_date ASC
    ";

    $statement = $conn->prepare($sql);
    $statement->execute([
        "location" => $location,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function getLatestThreeDataYears(
    PDO $conn,
    string $location
): array {
    $sql = "
        SELECT DISTINCT YEAR(price_date) AS data_year
        FROM egg_prices
        WHERE location = :location
        ORDER BY data_year DESC
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "location" => $location
    ]);

    $availableYears = array_values(array_unique(array_map("intval", $statement->fetchAll(PDO::FETCH_COLUMN))));
    $preferredYears = [2026, 2025, 2024];
    $matchingYears = array_values(array_intersect($preferredYears, $availableYears));

    if (!empty($matchingYears)) {
        return $matchingYears;
    }

    $fallbackYears = array_values(array_filter($availableYears, fn (int $year): bool => $year <= 2026));
    if (!empty($fallbackYears)) {
        return array_slice($fallbackYears, 0, 3);
    }

    return $preferredYears;
}

function getMonthlyComparisonData(
    PDO $conn,
    string $location
): array {
    $years = getLatestThreeDataYears($conn, $location);

    $labels = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec"
    ];

    $series = [];

    foreach ($years as $year) {
        $sql = "
            SELECT
                MONTH(price_date) AS month_number,
                ROUND(AVG(price), 2) AS average_price
            FROM egg_prices
            WHERE location = :location
              AND YEAR(price_date) = :year
            GROUP BY MONTH(price_date)
            ORDER BY month_number
        ";

        $statement = $conn->prepare($sql);

        $statement->execute([
            "location" => $location,
            "year" => $year
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $monthlyValues = array_fill(0, 12, null);

        foreach ($rows as $row) {
            $monthNumber = (int) $row["month_number"];

            if ($monthNumber < 1 || $monthNumber > 12) {
                continue;
            }

            $monthlyValues[$monthNumber - 1] =
                (float) $row["average_price"];
        }

        $series[(string) $year] = $monthlyValues;
    }

    return [
        "labels" => $labels,
        "series" => $series
    ];
}

function getDailyComparisonData(
    PDO $conn,
    string $location
): array {
    $years = getLatestThreeDataYears($conn, $location);

    /*
     * Use a leap year as the reference so the axis can contain
     * February 29 when required.
     */
    $referenceYear = 2024;

    $startDate = new DateTimeImmutable(
        $referenceYear . "-01-01"
    );

    $endDate = new DateTimeImmutable(
        $referenceYear . "-12-31"
    );

    $labels = [];
    $dateKeys = [];

    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        /*
         * Internal key:
         * 01-01, 01-02 ... 12-31
         */
        $dateKeys[] = $currentDate->format("m-d");

        /*
         * Label used by the chart.
         * The chart function will display only selected labels.
         */
        $labels[] = $currentDate->format("d M");

        $currentDate = $currentDate->modify("+1 day");
    }

    $series = [];

    foreach ($years as $year) {
        $sql = "
            SELECT
                DATE_FORMAT(price_date, '%m-%d') AS month_day,
                price
            FROM egg_prices
            WHERE location = :location
              AND YEAR(price_date) = :year
            ORDER BY price_date ASC
        ";

        $statement = $conn->prepare($sql);

        $statement->execute([
            "location" => $location,
            "year" => $year
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $priceMap = [];

        foreach ($rows as $row) {
            $priceMap[$row["month_day"]] =
                (float) $row["price"];
        }

        $dailyValues = [];

        foreach ($dateKeys as $dateKey) {
            /*
             * Non-leap years do not contain February 29,
             * so the value remains null.
             */
            $dailyValues[] = $priceMap[$dateKey] ?? null;
        }

        $series[(string) $year] = $dailyValues;
    }

    return [
        "labels" => $labels,
        "series" => $series
    ];
}

function getComparisonData(
    PDO $conn,
    string $location,
    string $view = "months"
): array {
    if ($view === "days") {
        return getDailyComparisonData(
            $conn,
            $location
        );
    }

    return getMonthlyComparisonData(
        $conn,
        $location
    );
}
function getMonthlyReportData(PDO $conn, string $location, int $year): array
{
    $sql = "
        SELECT *
        FROM monthly_rates
        WHERE location = :location
          AND year = :year
    ";

    $statement = $conn->prepare($sql);
    $statement->execute([
        "location" => $location,
        "year" => $year,
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [];
    }

    $months = [
        "Jan" => $row["jan"],
        "Feb" => $row["feb"],
        "Mar" => $row["mar"],
        "Apr" => $row["apr"],
        "May" => $row["may"],
        "Jun" => $row["jun"],
        "Jul" => $row["jul"],
        "Aug" => $row["aug"],
        "Sep" => $row["sep"],
        "Oct" => $row["oct"],
        "Nov" => $row["nov"],
        "Dec" => $row["dec"],
    ];

    $monthly = [];
    foreach ($months as $name => $value) {
        if ($value !== null) {
            $monthly[] = ["month" => $name, "value" => (float) $value];
        }
    }

    return [
        "year" => $year,
        "average" => $row["year_average"],
        "monthly" => $monthly,
    ];
}

function formatCurrency($value): string
{
    if ($value === null || $value === "") {
        return "₹—";
    }

    return "₹" . number_format((float) $value, 2);
}

function formatDateForDisplay($date): string
{
    if (!$date) {
        return "Selected period";
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date("d M Y", $timestamp);
}

function getAvailablePriceYears(PDO $conn): array
{
    $sql = "
        SELECT DISTINCT YEAR(price_date) AS price_year
        FROM egg_prices
        ORDER BY price_year DESC
    ";

    $statement = $conn->query($sql);

    return array_map(
        "intval",
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );
}

function getDailyPriceMatrix(
    PDO $conn,
    int $month,
    int $year
): array {
    $startDate = sprintf(
        "%04d-%02d-01",
        $year,
        $month
    );

    $endDate = date(
        "Y-m-t",
        strtotime($startDate)
    );

    $sql = "
        SELECT
            location,
            price_date,
            price
        FROM egg_prices
        WHERE price_date BETWEEN :start_date AND :end_date
          AND location IS NOT NULL
          AND location != ''
          AND location != 'Name Of Zone / Day'
        ORDER BY location ASC, price_date ASC
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $dates = [];

    $currentDate = new DateTimeImmutable($startDate);
    $lastDate = new DateTimeImmutable($endDate);

    while ($currentDate <= $lastDate) {
        $dates[] = $currentDate->format("Y-m-d");
        $currentDate = $currentDate->modify("+1 day");
    }

    $locations = [];

    foreach ($rows as $row) {
        $location = $row["location"];
        $date = $row["price_date"];

        if (!isset($locations[$location])) {
            $locations[$location] = [];
        }

        $locations[$location][$date] =
            (float) $row["price"];
    }

    return [
        "dates" => $dates,
        "locations" => $locations
    ];
}


function getMonthlyPriceMatrix(
    PDO $conn,
    int $year
): array {
    $sql = "
        SELECT
            location,
            MONTH(price_date) AS month_number,
            ROUND(AVG(price), 2) AS average_price
        FROM egg_prices
        WHERE YEAR(price_date) = :year
          AND location IS NOT NULL
          AND location != ''
          AND location != 'Name Of Zone / Day'
        GROUP BY location, MONTH(price_date)
        ORDER BY location ASC, month_number ASC
    ";

    $statement = $conn->prepare($sql);

    $statement->execute([
        "year" => $year
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $locations = [];

    foreach ($rows as $row) {
        $location = $row["location"];
        $monthNumber = (int) $row["month_number"];

        if (!isset($locations[$location])) {
            $locations[$location] = [];
        }

        $locations[$location][$monthNumber] =
            (float) $row["average_price"];
    }

    return [
        "months" => [
            1 => "Jan",
            2 => "Feb",
            3 => "Mar",
            4 => "Apr",
            5 => "May",
            6 => "Jun",
            7 => "Jul",
            8 => "Aug",
            9 => "Sep",
            10 => "Oct",
            11 => "Nov",
            12 => "Dec"
        ],
        "locations" => $locations
    ];
}


function getPriceMovement(
    ?float $currentPrice,
    ?float $previousPrice
): array {
    if ($currentPrice === null || $previousPrice === null) {
        return [
            "symbol" => "",
            "class" => ""
        ];
    }

    if ($currentPrice > $previousPrice) {
        return [
            "symbol" => "▲",
            "class" => "movement-up"
        ];
    }

    if ($currentPrice < $previousPrice) {
        return [
            "symbol" => "▼",
            "class" => "movement-down"
        ];
    }

    return [
        "symbol" => "•",
        "class" => "movement-same"
    ];
}

function buildPolylinePoints(array $values, int $width = 760, int $height = 240, int $padding = 30): string
{
    if (empty($values)) {
        return "";
    }

    $filtered = array_values(array_filter($values, fn ($item) => $item !== null && $item !== ""));
    $filtered = array_map('floatval', $filtered);
    if (empty($filtered)) {
        return "";
    }

    $min = min($filtered);
    $max = max($filtered);
    if ($min === $max) {
        $min -= 1;
        $max += 1;
    }

    $points = [];
    foreach ($values as $index => $value) {
        if ($value === null || $value === "") {
            continue;
        }

        $x = $padding + ($index * (($width - ($padding * 2)) / max(1, count($values) - 1)));
        $y = $height - $padding - (($value - $min) / ($max - $min || 1)) * ($height - ($padding * 2));
        $points[] = $x . "," . $y;
    }

    return implode(" ", $points);
}

function buildMultiLineChart(
    array $series,
    array $labels,
    int $width = 900,
    int $height = 360
): string {
    if (empty($series) || empty($labels)) {
        return '<div class="empty-chart">No comparison data available.</div>';
    }

    $colors = [
        "#2563eb",
        "#16a34a",
        "#f97316",
        "#7c3aed",
        "#dc2626"
    ];

    $paddingLeft = 70;
    $paddingRight = 25;
    $paddingTop = 30;
    $paddingBottom = 60;

    $chartWidth = $width - $paddingLeft - $paddingRight;
    $chartHeight = $height - $paddingTop - $paddingBottom;

    $allValues = [];

    foreach ($series as $values) {
        foreach ($values as $value) {
            if ($value !== null && $value !== "" && is_numeric($value)) {
                $allValues[] = (float) $value;
            }
        }
    }

    if (empty($allValues)) {
        return '<div class="empty-chart">No comparison data available.</div>';
    }

    $actualMin = min($allValues);
    $actualMax = max($allValues);

    $axisMin = floor($actualMin / 100) * 100;
    $axisMax = ceil($actualMax / 100) * 100;

    if ($axisMin > 0) {
        $axisMin = max(0, $axisMin - 100);
    }

    if ($axisMin === $axisMax) {
        $axisMax = $axisMin + 100;
    }

    $range = $axisMax - $axisMin;

    $svg = '<div class="chart-wrapper">';
    $svg .= '<svg
        viewBox="0 0 ' . $width . ' ' . $height . '"
        class="chart-svg"
        role="img"
        aria-label="Last three years comparison chart"
        preserveAspectRatio="none"
    >';

    $horizontalSteps = 4;

    for ($step = 0; $step <= $horizontalSteps; $step++) {
        $ratio = $step / $horizontalSteps;

        $y = $paddingTop + ($ratio * $chartHeight);

        $axisValue = $axisMax - ($ratio * $range);

        $svg .= '<line
            x1="' . $paddingLeft . '"
            y1="' . round($y, 2) . '"
            x2="' . ($width - $paddingRight) . '"
            y2="' . round($y, 2) . '"
            class="chart-grid-line"
        />';

        $svg .= '<text
            x="' . ($paddingLeft - 12) . '"
            y="' . round($y + 4, 2) . '"
            text-anchor="end"
            class="chart-y-label"
        >
            ₹' . number_format($axisValue, 0) . '
        </text>';
    }

    $labelCount = count($labels);
    $xDivisor = max(1, $labelCount - 1);

    $labelCount = count($labels);
    $xDivisor = max(1, $labelCount - 1);

    foreach ($labels as $index => $label) {
        $showLabel = true;
        $displayLabel = (string) $label;

        /*
        * Daily view contains more than 31 labels.
        * Show only the first day of every month.
        *
        * Example input labels:
        * 01 Jan
        * 02 Jan
        * ...
        * 01 Feb
        */
        if ($labelCount > 31) {
            $showLabel = str_starts_with(
                (string) $label,
                "01 "
            );

            if ($showLabel) {
                $displayLabel = substr(
                    (string) $label,
                    3
                );
            }
        }

        if (!$showLabel) {
            continue;
        }

        $x = $paddingLeft + (
            ($index / $xDivisor) * $chartWidth
        );

        $svg .= '<line
            x1="' . round($x, 2) . '"
            y1="' . $paddingTop . '"
            x2="' . round($x, 2) . '"
            y2="' . ($height - $paddingBottom) . '"
            class="chart-vertical-grid-line"
        />';

        $svg .= '<text
            x="' . round($x, 2) . '"
            y="' . ($height - 22) . '"
            text-anchor="middle"
            class="chart-x-label"
        >
            ' . htmlspecialchars($displayLabel) . '
        </text>';
    }
    $svg .= '<line
        x1="' . $paddingLeft . '"
        y1="' . $paddingTop . '"
        x2="' . $paddingLeft . '"
        y2="' . ($height - $paddingBottom) . '"
        class="chart-axis-line"
    />';

    $svg .= '<line
        x1="' . $paddingLeft . '"
        y1="' . ($height - $paddingBottom) . '"
        x2="' . ($width - $paddingRight) . '"
        y2="' . ($height - $paddingBottom) . '"
        class="chart-axis-line"
    />';

    $lineIndex = 0;

    foreach ($series as $year => $values) {
        $color = $colors[$lineIndex % count($colors)];

        $segments = [];
        $currentSegment = [];

        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? null;

            if ($value === null || $value === "" || !is_numeric($value)) {
                if (!empty($currentSegment)) {
                    $segments[] = $currentSegment;
                    $currentSegment = [];
                }

                continue;
            }

            $numericValue = (float) $value;

            $x = $paddingLeft + (
                ($index / $xDivisor) * $chartWidth
            );

            $normalizedValue = (
                $numericValue - $axisMin
            ) / $range;

            $y = $paddingTop
                + $chartHeight
                - ($normalizedValue * $chartHeight);

            $currentSegment[] = [
                "x" => round($x, 2),
                "y" => round($y, 2),
                "value" => $numericValue,
                "label" => (string) $label
            ];
        }

        if (!empty($currentSegment)) {
            $segments[] = $currentSegment;
        }

        foreach ($segments as $segment) {
            if (count($segment) > 1) {
                $pointText = [];

                foreach ($segment as $point) {
                    $pointText[] = $point["x"] . "," . $point["y"];
                }

                $svg .= '<polyline
                    fill="none"
                    stroke="' . $color . '"
                    stroke-width="3"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    points="' . implode(" ", $pointText) . '"
                />';
            }

            foreach ($segment as $point) {
                $svg .= '<circle
                    cx="' . $point["x"] . '"
                    cy="' . $point["y"] . '"
                    r="4"
                    fill="' . $color . '"
                    class="chart-point"
                >
                    <title>'
                        . htmlspecialchars($point["label"])
                        . ': ₹'
                        . number_format($point["value"], 2)
                        . '
                    </title>
                </circle>';
            }
        }

        $lineIndex++;
    }

    $svg .= '</svg>';

    $svg .= '<div class="chart-legend">';

    $legendIndex = 0;

    foreach ($series as $year => $values) {
        $color = $colors[
            $legendIndex % count($colors)
        ];

        $svg .= '<span class="legend-item">
            <span
                class="legend-dot"
                style="background:' . $color . ';"
            ></span>

            <span>'
                . htmlspecialchars((string) $year)
                . '
            </span>
        </span>';

        $legendIndex++;
    }

    $svg .= '</div>';
    $svg .= '</div>';

    return $svg;
}


function buildTrendChartSvg(
    array $values,
    array $labels = [],
    int $width = 900,
    int $height = 360
): string {
    if (empty($values)) {
        return '<div class="empty-chart">No daily data for this date range.</div>';
    }

    $numericValues = [];

    foreach ($values as $value) {
        if ($value !== null && $value !== "" && is_numeric($value)) {
            $numericValues[] = (float) $value;
        }
    }

    if (empty($numericValues)) {
        return '<div class="empty-chart">No daily data for this date range.</div>';
    }

    $paddingLeft = 70;
    $paddingRight = 25;
    $paddingTop = 30;
    $paddingBottom = 60;

    $chartWidth = $width - $paddingLeft - $paddingRight;
    $chartHeight = $height - $paddingTop - $paddingBottom;

    $actualMin = min($numericValues);
    $actualMax = max($numericValues);

    $axisMin = floor($actualMin / 100) * 100;
    $axisMax = ceil($actualMax / 100) * 100;

    if ($axisMin > 0) {
        $axisMin = max(0, $axisMin - 100);
    }

    if ($axisMin === $axisMax) {
        $axisMax = $axisMin + 100;
    }

    $range = $axisMax - $axisMin;

    $valueCount = count($values);
    $xDivisor = max(1, $valueCount - 1);

    if (empty($labels)) {
        for ($index = 0; $index < $valueCount; $index++) {
            $labels[] = (string) ($index + 1);
        }
    }

    $points = [];

    foreach ($values as $index => $value) {
        if ($value === null || $value === "" || !is_numeric($value)) {
            continue;
        }

        $numericValue = (float) $value;

        $x = $paddingLeft + (
            ($index / $xDivisor) * $chartWidth
        );

        $normalizedValue = (
            $numericValue - $axisMin
        ) / $range;

        $y = $paddingTop
            + $chartHeight
            - ($normalizedValue * $chartHeight);

        $points[] = [
            "x" => round($x, 2),
            "y" => round($y, 2),
            "value" => $numericValue,
            "label" => (string) ($labels[$index] ?? ($index + 1))
        ];
    }

    if (empty($points)) {
        return '<div class="empty-chart">No daily data for this date range.</div>';
    }

    $svg = '<div class="chart-wrapper">';
    $svg .= '<svg
        viewBox="0 0 ' . $width . ' ' . $height . '"
        class="chart-svg"
        role="img"
        aria-label="Daily price trend chart"
        preserveAspectRatio="none"
    >';

    $horizontalSteps = 4;

    for ($step = 0; $step <= $horizontalSteps; $step++) {
        $ratio = $step / $horizontalSteps;

        $y = $paddingTop + ($ratio * $chartHeight);

        $axisValue = $axisMax - ($ratio * $range);

        $svg .= '<line
            x1="' . $paddingLeft . '"
            y1="' . round($y, 2) . '"
            x2="' . ($width - $paddingRight) . '"
            y2="' . round($y, 2) . '"
            class="chart-grid-line"
        />';

        $svg .= '<text
            x="' . ($paddingLeft - 12) . '"
            y="' . round($y + 4, 2) . '"
            text-anchor="end"
            class="chart-y-label"
        >
            ₹' . number_format($axisValue, 0) . '
        </text>';
    }

    $labelStep = max(1, (int) ceil($valueCount / 7));

    foreach ($values as $index => $value) {
        if (
            $index % $labelStep !== 0
            && $index !== $valueCount - 1
        ) {
            continue;
        }

        $x = $paddingLeft + (
            ($index / $xDivisor) * $chartWidth
        );

        $label = $labels[$index] ?? ($index + 1);

        $svg .= '<line
            x1="' . round($x, 2) . '"
            y1="' . $paddingTop . '"
            x2="' . round($x, 2) . '"
            y2="' . ($height - $paddingBottom) . '"
            class="chart-vertical-grid-line"
        />';

        $svg .= '<text
            x="' . round($x, 2) . '"
            y="' . ($height - 22) . '"
            text-anchor="middle"
            class="chart-x-label"
        >
            ' . htmlspecialchars((string) $label) . '
        </text>';
    }

    $svg .= '<line
        x1="' . $paddingLeft . '"
        y1="' . $paddingTop . '"
        x2="' . $paddingLeft . '"
        y2="' . ($height - $paddingBottom) . '"
        class="chart-axis-line"
    />';

    $svg .= '<line
        x1="' . $paddingLeft . '"
        y1="' . ($height - $paddingBottom) . '"
        x2="' . ($width - $paddingRight) . '"
        y2="' . ($height - $paddingBottom) . '"
        class="chart-axis-line"
    />';

    $pointText = [];

    foreach ($points as $point) {
        $pointText[] = $point["x"] . "," . $point["y"];
    }

    if (count($points) > 1) {
        $svg .= '<polyline
            fill="none"
            stroke="#2563eb"
            stroke-width="3"
            stroke-linejoin="round"
            stroke-linecap="round"
            points="' . implode(" ", $pointText) . '"
        />';
    }

    foreach ($points as $point) {
        $svg .= '<circle
            cx="' . $point["x"] . '"
            cy="' . $point["y"] . '"
            r="4"
            fill="#2563eb"
            class="chart-point"
        >
            <title>'
                . htmlspecialchars($point["label"])
                . ': ₹'
                . number_format($point["value"], 2)
                . '
            </title>
        </circle>';
    }

    $svg .= '</svg>';
    $svg .= '</div>';

    return $svg;
}



