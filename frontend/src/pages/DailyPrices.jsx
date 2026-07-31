import { useState } from 'react';
import Filters from '../components/Filters';
import PriceTable from '../components/PriceTable';

export default function DailyPrices({ locations, filters, onFilterChange, onApply, daily }) {
  const [page, setPage] = useState(1);
  const apply = () => { setPage(1); onApply(); };
  return (
    <>
      <Filters locations={locations} values={filters} onChange={onFilterChange} onApply={apply} compact />
      <section className="panel">
        <div className="panel-head"><div><h2>Daily price records</h2><p>Review date-wise prices and movement</p></div></div>
        <PriceTable data={daily} page={page} pageSize={12} onPage={setPage} />
      </section>
    </>
  );
}
