const locations = ['Ahmedabad', 'Bengaluru', 'Chennai', 'Delhi', 'Hyderabad', 'Mumbai', 'Pune', 'Vijayawada'];

function seededPrice(dayIndex, locationIndex = 0) {
  const seasonal = Math.sin(dayIndex / 15) * 32;
  const weekly = Math.sin(dayIndex / 4.2) * 12;
  const trend = dayIndex * 0.22;
  return Math.round((505 + locationIndex * 8 + seasonal + weekly + trend) * 100) / 100;
}

export function buildDailyData(location = 'Ahmedabad', startDate = '2025-01-01', endDate = '2025-12-31') {
  const start = new Date(`${startDate}T00:00:00`);
  const end = new Date(`${endDate}T00:00:00`);
  const locationIndex = Math.max(0, locations.indexOf(location));
  const data = [];
  const cursor = new Date(start);
  let index = 0;

  while (cursor <= end) {
    data.push({
      date: cursor.toISOString().slice(0, 10),
      price: seededPrice(index, locationIndex),
    });
    cursor.setDate(cursor.getDate() + 1);
    index += 1;
  }

  return data;
}

export function summarize(prices) {
  if (!prices.length) return null;
  const values = prices.map((item) => Number(item.price));
  const latest = prices[prices.length - 1];
  return {
    current_price: Number(latest.price),
    current_date: latest.date,
    average_price: values.reduce((sum, value) => sum + value, 0) / values.length,
    minimum_price: Math.min(...values),
    maximum_price: Math.max(...values),
    total_records: prices.length,
  };
}

export function monthlyFromDaily(prices) {
  const groups = new Map();
  prices.forEach((item) => {
    const month = Number(item.date.slice(5, 7));
    if (!groups.has(month)) groups.set(month, []);
    groups.get(month).push(Number(item.price));
  });
  return [...groups.entries()].map(([month, values]) => ({
    month,
    average_price: values.reduce((sum, value) => sum + value, 0) / values.length,
    minimum_price: Math.min(...values),
    maximum_price: Math.max(...values),
  }));
}

export function yearlyMock(location) {
  return [2023, 2024, 2025, 2026].map((year, index) => ({
    year,
    average_price: 510 + index * 19 + Math.max(0, locations.indexOf(location)) * 3,
    minimum_price: 405 + index * 7,
    maximum_price: 650 + index * 18,
    total_records: year === 2026 ? 212 : 365,
  }));
}

export const mockLocations = locations;
