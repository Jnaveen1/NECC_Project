import { CalendarDays, MapPin, Search } from 'lucide-react';

export default function Filters({ locations, values, onChange, onApply, compact = false }) {
  return (
    <section className={`filters-card ${compact ? 'compact' : ''}`}>
      <label>
        <span><MapPin size={15} /> Location</span>
        <select value={values.location} onChange={(e) => onChange('location', e.target.value)}>
          {locations.map((location) => <option key={location} value={location}>{location}</option>)}
        </select>
      </label>
      <label>
        <span><CalendarDays size={15} /> Start date</span>
        <input type="date" value={values.startDate} onChange={(e) => onChange('startDate', e.target.value)} />
      </label>
      <label>
        <span><CalendarDays size={15} /> End date</span>
        <input type="date" value={values.endDate} onChange={(e) => onChange('endDate', e.target.value)} />
      </label>
      <button className="primary-button" onClick={onApply}><Search size={16} /> Apply</button>
    </section>
  );
}
