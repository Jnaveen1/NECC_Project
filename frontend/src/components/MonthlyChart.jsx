import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export default function MonthlyChart({ data }) {
  const normalized = data.map((item) => ({ ...item, name: months[item.month - 1] }));
  return (
    <ResponsiveContainer width="100%" height={330}>
      <BarChart data={normalized} margin={{ top: 10, right: 12, left: 0, bottom: 8 }}>
        <CartesianGrid strokeDasharray="4 4" vertical={false} stroke="#e5e7eb" />
        <XAxis dataKey="name" axisLine={false} tickLine={false} />
        <YAxis axisLine={false} tickLine={false} width={52} tickFormatter={(v) => `₹${v}`} />
        <Tooltip formatter={(value) => [`₹${Number(value).toFixed(2)}`, 'Average']} />
        <Bar dataKey="average_price" fill="#2563eb" radius={[7, 7, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}
