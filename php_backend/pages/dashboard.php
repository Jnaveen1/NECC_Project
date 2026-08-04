<?php
require_once __DIR__ . "/../includes/functions.php";

$locations = getLocations($conn);
$location = $_GET["location"] ?? ($locations[0] ?? "");
$compareLocation = $_GET["compare_location"] ?? $location;
$comparisonView = $_GET["comparison_view"] ?? "months";
$startDate = $_GET["start_date"] ?? date("Y-m-01");
$endDate = $_GET["end_date"] ?? date("Y-m-d");

if (!in_array($compareLocation, $locations, true)) {
    $compareLocation = $location;
}

$summary = $location !== "" ? getPriceSummary($conn, $location, $startDate, $endDate) : null;
$dailyRows = $location !== "" ? getDailyPrices($conn, $location, $startDate, $endDate) : [];
$trendValues = [];
foreach ($dailyRows as $row) {
    $trendValues[$row["price_date"]] = (float) $row["price"];
}

$comparison = $compareLocation !== "" ? getComparisonData($conn, $compareLocation, $comparisonView) : [
    "years" => [2026, 2025, 2024],
    "labels" => ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    "series" => []
];
$comparisonChart = buildMultiLineChart($comparison["series"], $comparison["labels"]);
$trendChart = buildTrendChartSvg(
    array_values($trendValues),
    array_map(
        function ($date) {
            return date("d M", strtotime($date));
        },
        array_keys($trendValues)
    )
);
$previousPrice = null;
if (count($dailyRows) > 1) {
    $previousPrice = (float) $dailyRows[count($dailyRows) - 2]["price"];
}
$currentPrice = $summary["current_price"] ?? null;
$changeValue = $previousPrice !== null && $currentPrice !== null ? $currentPrice - $previousPrice : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard | NECC PHP Frontend</title>
    <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <div class="brand-badge">NECC</div>
                <div>
                    <h2>Dashboard</h2>
                    <small>Egg Price Monitor</small>
                </div>
            </div>

            <nav class="nav-menu">
                <a class="nav-link active" href="/dashboard">Dashboard</a>
                <a class="nav-link" href="/daily">Daily Prices</a>
                <a class="nav-link" href="/monthly">Monthly Report</a>
                <!-- <a class="nav-link" href="/analysis">Custom Analysis</a> -->
            </nav>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Market Dashboard</p>
                    <h1>NECC egg price performance at a glance</h1>
                </div>
                <!-- <button class="primary-btn" type="button">Refresh</button> -->
            </header>

            <section class="panel comparison-panel">
                <div class="panel-head">
                    <div>
                        <h2>Last 3 years comparison</h2>
                        <p>Compare <?= htmlspecialchars($compareLocation ?: 'the selected location') ?> across the latest three years</p>
                    </div>
                    <div class="comparison-tools">
                        <label class="comparison-select-wrap">
                            <span>View</span>
                            <select class="comparison-select" name="comparison_view" form="comparison-form" onchange="this.form.submit()">
                                <option value="months" <?= $comparisonView === "months" ? "selected" : "" ?>>Months</option>
                                <option value="days" <?= $comparisonView === "days" ? "selected" : "" ?>>Days</option>
                            </select>
                        </label>
                        <label class="comparison-select-wrap">
                            <span>Location</span>
                            <select class="comparison-select" name="compare_location" form="comparison-form" onchange="this.form.submit()">
                                <?php foreach ($locations as $item): ?>
                                    <option value="<?= htmlspecialchars($item) ?>" <?= $item === $compareLocation ? "selected" : "" ?>>
                                        <?= htmlspecialchars($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                <form id="comparison-form" method="GET" action="/dashboard" class="comparison-form">
                    <input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>" />
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>" />
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>" />
                </form>
                <?= $comparisonChart ?>
            </section>

            <section class="filters-panel">
                <form method="GET" action="/dashboard" class="filters-grid">
                    <label>
                        <span>Location</span>
                        <select name="location">
                            <?php foreach ($locations as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $item === $location ? "selected" : "" ?>>
                                    <?= htmlspecialchars($item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Start date</span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" />
                    </label>

                    <label>
                        <span>End date</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" />
                    </label>

                    <button type="submit" class="primary-btn">Apply</button>
                </form>
            </section>

            <?php if ($summary): ?>
                <section class="summary-grid">
                    <article class="summary-card">
                        <div class="summary-head">
                            <span>Current price</span>
                            <span class="icon-badge">₹</span>
                        </div>
                        <strong><?= formatCurrency($summary["current_price"]) ?></strong>
                        <div class="summary-foot">
                            <span class="trend <?= $changeValue >= 0 ? 'up' : 'down' ?>">
                                <?= $changeValue >= 0 ? '▲' : '▼' ?> <?= formatCurrency(abs($changeValue)) ?>
                            </span>
                            <small>As of <?= htmlspecialchars(formatDateForDisplay($summary["current_date"])) ?></small>
                        </div>
                    </article>

                    <article class="summary-card">
                        <div class="summary-head">
                            <span>Average price</span>
                            <span class="icon-badge">Avg</span>
                        </div>
                        <strong><?= formatCurrency($summary["average_price"]) ?></strong>
                        <div class="summary-foot">
                            <span class="muted">Range: <?= htmlspecialchars(formatDateForDisplay($startDate)) ?> to <?= htmlspecialchars(formatDateForDisplay($endDate)) ?></span>
                        </div>
                    </article>

                    <article class="summary-card">
                        <div class="summary-head">
                            <span>Minimum price</span>
                            <span class="icon-badge">Low</span>
                        </div>
                        <strong><?= formatCurrency($summary["minimum_price"]) ?></strong>
                        <div class="summary-foot">
                            <small>Lowest recorded</small>
                        </div>
                    </article>

                    <article class="summary-card">
                        <div class="summary-head">
                            <span>Maximum price</span>
                            <span class="icon-badge">High</span>
                        </div>
                        <strong><?= formatCurrency($summary["maximum_price"]) ?></strong>
                        <div class="summary-foot">
                            <small>Highest recorded</small>
                        </div>
                    </article>

                    <article class="summary-card">
                        <div class="summary-head">
                            <span>Records</span>
                            <span class="icon-badge">#</span>
                        </div>
                        <strong><?= (int) $summary["total_records"] ?></strong>
                        <div class="summary-foot">
                            <small>Data points</small>
                        </div>
                    </article>
                </section>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Price trend</h2>
                        <p>Daily NECC price movement for <?= htmlspecialchars($location) ?></p>
                    </div>
                    <span class="range-chip"><?= htmlspecialchars($startDate) ?> → <?= htmlspecialchars($endDate) ?></span>
                </div>
                <?= $trendChart ?>
            </section>
        </main>
    </div>
</body>
</html>
