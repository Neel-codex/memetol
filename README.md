# MemeRadar AI

A production-ready, real-time **AI Meme Coin Scanner** SaaS platform. It detects new meme coins across 5 chains, scores rug-pull risk with an AI engine, tracks smart-money wallets, and sends instant alerts.

Built with **PHP 8 + MySQL (PDO)**, **Bootstrap 5**, vanilla **ES6**, **Chart.js** and **Font Awesome**. Designed to deploy on **shared cPanel hosting** — no Node.js, no Docker.

---

## Features

- **Auth system** — register, login, logout, forgot/reset password, remember-me, CSRF protection, bcrypt hashing, rate limiting.
- **Dashboard** — new tokens today, trending coins, AI buy signals, total volume, plus volume / market-cap / social-trend / AI-sentiment charts.
- **New coin scanner** — auto-scans Ethereum, BNB Chain, Solana, Base & Polygon via DexScreener (GeckoTerminal & CoinGecko configurable).
- **AI risk score (0–100)** — liquidity lock, holder distribution, volume growth, social activity, token age, smart-money buys, whale concentration.
- **Rug-pull detector** — mint function, honeypot, unlocked liquidity, owner privileges, hidden taxes, blacklist, whale > 40%.
- **Trending** — Most Viewed, Top Gainers, Trending, New Listings, AI Picks with watchlist & alert buttons.
- **Watchlist**, **Smart Money Tracker** (follow wallets), and a full **Alert system** (price / volume / new-listing / AI-buy via browser, Telegram & email).
- **Admin panel** — dashboard, users, coins, plans, APIs (with test connection), ads (incl. AdSense), banners (with scheduling & upload), settings (SEO, theme, Telegram, SMTP, maintenance) and logs.
- **SEO** — robots.txt, dynamic sitemap.xml, Open Graph, Twitter Cards, Schema.org markup.
- **Security** — PDO prepared statements, CSRF tokens, XSS escaping, session hardening, rate limiting, protected config & uploads.

---

## Quick install (cPanel)

1. Upload the project to your `public_html` (or a subfolder) and create a MySQL database + user in cPanel.
2. Make sure `config/` and `assets/uploads/` are writable.
3. Visit `https://yourdomain.com/install/` and follow the wizard:
   - It checks server requirements.
   - You enter DB credentials, site URL and your admin username/password.
   - It imports the schema, seeds demo data and writes `config/config.php`.
4. **Delete the `/install/` folder** after setup.
5. Log in to the admin panel at `/admin/`.

> Default seeded credentials (changed during install):
> - Admin: `admin` / `admin123` (forced password change on first login if you skip the wizard step)
> - Demo user: `demo@memeradar.ai` / `demo123`

### Manual install

If you prefer not to use the wizard:

1. Import `database/memeradar.sql` via phpMyAdmin.
2. Copy `config/config.sample.php` to `config/config.php` and fill in your DB + URL.

---

## Cron job (auto scanning)

Add a cron job in cPanel to scan coins and dispatch alerts every 10 minutes:

```
*/10 * * * * /usr/bin/php /home/USERNAME/public_html/cron/scan.php >> /home/USERNAME/cron.log 2>&1
```

Or trigger over HTTP (set a `cron_token` setting first):

```
*/10 * * * * curl -s "https://yourdomain.com/cron/scan.php?token=YOURTOKEN"
```

---

## Project structure

```
/                 public pages (index, login, register, dashboard, coin, trending, watchlist, alerts, pricing, profile)
/admin            admin panel (dashboard, users, coins, ads, plans, api, settings, banners, logs)
/api              REST API router (index.php)
/assets           css, js, images, uploads
/config           configuration (config.php — generated)
/cron             scan.php (scanner + alert dispatcher)
/database         memeradar.sql schema + seed
/includes         core classes (Database, Auth, Security, Settings, Scanner, RiskEngine, AlertEngine, Telegram, Http) + partials
/install          one-click installation wizard
```

---

## REST API

Base: `/api/{route}` (rewrite) or `/api/index.php?route={route}`.

| Route | Method | Description |
|-------|--------|-------------|
| `coins` | GET | List coins (`?chain=&limit=`) |
| `coin` | GET | Single coin + AI analysis (`?id=`) |
| `search` | GET | Live DexScreener + DB search (`?q=`) |
| `scan` | POST | Run the scanner (CSRF, rate-limited) |
| `watchlist` | POST | Toggle a coin in the user watchlist |
| `follow` | POST | Toggle following a wallet |
| `smart-money` | GET | Recent smart-money transactions |
| `stats` | GET | Platform stats |
| `csrf` | GET | Get a CSRF token |

---

## Disclaimer

MemeRadar AI is an analytics tool. Nothing here is financial advice. Crypto is high risk — always do your own research.
