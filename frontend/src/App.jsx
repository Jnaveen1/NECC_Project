import React from "react";

import { useCallback, useEffect, useMemo, useState } from 'react';
import Sidebar from './components/Sidebar';
import Topbar from './components/Topbar';
import Dashboard from './pages/Dashboard';
import DailyPrices from './pages/DailyPrices';
import MonthlyReport from './pages/MonthlyReport';
import CustomAnalysis from './pages/CustomAnalysis';
import { fetchDailyPrices, fetchLocations, fetchMarketAnalysis, fetchMonthlySummary, fetchSummary, fetchYearlySummary, getRuntimeConfig } from './services/api';

const pageMeta = {
  dashboard: ['Market Dashboard', 'NECC egg price performance at a glance'],
  daily: ['Daily prices', 'Explore day-wise rates and changes'],
  monthly: ['Monthly report', 'Official monthly and yearly averages'],
  analysis: ['Custom analysis', 'Analyze any location and date range'],
};

function getDefaultDateRange() {
  const today = new Date();
  const start = new Date(today.getFullYear(), today.getMonth(), 1);
  const toIsoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  return {
    startDate: toIsoDate(start),
    endDate: toIsoDate(today),
  };
}

export default function App() {
  const [activePage, setActivePage] = useState('dashboard');
  const [menuOpen, setMenuOpen] = useState(false);
  const [locations, setLocations] = useState(['Ahmedabad']);
  const defaultDateRange = useMemo(() => getDefaultDateRange(), []);
  const [filters, setFilters] = useState({
    location: 'Ahmedabad',
    startDate: defaultDateRange.startDate,
    endDate: defaultDateRange.endDate,
    month: 1,
    year: 2026,
    viewMode: 'daily',
  });
  const [activeFilterMode, setActiveFilterMode] = useState('monthyear');
  const [applied, setApplied] = useState(filters);
  const [daily, setDaily] = useState([]);
  const [summary, setSummary] = useState(null);
  const [marketInsight, setMarketInsight] = useState({ analysis: '', source: 'heuristic' });
  const [monthly, setMonthly] = useState([]);
  const [yearly, setYearly] = useState([]);
  const [reportYear, setReportYear] = useState(2026);
  const [loading, setLoading] = useState(false);
  const [insightLoading, setInsightLoading] = useState(false);
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

  const loadMarketInsight = useCallback(async (params = applied) => {
    setInsightLoading(true);
    try {
      const insight = await fetchMarketAnalysis(params);
      setMarketInsight(insight);
    } catch (err) {
      setMarketInsight({ analysis: 'Unable to load market insight for this selected period.', source: 'heuristic' });
    } finally {
      setInsightLoading(false);
    }
  }, [applied]);

  useEffect(() => {
    fetchLocations().then((items) => { if (items.length) { setLocations(items); setFilters((old) => ({ ...old, location: items.includes(old.location) ? old.location : items[0] })); } }).catch(() => {});
    loadRange(filters);
    loadReports(filters.location, reportYear);
    loadMarketInsight(filters);
  }, []);

  useEffect(() => { if (activePage === 'monthly') loadReports(filters.location, reportYear); }, [activePage, filters.location, reportYear, loadReports]);
  useEffect(() => { loadMarketInsight(applied); }, [applied, loadMarketInsight]);

  const onFilterChange = (key, value) => setFilters((old) => ({ ...old, [key]: value }));

  const applyMonthYearFilter = () => {
    const computedFilters = {
      ...filters,
      startDate: filters.viewMode === 'monthly'
        ? `${filters.year}-01-01`
        : new Date(filters.year, filters.month - 1, 1).toISOString().slice(0, 10),
      endDate: filters.viewMode === 'monthly'
        ? `${filters.year}-12-31`
        : new Date(filters.year, filters.month, 0).toISOString().slice(0, 10),
    };

    setActiveFilterMode('monthyear');
    setFilters((old) => ({ ...old, ...computedFilters }));
    setApplied(computedFilters);
    loadRange(computedFilters);
    loadMarketInsight(computedFilters);
  };

  const applyDateRangeFilter = () => {
    setActiveFilterMode('date-range');
    setApplied(filters);
    loadRange(filters);
    loadMarketInsight(filters);
  };

  const onApply = () => applyDateRangeFilter();
  const onRefresh = () => { loadRange(applied); loadMarketInsight(applied); if (activePage === 'monthly') loadReports(filters.location, reportYear); };
  const [title, subtitle] = pageMeta[activePage];

  return (
    <div className="app-shell">
      <Sidebar activePage={activePage} onNavigate={setActivePage} open={menuOpen} onToggle={(value) => setMenuOpen(typeof value === 'boolean' ? value : !menuOpen)} />
      <main className="main-content">
        <Topbar title={title} subtitle={subtitle} config={config} onRefresh={onRefresh} refreshing={loading} />
        {error && <div className="error-banner">{error}<button onClick={() => setError('')}>Dismiss</button></div>}
        {activePage === 'dashboard' && <Dashboard locations={locations} filters={filters} onFilterChange={onFilterChange} onApply={onApply} summary={summary} daily={daily} loading={loading} marketInsight={marketInsight} insightLoading={insightLoading} />}
        {activePage === 'daily' && (
          <DailyPrices
            locations={locations}
            filters={filters}
            activeFilterMode={activeFilterMode}
            onFilterChange={onFilterChange}
            onApplyMonthYear={applyMonthYearFilter}
            onApplyDateRange={applyDateRangeFilter}
            daily={daily}
          />
        )}
        {activePage === 'monthly' && <MonthlyReport locations={locations} location={filters.location} year={reportYear} onLocation={(location) => setFilters((old) => ({ ...old, location }))} onYear={setReportYear} monthly={monthly} yearly={yearly} />}
        {activePage === 'analysis' && <CustomAnalysis locations={locations} filters={filters} onFilterChange={onFilterChange} onApply={onApply} summary={summary} daily={daily} marketInsight={marketInsight} insightLoading={insightLoading} />}
      </main>
    </div>
  );
}
