import { useEffect, useMemo, useState } from 'react';
import { Activity, CalendarCheck, CircleDollarSign, TrendingDown, TrendingUp } from 'lucide-react';
import Filters from '../components/Filters';
import Filters2 from '../components/Filters2';
import MultiYearChart from '../components/MultiYearChart';
import PriceChart from '../components/PriceChart';
import SummaryCard from '../components/SummaryCard';
import { fetchDailyPrices, fetchMonthlySummary } from '../services/api';

const money = (value) => value == null ? '—' : `₹${Number(value).toFixed(2)}`;
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function buildMultiYearMonthData(years, results) {
  const monthMap = monthNames.reduce((acc, month) => {
    acc[month] = { month };
    return acc;
  }, {});

  years.forEach((year, index) => {
    const matched = results[index] || [];
    matched.forEach((item) => {
      const key = monthNames[item.month - 1];
      if (key && monthMap[key]) {
        monthMap[key][String(year)] = Number(item.average_price);
      }
    });
  });

  return Object.values(monthMap).filter((item) => years.some((year) => item[String(year)] != null));
}

function toIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function buildMultiYearDayData(years, results) {
  const baseYear = 2024;
  const timeline = [];
  const cursor = new Date(baseYear, 0, 1);
  const end = new Date(baseYear, 11, 31);

  while (cursor <= end) {
    timeline.push({
      label: cursor.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' }),
      key: `${cursor.getMonth() + 1}-${cursor.getDate()}`,
    });
    cursor.setDate(cursor.getDate() + 1);
  }

  years.forEach((year) => {
    const matched = results[years.indexOf(year)] || [];
    const byKey = Object.fromEntries(
      (matched || []).map((item) => {
        const dt = new Date(`${item.date}T00:00:00`);
        return [`${dt.getMonth() + 1}-${dt.getDate()}`, Number(item.price)];
      })
    );

    timeline.forEach((point) => {
      point[String(year)] = byKey[point.key] ?? null;
    });
  });

  return timeline.map((point) => {
    const output = { label: point.label };
    years.forEach((year) => {
      output[String(year)] = point[String(year)] ?? null;
    });
    return output;
  });
}

export default function Dashboard({ locations, filters, onFilterChange, onApply, summary, daily, loading, marketInsight, insightLoading }) {
  const previous = daily.length > 1 ? Number(daily[daily.length - 2].price) : null;
  const current = summary?.current_price;
  const change = previous == null || current == null ? 0 : current - previous;
  const dateFormatter = (dateValue) => {
    if (!dateValue) return 'Selected period';
    const parsed = new Date(`${dateValue}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return dateValue;
    return parsed.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  };

  const maxEntry = useMemo(() => {
    if (!daily.length) return null;
    return daily.reduce((highest, item) => (Number(item.price) > Number(highest.price) ? item : highest), daily[0]);
  }, [daily]);

  const minEntry = useMemo(() => {
    if (!daily.length) return null;
    return daily.reduce((lowest, item) => (Number(item.price) < Number(lowest.price) ? item : lowest), daily[0]);
  }, [daily]);

  const years = useMemo(() => {
    const currentYear = new Date().getFullYear();
    return [currentYear, currentYear - 1, currentYear - 2];
  }, []);
  const [comparisonLocation, setComparisonLocation] = useState(filters.location || locations[0] || '');
  const [comparisonView, setComparisonView] = useState('months');
  const [multiYearMonthData, setMultiYearMonthData] = useState([]);
  const [multiYearDayData, setMultiYearDayData] = useState([]);
  const [multiYearLoading, setMultiYearLoading] = useState(false);

  useEffect(() => {
    if (!filters.location && locations.length) {
      setComparisonLocation(locations[0]);
    }
  }, [filters.location, locations]);

  useEffect(() => {
    let active = true;

    const loadComparison = async () => {
      setMultiYearLoading(true);
      try {
        const [monthResponses, dayResponses] = await Promise.all([
          Promise.all(
            years.map(async (year) => {
              try {
                return await fetchMonthlySummary({ location: comparisonLocation, year });
              } catch {
                return [];
              }
            })
          ),
          Promise.all(
            years.map(async (year) => {
              try {
                const start = new Date(`${year}-01-01T00:00:00`);
                const end = new Date(`${year}-12-31T00:00:00`);
                return await fetchDailyPrices({ location: comparisonLocation, startDate: toIsoDate(start), endDate: toIsoDate(end) });
              } catch {
                return [];
              }
            })
          ),
        ]);

        if (!active) return;
        setMultiYearMonthData(buildMultiYearMonthData(years, monthResponses));
        setMultiYearDayData(buildMultiYearDayData(years, dayResponses));
      } catch {
        if (active) {
          setMultiYearMonthData([]);
          setMultiYearDayData([]);
        }
      } finally {
        if (active) setMultiYearLoading(false);
      }
    };

    if (comparisonLocation) loadComparison();
    return () => { active = false; };
  }, [comparisonLocation, years]);

  const activeComparisonData = comparisonView === 'months' ? multiYearMonthData : multiYearDayData;

  return (
    <>
      <section className="panel comparison-panel">
        <div className="panel-head">
          <div><h2>Last 3 years comparison</h2><p>Compare {comparisonLocation || 'the selected location'} across the latest three years</p></div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
            <label className="comparison-select-wrap" aria-label="Comparison mode">
              <span>View</span>
              <select className="comparison-select" value={comparisonView} onChange={(e) => setComparisonView(e.target.value)}>
                <option value="months">Months</option>
                <option value="days">Days</option>
              </select>
            </label>
            <label className="comparison-select-wrap" aria-label="Comparison location">
              <span>Location</span>
              <select
                className="comparison-select"
                value={comparisonLocation}
                onChange={(e) => setComparisonLocation(e.target.value)}
              >
                {locations.map((location) => (
                  <option key={location} value={location}>{location}</option>
                ))}
              </select>
            </label>
          </div>
        </div>
        {multiYearLoading ? <div className="skeleton chart-skeleton" /> : activeComparisonData.length ? <MultiYearChart data={activeComparisonData} years={years} mode={comparisonView} /> : <p>No comparison data found for the selected location.</p>}
      </section>
      <Filters locations={locations} values={filters} onChange={onFilterChange} onApply={onApply} />
      <div className="summary-grid">
        <SummaryCard label="Current price" value={money(summary?.current_price)} helper={summary?.current_date ? `As of ${dateFormatter(summary.current_date)}` : 'Selected period'} icon={CircleDollarSign} trend={{ direction: change >= 0 ? 'up' : 'down', value: `₹${Math.abs(change).toFixed(2)}` }} />
        <SummaryCard label="Average price" value={money(summary?.average_price)} helper={`Range: ${dateFormatter(filters.startDate)} to ${dateFormatter(filters.endDate)}`} icon={Activity} />
        <SummaryCard label="Minimum price" value={money(summary?.minimum_price)} helper={minEntry ? `Lowest on ${dateFormatter(minEntry.date)}` : 'Lowest recorded'} icon={TrendingDown} />
        <SummaryCard label="Maximum price" value={money(summary?.maximum_price)} helper={maxEntry ? `Highest on ${dateFormatter(maxEntry.date)}` : 'Highest recorded'} icon={TrendingUp} />
        <SummaryCard label="Records" value={summary?.total_records ?? '—'} helper="Data points" icon={CalendarCheck} />
      </div>
      <section className="panel">
        <div className="panel-head"><div><h2>Price trend</h2><p>Daily NECC price movement for {filters.location}</p></div><span className="range-chip">{filters.startDate} → {filters.endDate}</span></div>
        {loading ? <div className="skeleton chart-skeleton" /> : <PriceChart data={daily} />}
        {/* <div className="analysis-note">
          <strong>Market insight</strong>
          {insightLoading ? <p>Analyzing market conditions...</p> : <p>{marketInsight?.analysis || 'No insight available for this range.'}</p>}
        </div> */}
      </section>
    </>
  );
}
