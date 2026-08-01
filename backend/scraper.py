from datetime import date

import requests
from bs4 import BeautifulSoup


BASE_URL = "https://e2necc.com/home/eggprice"
MONTH_FIELDS = (
    "jan",
    "feb",
    "mar",
    "apr",
    "may",
    "jun",
    "jul",
    "aug",
    "sep",
    "oct",
    "nov",
    "dec",
)


def _post_report(form_data: dict[str, str]) -> str:
    response = requests.post(
        BASE_URL,
        data=form_data,
        timeout=30,
    )
    response.raise_for_status()
    return response.text


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


def _discover_monthly_report_value() -> str:
    """Read the form and discover the Monthly Average Sheet radio value."""
    try:
        response = requests.get(BASE_URL, timeout=30)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, "lxml")

        radios = soup.find_all(
            "input",
            attrs={"name": "rblReportType", "type": "radio"},
        )

        for radio in radios:
            value = (radio.get("value") or "").strip()
            if not value or value == "DailyReport":
                continue

            nearby_text = " ".join(
                part.get_text(" ", strip=True)
                for part in (radio.parent, soup.find("label", attrs={"for": radio.get("id")}))
                if part is not None
            ).lower()

            if "month" in nearby_text or "average" in nearby_text:
                return value

        # The page normally contains only Daily and Monthly report radios.
        for radio in radios:
            value = (radio.get("value") or "").strip()
            if value and value != "DailyReport":
                return value
    except requests.RequestException:
        # Keep a known fallback so temporary GET issues do not block the POST.
        pass

    return "MonthlyReport"


def fetch_monthly_average_page(year: int) -> str:
    """Fetch the website's Monthly Average Sheet for one year."""
    report_value = _discover_monthly_report_value()

    return _post_report(
        {
            "ddlMonth": "01",
            "ddlYear": str(year),
            "rblReportType": report_value,
            "btnReport": "Get Sheet",
        }
    )


def _find_data_table(soup: BeautifulSoup, minimum_columns: int):
    """Find the widest table that looks like a NECC price data table."""
    candidates = []

    for table in soup.find_all("table"):
        rows = table.find_all("tr")
        widest_row = max(
            (len(row.find_all(["th", "td"])) for row in rows),
            default=0,
        )

        if widest_row >= minimum_columns:
            candidates.append((widest_row, len(rows), table))

    if not candidates:
        raise ValueError("NECC price table not found in the returned page")

    candidates.sort(key=lambda item: (item[0], item[1]), reverse=True)
    return candidates[0][2]


def _parse_price(value: str) -> float | None:
    cleaned = value.replace(",", "").strip()

    if cleaned.upper() in {"", "-", "NA", "N/A", "NULL"}:
        return None

    try:
        return float(cleaned)
    except ValueError:
        return None


def scrape_month_prices(month: int, year: int) -> list[dict]:
    html = fetch_month_page(month, year)
    soup = BeautifulSoup(html, "lxml")
    price_table = _find_data_table(soup, minimum_columns=10)

    records = []

    for row in price_table.find_all("tr"):
        values = [
            cell.get_text(" ", strip=True)
            for cell in row.find_all(["th", "td"])
        ]

        if len(values) < 2:
            continue

        location = values[0].strip()
        if not location or location.lower() in {"location", "centre", "center"}:
            continue

        # The first 31 cells after location are daily prices. A final Average
        # column on the website is deliberately not stored in the daily table.
        for day_number, price_text in enumerate(values[1:32], start=1):
            price = _parse_price(price_text)
            if price is None:
                continue

            try:
                price_date = date(year, month, day_number)
            except ValueError:
                # Handles days 29-31 for shorter months.
                continue

            records.append(
                {
                    "location": location,
                    "price_date": price_date,
                    "price": price,
                    "source": BASE_URL,
                }
            )

    if not records:
        raise ValueError(
            f"No daily NECC prices were parsed for {month:02d}/{year}"
        )

    return records


def scrape_monthly_average_prices(year: int) -> list[dict]:
    """Scrape Jan-Dec averages and the final yearly average for every location."""
    html = fetch_monthly_average_page(year)
    soup = BeautifulSoup(html, "lxml")
    price_table = _find_data_table(soup, minimum_columns=14)

    records = []

    for row in price_table.find_all("tr"):
        values = [
            cell.get_text(" ", strip=True)
            for cell in row.find_all(["th", "td"])
        ]

        # location + 12 months + yearly average = at least 14 cells
        if len(values) < 14:
            continue

        location = values[0].strip()
        if not location or location.lower() in {"location", "centre", "center"}:
            continue

        month_values = [_parse_price(value) for value in values[1:13]]
        year_average = _parse_price(values[13])

        # Skip heading/description rows that happen to contain many cells.
        if all(value is None for value in month_values) and year_average is None:
            continue

        record = {
            "location": location,
            "year": year,
            "year_average": year_average,
            "source": BASE_URL,
        }
        record.update(dict(zip(MONTH_FIELDS, month_values)))
        records.append(record)

    if not records:
        raise ValueError(
            "No monthly average rows were parsed. The NECC report option or "
            "table structure may have changed."
        )

    return records


if __name__ == "__main__":
    daily_records = scrape_month_prices(month=7, year=2026)
    print("Daily records:", len(daily_records))
    print(daily_records[:3])

    monthly_records = scrape_monthly_average_prices(year=2025)
    print("Monthly-sheet records:", len(monthly_records))
    print(monthly_records[:1])
