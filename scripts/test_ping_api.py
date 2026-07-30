import requests


try:
    response = requests.get(
        "http://127.0.0.1:8000/ping",
        timeout=10,
    )

    print("Status code:", response.status_code)
    print("Response:", response.json())

except requests.RequestException as error:
    print("API request failed:", error)