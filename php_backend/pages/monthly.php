<?php

require_once __DIR__ . "/../includes/functions.php";

$locations = getLocations($conn);

$location = $_GET["location"]
    ?? ($locations[0] ?? "");

$availableYears = getAvailablePriceYears($conn);

$year = isset($_GET["year"])
    ? (int) $_GET["year"]
    : ($availableYears[0] ?? (int) date("Y"));

$report = $location !== ""
    ? getMonthlyReportData($conn, $location, $year)
    : [
        "year" => $year,
        "average" => null,
        "monthly" => []
    ];

$monthOrder = [
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

$fullMonthlyData = [];

foreach ($monthOrder as $monthName) {
    $fullMonthlyData[$monthName] = null;
}

foreach ($report["monthly"] ?? [] as $item) {
    $monthName = $item["month"];

    if (array_key_exists($monthName, $fullMonthlyData)) {
        $fullMonthlyData[$monthName] = (float) $item["value"];
    }
}

/*
|--------------------------------------------------------------------------
| Build monthly bar chart
|--------------------------------------------------------------------------
*/

function buildMonthlyBarChart(
    array $monthlyData,
    int $width = 1000,
    int $height = 390
): string {
    $numericValues = array_values(
        array_filter(
            $monthlyData,
            fn ($value) =>
                $value !== null &&
                $value !== "" &&
                is_numeric($value)
        )
    );

    if (empty($numericValues)) {
        return '<div class="empty-chart">
            No monthly average data available.
        </div>';
    }

    $numericValues = array_map(
        "floatval",
        $numericValues
    );

    $paddingLeft = 72;
    $paddingRight = 25;
    $paddingTop = 30;
    $paddingBottom = 62;

    $chartWidth =
        $width - $paddingLeft - $paddingRight;

    $chartHeight =
        $height - $paddingTop - $paddingBottom;

    $actualMaximum = max($numericValues);

    /*
     * Use rounded ₹200 steps, similar to the React chart.
     */
    $axisMaximum = max(
        800,
        (int) ceil($actualMaximum / 200) * 200
    );

    $horizontalSteps = $axisMaximum / 200;

    $monthCount = count($monthlyData);

    $columnWidth = $chartWidth / $monthCount;
    $barWidth = min(48, $columnWidth * 0.55);

    $svg = '
        <div class="monthly-chart-shell">
            <svg
                viewBox="0 0 ' . $width . ' ' . $height . '"
                class="monthly-chart-svg"
                role="img"
                aria-label="Official monthly average price chart"
            >
    ';

    /*
     * Horizontal grid and Y-axis labels.
     */
    for ($step = 0; $step <= $horizontalSteps; $step++) {
        $axisValue = $step * 200;

        $ratio = $axisValue / $axisMaximum;

        $y = $paddingTop
            + $chartHeight
            - ($ratio * $chartHeight);

        $svg .= '
            <line
                x1="' . $paddingLeft . '"
                y1="' . round($y, 2) . '"
                x2="' . ($width - $paddingRight) . '"
                y2="' . round($y, 2) . '"
                class="monthly-grid-line"
            />

            <text
                x="' . ($paddingLeft - 12) . '"
                y="' . round($y + 4, 2) . '"
                text-anchor="end"
                class="monthly-axis-label"
            >
                ₹' . number_format($axisValue, 0) . '
            </text>
        ';
    }

    /*
     * Y-axis and X-axis.
     */
    $svg .= '
        <line
            x1="' . $paddingLeft . '"
            y1="' . $paddingTop . '"
            x2="' . $paddingLeft . '"
            y2="' . ($height - $paddingBottom) . '"
            class="monthly-axis-line"
        />

        <line
            x1="' . $paddingLeft . '"
            y1="' . ($height - $paddingBottom) . '"
            x2="' . ($width - $paddingRight) . '"
            y2="' . ($height - $paddingBottom) . '"
            class="monthly-axis-line"
        />
    ';

    foreach (
        array_values(array_keys($monthlyData))
        as $index => $month
    ) {
        $value = $monthlyData[$month];

        $centerX = $paddingLeft
            + ($index * $columnWidth)
            + ($columnWidth / 2);

        $barX = $centerX - ($barWidth / 2);

        /*
         * Month label.
         */
        $svg .= '
            <text
                x="' . round($centerX, 2) . '"
                y="' . ($height - 25) . '"
                text-anchor="middle"
                class="monthly-month-label"
            >
                ' . htmlspecialchars($month) . '
            </text>
        ';

        if (
            $value === null ||
            $value === "" ||
            !is_numeric($value)
        ) {
            /*
             * Empty placeholder for months with no data.
             */
            $svg .= '
                <line
                    x1="' . round($barX, 2) . '"
                    y1="' . ($height - $paddingBottom - 2) . '"
                    x2="' . round($barX + $barWidth, 2) . '"
                    y2="' . ($height - $paddingBottom - 2) . '"
                    class="monthly-empty-bar"
                >
                    <title>
                        ' . htmlspecialchars($month) . ': No data
                    </title>
                </line>
            ';

            continue;
        }

        $numericValue = (float) $value;

        $barHeight =
            ($numericValue / $axisMaximum) * $chartHeight;

        $barY =
            $paddingTop + $chartHeight - $barHeight;

        $svg .= '
            <rect
                x="' . round($barX, 2) . '"
                y="' . round($barY, 2) . '"
                width="' . round($barWidth, 2) . '"
                height="' . round($barHeight, 2) . '"
                rx="6"
                class="monthly-bar"
            >
                <title>
                    ' . htmlspecialchars($month) . ':
                    ₹' . number_format($numericValue, 2) . '
                </title>
            </rect>
        ';
    }

    $svg .= '
            </svg>
        </div>
    ';

    return $svg;
}

$monthlyChart = buildMonthlyBarChart(
    $fullMonthlyData
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Monthly Report | NECC</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
</head>

<body>

<div class="app-shell">

    <aside class="sidebar">

        <div class="brand-block">

            <div class="brand-badge">
                NECC
            </div>

            <div>
                <h2>Dashboard</h2>
                <small>Egg Price Monitor</small>
            </div>

        </div>

        <nav class="nav-menu">

            <a
                class="nav-link"
                href="/dashboard"
            >
                Dashboard
            </a>

            <a
                class="nav-link"
                href="/daily"
            >
                Daily Prices
            </a>

            <a
                class="nav-link active"
                href="/monthly"
            >
                Monthly Report
            </a>

            <!-- <a
                class="nav-link"
                href="/analysis"
            >
                Custom Analysis
            </a> -->

        </nav>

    </aside>

    <main class="main-panel">

        <header class="topbar monthly-page-topbar">

            <div>
                <p class="eyebrow">
                    Monthly report
                </p>

                <h1>
                    Official monthly and yearly averages
                </h1>
            </div>

            <span class="live-api-badge">
                <span class="live-dot"></span>
                Live API
            </span>

        </header>

        <section class="monthly-filter-panel">

            <form
                method="GET"
                action="/monthly"
                class="monthly-filter-form"
            >

                <label class="monthly-filter-field">

                    <span>Location</span>

                    <select name="location">

                        <?php foreach ($locations as $item): ?>

                            <option
                                value="<?= htmlspecialchars($item) ?>"
                                <?= $item === $location
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= htmlspecialchars($item) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <label class="monthly-filter-field">

                    <span>Year</span>

                    <select name="year">

                        <?php foreach ($availableYears as $availableYear): ?>

                            <option
                                value="<?= $availableYear ?>"
                                <?= $availableYear === $year
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $availableYear ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <button
                    type="submit"
                    class="primary-btn monthly-apply-button"
                >
                    Apply
                </button>

            </form>

        </section>

        <section class="monthly-year-average-card">

            <span>Year average</span>

            <strong>
                <?= formatCurrency(
                    $report["average"] ?? null
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars($location) ?>
                ·
                <?= $year ?>
            </small>

        </section>

        <section class="panel monthly-chart-panel">

            <div class="panel-head">

                <div>
                    <h2>
                        Official monthly averages
                    </h2>

                    <p>
                        Monthly Average Sheet values for
                        <?= htmlspecialchars($location) ?>
                    </p>
                </div>

                <span class="period-badge">
                    <?= $year ?>
                </span>

            </div>

            <?= $monthlyChart ?>

        </section>

        <section class="panel monthly-values-panel">

            <div class="panel-head">

                <div>
                    <h2>Monthly values</h2>

                    <p>
                        Official average by month
                    </p>
                </div>

            </div>

            <?php if (!empty($report["monthly"])): ?>

                <div class="monthly-value-list">

                    <?php foreach ($fullMonthlyData as $month => $value): ?>

                        <article class="monthly-value-row">

                            <div class="monthly-value-name">

                                <span class="monthly-value-dot"></span>

                                <strong>
                                    <?php
                                    $fullMonthNames = [
                                        "Jan" => "January",
                                        "Feb" => "February",
                                        "Mar" => "March",
                                        "Apr" => "April",
                                        "May" => "May",
                                        "Jun" => "June",
                                        "Jul" => "July",
                                        "Aug" => "August",
                                        "Sep" => "September",
                                        "Oct" => "October",
                                        "Nov" => "November",
                                        "Dec" => "December"
                                    ];

                                    echo htmlspecialchars(
                                        $fullMonthNames[$month]
                                    );
                                    ?>
                                </strong>

                            </div>

                            <span class="monthly-value-price">
                                <?= formatCurrency($value) ?>
                            </span>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="message">
                    No monthly report data is available for
                    the selected location and year.
                </p>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>