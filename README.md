# MyMoney

A personal income and expense tracker built around how money actually moves: between your own
accounts, to and from the people you lend to, and out of the door. Laravel 13, Blade, Alpine and
Tailwind, with no SPA layer.

- **Books** keep separate ledgers (personal, business, household), each with its own currency,
  decimals and history.
- **Accounts and contacts** live in the same ledger, so what you lent to someone sits beside what
  is in your bank.
- **Bulk entry** takes a month of transactions in one sitting: paste from a spreadsheet, import a
  CSV, or type across the grid with the keyboard alone.
- **Balances are derived**, never patched. Edit an opening balance or delete an entry and every
  figure after it is recalculated.
- **`.mbak` import and export**, so you can move in from the mobile app and back out again, plus a
  multi-sheet **Excel export**.

---

## Getting started

Requirements: **PHP 8.3+**, **Composer**, **Node 20+**, and SQLite (the default) or MySQL/Postgres.

```bash
git clone <repository-url> mymoney
cd mymoney

composer setup        # install, .env, key, migrate, npm install, npm run build
php artisan db:seed   # the shared icon library, and a test user
composer run dev      # serve + queue + logs + vite, all at once
```

`composer setup` is defined in `composer.json`; if you prefer the long way:

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
```

Then open <http://localhost:8000>. Registering creates your first book automatically.

### Environment worth setting

| Variable | Purpose |
|---|---|
| `APP_NAME` | Brand name, used in every title, the nav and the emails |
| `SITE_CONTACT_MAIL` | Where the marketing site's contact form delivers |
| `SITE_TAGLINE`, `SITE_COMPANY` | Marketing and legal-page copy |
| `MAIL_MAILER` | `log` in development; the contact form needs a real mailer in production |

---

## Commands

```bash
composer run dev     # php artisan serve + queue:listen + pail + vite
composer test        # the full suite
php artisan test     # same, without clearing config first
./vendor/bin/pint    # format (run before committing)
npm run build        # compile assets
```

---

## How it is put together

### Books scope everything

`accounts`, `contacts`, `categories`, `transactions` and `config_dictionaries` all carry a
`book_id`. The `BelongsToBook` trait adds a global scope for the active book and stamps `book_id`
on create, so a book switch re-scopes the whole application without a single controller knowing
about it. The active book lives in the session and is resolved by `App\Support\CurrentBook`.

Books are owner-scoped today. **Book-level access control belongs in `CurrentBook` and the `Book`
model** — change it there and every downstream model follows.

### Money is stored in minor units

Every amount is an integer number of cents. `App\Lib\Util` is the only place that formats them:

```php
Util::displayAmount(123456);   // "1,234.56", or "৳1,234.56" — the book decides
Util::toMinorUnits('12.50');   // 1250
```

Decimal places (0–2), currency symbol and its position are per-book settings.

### The three movements, and where charges land

| Type | Effect |
|---|---|
| **Income** | Credits the account with `amount − charge` |
| **Expense** | Debits the account by `amount + charge` |
| **Transfer** | Debits the source by `amount + charge`, credits the target by `amount` only |

A transfer is never income or expense — it moves money inside the book — but the charge on one is
a real cost, so reports count it with the outgoings. These rules live in one place
(`LedgerService`) and are pinned by tests.

### Balances are replayed, not adjusted

`App\Services\LedgerService` rebuilds an account's running balances from its opening figure in
date order, writing `from_account_balance` / `to_account_balance` onto each transaction and the
closing balance onto the account. Every write path funnels through it:

- a transaction created, edited or deleted → the affected accounts replay
- an opening balance changed on an account, or on a contact → that account replays
- a bulk save or a `.mbak` import → one replay per touched account, not one per row

That is why stored balances cannot drift from the transactions that produced them.

### Contacts are accounts

Creating a contact also creates a mirror `Account` of type `Contact`, kept name-synced. The sign
tells you which way the debt runs:

- **positive** — they owe you; the money is out with them
- **negative** — you owe them

The dashboard reports the two separately and never folds contact balances into "money you hold".

### Amounts do arithmetic

Any amount field accepts `10 * 2` or `(4 * 4)+54`. The browser evaluates it as you leave the
field; the server re-parses whatever is submitted with `App\Lib\MathExpression`, a hand-written
tokeniser and shunting-yard evaluator. **`eval()` is never involved**, so a hand-crafted POST
cannot smuggle anything through.

### The `.mbak` format

Backups from the mobile app are one line of `base64(iv):base64(mac):base64(ciphertext)` —
AES-128-CBC with an HMAC-SHA256 tag, keys derived by PBKDF2 over constants baked into that app.
`App\Lib\MbakFile` verifies and decodes; `MbakImporter` and `MbakExporter` translate.

Notes on the round trip:

- Accounts and categories are matched **by name**, so importing twice does not duplicate them.
- A leading dot on an account name means inactive (`.Savings` → inactive "Savings"), both ways.
- The format has no charge field, so exports write each charge as a separate **"Transfer Charge"**
  expense; the money still lands in the same place.
- Icons map through `images.export_icon_id`, within the matching image type.
- Timestamps are epoch milliseconds; amounts are major units.

---

## Project layout

```
app/
  Enums/            AccountType, CategoryType, TransactionType, ImageType, CurrencyPosition…
  Lib/              Util (money), MathExpression, MbakFile + the crypto classes
  Services/         LedgerService, TransactionRecorder, MbakImporter/Exporter,
                    DashboardSummary, ReportSummary
  Support/          CurrentBook, DateRange
resources/
  views/components/ ui/*  (input, select, button, field, card, dialog, stat)
                    chart/* (flow, ranking, grouped)  · image-picker · book-switcher
  js/               Alpine components: bulk-transactions, image-picker, option-picker,
                    transaction-form, book-import, report-detail
database/seeders/   SharedImageSeeder — the built-in icon library
```

### Images

Two kinds share one table. **Shared** icons (`user_id` null) ship in `public/images` and are served
straight from disk; **user uploads** are cropped to a 69×69 circle in the browser, stored as PNG
outside the web root, and served through a route that checks ownership. Images are typed
(`Account`, `Category`, `Picture`, `Book`) and each picker only offers its own type.

Endpoints live under `/media`, not `/images` — the latter is a real directory in `public/`, and the
web server would answer from disk before Laravel ever saw the request.

### Charts

Charts are plain HTML and CSS — donut slices are stroked SVG circles, the Sankey ribbons are two
cubic curves each (a transfer loops from one account's right edge back to another's left), the
flow calendars are CSS grids: no charting library. The flow reports filter by account and category; the overviews filter
by account only, since the pie is already the category split. An account filter matches the side
the money moved on (out of it for expense, into it for income), so a transfer's arrival never
reads as income. Colours are CSS roles (`--viz-income`,
`--viz-expense`, `--viz-bar`) defined once per theme and **validated for colour-blind separation
and contrast** rather than eyeballed. Every chart ships a legend for two or more series, hover and
keyboard-focus tooltips, and a route to the numbers that does not require hovering (direct labels
or a table view).

---

## Testing

```bash
composer test                        # everything
php artisan test --filter TransactionTest   # one file
```

The suite covers the money rules end to end: charge handling per transaction type, ledger replay
after edits and deletes, book scoping on every model, `.mbak` round trips, the bulk grid's
validation and all-or-nothing save, report reconciliation (opening + movements = the account's live
balance), and that a server error renders the 500 page without leaking the exception.

> **If PHP commands hang on your machine**, check Xdebug: `xdebug.start_with_request=yes` with no
> debug client listening blocks every CLI process. Either set it to `trigger` or prefix commands
> with `XDEBUG_MODE=off`.

---

## Status

Built: books, accounts, contacts, categories, transactions (single and bulk), dashboard, seven
reports (Money Flow, Expense/Income Overview, Expense/Income Flow, Account and Category analysis),
`.mbak` and Excel export, the marketing site and legal pages.

Next: budgets (the `.mbak` format carries a `budgets` array we currently
discard), and book-level sharing.
