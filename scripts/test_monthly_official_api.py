import requests


URL = "http://127.0.0.1:8000/api/prices/monthly-summary"
PARAMS = {"location": "Ahmedabad", "year": 2025}


try:
    response = requests.get(URL, params=PARAMS, timeout=10)
    print("Status code:", response.status_code)

    if response.ok:
        data = response.json()
        print("Location:", data["location"])
        print("Year:", data["year"])
        print("Official year average:", data["year_average"])
        print("-" * 55)

        for item in data["monthly_data"]:
            print(
                f"{item['month']:02d} {item['month_name']:<9} "
                f"Average: {item['average_price']:.2f}"
            )
    else:
        print(response.text)

except requests.RequestException as error:
    print("API request failed:", error)
