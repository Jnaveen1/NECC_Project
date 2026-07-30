import requests


URL = "http://127.0.0.1:8000/api/prices/summary"

params = {
    "location": "Ahmedabad",
    "start_date": "2025-01-01",
    "end_date": "2025-12-31",
}


try:
    response = requests.get(
        URL,
        params=params,
        timeout=30,
    )

    print("Status code:", response.status_code)

    if response.ok:
        data = response.json()

        print("\nDashboard Summary")
        print("-" * 45)
        print("Location:", data["location"])
        print("From:", data["start_date"])
        print("To:", data["end_date"])
        print("Current date:", data["current_date"])
        print("Current price:", data["current_price"])
        print("Average price:", data["average_price"])
        print("Minimum price:", data["minimum_price"])
        print("Maximum price:", data["maximum_price"])
        print("Total records:", data["total_records"])

    else:
        print("Error response:", response.text)

except requests.RequestException as error:
    print("API request failed:", error)