import requests


URL = "http://127.0.0.1:8000/api/prices/daily"

params = {
    "location": "Ahmedabad",
    "start_date": "2025-01-01",
    "end_date": "2025-01-10",
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

        print("Location:", data["location"])
        print("From:", data["start_date"])
        print("To:", data["end_date"])
        print("Total records:", data["total_records"])
        print("-" * 45)

        for record in data["prices"]:
            print(
                f"Date: {record['date']} | "
                f"Price: {record['price']}"
            )
    else:
        print("Error response:", response.text)

except requests.RequestException as error:
    print("API request failed:", error)