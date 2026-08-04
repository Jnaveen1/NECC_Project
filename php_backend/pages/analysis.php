<!-- <?php

require_once __DIR__ . "/../includes/functions.php";

$locations = getLocations($conn);

$location = $_GET["location"]
    ?? ($locations[0] ?? "");

$latestDateSql = "
    SELECT MAX(price_date)
    FROM egg_prices
";

$latestDate = $conn
    ->query($latestDateSql)
    ->fetchColumn();

if (!$latestDate) {
    $latestDate = date("Y-m-d");
}

$defaultStartDate = date(
    "Y-m-01",
    strtotime($latestDate)
);

$startDate = $_GET["start_date"]
    ?? $defaultStartDate;

$endDate = $_GET["end_date"]
    ?? $latestDate;

$errorMessage = null;

if ($startDate > $endDate) {
    $errorMessage =
        "Start date cannot be greater than end date.";

    $startDate = $defaultStartDate;
    $endDate = $latestDate;
}

$records = [];

if ($location !== "") {
    $records = getDailyPrices(
        $conn,
        $location,
        $startDate,
        $endDate
    );
}

/*
|--------------------------------------------------------------------------
| Calculate custom-period statistics
|--------------------------------------------------------------------------
*/

$prices = array_map(
    fn ($record) => (float) $record["price"],
    $records
);

$periodAverage = null;
$netChange = null;
$percentageChange = null;
$volatilityRange = null;
$minimumPrice = null;
$maximumPrice = null;

if (!empty($prices)) {
    $periodAverage = array_sum($prices) / count($prices);

    $firstPrice = $prices[0];
    $lastPrice = $prices[count($prices) - 1];

    $netChange = $lastPrice - $firstPrice;

    $percentageChange = $firstPrice != 0
        ? ($netChange / $firstPrice) * 100
        : null;

    $minimumPrice = min($prices);
    $maximumPrice = max($prices);

    $volatilityRange =
        $maximumPrice - $minimumPrice;
}

/*
|--------------------------------------------------------------------------
| Build chart values and labels
|--------------------------------------------------------------------------
*/

$chartValues = [];
$chartLabels = [];

foreach ($records as $record) {
    $chartValues[] = (float) $record["price"];

    $chartLabels[] = date(
        "d M",
        strtotime($record["price_date"])
    );
}

$analysisChart = buildTrendChartSvg(
    $chartValues,
    $chartLabels
);

$changeClass = "analysis-neutral";
$changeSymbol = "";

if ($netChange !== null) {
    if ($netChange > 0) {
        $changeClass = "analysis-positive";
        $changeSymbol = "▲";
    } elseif ($netChange < 0) {
        $changeClass = "analysis-negative";
        $changeSymbol = "▼";
    } else {
        $changeClass = "analysis-neutral";
        $changeSymbol = "•";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Custom Analysis | NECC</title>

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
                class="nav-link"
                href="/monthly"
            >
                Monthly Report
            </a>

            <a
                class="nav-link active"
                href="/analysis"
            >
                Custom Analysis
            </a>

        </nav>

    </aside>

    <main class="main-panel">

        <header class="topbar analysis-page-topbar">

            <div>
                <p class="eyebrow">
                    Custom analysis
                </p>

                <h1>
                    Analyze any location and date range
                </h1>
            </div>

            <span class="live-api-badge">
                <span class="live-dot"></span>
                Live API
            </span>

        </header>

        <?php if ($errorMessage): ?>

            <div class="analysis-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>

        <?php endif; ?>

        <section class="analysis-filter-panel">

            <form
                method="GET"
                action="/analysis#analysis-results"
                class="analysis-filter-form"
            >

                <label class="analysis-filter-field">

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

                <label class="analysis-filter-field">

                    <span>Start date</span>

                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($startDate) ?>"
                        max="<?= htmlspecialchars($latestDate) ?>"
                    >

                </label>

                <label class="analysis-filter-field">

                    <span>End date</span>

                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($endDate) ?>"
                        max="<?= htmlspecialchars($latestDate) ?>"
                    >

                </label>

                <button
                    type="submit"
                    class="primary-btn analysis-apply-button"
                >
                    Apply
                </button>

            </form>

        </section>

        <div id="analysis-results">

            <?php if (!empty($records)): ?>

                <section class="analysis-summary-grid">

                    <article class="analysis-summary-card">

                        <span class="analysis-card-label">
                            Period average
                        </span>

                        <strong class="analysis-card-value">
                            <?= formatCurrency($periodAverage) ?>
                        </strong>

                        <small>
                            Mean daily price
                        </small>

                    </article>

                    <article class="analysis-summary-card">

                        <span class="analysis-card-label">
                            Net change
                        </span>

                        <strong
                            class="analysis-card-value <?= $changeClass ?>"
                        >
                            <?= $changeSymbol ?>

                            <?= formatCurrency($netChange) ?>
                        </strong>

                        <small class="<?= $changeClass ?>">

                            <?php if ($percentageChange !== null): ?>

                                <?= number_format(
                                    $percentageChange,
                                    2
                                ) ?>%

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </small>

                    </article>

                    <article class="analysis-summary-card">

                        <span class="analysis-card-label">
                            Volatility range
                        </span>

                        <strong class="analysis-card-value">
                            <?= formatCurrency($volatilityRange) ?>
                        </strong>

                        <small>
                            Maximum minus minimum
                        </small>

                    </article>

                </section>

                <section class="panel analysis-chart-panel">

                    <div class="panel-head">

                        <div>
                            <h2>
                                Custom period analysis
                            </h2>

                            <p>
                                Calculated from daily records in the
                                selected range
                            </p>
                        </div>

                        <span class="period-badge">
                            <?= htmlspecialchars($location) ?>
                        </span>

                    </div>

                    <?= $analysisChart ?>

                </section>

                <section class="panel analysis-details-panel">

                    <div class="panel-head">

                        <div>
                            <h2>Period details</h2>

                            <p>
                                Daily price values used in the analysis
                            </p>
                        </div>

                        <span class="period-badge">
                            <?= date(
                                "d M Y",
                                strtotime($startDate)
                            ) ?>

                            →

                            <?= date(
                                "d M Y",
                                strtotime($endDate)
                            ) ?>
                        </span>

                    </div>

                    <div class="analysis-day-grid">

                        <?php
                        $previousPrice = null;
                        ?>

                        <?php foreach ($records as $record): ?>

                            <?php
                            $currentPrice =
                                (float) $record["price"];

                            $movement = getPriceMovement(
                                $currentPrice,
                                $previousPrice
                            );
                            ?>

                            <article class="analysis-day-card">

                                <span class="analysis-day-date">
                                    <?= date(
                                        "d M",
                                        strtotime(
                                            $record["price_date"]
                                        )
                                    ) ?>
                                </span>

                                <strong>
                                    <?= formatCurrency(
                                        $currentPrice
                                    ) ?>
                                </strong>

                                <?php if (
                                    $movement["symbol"] !== ""
                                ): ?>

                                    <span
                                        class="analysis-day-movement <?= $movement["class"] ?>"
                                    >
                                        <?= $movement["symbol"] ?>
                                    </span>

                                <?php endif; ?>

                            </article>

                            <?php
                            $previousPrice = $currentPrice;
                            ?>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php else: ?>

                <section class="panel">

                    <p class="message">
                        No price data found for the selected location
                        and date range.
                    </p>

                </section>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html> -->