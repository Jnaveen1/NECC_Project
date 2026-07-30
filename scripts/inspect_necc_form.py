import requests
from bs4 import BeautifulSoup


URL = "https://e2necc.com/home/eggprice"


def inspect_form():
    response = requests.get(URL, timeout=30)
    response.raise_for_status()

    soup = BeautifulSoup(response.text, "lxml")

    forms = soup.find_all("form")

    print("Total forms:", len(forms))
    print("=" * 80)

    for form_number, form in enumerate(forms, start=1):
        print(f"\nFORM {form_number}")
        print("Action:", form.get("action"))
        print("Method:", form.get("method"))
        print("-" * 80)

        selects = form.find_all("select")

        for select in selects:
            print("SELECT NAME:", select.get("name"))
            print("SELECT ID:", select.get("id"))

            options = select.find_all("option")

            for option in options[:15]:
                print(
                    "  Text:",
                    option.get_text(strip=True),
                    "| Value:",
                    option.get("value"),
                )

            print("-" * 40)

        inputs = form.find_all("input")

        for input_element in inputs:
            print(
                "INPUT:",
                "name=", input_element.get("name"),
                "value=", input_element.get("value"),
                "type=", input_element.get("type"),
            )


if __name__ == "__main__":
    inspect_form()