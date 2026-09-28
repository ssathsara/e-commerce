# ShopSphere SPA (React + Vite + Tailwind CSS)

Storefront and admin portal. See the [project README](../README.md) and
[docs/architecture.md](../docs/architecture.md#frontend).

```bash
npm install && cp .env.example .env
npm run dev        # http://localhost:5173 (proxies /api, /sanctum, /storage to Laravel)
npm test           # Vitest
npm run lint       # oxlint
npm run build      # production build → dist/
```
