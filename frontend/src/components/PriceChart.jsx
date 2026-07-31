import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

function TickDate({ x, y, payload }) {
  const date = new Date(`${payload.value}T00:00:00`);
  return <text x={x} y={y + 14} textAnchor="middle" className="chart-tick">{date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })}</text>;
}

export default function PriceChart({ data }) {
  return (
    <div className="chart-wrap">
      <ResponsiveContainer width="100%" height={330}>
        <LineChart data={data} margin={{ top: 10, right: 12, left: 0, bottom: 8 }}>
          <CartesianGrid strokeDasharray="4 4" vertical={false} stroke="#e5e7eb" />
          <XAxis dataKey="date" tick={<TickDate />} minTickGap={42} axisLine={false} tickLine={false} />
          <YAxis axisLine={false} tickLine={false} width={52} tickFormatter={(v) => `₹${v}`} />
          <Tooltip formatter={(value) => [`₹${Number(value).toFixed(2)}`, 'Price']} labelFormatter={(label) => new Date(`${label}T00:00:00`).toLocaleDateString('en-IN', { dateStyle: 'medium' })} />
          <Line type="monotone" dataKey="price" stroke="#2563eb" strokeWidth={3} dot={false} activeDot={{ r: 5 }} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
