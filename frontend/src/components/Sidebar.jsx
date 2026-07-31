import { BarChart3, CalendarRange, Gauge, LineChart, Menu, X } from 'lucide-react';

const items = [
  { id: 'dashboard', label: 'Dashboard', icon: Gauge },
  { id: 'daily', label: 'Daily Prices', icon: LineChart },
  { id: 'monthly', label: 'Monthly Report', icon: BarChart3 },
  { id: 'analysis', label: 'Custom Analysis', icon: CalendarRange },
];

export default function Sidebar({ activePage, onNavigate, open, onToggle }) {
  return (
    <>
      <button className="mobile-menu" onClick={onToggle} aria-label="Toggle menu">
        {open ? <X size={20} /> : <Menu size={20} />}
      </button>
      <aside className={`sidebar ${open ? 'sidebar-open' : ''}`}>
        <div className="brand">
          <div className="brand-mark">N</div>
          <div><strong>NECC</strong><span>Price Intelligence</span></div>
        </div>
        <nav>
          {items.map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              className={activePage === id ? 'nav-item active' : 'nav-item'}
              onClick={() => { onNavigate(id); onToggle(false); }}
            >
              <Icon size={19} /><span>{label}</span>
            </button>
          ))}
        </nav>
        <div className="sidebar-footer">
          <span className="status-dot" />
          <div><strong>NECC Data</strong><small>Historical price analytics</small></div>
        </div>
      </aside>
    </>
  );
}
