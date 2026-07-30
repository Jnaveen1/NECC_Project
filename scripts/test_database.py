import os
import sys
from sqlalchemy import text

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from backend.database import engine


def test_connection():
    try:
        with engine.connect() as connection:
            result = connection.execute(text("SELECT DATABASE()"))
            database_name = result.scalar()

            print("MySQL connection successful")
            print("Connected database:", database_name)

    except Exception as error:
        print("MySQL connection failed")
        print("Error:", error)


if __name__ == "__main__":
    test_connection()