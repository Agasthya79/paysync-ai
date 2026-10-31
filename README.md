# PaySync AI

**Smart Invoice & Payment Matching** - Every Bill in Sync, Every Payment Just Right!

PaySync AI reconciles invoices against payments, flags discrepancies, and explains every decision. Built solo as a hackathon MVP.

## What it does

- Manage invoices and payment transactions through a REST API
- Import external payment records from CSV, with per-row validation and duplicate protection
- Reconcile invoices against payments using reference, currency and amount
- Detect matched, underpaid, overpaid, split, partially paid, duplicate-candidate and missing payments, plus payments that belong to no invoice
- Show evidence (amounts, totals, difference) and a recommended action for every result
- Keep an audit trail: every reconciliation run is stored, never overwritten
- Human approval: an invoice is marked paid only after a person approves a matched result
- Pay an invoice through a PayPal order and capture flow (see "PayPal" below)

## How matching works

The matching engine is **deterministic and auditable**. It never guesses on money.

1. Invoices and payments are grouped by reference and currency.
2. Pass 1: exact-amount pairs claim payments first, so a wrong-amount invoice cannot steal a payment.
3. Pass 2: leftovers become underpaid, overpaid, partially paid or split (payments summing exactly to the invoice).
4. Anything ambiguous is flagged `duplicate_candidates` for manual review.

All money is stored and compared as integer cents. Explanations are generated from validated data, so they always agree with the decision. The confidence value is a rule-based score, not a statistical probability.

## Tech stack

- Backend: Laravel 12 (PHP 8.2), SQLite
- Frontend: Flutter (web, run in Chrome)
- Payments: PayPal Orders API flow, currently in mock mode

## PayPal

PayPal runs in **mock mode** (`PAYPAL_MODE=mock`) because the developer's sandbox account could not be verified in time. The server still:

- takes the amount and currency from its own database, never from the client
- verifies the capture status, amount and currency against the stored order
- records a `paypal` transaction using the invoice reference, so reconciliation matches it

Real sandbox calls would replace the two mocked methods in `app/Services/PayPalService.php`. Credentials belong only in the backend `.env`, never in Flutter or git.

## Setup

Backend:

```powershell
cd paysync-api
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Make sure `.env` contains `DB_CONNECTION=sqlite` and `PAYPAL_MODE=mock`. The API runs at http://127.0.0.1:8000/api.

Frontend:

```powershell
cd paysync-app
flutter pub get
flutter run -d chrome
```

## API overview

- `GET/POST /api/invoices`, `GET/PUT /api/invoices/{id}`
- `GET/POST /api/transactions`, `GET/PUT /api/transactions/{id}`
- `POST /api/transactions/import` (CSV upload)
- `POST /api/reconciliation/run`, `GET /api/reconciliation`, `GET /api/reconciliation/runs`
- `POST /api/reconciliation/{id}/approve`
- `POST /api/invoices/{id}/paypal/order`, `POST /api/paypal/orders/{orderId}/capture`

## CSV format

Required columns: `external_id, reference_id, amount, currency, status, paid_at`. Amounts like `1,500.50` are converted to cents with string math. Dates must be `YYYY-MM-DD`. Supported currencies: USD, EUR, GBP, INR. Bad rows are skipped and reported; good rows still import.

## Known limits

- PayPal is mocked; real sandbox integration is future work
- No authentication yet
- Currencies are assumed to have 2 decimal places
- European number formats (`1.500,50`) are not supported
- List endpoints return the first 15 records
- Automated test coverage is minimal (one engine test)
- Matching is rule-based, not machine-learned; an AI explanation layer is future work