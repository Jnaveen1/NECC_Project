import { useEffect, useMemo, useState } from 'react';
import Filters from '../components/Filters';
import Filters2 from '../components/Filters2';

import PriceTable from '../components/PriceTable';
import { fetchDailyPrices, fetchMonthlySummary } from '../services/api';

function toLocalDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getDateRange(startDate, endDate) {
  const start = new Date(`${startDate}T00:00:00`);
  const end = new Date(`${endDate}T00:00:00`);
  const dates = [];

  for (let current = new Date(start); current <= end; current.setDate(current.getDate() + 1)) {
    dates.push(toLocalDateString(current));
  }

  return dates;
}

function getMonthDateRange(year, month) {
  const start = new Date(year, month - 1, 1);
  const end = new Date(year, month, 0);
  return {
    startDate: toLocalDateString(start),
    endDate: toLocalDateString(end),
  };
}

export default function DailyPrices({
  locations,
  filters,
  activeFilterMode,
  onFilterChange,
  onApplyMonthYear,
  onApplyDateRange,
}) {
  const [matrixData, setMatrixData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const viewMode = filters.viewMode || 'daily';
  const resolvedMode = activeFilterMode === 'date-range' ? 'date-range' : 'monthyear';

  const dates = useMemo(() => {
    if (resolvedMode === 'date-range') {
      return getDateRange(filters.startDate, filters.endDate);
    }

    if (viewMode === 'monthly') {
      return Array.from({ length: 12 }, (_, index) => index + 1);
    }

    const { startDate, endDate } = getMonthDateRange(filters.year, filters.month);
    return getDateRange(startDate, endDate);
  }, [filters.year, filters.month, filters.startDate, filters.endDate, viewMode, resolvedMode]);

  useEffect(() => {
    let isMounted = true;

    const loadMatrix = async () => {
      if (!locations.length) {
        setMatrixData([]);
        return;
      }

      setLoading(true);
      setError('');

      try {
        const allLocations = await Promise.all(
          locations.map(async (location) => {
            try {
if (resolvedMode === 'date-range') {
              const rows = await fetchDailyPrices({
                location,
                startDate: filters.startDate,
                endDate: filters.endDate,
              });

              const priceMap = Object.fromEntries(
                rows.map((row) => [row.date, Number(row.price)])
              );

              return {
                location,
                values: getDateRange(filters.startDate, filters.endDate).map((date) => {
                  const value = priceMap[date];
                  return value !== undefined ? value : null;
                }),
              };
            }

            if (viewMode === 'monthly') {
                const rows = await fetchMonthlySummary({ location, year: filters.year });
                const priceMap = Object.fromEntries(
                  rows.map((row) => [Number(row.month), Number(row.average_price)])
                );

                return {
                  location,
                  values: Array.from({ length: 12 }, (_, index) => {
                    const monthNumber = index + 1;
                    return priceMap[monthNumber] ?? null;
                  }),
                };
              }

              const monthRange = getMonthDateRange(filters.year, filters.month);
              const rows = await fetchDailyPrices({
                location,
                startDate: monthRange.startDate,
                endDate: monthRange.endDate,
              });

              const priceMap = Object.fromEntries(
                rows.map((row) => [row.date, Number(row.price)])
              );

              return {
                location,
                values: dates.map((date) => {
                  const value = priceMap[date];
                  return value !== undefined ? value : null;
                }),
              };
            } catch (locationError) {
              return { location, values: Array(dates.length).fill(null) };
            }
          })
        );

        if (isMounted) setMatrixData(allLocations);
      } catch (err) {
        if (isMounted) {
          setError(err.response?.data?.detail || err.message || 'Unable to load daily matrix.');
          setMatrixData([]);
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    loadMatrix();

    return () => {
      isMounted = false;
    };
  }, [locations, filters.year, filters.month, filters.startDate, filters.endDate, viewMode, resolvedMode, dates]);

  return (
    <>
    <Filters2
        locations={locations}
        values={filters}
        activeFilterMode={activeFilterMode}
        onChange={onFilterChange}
        onApplyMonthYear={onApplyMonthYear}
        onApplyDateRange={onApplyDateRange}
        compact
      />
      {/* <Filters
        locations={locations}
        values={filters}
        activeFilterMode={activeFilterMode}
        onChange={onFilterChange}
        onApplyMonthYear={onApplyMonthYear}
        onApplyDateRange={onApplyDateRange}
        compact
      /> */}
      <section className="panel">
        <div className="panel-head">
          <div>
            <h2>Daily price records</h2>
            <p>Review all locations across the selected period</p>
          </div>
        </div>

        {loading && <div className="table-status">Loading daily matrix...</div>}
        {error && <div className="error-banner">{error}</div>}
        {!loading && !error && <PriceTable data={matrixData} dates={dates} viewMode={viewMode} />}
      </section>
    </>
  );
}
