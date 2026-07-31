from sqlalchemy.dialects.mysql import insert

from backend.database import SessionLocal
from backend.models import EggPrice



def save_egg_prices(records: list[dict]) -> int:
    if not records:
        return 0

    db = SessionLocal()

    try:
        statement = insert(EggPrice).values(records)
        statement = statement.on_duplicate_key_update(
            price=statement.inserted.price,
            source=statement.inserted.source,
        )

        result = db.execute(statement)
        db.commit()
        return result.rowcount

    except Exception:
        db.rollback()
        raise

    finally:
        db.close()


def save_monthly_rates(records: list[dict]) -> int:
    """Insert or update complete Monthly Average Sheet rows."""
    if not records:
        return 0

    db = SessionLocal()

    try:
        statement = insert(MonthlyRate).values(records)
        statement = statement.on_duplicate_key_update(
            jan=statement.inserted.jan,
            feb=statement.inserted.feb,
            mar=statement.inserted.mar,
            apr=statement.inserted.apr,
            may=statement.inserted.may,
            jun=statement.inserted.jun,
            jul=statement.inserted.jul,
            aug=statement.inserted.aug,
            sep=statement.inserted.sep,
            oct=statement.inserted.oct,
            nov=statement.inserted.nov,
            dec=statement.inserted.dec,
            year_average=statement.inserted.year_average,
            source=statement.inserted.source,
        )

        result = db.execute(statement)
        db.commit()
        return result.rowcount

    except Exception:
        db.rollback()
        raise

    finally:
        db.close()
