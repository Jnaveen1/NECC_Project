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


class MonthlyRate(Base):
    """One complete NECC Monthly Average Sheet row for a location and year."""

    __tablename__ = "monthly_rates"

    id = Column(Integer, primary_key=True, index=True)
    location = Column(String(100), nullable=False)
    year = Column(Integer, nullable=False)

    jan = Column(Numeric(10, 2), nullable=True)
    feb = Column(Numeric(10, 2), nullable=True)
    mar = Column(Numeric(10, 2), nullable=True)
    apr = Column(Numeric(10, 2), nullable=True)
    may = Column(Numeric(10, 2), nullable=True)
    jun = Column(Numeric(10, 2), nullable=True)
    jul = Column(Numeric(10, 2), nullable=True)
    aug = Column(Numeric(10, 2), nullable=True)
    sep = Column(Numeric(10, 2), nullable=True)
    oct = Column(Numeric(10, 2), nullable=True)
    nov = Column(Numeric(10, 2), nullable=True)
    dec = Column(Numeric(10, 2), nullable=True)

    year_average = Column(Numeric(10, 2), nullable=True)
    source = Column(String(255), nullable=True)

    __table_args__ = (
        UniqueConstraint(
            "location",
            "year",
            name="uq_monthly_location_year",
        ),
    )


