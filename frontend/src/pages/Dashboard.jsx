import { Activity, CalendarCheck, CircleDollarSign, TrendingDown, TrendingUp } from 'lucide-react';
import Filters from '../components/Filters';
import PriceChart from '../components/PriceChart';
import SummaryCard from '../components/SummaryCard';

const money = (value) => value == null ? '—' : `₹${Number(value).toFixed(2)}`;

export default function Dashboard({ locations, filters, onFilterChange, onApply, summary, daily, loading, marketInsight, insightLoading }) {
  const previous = daily.length > 1 ? Number(daily[daily.length - 2].price) : null;
  const current = summary?.current_price;
  const change = previous == null || current == null ? 0 : current - previous;
  return (
    <>
      <Filters locations={locations} values={filters} onChange={onFilterChange} onApply={onApply} />
      <div className="summary-grid">
        <SummaryCard label="Current price" value={money(summary?.current_price)} helper={summary?.current_date || 'Selected period'} icon={CircleDollarSign} trend={{ direction: change >= 0 ? 'up' : 'down', value: `₹${Math.abs(change).toFixed(2)}` }} />
        <SummaryCard label="Average price" value={money(summary?.average_price)} helper="Selected range" icon={Activity} />
        <SummaryCard label="Minimum price" value={money(summary?.minimum_price)} helper="Lowest recorded" icon={TrendingDown} />
        <SummaryCard label="Maximum price" value={money(summary?.maximum_price)} helper="Highest recorded" icon={TrendingUp} />
        <SummaryCard label="Records" value={summary?.total_records ?? '—'} helper="Data points" icon={CalendarCheck} />
      </div>
      <section className="panel">
        <div className="panel-head"><div><h2>Price trend</h2><p>Daily NECC price movement for {filters.location}</p></div><span className="range-chip">{filters.startDate} → {filters.endDate}</span></div>
        {loading ? <div className="skeleton chart-skeleton" /> : <PriceChart data={daily} />}
        <div className="analysis-note">
          <strong>Market insight</strong>
          {insightLoading ? <p>Analyzing market conditions...</p> : <p>{marketInsight?.analysis || 'No insight available for this range.'}</p>}
        </div>
      </section>
    </>
  );
}
