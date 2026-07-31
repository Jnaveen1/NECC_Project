import os
from datetime import datetime

try:
    from google import genai
except ImportError:  # pragma: no cover - optional dependency
    genai = None


MONTH_MAP = {
    1: "January",
    2: "February",
    3: "March",
    4: "April",
    5: "May",
    6: "June",
    7: "July",
    8: "August",
    9: "September",
    10: "October",
    11: "November",
    12: "December",
}


def _parse_date(value):
    if not value:
        return None
    if isinstance(value, str):
        try:
            return datetime.strptime(value, "%Y-%m-%d")
        except ValueError:
            try:
                return datetime.fromisoformat(value)
            except ValueError:
                return None
    return value


def _seasonal_context(start_date, end_date):
    start = _parse_date(start_date)
    end = _parse_date(end_date)
    if start is None or end is None:
        return "general market conditions"

    months = set(range(start.month, end.month + 1))
    if months & {10, 11, 12}:
        festival_hint = "festival and wedding-season demand"
    elif months & {1, 2}:
        festival_hint = "post-holiday and winter demand"
    elif months & {6, 7, 8}:
        festival_hint = "school reopening and monsoon logistics pressure"
    elif months & {3, 4, 5}:
        festival_hint = "summer demand and supply tightening"
    else:
        festival_hint = "normal seasonal demand"

    if months & {6, 7}:
        school_hint = "school reopening and hostel demand"
    else:
        school_hint = "no major school-opening pressure"

    return f"{festival_hint}; {school_hint}"


def _fallback_market_analysis(data: dict) -> str:
    location = data.get("location", "this market")
    start_price = float(data.get("start_price", 0))
    end_price = float(data.get("end_price", 0))
    change = end_price - start_price
    direction = "increased" if change >= 0 else "declined"
    percent = abs(float(data.get("percentage_change", 0)))
    start_date = data.get("start_date")
    end_date = data.get("end_date")
    seasonal_context = _seasonal_context(start_date, end_date)
    start_month = _parse_date(start_date)
    end_month = _parse_date(end_date)

    likely_reasons = []
    if start_month and end_month:
        month_names = {MONTH_MAP[m.month] for m in [start_month, end_month]}
        if any(name in {"October", "November", "December"} for name in month_names):
            likely_reasons.append("festival and wedding-season demand")
        if any(name in {"June", "July", "August"} for name in month_names):
            likely_reasons.append("school reopening and hostel demand")
        if any(name in {"January", "February"} for name in month_names):
            likely_reasons.append("post-holiday retail and winter consumption")
        if any(name in {"March", "April", "May"} for name in month_names):
            likely_reasons.append("summer supply tightening")

    if not likely_reasons:
        likely_reasons = [
            "seasonal demand",
            "local supply variation",
            "transport and logistics constraints",
        ]

    if percent > 10:
        likely_reasons.insert(0, "sharp demand pressure")
    elif percent < 3:
        likely_reasons.insert(0, "stable local supply")

    reason_text = ", ".join(likely_reasons[:4])
    return (
        f"Egg prices {direction} from ₹{start_price:.2f} to ₹{end_price:.2f} in {location} over this period. "
        f"This pattern likely reflects {reason_text}, and the selected months also suggest {seasonal_context}. "
        f"Short-term supply changes, logistics, and local demand shifts may have amplified the movement."
    )


def _fallback_monthly_analysis(data: dict) -> str:
    location = data.get("location", "this market")
    year = data.get("year")
    month_name = data.get("peak_month") or data.get("focus_month") or "this month"
    annual_average = float(data.get("annual_average", 0))
    peak_value = float(data.get("peak_value", 0))
    peak_delta = float(data.get("peak_delta", 0))
    focus_value = float(data.get("focus_value", 0))
    focus_delta = float(data.get("focus_delta", 0))

    if focus_value and annual_average:
        return (
            f"{month_name} stands at ₹{focus_value:.2f}, compared with the yearly average of ₹{annual_average:.2f} in {location}. "
            f"This deviance may be tied to {data.get('likely_reason', 'seasonal demand, local supply shifts, and transport constraints')}, since it is {abs(focus_delta):.2f} higher/lower than the rest of the year."
        )

    return (
        f"{month_name} is the strongest month in {location} for {year}, at ₹{peak_value:.2f}, compared with the annual average of ₹{annual_average:.2f}. "
        f"This likely reflects strong seasonal demand, local supply changes, and logistics pressure rather than a single fixed cause."
    )


def generate_market_analysis(data: dict) -> dict:
    if not data:
        return {"analysis": "No price data available for analysis.", "source": "heuristic"}

    api_key = os.getenv("GEMINI_API_KEY")
    client = None
    if genai is not None and api_key:
        try:
            client = genai.Client(api_key=api_key)
        except Exception:
            client = None

    if client is None:
        return {
            "analysis": _fallback_market_analysis(data),
            "source": "heuristic",
        }

    season_context = _seasonal_context(data.get("start_date"), data.get("end_date"))
    month_hint = ""
    start = _parse_date(data.get("start_date"))
    end = _parse_date(data.get("end_date"))
    if start and end:
        month_hint = (
            f"Selected months: {MONTH_MAP[start.month]} to {MONTH_MAP[end.month]}. "
            f"Context: {season_context}."
        )

    prompt = f"""
You are an experienced Indian poultry market analyst.
Analyze only the actual market reason behind the price movement in this selected period.
Location: {data.get('location')}
Start date: {data.get('start_date')}
End date: {data.get('end_date')}
Trend: {data.get('trend')}
Price change: {data.get('percentage_change')}%
Start price: ₹{data.get('start_price')}
End price: ₹{data.get('end_price')}
{month_hint}
Use the selected period and the price change to decide likely causes.
Consider festival demand, wedding demand, school reopening, hostel demand, weather, poultry feed cost, transport issues, local supply variation, and holiday effects.
Be specific to the dates and months, not generic.
Use words like may, could, likely, possibly.
Write exactly 2 sentences, maximum 90 words total.
Do not repeat the same generic reason for every case.
"""

    try:
        response = client.models.generate_content(
            model="gemini-2.5-flash",
            contents=prompt,
        )
        text = getattr(response, "text", None) or str(response)
        cleaned = (text or _fallback_market_analysis(data)).strip()
        return {"analysis": cleaned, "source": "gemini"}
    except Exception:
        return {
            "analysis": _fallback_market_analysis(data),
            "source": "heuristic",
        }


def generate_monthly_market_analysis(data: dict) -> dict:
    if not data:
        return {"analysis": "No monthly data available for analysis.", "source": "heuristic"}

    api_key = os.getenv("GEMINI_API_KEY")
    client = None
    if genai is not None and api_key:
        try:
            client = genai.Client(api_key=api_key)
        except Exception:
            client = None

    if client is None:
        return {
            "analysis": _fallback_monthly_analysis(data),
            "source": "heuristic",
        }

    month_rows = data.get("months", [])
    if month_rows:
        focus = data.get("focus_month") or max(month_rows, key=lambda item: item["average_price"])['month_name']
        focus_value = next((item["average_price"] for item in month_rows if item["month_name"] == focus), None)
        annual_average = data.get("annual_average")
        delta = (focus_value - annual_average) if annual_average and focus_value is not None else 0
        likely_reason = data.get("likely_reason", "festival demand, supply pressure, and logistics issues")
    else:
        focus = data.get("focus_month", "this month")
        focus_value = data.get("focus_value")
        annual_average = data.get("annual_average")
        delta = data.get("focus_delta", 0)
        likely_reason = data.get("likely_reason", "seasonal demand and local supply variation")

    prompt = f"""
You are an experienced Indian poultry market analyst.
Compare the selected month with the rest of the year to explain why it is above or below the annual average.
Location: {data.get('location')}
Year: {data.get('year')}
Selected month: {focus}
Selected month average: ₹{focus_value:.2f}
Annual average: ₹{annual_average:.2f}
Difference from annual average: {delta:+.2f}
Other monthly averages in the same year are available for comparison.
Reason about likely drivers such as festival demand, school reopening, wedding season, weather, transport pressure, feed cost, and local supply shortage.
Focus on the actual month and the comparison with the rest of the year.
Use words like may, could, likely, possibly.
Write exactly 2 sentences, maximum 90 words total.
Do not give a generic answer for all months.
"""

    try:
        response = client.models.generate_content(
            model="gemini-2.5-flash",
            contents=prompt,
        )
        text = getattr(response, "text", None) or str(response)
        cleaned = (text or _fallback_monthly_analysis(data)).strip()
        return {"analysis": cleaned, "source": "gemini"}
    except Exception:
        return {
            "analysis": _fallback_monthly_analysis(data),
            "source": "heuristic",
        }
