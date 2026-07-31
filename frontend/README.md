# NECC Frontend

## Run with demo data

```powershell
cd frontend
npm install
copy .env.example .env
npm run dev
```

The default `.env.example` uses mock data, so the complete UI works without MySQL or FastAPI.

## Connect to the office backend

Edit `frontend/.env`:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000
VITE_USE_MOCK_DATA=false
```

Then restart `npm run dev`.

## Production build

```powershell
npm run build
```

The output is created in `frontend/dist`.
