from datetime import date

from fastapi import Depends, FastAPI, HTTPException, Query
from sqlalchemy import extract, func
from sqlalchemy import distinct
from sqlalchemy.orm import Session

from backend.database import get_db
from backend.models import EggPrice, MonthlyRate

app = FastAPI(
    title="NECC Egg Price API",
    version="1.1.0",
)

MONTH_COLUMNS = (
    (1, "January", MonthlyRate.jan),
    (2, "February", MonthlyRate.feb),
    (3, "March", MonthlyRate.mar),
    (4, "April", MonthlyRate.apr),
    (5, "May", MonthlyRate.may),
    (6, "June", MonthlyRate.jun),
    (7, "July", MonthlyRate.jul),
    (8, "August", MonthlyRate.aug),
    (9, "September", MonthlyRate.sep),
    (10, "October", MonthlyRate.oct),
    (11, "November", MonthlyRate.nov),
    (12, "December", MonthlyRate.dec),
)


@app.get("/ping")
def ping():
    return {"message": "pong"}


@app.get("/")
def home():
    return {"message": "NECC Egg Price API is running"}


@app.get("/api/locations")
def get_locations(db: Session = Depends(get_db)):
    daily_locations = db.query(distinct(EggPrice.location)).all()
    monthly_locations = db.query(distinct(MonthlyRate.location)).all()

    locations = sorted(
        {row[0] for row in daily_locations + monthly_locations if row[0]}
    )

    return {
        "total": len(locations),
        "locations": locations,
    }


@app.get("/api/prices")
def get_prices(
    location: str = Query(...),
    start_date: date | None = Query(None),
    end_date: date | None = Query(None),
    db: Session = Depends(get_db),
):
    query = db.query(EggPrice).filter(EggPrice.location == location)

    if start_date:
        query = query.filter(EggPrice.price_date >= start_date)

    if end_date:
        query = query.filter(EggPrice.price_date <= end_date)

    records = query.order_by(EggPrice.price_date).all()

    if not records:
        raise HTTPException(status_code=404, detail="No price records found")

    prices = [
        {
            "date": record.price_date.isoformat(),
            "price": float(record.price),
        }
        for record in records
    ]

    return {
        "location": location,
        "total": len(prices),
        "prices": prices,
    }


@app.get("/api/prices/monthly-summary")
def get_monthly_summary(
    location: str = Query(...),
    year: int = Query(...),
    db: Session = Depends(get_db),
):
    """Return official monthly averages from the NECC Monthly Average Sheet."""
    record = (
        db.query(MonthlyRate)
        .filter(
            MonthlyRate.location == location,
            MonthlyRate.year == year,
        )
        .first()
    )

    if not record:
        raise HTTPException(
            status_code=404,
            detail="No official monthly average data found",
        )

    monthly_data = []

    for month_number, month_name, column in MONTH_COLUMNS:
        value = getattr(record, column.key)
        if value is None:
            continue

        monthly_data.append(
            {
                "month": month_number,
                "month_name": month_name,
                "average_price": float(value),
            }
        )

    return {
        "location": location,
        "year": year,
        "source_type": "official_monthly_average_sheet",
        "total_months": len(monthly_data),
        "monthly_data": monthly_data,
        "year_average": (
            float(record.year_average)
            if record.year_average is not None
            else None
        ),
    }


@app.get("/api/prices/yearly-summary")
def get_yearly_summary(
    location: str = Query(...),
    db: Session = Depends(get_db),
):
    """Return official yearly averages stored in Monthly Average Sheet rows."""
    records = (
        db.query(MonthlyRate)
        .filter(MonthlyRate.location == location)
        .order_by(MonthlyRate.year)
        .all()
    )

    if not records:
        raise HTTPException(
            status_code=404,
            detail="No official yearly average data found",
        )

    yearly_data = [
        {
            "year": record.year,
            "average_price": (
                float(record.year_average)
                if record.year_average is not None
                else None
            ),
            "available_months": sum(
                1
                for _, _, column in MONTH_COLUMNS
                if getattr(record, column.key) is not None
            ),
        }
        for record in records
    ]

    return {
        "location": location,
        "source_type": "official_monthly_average_sheet",
        "total_years": len(yearly_data),
        "yearly_data": yearly_data,
    }


@app.get("/api/prices/daily")
def get_daily_prices(
    location: str = Query(...),
    start_date: date = Query(...),
    end_date: date = Query(...),
    db: Session = Depends(get_db),
):
    if start_date > end_date:
        raise HTTPException(
            status_code=400,
            detail="start_date cannot be greater than end_date",
        )

    records = (
        db.query(EggPrice)
        .filter(
            EggPrice.location == location,
            EggPrice.price_date >= start_date,
            EggPrice.price_date <= end_date,
        )
        .order_by(EggPrice.price_date)
        .all()
    )

    if not records:
        raise HTTPException(
            status_code=404,
            detail="No daily price data found",
        )

    prices = [
        {
            "date": record.price_date.isoformat(),
            "price": float(record.price),
        }
        for record in records
    ]

    return {
        "location": location,
        "start_date": start_date,
        "end_date": end_date,
        "total_records": len(prices),
        "prices": prices,
    }


@app.get("/api/prices/summary")
def get_price_summary(
    location: str = Query(...),
    start_date: date = Query(...),
    end_date: date = Query(...),
    db: Session = Depends(get_db),
):
    """Calculate analysis for any custom date range from daily prices."""
    if start_date > end_date:
        raise HTTPException(
            status_code=400,
            detail="start_date cannot be greater than end_date",
        )

    summary = (
        db.query(
            func.avg(EggPrice.price).label("average_price"),
            func.min(EggPrice.price).label("minimum_price"),
            func.max(EggPrice.price).label("maximum_price"),
            func.count(EggPrice.id).label("total_records"),
        )
        .filter(
            EggPrice.location == location,
            EggPrice.price_date >= start_date,
            EggPrice.price_date <= end_date,
        )
        .first()
    )

    latest_record = (
        db.query(EggPrice)
        .filter(
            EggPrice.location == location,
            EggPrice.price_date >= start_date,
            EggPrice.price_date <= end_date,
        )
        .order_by(EggPrice.price_date.desc())
        .first()
    )

    if not latest_record:
        raise HTTPException(status_code=404, detail="No price data found")

    return {
        "location": location,
        "start_date": start_date,
        "end_date": end_date,
        "source_type": "calculated_from_daily_rates",
        "current_price": float(latest_record.price),
        "current_date": latest_record.price_date.isoformat(),
        "average_price": round(float(summary.average_price), 2),
        "minimum_price": float(summary.minimum_price),
        "maximum_price": float(summary.maximum_price),
        "total_records": summary.total_records,
    }
