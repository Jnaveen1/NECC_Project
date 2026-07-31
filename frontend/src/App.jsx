import React from "react";

import { useCallback, useEffect, useMemo, useState } from 'react';
import Sidebar from './components/Sidebar';
import Topbar from './components/Topbar';
import Dashboard from './pages/Dashboard';
import DailyPrices from './pages/DailyPrices';
import MonthlyReport from './pages/MonthlyReport';
import CustomAnalysis from './pages/CustomAnalysis';
import { fetchDailyPrices, fetchLocations, fetchMonthlySummary, fetchSummary, fetchYearlySummary, getRuntimeConfig } from './services/api';

const pageMeta = {
  dashboard: ['Market dashboard', 'NECC egg price performance at a glance'],
  daily: ['Daily prices', 'Explore day-wise rates and changes'],
  monthly: ['Monthly report', 'Official monthly and yearly averages'],
  analysis: ['Custom analysis', 'Analyze any location and date range'],
};

export default function App() {
  const [activePage, setActivePage] = useState('dashboard');
  const [menuOpen, setMenuOpen] = useState(false);
  const [locations, setLocations] = useState(['Ahmedabad']);
  const [filters, setFilters] = useState({ location: 'Ahmedabad', startDate: '2025-01-01', endDate: '2025-12-31' });
  const [applied, setApplied] = useState(filters);
  const [daily, setDaily] = useState([]);
  const [summary, setSummary] = useState(null);
  const [monthly, setMonthly] = useState([]);
  const [yearly, setYearly] = useState([]);
  const [reportYear, setReportYear] = useState(2025);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const config = useMemo(() => getRuntimeConfig(), []);

  const loadRange = useCallback(async (params = applied) => {
    setLoading(true); setError('');
    try {
      const [dailyRows, summaryData] = await Promise.all([fetchDailyPrices(params), fetchSummary(params)]);
      setDaily(dailyRows); setSummary(summaryData);
    } catch (err) {
      setError(err.response?.data?.detail || err.message || 'Unable to load price data.');
    } finally { setLoading(false); }
  }, [applied]);

  const loadReports = useCallback(async (location = filters.location, year = reportYear) => {
    try {
      const [monthlyRows, yearlyRows] = await Promise.all([fetchMonthlySummary({ location, year }), fetchYearlySummary({ location })]);
      setMonthly(monthlyRows); setYearly(yearlyRows);
    } catch (err) { setError(err.response?.data?.detail || err.message || 'Unable to load report data.'); }
  }, [filters.location, reportYear]);

  useEffect(() => {
    fetchLocations().then((items) => { if (items.length) { setLocations(items); setFilters((old) => ({ ...old, location: items.includes(old.location) ? old.location : items[0] })); } }).catch(() => {});
    loadRange(filters);
    loadReports(filters.location, reportYear);
  }, []);

  useEffect(() => { if (activePage === 'monthly') loadReports(filters.location, reportYear); }, [activePage, filters.location, reportYear, loadReports]);

  const onFilterChange = (key, value) => setFilters((old) => ({ ...old, [key]: value }));
  const onApply = () => { setApplied(filters); loadRange(filters); };
  const onRefresh = () => { loadRange(applied); if (activePage === 'monthly') loadReports(filters.location, reportYear); };
  const [title, subtitle] = pageMeta[activePage];

  return (
    <div className="app-shell">
      <Sidebar activePage={activePage} onNavigate={setActivePage} open={menuOpen} onToggle={(value) => setMenuOpen(typeof value === 'boolean' ? value : !menuOpen)} />
      <main className="main-content">
        <Topbar title={title} subtitle={subtitle} config={config} onRefresh={onRefresh} refreshing={loading} />
        {error && <div className="error-banner">{error}<button onClick={() => setError('')}>Dismiss</button></div>}
        {activePage === 'dashboard' && <Dashboard locations={locations} filters={filters} onFilterChange={onFilterChange} onApply={onApply} summary={summary} daily={daily} loading={loading} />}
        {activePage === 'daily' && <DailyPrices locations={locations} filters={filters} onFilterChange={onFilterChange} onApply={onApply} daily={daily} />}
        {activePage === 'monthly' && <MonthlyReport locations={locations} location={filters.location} year={reportYear} onLocation={(location) => setFilters((old) => ({ ...old, location }))} onYear={setReportYear} monthly={monthly} yearly={yearly} />}
        {activePage === 'analysis' && <CustomAnalysis locations={locations} filters={filters} onFilterChange={onFilterChange} onApply={onApply} summary={summary} daily={daily} />}
      </main>
    </div>
  );
}
