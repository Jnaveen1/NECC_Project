<?php

require_once __DIR__ . "/../config/database.php";

try {
    $eggPricesTable = "
        CREATE TABLE IF NOT EXISTS egg_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(150) NOT NULL,
            price_date DATE NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            source VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY unique_location_date (
                location,
                price_date
            )
        )
    ";

    $monthlyRatesTable = "
        CREATE TABLE IF NOT EXISTS monthly_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(150) NOT NULL,
            year INT NOT NULL,

            jan DECIMAL(10, 2) NULL,
            feb DECIMAL(10, 2) NULL,
            mar DECIMAL(10, 2) NULL,
            apr DECIMAL(10, 2) NULL,
            may DECIMAL(10, 2) NULL,
            jun DECIMAL(10, 2) NULL,
            jul DECIMAL(10, 2) NULL,
            aug DECIMAL(10, 2) NULL,
            sep DECIMAL(10, 2) NULL,
            oct DECIMAL(10, 2) NULL,
            nov DECIMAL(10, 2) NULL,
            `dec` DECIMAL(10, 2) NULL,

            year_average DECIMAL(10, 2) NULL,
            source VARCHAR(255) NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY unique_location_year (
                location,
                year
            )
        )
    ";

    $conn->exec($eggPricesTable);
    $conn->exec($monthlyRatesTable);

    echo "Tables created successfully\n";
} catch (PDOException $exception) {
    echo "Table creation failed: ";
    echo $exception->getMessage() . "\n";

    exit(1);
}