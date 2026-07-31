import argparse
import os
import sys
from datetime import date

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.crud import save_egg_prices
from backend.scraper import scrape_month_prices


def run(month: int | None = None, year: int | None = None):
    today = date.today()
    month = month or today.month
    year = year or today.year

    print(f"Scraping {month}/{year}...")

    records = scrape_month_prices(
        month=month,
        year=year,
    )

    print("Scraped records:", len(records))

    affected_rows = save_egg_prices(records)

    print("Database rows affected:", affected_rows)
    print("Latest daily data saved successfully")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Scrape one NECC report month and save it to the database.")
    parser.add_argument("--month", type=int, help="Month number (defaults to current month)")
    parser.add_argument("--year", type=int, help="Year (defaults to current year)")
    args = parser.parse_args()
    run(month=args.month, year=args.year)
