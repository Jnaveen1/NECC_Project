import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function PriceTable({ data, page, pageSize, onPage }) {
  const totalPages = Math.max(1, Math.ceil(data.length / pageSize));
  const rows = data.slice((page - 1) * pageSize, page * pageSize);
  return (
    <div className="table-shell">
      <div className="table-scroll">
        <table>
          <thead><tr><th>Date</th><th>Price</th><th>Change</th><th>Direction</th></tr></thead>
          <tbody>
            {rows.map((row) => {
              const globalIndex = data.findIndex((item) => item.date === row.date);
              const previous = globalIndex > 0 ? Number(data[globalIndex - 1].price) : Number(row.price);
              const change = Number(row.price) - previous;
              return (
                <tr key={row.date}>
                  <td>{new Date(`${row.date}T00:00:00`).toLocaleDateString('en-IN', { dateStyle: 'medium' })}</td>
                  <td className="price-cell">₹{Number(row.price).toFixed(2)}</td>
                  <td className={change > 0 ? 'positive' : change < 0 ? 'negative' : ''}>{change > 0 ? '+' : ''}{change.toFixed(2)}</td>
                  <td><span className={`direction-pill ${change > 0 ? 'rise' : change < 0 ? 'fall' : 'flat'}`}>{change > 0 ? '▲ Increase' : change < 0 ? '▼ Decrease' : '— No change'}</span></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="pagination">
        <span>Page {page} of {totalPages} · {data.length} records</span>
        <div>
          <button disabled={page === 1} onClick={() => onPage(page - 1)}><ChevronLeft size={17} /></button>
          <button disabled={page === totalPages} onClick={() => onPage(page + 1)}><ChevronRight size={17} /></button>
        </div>
      </div>
    </div>
  );
}
