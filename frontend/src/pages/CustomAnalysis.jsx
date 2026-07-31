import { Activity, ArrowDownRight, ArrowUpRight, Sigma } from 'lucide-react';
import Filters from '../components/Filters';
import PriceChart from '../components/PriceChart';
import SummaryCard from '../components/SummaryCard';

export default function CustomAnalysis({ locations, filters, onFilterChange, onApply, summary, daily }) {
  const first = daily[0]?.price;
  const last = daily[daily.length - 1]?.price;
  const change = first == null || last == null ? 0 : Number(last) - Number(first);
  const percent = first ? (change / Number(first)) * 100 : 0;
  return (
    <>
      <Filters locations={locations} values={filters} onChange={onFilterChange} onApply={onApply} />
      <div className="summary-grid analysis-grid">
        <SummaryCard label="Period average" value={summary ? `₹${Number(summary.average_price).toFixed(2)}` : '—'} helper="Mean daily price" icon={Sigma} />
        <SummaryCard label="Net change" value={`${change >= 0 ? '+' : ''}₹${change.toFixed(2)}`} helper={`${percent >= 0 ? '+' : ''}${percent.toFixed(2)}%`} icon={change >= 0 ? ArrowUpRight : ArrowDownRight} />
        <SummaryCard label="Volatility range" value={summary ? `₹${(Number(summary.maximum_price) - Number(summary.minimum_price)).toFixed(2)}` : '—'} helper="Maximum minus minimum" icon={Activity} />
      </div>
      <section className="panel"><div className="panel-head"><div><h2>Custom period analysis</h2><p>Calculated from daily records in the selected range</p></div></div><PriceChart data={daily} /></section>
    </>
  );
}
