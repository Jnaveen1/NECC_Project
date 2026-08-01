import { CalendarDays, Search } from 'lucide-react';

const monthOptions = Array.from({ length: 12 }, (_, index) => ({
  value: index + 1,
  label: new Date(2024, index, 1).toLocaleString('en-US', { month: 'long' }),
}));

const yearOptions = Array.from({ length: 8 }, (_, index) => 2020 + index);

export default function Filters({
  locations,
  values,
  activeFilterMode,
  onChange,
  onApply,
  onApplyMonthYear,
  onApplyDateRange,
  compact = false,
}) {
  const handlePrimaryApply = onApplyMonthYear || onApply || (() => {});
  const handleSecondaryApply = onApplyDateRange || onApply || (() => {});

  return (
    <>
      <section className={`filters-card ${compact ? 'compact' : ''} ${activeFilterMode && activeFilterMode !== 'monthyear' ? 'filter-disabled' : ''}`}>
        <label>
          <span><CalendarDays size={15} /> Month</span>
          <select
            value={values.month ?? 1}
            onChange={(e) => onChange('month', Number(e.target.value))}
            disabled={values.viewMode === 'monthly' || activeFilterMode === 'date-range'}
          >
            {monthOptions.map((month) => (
              <option key={month.value} value={month.value}>{month.label}</option>
            ))}
          </select>
        </label>

        <label>
          <span><CalendarDays size={15} /> Year</span>
          <select
            value={values.year ?? 2026}
            onChange={(e) => onChange('year', Number(e.target.value))}
            disabled={activeFilterMode === 'date-range'}
          >
            {yearOptions.map((year) => (
              <option key={year} value={year}>{year}</option>
            ))}
          </select>
        </label>

        <label className="toggle-group">
          <span><CalendarDays size={15} /> View</span>
          <div className="radio-row">
            <label className="radio-option">
              <input
                type="radio"
                name="daily-view-mode"
                value="daily"
                checked={values.viewMode === 'daily'}
                onChange={(e) => onChange('viewMode', e.target.value)}
                disabled={activeFilterMode === 'date-range'}
              />
              <span>Daily</span>
            </label>
            <label className="radio-option">
              <input
                type="radio"
                name="daily-view-mode"
                value="monthly"
                checked={values.viewMode === 'monthly'}
                onChange={(e) => onChange('viewMode', e.target.value)}
                disabled={activeFilterMode === 'date-range'}
              />
              <span>Monthly</span>
            </label>
          </div>
        </label>

        <div className="button-column">
          <button className="primary-button" onClick={handlePrimaryApply}><Search size={16} /> Apply</button>
        </div>
      </section>

      <section className={`filters-card ${compact ? 'compact' : ''} ${activeFilterMode === 'monthyear' ? 'filter-disabled' : ''}`}>
        <label>
          <span><CalendarDays size={15} /> Start date</span>
          <input
            type="date"
            value={values.startDate}
            onChange={(e) => onChange('startDate', e.target.value)}
            disabled={activeFilterMode === 'monthyear'}
          />
        </label>

        <label>
          <span><CalendarDays size={15} /> End date</span>
          <input
            type="date"
            value={values.endDate}
            onChange={(e) => onChange('endDate', e.target.value)}
            disabled={activeFilterMode === 'monthyear'}
          />
        </label>

        <div className="button-column">
          <button className="primary-button" onClick={handleSecondaryApply}><Search size={16} /> Apply</button>
        </div>
      </section>
    </>
  );
}
