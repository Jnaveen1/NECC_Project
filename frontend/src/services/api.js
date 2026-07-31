import axios from 'axios';
import { buildDailyData, mockLocations, monthlyFromDaily, summarize, yearlyMock } from '../data/mockData';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
const useMock = String(import.meta.env.VITE_USE_MOCK_DATA ?? 'true').toLowerCase() === 'true';

const client = axios.create({
  baseURL: API_BASE_URL,
  timeout: 20000,
});

function toDateString(value) {
  return typeof value === 'string' ? value : new Date(value).toISOString().slice(0, 10);
}

export async function fetchLocations() {
  if (useMock) return mockLocations;
  const { data } = await client.get('/api/locations');
  return data.locations || [];
}

export async function fetchDailyPrices({ location, startDate, endDate }) {
  if (useMock) return buildDailyData(location, startDate, endDate);
  const { data } = await client.get('/api/prices/daily', {
    params: { location, start_date: startDate, end_date: endDate },
  });
  return (data.prices || []).map((row) => ({ date: toDateString(row.date), price: Number(row.price) }));
}

export async function fetchSummary({ location, startDate, endDate }) {
  if (useMock) return summarize(buildDailyData(location, startDate, endDate));
  const { data } = await client.get('/api/prices/summary', {
    params: { location, start_date: startDate, end_date: endDate },
  });
  return data;
}

export async function fetchMonthlySummary({ location, year }) {
  if (useMock) {
    return monthlyFromDaily(buildDailyData(location, `${year}-01-01`, `${year}-12-31`));
  }
  const { data } = await client.get('/api/prices/monthly-summary', { params: { location, year } });
  if (Array.isArray(data.monthly_data)) return data.monthly_data;

  const monthKeys = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
  const record = data.data || data.monthly_rate || data;
  return monthKeys
    .map((key, index) => ({ month: index + 1, average_price: record[key] }))
    .filter((item) => item.average_price !== null && item.average_price !== undefined)
    .map((item) => ({ ...item, average_price: Number(item.average_price) }));
}

export async function fetchYearlySummary({ location }) {
  if (useMock) return yearlyMock(location);
  const { data } = await client.get('/api/prices/yearly-summary', { params: { location } });
  return data.yearly_data || data.data || [];
}

export function getRuntimeConfig() {
  return { apiBaseUrl: API_BASE_URL, useMock };
}
