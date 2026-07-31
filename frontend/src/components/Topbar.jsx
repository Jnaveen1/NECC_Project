import { Database, RefreshCw } from 'lucide-react';

export default function Topbar({ title, subtitle, config, onRefresh, refreshing }) {
  return (
    <header className="topbar">
      <div>
        <h1>{title}</h1>
        <p>{subtitle}</p>
      </div>
      <div className="topbar-actions">
        <span className={`mode-badge ${config.useMock ? 'mock' : 'live'}`}>
          <Database size={15} /> {config.useMock ? 'Demo data' : 'Live API'}
        </span>
        <button className="icon-button" onClick={onRefresh} aria-label="Refresh data">
          <RefreshCw size={18} className={refreshing ? 'spin' : ''} />
        </button>
      </div>
    </header>
  );
}
