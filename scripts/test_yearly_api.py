import requests


URL = "http://127.0.0.1:8000/api/prices/yearly-summary"

params = {
    "location": "Ahmedabad",
}


try:
    response = requests.get(
        URL,
        params=params,
        timeout=10,
    )

    print("Status code:", response.status_code)
    print("Response:")

    if response.ok:
        data = response.json()

        print("Location:", data["location"])
        print("Total years:", data["total_years"])
        print("-" * 85)

        for record in data["yearly_data"]:
            print(
                f"Year: {record['year']} | "
                f"Avg: {record['average_price']:7.2f} | "
                f"Min: {record['minimum_price']:6.2f} | "
                f"Max: {record['maximum_price']:6.2f} | "
                f"Records: {record['total_records']}"
            )
    else:
        print(response.text)

except requests.RequestException as error:
    print("API request failed:", error)