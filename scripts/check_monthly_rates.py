import os
import sys

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.database import SessionLocal
from backend.models import MonthlyRate


def main() -> None:
    db = SessionLocal()

    try:
        rows = (
            db.query(MonthlyRate)
            .order_by(MonthlyRate.year.desc(), MonthlyRate.location)
            .limit(20)
            .all()
        )

        print("Rows found:", len(rows))
        print("-" * 90)

        for row in rows:
            print(
                f"{row.location:<20} | {row.year} | "
                f"Jan: {row.jan} | Dec: {row.dec} | Year Avg: {row.year_average}"
            )
    finally:
        db.close()


if __name__ == "__main__":
    main()
