import argparse

from backend.crud import save_monthly_rates
from backend.scraper import scrape_monthly_average_prices


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Scrape and save the NECC Monthly Average Sheet."
    )
    parser.add_argument("year", type=int, help="Year to scrape, for example 2025")
    args = parser.parse_args()

    print(f"Scraping official monthly averages for {args.year}...")
    records = scrape_monthly_average_prices(args.year)
    print(f"Parsed {len(records)} location rows")

    affected_rows = save_monthly_rates(records)
    print(f"Database rows inserted/updated: {affected_rows}")


if __name__ == "__main__":
    main()
