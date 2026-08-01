export default function PriceTable({ data, dates, viewMode }) {
  const renderHeader = (value) => {
    if (viewMode === 'monthly') {
      return new Date(2024, Number(value) - 1, 1).toLocaleString('en-US', { month: 'short' });
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: '2-digit',
    });
  };

  return (
    <div className="table-shell">
      <div className="table-scroll full-page-table">
        <table className="matrix-table">
          <thead>
            <tr>
              <th className="location-head">Location</th>
              {dates.map((value) => (
                <th key={value} className="date-head">
                  {renderHeader(value)}
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {data.map((row) => (
              <tr key={row.location}>
                <td className="location-name">{row.location}</td>
                {row.values.map((value, index) => {
                  const previousValue = index > 0 ? row.values[index - 1] : null;
                  const isUp = value !== null && previousValue !== null && value > previousValue;
                  const isDown = value !== null && previousValue !== null && value < previousValue;
                  const arrow = value === null || previousValue === null ? '' : isUp ? '▲' : isDown ? '▼' : '•';

                  return (
                    <td key={`${row.location}-${dates[index]}`} className="price-cell">
                      <span className={`price-tag ${isUp ? 'up' : isDown ? 'down' : 'flat'}`}>
                        {value === null ? '—' : `₹${Number(value).toFixed(2)}`}
                        {arrow ? ` ${arrow}` : ''}
                      </span>
                    </td>
                  );
                })}
              </tr>
            ))}

            {data.length === 0 && (
              <tr>
                <td colSpan={dates.length + 1} className="empty-table">
                  No records found for the selected period.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}