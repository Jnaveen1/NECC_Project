import os
import sys

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from backend.database import SessionLocal
from backend.models import EggPrice


db = SessionLocal()

try:
    for month in [1, 2, 3]:
        record = (
            db.query(EggPrice)
            .filter(
                EggPrice.location == "Ahmedabad",
                EggPrice.price_date == f"2025-{month:02d}-01",
            )
            .first()
        )

        if record:
            print(record.price_date, float(record.price))
finally:
    db.close()