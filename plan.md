# Simple Visitor Analytics — Development Plan

## 1. Goal
Privacy-friendly, lightweight WP analytics plugin. Portfolio-grade code: clean architecture, security-first, wp.org submission ready.

## 2. Tech Stack
- PHP 7.4+ (OOP, PSR-12), WordPress Plugin API
- MySQL (custom tables via `$wpdb`, not postmeta)
- Vanilla JS or Chart.js for dashboard graphs (no heavy build tools needed, but Webpack/`@wordpress/scripts` if you want it modern)
- No external API calls (aligns with "no external services" promise)

## 3. Folder Structure
```
simple-visitor-analytics/
├── simple-visitor-analytics.php   (main bootstrap file)
├── uninstall.php
├── includes/
│   ├── class-sva-loader.php
│   ├── class-sva-activator.php
│   ├── class-sva-tracker.php      (frontend hit logging)
│   ├── class-sva-db.php           (schema + queries)
│   ├── class-sva-aggregator.php   (cron rollups)
│   ├── class-sva-admin.php
│   ├── class-sva-settings.php
│   ├── class-sva-privacy.php      (GDPR export/erase hooks)
│   └── class-sva-geoip.php
├── admin/
│   ├── views/ (dashboard.php, settings.php)
│   ├── js/ (dashboard.js, chart config)
│   └── css/
├── assets/ (tracker.js — tiny beacon script)
├── languages/
├── README.txt (wp.org format)
└── plan.md
```

## 4. Database Schema
**wp_sva_hits** (raw, short-lived)
- id, url_id (FK), visitor_hash, referrer, device, browser, os, country, created_at

**wp_sva_urls**
- id, url, title

**wp_sva_stats_daily** (aggregated — this is what powers the dashboard)
- date, url_id, pageviews, uniques, device breakdown JSON, referrer breakdown JSON

Raw hits table gets pruned by cron after aggregation + retention window (default 90 days, configurable).

## 5. Tracking Mechanism (no cookies)
- Lightweight JS beacon (`tracker.js`, <2KB) fires `admin-ajax.php` or a custom REST route on pageview.
- Unique visitor = daily salted hash of `IP + User-Agent + site salt`, salt rotates daily → no persistent identifier stored, satisfies "privacy-friendly."
- IP truncated/anonymized before hashing (drop last octet) — never store raw IP.
- Exclude logged-in users/admins via `current_user_can()` check before enqueueing tracker.
- Respect `Do Not Track` header optionally (toggle in settings).

## 6. Geo-Location Decision
- Bundle lightweight local GeoIP DB (e.g. MaxMind GeoLite2-Country, country-level only, not city) — no external pings, license/attribution handled at plugin activation, DB updated via manual admin button (not silent auto-download, keeps it honest with reviewers).
- Country-level only, not city — keeps data minimal and matches privacy positioning.

## 7. Aggregation & Performance
- WP-Cron hourly job rolls raw hits into `wp_sva_stats_daily`, then deletes raw rows older than X hours.
- Dashboard queries only ever hit the aggregated table — stays fast even on high-traffic sites.
- Add proper indexes (url_id, date) from day one.

## 8. Admin Dashboard
- Overview cards: pageviews, uniques, avg. session pages, bounce estimate
- Chart.js line graph: daily/weekly/monthly/custom range
- Top pages/posts table
- Traffic sources (referrer breakdown)
- Device / browser / OS breakdown (pie or bar)
- Country breakdown (table, not map — keep it simple)

## 9. Settings Page
- Exclude admins/logged-in users (default on)
- Exclude specific IPs (textarea)
- Exclude specific pages/post types
- Data retention period dropdown (30/90/180/365 days / forever)
- "Delete all analytics data" button (with confirmation)
- Respect DNT toggle

## 10. Privacy / GDPR
- No cookies, no cross-site tracking, no raw IP storage — document this clearly in README.
- Hook into WP core Privacy Tools (`wp_privacy_personal_data_exporters/erasers`) even though you're not storing PII tied to identity — do it anyway for compliance credibility.
- Uninstall.php fully drops custom tables + options (opt-in toggle: "keep data on uninstall").

## 11. Security Checklist
- Nonces on all AJAX/REST tracking + admin actions
- Capability checks (`manage_options`) on all admin/settings actions
- `$wpdb->prepare()` on every query, zero raw SQL concatenation
- Sanitize/escape all input & output (`sanitize_text_field`, `esc_html`, `esc_attr`)
- No `eval`, no remote code execution, no external asset loading (wp.org will reject instantly)

## 12. wp.org Submission Prep
- README.txt in correct wp.org format (tested up to, stable tag, FAQ, changelog)
- GPLv2+ license
- No "SEO spam" links, no trialware/upsell nags in free version
- Plugin Check (PCP) plugin run locally before submission
- Expect 1-4 week review queue + possible back-and-forth on guideline nitpicks

## 13. Build Phases (Roadmap)
1. **Phase 1 — Core tracking**: DB schema, tracker.js, hit logging, exclusion rules
2. **Phase 2 — Aggregation**: cron rollup, retention pruning
3. **Phase 3 — Admin dashboard**: overview, charts, tables
4. **Phase 4 — Settings**: exclusions, retention, delete data
5. **Phase 5 — Privacy/GDPR hooks + uninstall**
6. **Phase 6 — GeoIP integration**
7. **Phase 7 — Polish**: README, screenshots, i18n/translations, Plugin Check pass
8. **Phase 8 — Submit to wp.org**

## 14. Nice-to-Haves (post-v1, for portfolio flex)
- Email weekly summary report
- Export stats to CSV
- REST API endpoint for external dashboards
- Dark mode admin UI
