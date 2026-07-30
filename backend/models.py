from sqlalchemy import Column, Date, Integer, Numeric, String, UniqueConstraint

from backend.database import Base


class EggPrice(Base):
    __tablename__ = "egg_prices"

    id = Column(Integer, primary_key=True, index=True)

    location = Column(String(100), nullable=False)

    price_date = Column(Date, nullable=False)

    price = Column(Numeric(10, 2), nullable=False)

    source = Column(String(255), nullable=True)

    __table_args__ = (
        UniqueConstraint(
            "location",
            "price_date",
            name="uq_location_price_date",
        ),
    )