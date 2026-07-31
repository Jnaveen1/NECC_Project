import os
import sys

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.database import SessionLocal
from backend.models import EggPrice
from sqlalchemy import extract, func


def test_monthly_summary(location, year):
    db = SessionLocal()

    try:
        records = (
            db.query(
                extract("month", EggPrice.price_date).label("month"),
                func.avg(EggPrice.price).label("average_price"),
                func.min(EggPrice.price).label("minimum_price"),
                func.max(EggPrice.price).label("maximum_price"),
            )
            .filter(
                EggPrice.location == location,
                extract("year", EggPrice.price_date) == year,
            )
            .group_by(extract("month", EggPrice.price_date))
            .order_by(extract("month", EggPrice.price_date))
            .all()
        )

        print(f"\nMonthly Summary for {location} ({year})")
        print("-" * 70)

        for record in records:
            print(
                f"Month: {int(record.month):2d} | "
                f"Avg: {float(record.average_price):7.2f} | "
                f"Min: {float(record.minimum_price):6.2f} | "
                f"Max: {float(record.maximum_price):6.2f}"
            )

    finally:
        db.close()


if __name__ == "__main__":
    test_monthly_summary("Ahmedabad", 2025)