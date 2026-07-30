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