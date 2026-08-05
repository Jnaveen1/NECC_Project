<?php

require_once __DIR__ . "/../includes/functions.php";

$availableYears = getAvailablePriceYears($conn);

$latestYear = $availableYears[0]
    ?? (int) date("Y");

$month = isset($_GET["month"])
    ? (int) $_GET["month"]
    : (int) date("n");

$year = isset($_GET["year"])
    ? (int) $_GET["year"]
    : $latestYear;

$view = $_GET["view"] ?? "daily";

if ($month < 1 || $month > 12) {
    $month = 1;
}

if (!in_array($view, ["daily", "monthly"], true)) {
    $view = "daily";
}

$monthNames = [
    1 => "January",
    2 => "February",
    3 => "March",
    4 => "April",
    5 => "May",
    6 => "June",
    7 => "July",
    8 => "August",
    9 => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];

$dailyMatrix = [];
$monthlyMatrix = [];

if ($view === "monthly") {
    $monthlyMatrix = getMonthlyPriceMatrix(
        $conn,
        $year
    );
} else {
    $dailyMatrix = getDailyPriceMatrix(
        $conn,
        $month,
        $year
    );
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

    <title>Daily Prices | NECC</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
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
            <a class="nav-link" href="/dashboard">
                Dashboard
            </a>

            <a class="nav-link active" href="/daily">
                Daily Prices
            </a>

            <a class="nav-link" href="/monthly">
                Monthly Report
            </a>

            <!-- <a class="nav-link" href="/analysis">
                Custom Analysis
            </a> -->
        </nav>

    </aside>

    <main class="main-panel">

        <header class="topbar daily-page-topbar">

            <div>
                <p class="eyebrow">Daily prices</p>
                <h1>Explore day-wise rates and changes</h1>
            </div>

            <span class="live-api-badge">
                <span class="live-dot"></span>
                Live API
            </span>

        </header>

        <section class="daily-filter-panel">

            <form
                method="GET"
                action="/daily#daily-records"
                class="daily-filter-form"
            >

                <label class="daily-filter-field">

                    <span>Month</span>

                    <select name="month"  onchange="this.form.submit()">

                        <?php foreach ($monthNames as $number => $name): ?>

                            <option
                                value="<?= $number ?>"
                                <?= $number === $month
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= htmlspecialchars($name) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <label class="daily-filter-field">

                    <span>Year</span>

                    <select name="year" onchange="this.form.submit()">

                        <?php foreach ($availableYears as $priceYear): ?>

                            <option
                                value="<?= $priceYear ?>"
                                <?= $priceYear === $year
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $priceYear ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <fieldset class="view-field">

                    <legend>View</legend>

                    <div class="view-toggle">

                        <label
                            class="view-option <?= $view === "daily"
                                ? "active"
                                : "" ?>"
                        >
                            <input
                                type="radio"
                                name="view"
                                value="daily"
                                onchange="this.form.submit()"
                                <?= $view === "daily"
                                    ? "checked"
                                    : "" ?>
                            >

                            <span>Daily</span>
                        </label>

                        <label
                            class="view-option <?= $view === "monthly"
                                ? "active"
                                : "" ?>"
                        >
                            <input
                                type="radio"
                                name="view"
                                value="monthly"
                                onchange="this.form.submit()"
                                <?= $view === "monthly"
                                    ? "checked"
                                    : "" ?>
                            >

                            <span>Monthly</span>
                        </label>

                    </div>

                </fieldset>

            </form>

        </section>

        <section
            class="panel daily-record-panel"
            id="daily-records"
        >

            <div class="panel-head">

                <div>
                    <h2>
                        <?= $view === "monthly"
                            ? "Monthly price records"
                            : "Daily price records" ?>
                    </h2>

                    <p>
                        <?= $view === "monthly"
                            ? "Review all locations across the selected year"
                            : "Review all locations across the selected period" ?>
                    </p>
                </div>

                <span class="period-badge">

                    <?php if ($view === "monthly"): ?>

                        <?= $year ?>

                    <?php else: ?>

                        <?= htmlspecialchars($monthNames[$month]) ?>
                        <?= $year ?>

                    <?php endif; ?>

                </span>

            </div>

            <?php if ($view === "monthly"): ?>

                <?php
                $monthlyLocations =
                    $monthlyMatrix["locations"] ?? [];

                $monthlyColumns =
                    $monthlyMatrix["months"] ?? [];
                ?>

                <?php if (!empty($monthlyLocations)): ?>

                    <div class="price-matrix-shell">

                        <table class="price-matrix-table">

                            <thead>
                                <tr>
                                    <th class="sticky-location-column">
                                        Location
                                    </th>

                                    <?php foreach ($monthlyColumns as $monthName): ?>

                                        <th>
                                            <?= htmlspecialchars($monthName) ?>
                                            <?= $year ?>
                                        </th>

                                    <?php endforeach; ?>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($monthlyLocations as $locationName => $prices): ?>

                                    <tr>

                                        <th class="sticky-location-column location-name-cell">
                                            <?= htmlspecialchars($locationName) ?>
                                        </th>

                                        <?php
                                        $previousPrice = null;
                                        ?>

                                        <?php foreach ($monthlyColumns as $monthNumber => $monthName): ?>

                                            <?php
                                            $currentPrice =
                                                isset($prices[$monthNumber])
                                                    ? (float) $prices[$monthNumber]
                                                    : null;

                                            $movement = getPriceMovement(
                                                $currentPrice,
                                                $previousPrice
                                            );
                                            ?>

                                            <td>

                                                <?php if ($currentPrice !== null): ?>

                                                    <span class="matrix-price">
                                                        <?= formatCurrency(
                                                            $currentPrice
                                                        ) ?>
                                                    </span>

                                                    <?php if ($movement["symbol"] !== ""): ?>

                                                        <span
                                                            class="price-movement <?= $movement["class"] ?>"
                                                        >
                                                            <?= $movement["symbol"] ?>
                                                        </span>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <span class="missing-value">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <?php
                                            if ($currentPrice !== null) {
                                                $previousPrice =
                                                    $currentPrice;
                                            }
                                            ?>

                                        <?php endforeach; ?>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="message">
                        No monthly price data found for the selected year.
                    </p>

                <?php endif; ?>

            <?php else: ?>

                <?php
                $dailyLocations =
                    $dailyMatrix["locations"] ?? [];

                $dailyDates =
                    $dailyMatrix["dates"] ?? [];
                ?>

                <?php if (!empty($dailyLocations)): ?>

                    <div class="price-matrix-shell">

                        <table class="price-matrix-table">

                            <thead>
                                <tr>

                                    <th class="sticky-location-column">
                                        Location
                                    </th>

                                    <?php foreach ($dailyDates as $date): ?>

                                        <th>
                                            <?= htmlspecialchars(
                                                date(
                                                    "d M y",
                                                    strtotime($date)
                                                )
                                            ) ?>
                                        </th>

                                    <?php endforeach; ?>

                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($dailyLocations as $locationName => $prices): ?>

                                    <tr>

                                        <th class="sticky-location-column location-name-cell">
                                            <?= htmlspecialchars($locationName) ?>
                                        </th>

                                        <?php
                                        $previousPrice = null;
                                        ?>

                                        <?php foreach ($dailyDates as $date): ?>

                                            <?php
                                            $currentPrice =
                                                isset($prices[$date])
                                                    ? (float) $prices[$date]
                                                    : null;

                                            $movement = getPriceMovement(
                                                $currentPrice,
                                                $previousPrice
                                            );
                                            ?>

                                            <td>

                                                <?php if ($currentPrice !== null): ?>

                                                    <span class="matrix-price">
                                                        <?= formatCurrency(
                                                            $currentPrice
                                                        ) ?>
                                                    </span>

                                                    <?php if ($movement["symbol"] !== ""): ?>

                                                        <span
                                                            class="price-movement <?= $movement["class"] ?>"
                                                        >
                                                            <?= $movement["symbol"] ?>
                                                        </span>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <span class="missing-value">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <?php
                                            if ($currentPrice !== null) {
                                                $previousPrice =
                                                    $currentPrice;
                                            }
                                            ?>

                                        <?php endforeach; ?>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="message">
                        No daily price data found for the selected month.
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </section>

    </main>

</div>
    <script>
    window.addEventListener("beforeunload", function () {
        sessionStorage.setItem(
            "scrollPosition",
            window.scrollY
        );
    });

    window.addEventListener("load", function () {
        const y = sessionStorage.getItem("scrollPosition");

        if (y !== null) {
            window.scrollTo(0, parseInt(y));
            sessionStorage.removeItem("scrollPosition");
        }
    });
    </script>

</body>
</html>