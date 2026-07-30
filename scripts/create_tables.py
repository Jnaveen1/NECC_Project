from backend.database import Base, engine
from backend import models


def create_tables():
    print("Creating tables in:", engine.url.database)

    Base.metadata.create_all(bind=engine)

    print("Registered tables:", list(Base.metadata.tables.keys()))
    print("Tables created successfully")


if __name__ == "__main__":
    create_tables()