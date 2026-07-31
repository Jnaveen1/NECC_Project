export default function SummaryCard({ label, value, helper, icon: Icon, trend }) {
  return (
    <article className="summary-card">
      <div className="summary-head"><span>{label}</span><span className="summary-icon"><Icon size={18} /></span></div>
      <strong>{value}</strong>
      <div className="summary-foot">
        {trend && <span className={trend.direction === 'up' ? 'trend up' : 'trend down'}>{trend.direction === 'up' ? '▲' : '▼'} {trend.value}</span>}
        <small>{helper}</small>
      </div>
    </article>
  );
}
