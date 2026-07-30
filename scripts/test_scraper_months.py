from backend.scraper import scrape_month_prices


def find_price(records, location, day):
    for record in records:
        if (
            record["location"] == location
            and record["price_date"].day == day
        ):
            return record["price"]

    return None


if __name__ == "__main__":
    for month in [1, 2, 3]:
        records = scrape_month_prices(month=month, year=2025)

        price = find_price(
            records=records,
            location="Ahmedabad",
            day=1,
        )

        print(
            f"2025-{month:02d}-01 | "
            f"Ahmedabad price: {price} | "
            f"Records: {len(records)}"
        )