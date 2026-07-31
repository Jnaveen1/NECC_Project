from datetime import date

from fastapi import Depends, FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import extract, func
from sqlalchemy import distinct
from sqlalchemy.orm import Session

from backend.ai_analysis import generate_market_analysis, generate_monthly_market_analysis
from backend.database import get_db
from backend.models import EggPrice, MonthlyRate

from dotenv import load_dotenv

load_dotenv()

app = FastAPI(
    title="NECC Egg Price API",
    version="1.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:5173",
        "http://127.0.0.1:5173",
        "http://localhost:3000",
        "http://127.0.0.1:3000",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
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


@app.get("/api/analysis/monthly")
def get_monthly_market_analysis(
    location: str = Query(...),
    year: int = Query(...),
    db: Session = Depends(get_db),
):
    """Explain why a monthly average stands out relative to the rest of the year."""
    record = (
        db.query(MonthlyRate)
        .filter(MonthlyRate.location == location, MonthlyRate.year == year)
        .first()
    )

    if not record:
        raise HTTPException(status_code=404, detail="No monthly market summary found")

    month_values = []
    for month_number, _, column in MONTH_COLUMNS:
        value = getattr(record, column.key)
        if value is None:
            continue
        month_values.append({
            "month": month_number,
            "month_name": MONTH_COLUMNS[month_number - 1][1],
            "average_price": float(value),
        })

    annual_average = float(record.year_average) if record.year_average is not None else sum(item["average_price"] for item in month_values) / len(month_values) if month_values else 0
    focus_month = max(month_values, key=lambda item: item["average_price"]) if month_values else None
    focus_month_name = focus_month["month_name"] if focus_month else "this month"
    focus_value = float(focus_month["average_price"]) if focus_month else 0
    delta = focus_value - annual_average if annual_average else 0
    likely_reason = "festival demand, school reopening, and local supply variation"

    if focus_month_name in {"June", "July", "August"}:
        likely_reason = "school reopening, hostel demand, and monsoon logistics pressure"
    elif focus_month_name in {"October", "November", "December"}:
        likely_reason = "festival and wedding-season demand"
    elif focus_month_name in {"January", "February"}:
        likely_reason = "post-holiday and winter consumption"
    elif focus_month_name in {"March", "April", "May"}:
        likely_reason = "summer demand and supply tightening"

    analysis_payload = {
        "location": location,
        "year": year,
        "focus_month": focus_month_name,
        "focus_value": focus_value,
        "annual_average": annual_average,
        "focus_delta": delta,
        "months": month_values,
        "likely_reason": likely_reason,
    }
    result = generate_monthly_market_analysis(analysis_payload)

    return {
        "location": location,
        "year": year,
        "focus_month": focus_month_name,
        "focus_value": focus_value,
        "annual_average": annual_average,
        "difference_from_average": round(delta, 2),
        "analysis": result["analysis"],
        "source": result["source"],
    }


@app.get("/api/analysis/market")
def get_market_analysis(
    location: str = Query(...),
    start_date: date = Query(...),
    end_date: date = Query(...),
    db: Session = Depends(get_db),
):
    """Generate a market explanation for the selected price range."""
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
        raise HTTPException(status_code=404, detail="No price data found for analysis")

    start_price = float(records[0].price)
    end_price = float(records[-1].price)
    change = end_price - start_price
    percentage_change = ((change / start_price) * 100) if start_price else 0
    trend = "rising" if change >= 0 else "falling"

    analysis_data = {
        "location": location,
        "start_date": start_date.isoformat(),
        "end_date": end_date.isoformat(),
        "trend": trend,
        "percentage_change": round(percentage_change, 2),
        "start_price": round(start_price, 2),
        "end_price": round(end_price, 2),
    }
    analysis_result = generate_market_analysis(analysis_data)

    return {
        "location": location,
        "start_date": start_date.isoformat(),
        "end_date": end_date.isoformat(),
        "trend": trend,
        "percentage_change": round(percentage_change, 2),
        "start_price": round(start_price, 2),
        "end_price": round(end_price, 2),
        "analysis": analysis_result["analysis"],
        "source": analysis_result["source"],
    }




