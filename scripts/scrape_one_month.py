import os
import sys

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.crud import save_egg_prices
from backend.scraper import scrape_month_prices


def run():
    month = 7
    year = 2026

    print(f"Scraping {month}/{year}...")

    records = scrape_month_prices(
        month=month,
        year=year,
    )

    print("Scraped records:", len(records))

    affected_rows = save_egg_prices(records)

    print("Database rows affected:", affected_rows)
    print("Monthly data saved successfully")


if __name__ == "__main__":
    run()