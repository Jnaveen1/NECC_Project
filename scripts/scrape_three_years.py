import os
import sys
from datetime import date

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.crud import save_egg_prices
from backend.scraper import scrape_month_prices


def get_months_for_last_three_years():
    today = date.today()

    start_year = today.year - 3
    start_month = today.month

    year = start_year
    month = start_month

    while (year, month) <= (today.year, today.month):
        yield month, year

        month += 1

        if month == 13:
            month = 1
            year += 1


def run():
    total_scraped = 0

    for month, year in get_months_for_last_three_years():
        print(f"\nScraping {month:02d}/{year}...")

        try:
            records = scrape_month_prices(
                month=month,
                year=year,
            )

            print("Scraped records:", len(records))

            affected_rows = save_egg_prices(records)

            print("Database rows affected:", affected_rows)

            total_scraped += len(records)

        except Exception as error:
            print(f"Failed for {month:02d}/{year}")
            print("Error:", error)

    print("\nThree-year scraping completed")
    print("Total scraped records:", total_scraped)


if __name__ == "__main__":
    run()