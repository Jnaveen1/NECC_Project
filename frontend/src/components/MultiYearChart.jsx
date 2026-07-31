import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const LINE_COLORS = ['#2563eb', '#16a34a', '#f59e0b'];

export default function MultiYearChart({ data, years = [], mode = 'months' }) {
  const xKey = mode === 'months' ? 'month' : 'label';

  return (
    <div className="chart-wrap">
      <ResponsiveContainer width="100%" height={330}>
        <LineChart data={data} margin={{ top: 10, right: 12, left: 0, bottom: 8 }}>
          <CartesianGrid strokeDasharray="4 4" vertical={false} stroke="#e5e7eb" />
          <XAxis dataKey={xKey} axisLine={false} tickLine={false} minTickGap={18} />
          <YAxis axisLine={false} tickLine={false} width={52} tickFormatter={(v) => `₹${v}`} />
          <Tooltip
            formatter={(value) => [`₹${Number(value).toFixed(2)}`, 'Average']}
            labelFormatter={(label) => label}
          />
          <Legend />
          {years.map((year, index) => (
            <Line
              key={year}
              type="monotone"
              dataKey={String(year)}
              name={String(year)}
              stroke={LINE_COLORS[index % LINE_COLORS.length]}
              strokeWidth={3}
              dot={{ r: 2 }}
              activeDot={{ r: 5 }}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
