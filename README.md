# CheckIN Events

**Version:** 0.0.8
**Author:** Norsk Interaktiv AS, Martin Morfjord

A WordPress plugin that integrates with the [CheckIN](https://checkin.no) event registration platform. It displays CheckIN events as a custom post type and lets you add events by ID or bulk-import them from your CheckIN account.

---

## What it does

### Custom Post Type
Registers a `checkin_event` post type with its own admin menu, archive, and permalink slug (`/checkin-events/`). Each event post is linked to a CheckIN event by storing the CheckIN Event ID as post meta.

### Admin Menu
Four pages under the **CheckIN Events** menu:

| Page | Purpose |
|------|---------|
| Alle Events | Lists all `checkin_event` posts with their CheckIN IDs, status, and shortcodes |
| Legg til Event | Standard WordPress new-post screen for `checkin_event` |
| Importer fra CheckIN | Fetch all events from the CheckIN API and import them with one click, or add a single event manually by ID |
| Innstillinger | Store your CheckIN customer number, API key, and API base URL |

### Settings
Saved to WordPress options:
- `checkin_customer_number` — your CheckIN organizer ID
- `checkin_api_key` — Bearer token for the API (optional)
- `checkin_api_base_url` — defaults to `https://api.checkin.no/v1`

### Event Editor Panel
A meta panel is injected above the post title on the `checkin_event` edit screen. From here you can:
- Set or change the CheckIN Event ID
- Fetch/refresh event data from the API (description, start date, end date)
- Toggle automatic display of the registration widget, description, and dates on the frontend
- Copy ready-made shortcodes

### API & Caching
Event data is fetched from `https://api.checkin.no/v1`:
- **Event list** — cached for 5 minutes via WordPress transients
- **Single event** — cached for 1 hour via transients; also stored in post meta so repeat page loads never hit the API

Data is fetched automatically when:
- A new CheckIN Event ID is saved for the first time
- The ID changes
- The editor clicks "Oppdater fra CheckIN" (force refresh)

### Frontend Auto-Display
On singular `checkin_event` pages, the plugin prepends/appends content automatically (unless toggled off in the editor):
1. **Dates** — start and end date from the CheckIN API
2. **Description** — "Om arrangementet" from the CheckIN API
3. **Registration widget** — the CheckIN embed script (`registration.checkin.no`)

### Shortcodes

| Shortcode | Output |
|-----------|--------|
| `[checkin_event]` | Registration widget for the current post |
| `[checkin_event id="123103"]` | Registration widget for a specific event ID |
| `[checkin_event show="description,dates,embed"]` | Any combination of description, dates, and embed |
| `[checkin_description]` | Event description for the current post |
| `[checkin_description id="123103"]` | Event description by ID |
| `[checkin_start_date]` | Formatted start date for the current post |
| `[checkin_start_date id="123103" format="d. F Y \k\l. H:i"]` | Start date with custom PHP date format |
| `[checkin_end_date]` | Formatted end date for the current post |
| `[checkin_end_date id="123103" format="d. F Y \k\l. H:i"]` | End date with custom PHP date format |

Shortcodes without an explicit `id` resolve the CheckIN Event ID from the current post automatically, which means they work inside **Beaver Builder Themer** singular post templates as well as standard post content.

### Beaver Builder Themer Integration
Registers five dynamic field properties under the **posts** group:

| Field label | Type | Description |
|-------------|------|-------------|
| CheckIN – Event ID | string | Raw event ID |
| CheckIN – Registreringswidget | html | Full registration embed HTML |
| CheckIN – Om arrangementet | html | Event description |
| CheckIN – Startdato | string | Formatted start date |
| CheckIN – Sluttdato | string | Formatted end date |

---

## Installation

1. Upload the `checkin-events` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins > Installed Plugins**.
3. Go to **CheckIN Events > Innstillinger** and enter your customer number and API key.
4. Import events via **CheckIN Events > Importer fra CheckIN**, or add them manually.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A CheckIN account at [checkin.no](https://checkin.no)
