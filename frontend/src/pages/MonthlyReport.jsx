import MonthlyChart from '../components/MonthlyChart';

const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

export default function MonthlyReport({ locations, location, year, onLocation, onYear, monthly, yearly }) {
  const officialYear = yearly.find((row) => Number(row.year) === Number(year));
  return (
    <>
      <section className="filters-card monthly-filters">
        <label><span>Location</span><select value={location} onChange={(e) => onLocation(e.target.value)}>{locations.map((item) => <option key={item}>{item}</option>)}</select></label>
        <label><span>Year</span><select value={year} onChange={(e) => onYear(Number(e.target.value))}>{[2026,2025,2024,2023].map((item) => <option key={item}>{item}</option>)}</select></label>
        <div className="official-average"><small>Year average</small><strong>{officialYear ? `₹${Number(officialYear.average_price).toFixed(2)}` : '—'}</strong></div>
      </section>
      <section className="panel">
        <div className="panel-head"><div><h2>Official monthly averages</h2><p>Monthly Average Sheet values for {location}</p></div></div>
        <MonthlyChart data={monthly} />
      </section>
      <section className="panel">
        <div className="panel-head"><div><h2>Monthly values</h2><p>Official average by month</p></div></div>
        <div className="month-grid">{monthly.map((item) => <article key={item.month}><span>{months[item.month - 1]}</span><strong>₹{Number(item.average_price).toFixed(2)}</strong>{item.minimum_price != null && <small>₹{Number(item.minimum_price).toFixed(0)} – ₹{Number(item.maximum_price).toFixed(0)}</small>}</article>)}</div>
      </section>
    </>
  );
}
