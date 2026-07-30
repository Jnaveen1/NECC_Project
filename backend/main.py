from datetime import date

from fastapi import Depends, FastAPI, HTTPException, Query
from sqlalchemy import extract, func
from sqlalchemy import distinct
from sqlalchemy.orm import Session

from backend.database import get_db
from backend.models import EggPrice


app = FastAPI(
    title="NECC Egg Price API",
    version="1.0.0",
)

@app.get("/ping")
def ping():
    return {"message": "pong"}

@app.get("/")
def home():
    return {"message": "NECC Egg Price API is running"}


@app.get("/api/locations")
def get_locations(db: Session = Depends(get_db)):
    locations = (
        db.query(distinct(EggPrice.location))
        .order_by(EggPrice.location)
        .all()
    )

    return {
        "total": len(locations),
        "locations": [location[0] for location in locations],
    }


@app.get("/api/prices")
def get_prices(
    location: str = Query(...),
    start_date: date | None = Query(None),
    end_date: date | None = Query(None),
    db: Session = Depends(get_db),
):
    query = db.query(EggPrice).filter(
        EggPrice.location == location
    )

    if start_date:
        query = query.filter(EggPrice.price_date >= start_date)

    if end_date:
        query = query.filter(EggPrice.price_date <= end_date)

    records = query.order_by(EggPrice.price_date).all()

    if not records:
        raise HTTPException(
            status_code=404,
            detail="No price records found",
        )

    prices = [
        {
            "date": record.price_date,
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
    records = (
        db.query(
            extract("month", EggPrice.price_date).label("month"),
            func.avg(EggPrice.price).label("average_price"),
            func.min(EggPrice.price).label("minimum_price"),
            func.max(EggPrice.price).label("maximum_price"),
        )
        .filter(
            EggPrice.location == location,
            extract("year", EggPrice.price_date) == year,
        )
        .group_by(extract("month", EggPrice.price_date))
        .order_by(extract("month", EggPrice.price_date))
        .all()
    )

    if not records:
        raise HTTPException(
            status_code=404,
            detail="No monthly data found",
        )

    monthly_data = []

    for record in records:
        monthly_data.append(
            {
                "month": int(record.month),
                "average_price": round(float(record.average_price), 2),
                "minimum_price": float(record.minimum_price),
                "maximum_price": float(record.maximum_price),
            }
        )

    return {
        "location": location,
        "year": year,
        "total_months": len(monthly_data),
        "monthly_data": monthly_data,
    }

@app.get("/api/prices/yearly-summary")
def get_yearly_summary(
    location: str = Query(...),
    db: Session = Depends(get_db),
):
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

    if not records:
        raise HTTPException(
            status_code=404,
            detail="No yearly data found",
        )

    yearly_data = []

    for record in records:
        yearly_data.append(
            {
                "year": int(record.year),
                "average_price": round(float(record.average_price), 2),
                "minimum_price": float(record.minimum_price),
                "maximum_price": float(record.maximum_price),
                "total_records": record.total_records,
            }
        )

    return {
        "location": location,
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

    prices = []

    for record in records:
        prices.append(
            {
                "date": record.price_date.isoformat(),
                "price": float(record.price),
            }
        )

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
        raise HTTPException(
            status_code=404,
            detail="No price data found",
        )

    return {
        "location": location,
        "start_date": start_date,
        "end_date": end_date,
        "current_price": float(latest_record.price),
        "current_date": latest_record.price_date.isoformat(),
        "average_price": round(float(summary.average_price), 2),
        "minimum_price": float(summary.minimum_price),
        "maximum_price": float(summary.maximum_price),
        "total_records": summary.total_records,
    }
