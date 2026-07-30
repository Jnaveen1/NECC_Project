from datetime import date

import requests
from bs4 import BeautifulSoup


BASE_URL = "https://e2necc.com/home/eggprice"


def fetch_month_page(month: int, year: int) -> str:
    form_data = {
        "ddlMonth": f"{month:02d}",
        "ddlYear": str(year),
        "rblReportType": "DailyReport",
        "btnReport": "Get Sheet",
    }

    response = requests.post(
        BASE_URL,
        data=form_data,
        timeout=30,
    )

    response.raise_for_status()
    return response.text

def scrape_month_prices(month: int, year: int) -> list[dict]:
    html = fetch_month_page(month, year)
    soup = BeautifulSoup(html, "lxml")

    tables = soup.find_all("table")

    if len(tables) < 3:
        raise ValueError("Historical price table not found")

    price_table = tables[2]
    rows = price_table.find_all("tr")

    records = []

    # Skip:
    # Row 1: table headings
    # Row 2: NECC SUGGESTED EGG PRICES
    for row in rows[2:]:
        cells = row.find_all(["th", "td"])
        values = [cell.get_text(" ", strip=True) for cell in cells]

        if len(values) < 2:
            continue

        location = values[0]

        # First 31 values after location are day prices.
        day_prices = values[1:32]

        for day_number, price_text in enumerate(day_prices, start=1):
            if price_text in {"", "-", "NA", "N/A"}:
                continue

            try:
                price_date = date(year, month, day_number)
                price = float(price_text)
            except (ValueError, TypeError):
                continue

            records.append(
                {
                    "location": location,
                    "price_date": price_date,
                    "price": price,
                    "source": BASE_URL,
                }
            )

    return records


if __name__ == "__main__":
    scraped_records = scrape_month_prices(month=7, year=2026)

    print("Total records:", len(scraped_records))
    print("-" * 60)

    for record in scraped_records[:10]:
        print(record)