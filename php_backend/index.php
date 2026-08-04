<?php

require_once __DIR__ . "/includes/functions.php";

$locations = getLocations($conn);

$location = $_GET["location"] ?? ($locations[0] ?? "");

$startDate = $_GET["start_date"]
    ?? date("Y-m-01");

$endDate = $_GET["end_date"]
    ?? date("Y-m-d");

$summary = null;

if ($location !== "") {
    $summary = getPriceSummary(
        $conn,
        $location,
        $startDate,
        $endDate
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

    <title>NECC Egg Price Dashboard</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
</head>

<body>

<header class="header">
    <h1>NECC Egg Price Dashboard</h1>

    <p>National Egg Co-ordination Committee</p>
</header>

<main class="container">

    <form method="GET" class="filters">

        <div class="form-group">
            <label for="location">Location</label>

            <select name="location" id="location">

                <?php foreach ($locations as $item): ?>

                    <option
                        value="<?= htmlspecialchars($item) ?>"
                        <?= $item === $location ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($item) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <div class="form-group">
            <label for="start_date">Start Date</label>

            <input
                type="date"
                name="start_date"
                id="start_date"
                value="<?= htmlspecialchars($startDate) ?>"
            >
        </div>

        <div class="form-group">
            <label for="end_date">End Date</label>

            <input
                type="date"
                name="end_date"
                id="end_date"
                value="<?= htmlspecialchars($endDate) ?>"
            >
        </div>

        <button type="submit">
            Apply
        </button>

    </form>

    <?php if ($summary): ?>

        <section class="cards">

            <article class="card">
                <h3>Current Price</h3>

                <p>
                    ₹<?= number_format(
                        (float) $summary["current_price"],
                        2
                    ) ?>
                </p>

                <small>
                    <?= htmlspecialchars(
                        $summary["current_date"]
                    ) ?>
                </small>
            </article>

            <article class="card">
                <h3>Average Price</h3>

                <p>
                    ₹<?= number_format(
                        (float) $summary["average_price"],
                        2
                    ) ?>
                </p>
            </article>

            <article class="card">
                <h3>Minimum Price</h3>

                <p>
                    ₹<?= number_format(
                        (float) $summary["minimum_price"],
                        2
                    ) ?>
                </p>
            </article>

            <article class="card">
                <h3>Maximum Price</h3>

                <p>
                    ₹<?= number_format(
                        (float) $summary["maximum_price"],
                        2
                    ) ?>
                </p>
            </article>

        </section>

    <?php else: ?>

        <p class="message">
            No data found for the selected filters.
        </p>

    <?php endif; ?>

</main>

</body>
</html>