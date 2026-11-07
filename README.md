# PaySync AI

**Smart Invoice & Payment Matching** - Every Bill in Sync, Every Payment Just Right!

PaySync AI reconciles invoices against payments, flags discrepancies, and explains every decision. Built solo as a hackathon MVP.

## What it does

- Manage invoices and payment transactions through a REST API
- Import external payment records from CSV, with per-row validation and duplicate protection
- Reconcile invoices against payments using reference, currency and amount
- Detect matched, underpaid, overpaid, split, partially paid, duplicate-candidate and missing payments, plus payments that belong to no invoice
- Show evidence (amounts, totals, difference) and a recommended action for every result
- **AI summaries:** Gemini rewrites the engine's validated facts in plain language
- Keep an audit trail: every reconciliation run is stored, never overwritten
- Human approval: an invoice is marked paid only after a person approves a matched result
- **Pay with PayPal** through the PayPal sandbox (Orders API v2)

## Technology requirement: PayPal and AI

**PayPal developer platform (free sandbox):** the backend creates an order with the PayPal Orders API v2, the buyer approves on PayPal's sandbox checkout, and the server captures the payment and verifies the status, amount and currency against its own stored order before recording a `paypal` transaction. The amount always comes from the database, never from the client. Credentials stay in the backend `.env`.

**AI (Google Gemini API):** after the rule-based engine decides, the "Explain with AI" button sends only the validated facts (status, amounts, difference, payment IDs) to Gemini and shows a short plain-English summary. The AI never decides or changes a result, and if the call fails the app falls back to the rule-based explanation. I also used Claude (Anthropic) as a development assistant while building the project.

## How matching works

The matching engine is **deterministic and auditable**. It never guesses on money.

1. Invoices and payments are grouped by reference and currency.
2. Pass 1: exact-amount pairs claim payments first, so a wrong-amount invoice cannot steal a payment.
3. Pass 2: leftovers become underpaid, overpaid, partially paid or split (payments summing exactly to the invoice).
4. Anything ambiguous is flagged `duplicate_candidates` for manual review.

All money is stored and compared as integer cents. The confidence value is a rule-based score, not a statistical probability.

## Tech stack

- Backend: Laravel 12 (PHP 8.2), SQLite
- Frontend: Flutter (web, run in Chrome)
- Payments: PayPal sandbox (Orders API v2)
- AI: Google Gemini API (free tier)

## Setup

You need PHP 8.2+, Composer and Flutter. Free keys are required for the PayPal and AI features:

- PayPal: create a Merchant app at developer.paypal.com (Sandbox), and create **US** sandbox business and personal accounts. Link the app to the US business account.
- Gemini: create a key at aistudio.google.com.

Backend:

```powershell
cd paysync-api
composer install
copy .env.example .env
php artisan key:generate
New-Item database\database.sqlite -ItemType File
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

Edit `.env` and set:

```env
DB_CONNECTION=sqlite
PAYPAL_MODE=sandbox
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_CLIENT_ID=your_sandbox_client_id
PAYPAL_CLIENT_SECRET=your_sandbox_secret
GEMINI_API_KEY=your_gemini_key
GEMINI_MODEL=gemini-flash-lite-latest
```

Then start the API (it runs at http://127.0.0.1:8000/api):

```powershell
php artisan config:clear
php artisan serve
```

Frontend:

```powershell
cd paysync-app
flutter pub get
flutter run -d chrome
```

**No keys?** Set `PAYPAL_MODE=mock` to simulate PayPal locally (the server still verifies the capture), and leave `GEMINI_API_KEY` empty. The app then shows the rule-based explanations instead of AI summaries. Everything else works.

## Suggested test path

1. Dashboard: click "Run reconciliation" (fresh demo data: 2 matched, 6 need review)
2. Results: open INV-3001 (split), INV-2001 (underpaid, try "Explain with AI"), INV-4001 (ambiguous)
3. CSV import: `curl.exe -X POST -H "Accept: application/json" -F "file=@demo-import.csv" http://127.0.0.1:8000/api/transactions/import`
4. Invoices: tap INV-5001 and pay with PayPal (sandbox buyer login), then run reconciliation again
5. Results: open INV-5001 and approve

Reset the demo data any time: `php artisan migrate:fresh --seed --seeder=DemoSeeder`

## API overview

- `GET/POST /api/invoices`, `GET/PUT /api/invoices/{id}`
- `GET/POST /api/transactions`, `GET/PUT /api/transactions/{id}`
- `POST /api/transactions/import` (CSV upload)
- `POST /api/reconciliation/run`, `GET /api/reconciliation`, `GET /api/reconciliation/runs`
- `POST /api/reconciliation/{id}/approve`, `POST /api/reconciliation/{id}/explain`
- `POST /api/invoices/{id}/paypal/order`, `POST /api/paypal/orders/{orderId}/capture`

## CSV format

Required columns: `external_id, reference_id, amount, currency, status, paid_at`. Amounts like `1,500.50` are converted to cents with string math. Dates must be `YYYY-MM-DD`. Supported currencies: USD, EUR, GBP, INR. Bad rows are skipped and reported; good rows still import.

## Known limits

- No authentication yet
- Currencies are assumed to have 2 decimal places
- European number formats (`1.500,50`) are not supported
- List endpoints return the first 15 records
- Automated test coverage is minimal
- Matching is rule-based by design; AI is used only to explain results
- Gemini model names change over time; if the default stops working, set `GEMINI_MODEL` to a current one

## License

MIT