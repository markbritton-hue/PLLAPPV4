# PowerLift Live (PLL App)

> **Disclaimer:** PowerLift Live (PLL App) is an unofficial, independent tool and is **not the official scoring app** for any competition. It is **not affiliated with, endorsed by, or supported by the Michigan High School Powerlifting Association (MHSPL)**. For official results, always refer to the meet director or official MHSPL resources.

A real-time powerlifting competition companion app that reads live data directly from your meet's Google Sheet. No backend, no app install — just open it in a browser.

---

## What It Does

PowerLift Live connects to a Google Sheets spreadsheet (the kind used by meet management software like TRPL) and displays the data in a clean, easy-to-read format across six views:

| Page | Who It's For | What It Shows |
|---|---|---|
| **Team Athletes** | Coaches / Athletes | Your school's varsity and JV athletes organized by weight class, with all three attempts per lift and running totals |
| **Coaches View** | Coaches on the floor | Only your athletes — filtered to show who is currently **lifting** or **on deck** across every platform |
| **Platform View** | Anyone at a platform | The full lifting order for one specific platform, with current lifter highlighted |
| **Display View** | Scoreboard / big screen | A TV-friendly layout showing all platforms and their current lifting order simultaneously |
| **All Results** | Everyone | Complete meet results grouped and ranked by weight class for all schools |
| **Team Standings** | Coaches / Fans | School-by-school team point totals from the Varsity Team sheet, sorted by total points |

All pages support **auto-refresh** so data stays current during the meet. The refresh interval is configurable, and the on/off state is remembered across page reloads.

---

## Requirements

- A Google Sheets spreadsheet shared as **"Anyone with the link can view"**
- The spreadsheet must follow the standard TRPL column layout used by your meet management software
- A modern browser (Chrome, Firefox, Edge, Safari) — works on phones and tablets too

---

## Getting Started

### Step 1 — Share your Google Sheet

1. Open the competition Google Sheet in your browser
2. Click **Share** → **Change to anyone with the link** → set permission to **Viewer**
3. Copy the share link — it will look like:
   `https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit?usp=sharing`

### Step 2 — Open the app and go to Setup

Open `index.html` in your browser (or navigate to the hosted URL), then click **Setup**.

### Step 3 — Enter the Google Sheet URL

In the **Spreadsheet URL** field, paste the link you copied in Step 1.

> **Tip:** If you're on a phone at the meet, tap **📷 Scan QR** and scan the QR code for the sheet instead of typing the URL.

### Step 4 — Auto-detect sheets

Click **🔍 Auto-Detect All Sheets**. The app will find all tabs in the spreadsheet (platforms, varsity, JV, results, team standings, etc.) and list them automatically.

### Step 5 — Enter your school name

In the **School Name** field, type your school's name exactly as it appears in the spreadsheet (e.g. `Northwest` or `Three Rivers`). This is used to filter and highlight your athletes across all views.

### Step 6 — Set auto-refresh interval

Enter how often (in seconds) you want pages to refresh during the meet. **20 seconds** is a good default. You can always toggle auto-refresh on/off from any page.

### Step 7 — Save

Click **💾 Save Configuration**. That's it — navigate to any page and data will load automatically.

---

## Page-by-Page Guide

### Team Athletes
Shows every athlete from **your school** across Sheet 1 (Varsity) and Sheet 2 (JV), grouped by weight class in ascending order. Each card shows:
- Body weight and lb-per-lb ratio
- All three attempts for squat, bench, and deadlift (color-coded: green = good lift, red = failed)
- Running total
- Platform assignment badge (purple, if the athlete appears on a platform sheet)

### Coaches View
Filtered to **your athletes only**. Shows which platform each athlete is on, their current position in the lifting order, and whether they are:
- **LIFTING** — currently on the platform
- **ON DECK** — next up
- **Position 3+** — further back in the queue

Use the auto-refresh toggle to keep this live during the meet.

### Platform View
Select a specific platform from the dropdown. Shows all athletes in lifting order for that platform with their upcoming attempt weights.

### Display View
A full-screen scoreboard layout showing all platforms side by side. Designed to be displayed on a TV or projector at the meet.

### All Results
Loads from the sheet named **"Varsity Results"** (or whichever sheet contains those words). Athletes are grouped by weight class and ranked within each class by total. Your school's athletes are highlighted in green.

### Team Standings
Loads from the sheet named **"Varsity Team"** (or whichever sheet contains those words). Shows all schools ranked by total team points.

---

## How the App Stores Configuration

All settings are saved in your browser's **localStorage** under the key `trplConfig`. This means:
- Configuration persists across page reloads and browser restarts
- Each browser/device needs to be set up separately
- Clearing browser data will erase the configuration (just run Setup again)

The stored config includes:
- `schoolName` — your school name for filtering
- `spreadsheetId` — extracted from the Google Sheet URL
- `sheets` — list of detected sheet tabs (name + GID)
- `autoRefreshSeconds` — the refresh interval

---

## Cache Server Architecture

All live data requests are routed through a server-side caching proxy hosted at `https://pllv4.streamwaveprod.com/cache/` rather than hitting Google Sheets directly. This reduces load on Google and speeds up data delivery for all connected clients.

### How It Works

The cache layer is a PHP proxy (`proxy.php`) that intercepts requests, serves cached responses when fresh, and only fetches from Google when the cache is stale.

**Cache types and TTLs:**

| Type | TTL | Used For |
|------|-----|----------|
| Sheet CSV | 20 seconds | Live athlete/results data |
| Sheet metadata (HTML) | 5 minutes | Discovering sheet names and GIDs |

**Cache files** are stored server-side in the temp directory using MD5-hashed filenames:
- `/tmp/pllapp_sheet_{md5(id_gid)}.csv`
- `/tmp/pllapp_meta_{md5(id)}.html`

**Request flow:**

```
Frontend → proxy.php → [cache fresh?] → return CSV  (X-Cache: HIT)
                     → [cache stale]  → fetch Google Sheets → cache → return CSV  (X-Cache: MISS)
                     → [fetch failed] → serve stale cache  (X-Cache: STALE)
```

### Frontend Endpoints

| Endpoint | Purpose |
|----------|---------|
| `cache/sheet_{id}_{gid}.csv` | Live sheet data |
| `cache/meta_{id}.html` | Sheet tab discovery |
| `cache/heartbeat.json` | Server health check |
| `cache/stats.json` | Cache status / offline detection |

The frontend reads `X-Cache` and `X-Cache-Age` response headers to display cache status in the UI.

### Meet Registration API

A Python Flask API (`api.py`) runs on `127.0.0.1:5000` and handles meet registration:

- `POST /api/register` — password-protected; registers a new meet, stores metadata in `/var/www/html/events.json`, updates `/etc/pllapp/urls.conf`, and triggers a background script (`fetch_meta.sh`) to pre-warm the cache
- `GET /api/events` — returns all registered meets as JSON

### Deployment

- Hosted on **IONOS**, deployed automatically via GitHub Actions (`.github/workflows/deploy.yml`)
- Registered sheet URLs: `/etc/pllapp/urls.conf`
- Meet metadata: `/var/www/html/events.json`

---

## Tips for Meet Day

- **Coaches View** on your phone is the most useful page during the meet — turn on auto-refresh and keep it open
- **Display View** on a laptop/TV gives spectators and coaches a full picture of all platforms
- If data stops updating, check that the Google Sheet is still shared publicly
- The app falls back to a CORS proxy if direct Google Sheets access is blocked on the network
- Auto-refresh state (on/off) is remembered per page, so you can set each page once
