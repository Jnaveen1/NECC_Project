import os
import sys

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.database import SessionLocal
from backend.models import EggPrice
from sqlalchemy import extract, func


def test_yearly_summary(location):
    db = SessionLocal()

    try:
        records = (
            db.query(
                extract("year", EggPrice.price_date).label("year"),
                func.avg(EggPrice.price).label("average_price"),
                func.min(EggPrice.price).label("minimum_price"),
                func.max(EggPrice.price).label("maximum_price"),
                func.count(EggPrice.id).label("total_records"),
            )
            .filter(EggPrice.location == location)
            .group_by(extract("year", EggPrice.price_date))
            .order_by(extract("year", EggPrice.price_date))
            .all()
        )

        print(f"\nYearly Summary for {location}")
        print("-" * 85)

        for record in records:
            print(
                f"Year: {int(record.year)} | "
                f"Avg: {float(record.average_price):7.2f} | "
                f"Min: {float(record.minimum_price):6.2f} | "
                f"Max: {float(record.maximum_price):6.2f} | "
                f"Records: {record.total_records}"
            )

    finally:
        db.close()


if __name__ == "__main__":
    test_yearly_summary("Ahmedabad")