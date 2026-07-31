import argparse

from backend.crud import save_monthly_rates
from backend.scraper import scrape_monthly_average_prices


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Scrape NECC Monthly Average Sheets for a range of years."
    )
    parser.add_argument("start_year", type=int)
    parser.add_argument("end_year", type=int)
    args = parser.parse_args()

    if args.start_year > args.end_year:
        raise ValueError("start_year cannot be greater than end_year")

    total_affected = 0

    for year in range(args.start_year, args.end_year + 1):
        print(f"\nScraping {year}...")
        records = scrape_monthly_average_prices(year)
        affected = save_monthly_rates(records)
        total_affected += affected
        print(f"Parsed: {len(records)} | Inserted/updated: {affected}")

    print(f"\nCompleted. Total inserted/updated rows: {total_affected}")


if __name__ == "__main__":
    main()
