# RikoWallet — Product and Integration Guide

Version: planning v2 · 3 October 2026

Status: development authorised by the owner on 3 October 2026. Laravel now lives at the repository root, with Flutter in flutter_apk/. This guide describes the target scope; see DEVELOPMENT-STATUS.md for implemented behaviour and remaining work. Live provider integration, deployment and real financial operations remain unconfigured.

## 1. Approved direction and latest changes

- Premium-v2 visual direction: white surfaces, lavender, blush pink, butter yellow, restrained gradients and deep-purple navigation. Reference boards are in the repository's `design/premium-v2/` directory.
- One RikoWallet product: responsive website, Android/iOS application, merchant tools and a separate privileged admin panel.
- INR, USD, EUR and selected crypto assets, subject to country and provider eligibility. USD and USDT are separate assets. Crypto network is an explicit dimension.
- User-to-user sending is internal only. External movement is deposit or withdrawal. Prepaid-card loading is a separate operation.
- Earning and crypto both stay in scope.
- **Latest instruction supersedes app-only earning:** admin can independently enable earning on website and app. Only an enabled channel displays earning UI and allows new task starts/claims/redemptions from that channel.
- App surf, web surf, app install and survey categories; admin-controlled country tiers, task rates and device/platform targeting.
- RikoCoin is an internal reward unit, not a blockchain token and not mined cryptocurrency. UI calls it "earned"; admin may display "RikoCoin earned (mined)" as the owner's terminology.
- Three mutually exclusive referral reward modes, configurable in admin, plus OFF.
- Monthly referral-earning contest with an independently enabled leaderboard, funded RikoCoin prize pool and first-of-month settlement; My Referrals, consent-aware inactive-referral reminders and support chat.
- Stable numeric wallet ID is the public primary identifier for payments and referral attribution. Internal database IDs remain private; earning pairing tokens remain separate security credentials.
- Google advertising is optional and policy-dependent; it is not assumed revenue. A feature toggle cannot be used to hide prohibited behaviour from review.
- Recharge, travel bookings, investments and insurance distribution are outside this version.

## 2. System architecture and ownership

Planning stack updated following owner feedback: Flutter/Dart Android/iOS app; Laravel website, API and protected admin panel, with Vue/Inertia and Tailwind proposed for the website UI; MySQL transaction database (owner-selected); Redis for queues, cache and coordination. Nothing has been installed or implemented. Use maintained compatible versions selected at implementation. Production needs managed infrastructure or a suitable VPS with workers, backups and HTTPS; the current local XAMPP directory is not the production security boundary.

MySQL financial tables must use InnoDB, explicit transactions, exact DECIMAL or integer-unit amounts, foreign keys, unique operation constraints and deliberate row-lock ordering. Handle deadlocks with bounded idempotent retries. Select and test the isolation level and indexes for concurrent balance operations; do not rely on application-only checks or cache locks. Flutter and web UI implementations are separate but share API contracts, business rules and premium-v2 design tokens.

Both clients use the same backend business rules and authoritative ledger. The backend owns eligibility, quotes, limits, referrals, task sessions, rewards and fees. Clients render data and submit user intent; they do not determine credits or balances.

External integration boundaries:

| Integration | Responsibility | Must confirm before selection |
|---|---|---|
| KYC provider | Identity/document checks and approved verification results | Countries, retention, callback signing, false-positive handling |
| Fiat partner | Supported deposits, safeguarded/custodied funds and bank payouts | Our legal role, permissible currencies, receiving accounts, fees and settlement |
| Crypto custodian | Custody, addresses, confirmations, withdrawals, key management | Countries/assets/networks, KYT, segregation, signing controls, recovery |
| Swap/liquidity provider | Executable quotes, conversions, execution status | Fee markup/share, slippage, expiry, settlement, failed swaps |
| Card issuer/program | Virtual/physical prepaid cards, card balance, authorisation, refunds | Country coverage, crypto-origin funding acceptance, shipping, fees and dispute model |
| Task partners | Evidence of real task completion, event IDs and reversals | Signed server callbacks, budgets, attribution, settlement and traffic policy |
| Messaging/storage | Email, notifications, private evidence/documents | Data access, delivery, encryption, regional requirements |

An API integration does not automatically grant financial authorisation. Provider onboarding, applicable permissions and country restrictions are launch dependencies. Provider contracts must define whether RikoWallet is an agent, distributor, programme manager or independently regulated operator.

## 3. Product navigation and screens

### Website

Public pages: home, wallet features, crypto/currencies, cards, business payments, fees, supported countries, help/contact, privacy, terms, risk disclosures and service status. Avoid live-looking unsupported offers.

Authenticated dashboard: overview; individual balances; deposit; internal send/receive; requests; swap; withdrawals/beneficiaries; cards; activity/receipts/statements; payment links; invoices; subscriptions; merchant settings; support/disputes; profile/KYC/security.

When web earning is ON: Earn appears in navigation, with dashboard, task catalogue, task details, earning code/linking, active sessions, verification status, reward history, referrals and redemption. When OFF: these routes reject access as unavailable and navigation does not show them. An app redemption already credited to fiat remains a normal wallet transaction visible in wallet history.

### Native app

Onboarding: welcome, signup, verification, optional referral-code step, country, KYC where required and device security. Main navigation: Home, Pay, Cards, Earn (only when app earning is enabled), Profile. If Earn is disabled, navigation reflows; do not leave a broken placeholder.

All wallet flows from the website are available subject to device/provider support. Biometric confirmation protects local access but does not replace server authorisation. App includes push notifications, QR interactions and supported deep links.

### Admin

Separate sign-in and permissions: overview, users, KYC, ledger/transactions, deposits, withdrawals, swaps, assets/networks, cards, merchants, earning/tasks, reward rates, referrals, provider callbacks, fraud review, fees/revenue, content, reports, Cron & Jobs, audit and settings.

### Responsive design requirements

- Follow the six premium-v2 boards, not their illustrative financial calculations or placeholder copy.
- Small phones stack panels; larger phones may use two columns; desktop/tablet use sidebar and content columns. No stretched mobile screenshots.
- Test narrow screens, landscape, tablet, desktop, long translated text, keyboard-open forms and font scaling.
- Sufficient text contrast, keyboard navigation, focus states, accessible controls and reduced-motion support.
- Loading, empty, failed, offline, pending, disabled-country, rejected-KYC and insufficient-balance states belong to every affected flow.
- Every financial confirmation shows asset/network, recipient, exact debit, fees, expected credit, expiry where relevant and final status.

## 4. Financial ledger and transaction invariants

### Admin-controlled deposit and withdrawal methods by country

Admin creates and edits deposit methods and withdrawal methods independently. Each method has a name, icon, provider/configuration reference, direction, enabled status, explicit allowed account-country list, supported assets/currencies/networks, permitted channels, required user/KYC eligibility, input fields/instructions, fees, minimum/maximum, processing-time description and configuration version. Country-specific fee/limit overrides can be configured. No selected country means unavailable everywhere; do not implicitly enable a method worldwide.

The owner selects which countries may use each method. For example, admin can assign a UPI deposit method only to India and omit any other method from Bangladesh or another country. These are admin configuration examples, not claims about a provider's present country coverage. One method may allow multiple explicitly selected countries. A method can allow deposits in a country while withdrawals remain disabled there.

Effective availability is the intersection of admin-selected countries, actual provider-supported countries/currencies, verified user eligibility, enabled method/direction/channel and applicable limits. Admin can narrow provider coverage, not override a provider restriction. Country is the server-held verified account/residence country, not a browser language, client parameter or temporary IP location. Pending verification follows onboarding restrictions; changing residence requires review, not a user toggle to unlock methods.

Only eligible methods are returned in deposit/withdrawal method listings and displayed in the app/website. Revalidate eligibility at quote/instruction creation, confirmation and operation execution. Guessing a hidden method ID or using a stale screen must not bypass these rules. Cache method catalogues with country/asset/direction/eligibility scope and invalidate on admin changes; never reuse one user's personalised list for another.

When admin removes a country or disables a method, block new operations immediately. Preserve existing operation records and reconcile provider events: funds already received must be accounted for and held/reviewed/refunded according to policy, never silently dropped. Already-submitted withdrawals are reconciled before releasing holds or retrying. Previously issued reusable deposit instructions may require provider-side deactivation; hiding them in UI alone does not stop incoming funds. Audit every configuration change and show affected pending operations before applying it.

Admin preview: select country + currency + deposit/withdrawal + channel to inspect visible methods and reasons for ineligibility. Acceptance tests cover India-only UPI visibility, different country lists per direction, empty lists, unverified country, spoofed country/header, direct method-ID access, stale cache, mid-operation disablement and already-issued deposit instructions.

Use an append-only double-entry ledger, balanced separately for each asset. Keep customer liabilities, provider balances/receivables, platform revenue, fees, reward budgets and suspense accounts distinct. Use asset precision and exact decimal/integer units, never binary floating-point arithmetic for money.

Available, reserved and pending funds are separate. Prepaid-card funds and wallet funds cannot both be spendable after a load. RikoCoin has its own ledger and is not included in spendable fiat merely because an estimated conversion value is displayed.

Financial writes require database transactions, concurrency-safe account/hold operations, non-negative available-balance checks and database uniqueness constraints. An immutable business operation ID/idempotency key guards every money movement. A Redis lock alone is insufficient.

Internal transfer: reserve/debit sender and credit receiver atomically in the same currency; enforce recipient and asset eligibility. No blockchain transfer is created for each internal transfer.

External operations use state machines and reconciled asynchronous confirmation. Never credit from a browser success redirect. Deposits credit only after authenticated provider confirmation and required checks. Withdrawals reserve funds before submission; a timeout is UNKNOWN/PENDING, not proof of failure. Query provider status before retrying or releasing a hold. Do not send a second payout with a new ID because the first timed out.

Crypto deposit policy is per network: supported asset/contract, deposit minimum, confirmations/finality, memo/tag requirements, KYT and reorganisation handling. Unsupported networks/assets do not auto-credit.

Swap policy: quote → accept before expiry → reserve input → execute once → reconcile → post output and fee entries. Exchange rate, provider fee and platform fee are versioned with the accepted quote. Provider/network charges can exist on failed on-chain execution; disclose before submission where applicable. Never manufacture a network charge for an entirely internal conversion without an applicable cost/fee basis.

Refunds, chargebacks, task reversals and admin corrections use compensating entries linked to originals. Deleting ledger entries or editing a balance column directly is forbidden. Handle already-spent reversed rewards by holds/recovery policy and review, not silently debiting unrelated customer deposits.

## 5. Swap fees and platform revenue

Fees are configurable only within partner and legal limits. Support fixed plus percentage, minimum/maximum, pair/network/channel/country targeting and effective version dates. Publish customer-facing fees; separate tax treatment in reporting.

| Component | Beneficiary |
|---|---|
| Provider/exchange charge | External provider |
| Applicable blockchain cost | Network/transaction execution cost |
| RikoWallet platform fee/markup | RikoWallet, subject to contract |
| Provider revenue share | Provider settles the agreed part to RikoWallet |

Admin configuration does not itself cause a provider to pay us. Integration must support explicit collection, fee recipient or contractual revenue-share settlement. Some fees are deducted immediately; others are receivable and paid later. Track both recognised revenue and received settlement.

Illustrative only: 100 USDT input; provider cost 0.20 USDT; platform fee 0.50 USDT; applicable network cost equivalent to 0.10 USDT; 99.20 USDT equivalent buys the target asset when all deductions are from input. Platform gross fee income is 0.50, not 0.80. Actual output comes from the executable quote. Define whether the provider's fee is already included in its rate to avoid charging/counting it twice.

Customer money, deposit volume, internal transfers and card loads are not platform revenue. Revenue reports distinguish gross platform income, provider costs, referral/task reward expense, refunds, tax liabilities and net contribution before fixed operating costs.

## 6. Independent web/app earning switches

Settings: global earning enabled, web enabled, app enabled; plus category, task, partner and country/device eligibility. Global OFF overrides both channel switches.

| Web | App | Behaviour |
|---|---|---|
| OFF | OFF | No new earning sessions/claims/redemptions on either channel |
| ON | OFF | Website only; app cannot start or claim |
| OFF | ON | App only; website cannot start or claim |
| ON | ON | Both, but shared limits and one reward per eligible completion |

The backend applies switches to catalogue, detail, session start, verify-code/linking, user claim, referral reward claim if exposed, and redemption endpoints. UI hiding is not enforcement. Referral attribution can still be recorded during signup; payout stays pending if the earning programme is globally paused.

Origin is recorded when a server-authorised task session is created. A supplied `platform=app` header or user agent is not sufficient proof. Web uses authenticated browser session/CSRF protections; app uses authenticated sessions and appropriate app integrity/risk checks. App integrity is a signal, not proof that a task was genuinely completed.

If a channel is switched OFF after a task starts, signed completion evidence can still be durably received, deduplicated and marked `verified_pending_channel`; no new coin credit/claim executes while the origin channel is disabled. Preserve entitlement and explain the pause to the user. Resume eligible processing after re-enable. Show pending liabilities to admin. Do not label receipt of evidence as reward paid.

Normal withdrawal of previously redeemed wallet funds remains a financial flow, not an earning claim; turning Earn OFF must not erase or confiscate existing wallet funds.

## 7. Task catalogue, targeting and rates

Task definition fields:

- ID, type (app surf/web surf/app install/survey), title, instructions, guide media, owner/provider, status and start/end dates.
- Android package name, expected signing-certificate identity where available, store URL, iOS bundle identifier if supported; web domain/approved landing path for web tasks.
- Allowed origin channels (web/app), device classes (mobile/tablet/desktop), operating systems and countries. These are separate dimensions.
- Required activity/milestones, minimum duration where meaningful, completion evidence scheme, repeat policy, per-user/day/lifetime limits, global cap and campaign budget.
- Country tier and rate-table version, task override, base RikoCoin amount, review/hold period and referral eligibility.
- Integration credentials reference, callback endpoint, provider task ID, reversal support and funded reward budget.

Adding a package name identifies a target app; **it does not give RikoWallet access to that app's usage or prove completion**. Production app surf/install requires cooperation from the app owner, a trusted attribution/measurement provider or another independently verifiable completion integration. Apps without this evidence route stay disabled for automatic payouts.

Web surf similarly needs an authorised measurement flow; a client timer or a page visit alone is not trusted proof. Do not inflate Google ad impressions/clicks. No arbitrary installed-app inventory collection to implement package checks. Platform permissions, consent and store rules need implementation review.

Country tiers are admin-defined groups (e.g. Tier A/B/C, without assuming actual members). Rate precedence:

1. Exact task + verified country + device/OS match.
2. Task + country-tier match.
3. Category + exact country match.
4. Category + country-tier match.
5. Explicit fallback, only if configured; otherwise task unavailable.

Ambiguous overlapping rules fail admin validation rather than selecting randomly. Rates, eligibility and repeat windows are snapshotted into a task session. Later rate edits apply to new sessions, not already-promised rewards; emergencies can pause settlement with an audit reason.

Country is backend-determined from verified residence/account eligibility plus risk signals. IP/GeoIP is supporting evidence, not a definitive identity. VPN/travel mismatch routes to review or unavailability under disclosed rules. Client-submitted country/IP/time cannot raise the reward.

Examples (illustrative): a survey allowed only on mobile in India appears only to eligible India/mobile users; app install targeting Android never appears as executable on desktop or iOS; an app-surf task started in RikoWallet Android cannot be claimed again on mobile web.

## 8. RikoCoin earning and redemption

Each task session snapshots the fixed user reward, country/tier/device rule, referral policy, funding reservation and expiry. Advertiser/task budget must cover both user reward and additional referral commission before accepting new sessions.

Suggested states: CREATED → STARTED → EVIDENCE_RECEIVED → VERIFIED_PENDING → APPROVED/CREDITED; alternatives REJECTED, EXPIRED, HELD or REVERSED. All transitions are authorised and audited. A successful HTTP callback is not inherently a completed reward.

RikoCoin statuses: pending, available, reserved-for-redemption. Redemption: show rate/version and fees, reserve coins, check funded payout budget and eligibility, credit chosen supported wallet currency atomically, then mark redeemed. Async provider operations use holds and reconciliation. A redemption changes company reward liability into a funded wallet liability; coins do not generate backing money.

Admin sets conversion unit/rate, effective time, minimum redemption, daily cap, allowed output currencies and fees. Never conflate market crypto swap with admin-priced RikoCoin redemption. Rate changes require preview, audit and user-visible terms; completed redemptions remain unchanged.

## 9. Referral modes and attribution

After signup/verification show "Have a referral code?" with enter and skip. Proposed attribution window: configurable, initially 7 days after signup and before first eligible earning session. This is a proposed default, not a confirmed commercial rule. Record a valid referrer once; do not allow changing referrer after attribution/reward. Block self-referral, cycles and deleted/ineligible codes.

| Mode | New user | Referrer |
|---|---|---|
| A — Fixed reward to both | Admin-set X RC after qualifying valid-code signup | Admin-set Y RC after same qualification |
| B — Fixed reward to new user only | Admin-set X RC after qualifying valid-code signup | No signup or ongoing commission |
| C — Earning commission only | No fixed signup reward; retains their full task reward | Admin-set P% of eligible approved task RikoCoin |
| OFF | No referral reward | No referral reward |

Modes are mutually exclusive, as requested. No automatic combination of fixed bonuses and ongoing commission. X and Y can be different. Qualification defaults to verified account, fraud checks and budget availability; admin may select additional disclosed milestone. Mere repeated submission of a code cannot mint rewards.

Mode C commission base is **only approved task earning RikoCoin**. Exclude deposits, wallet transfers, withdrawals, swaps, card loads/spend, signup bonuses, referral commission, admin grants and reversals. Direct referrer only; no recursive or multi-level commission.

Proposed commission economics: if a user earns 100 RC and P=10%, user receives 100 RC and referrer receives an additional 10 RC funded by the campaign. Do not deduct commission from the user's advertised reward. UI and budgets must say this explicitly.

Calculate using fixed precision and a documented rounding rule (proposed: floor to 0.01 RC per reward); store basis, rate and result. Unique key `(source_reward_id, referrer_id, referral_policy_version)` prevents repeat commissions, with an additional one-commission-per-source constraint to prevent version-based duplicates. Referral rewards never generate more referral rewards.

Policy version is attached to the attribution cohort; changes affect new referrals by default. Existing commission cohorts keep their disclosed rules. Any migration needs an explicit effective-date operation, user notice and audit, never retroactive recomputation. Task-session snapshots preserve the then-applicable terms.

Source reward reversal reverses its linked referral commission once using compensating entries. If funds are spent, follow a documented recovery/review process. Signup bonus unique keys enforce once per qualifying recipient and programme; mode switches do not regrant bonuses to old signups.

Referral UI: own code/share action, referred count, pending/approved rewards, direct task-commission total and daily history. Sharing a code is not permission to disclose another user's financial history. Only authorised admins see referral-linked user financial data.

## 10. Secure partner callback protocol

### Why the supplied PHP must not be used as production reward logic

- Static codes are not account attribution and cannot authorise credits.
- `is_test=true` must never make an arbitrary code valid in production.
- Wildcard CORS is not authentication; server-to-server callbacks need no browser CORS permission.
- Claimed package/IP/geo/time can all be forged by a caller.
- The first example builds `$logEntry` but never writes it, and returns success without a durable event or reward record.
- There is no signature, task-session binding, uniqueness, budget check, rate selection, expiry or replay protection.

Keep the requested public route names as aliases to versioned backend handlers:

| Route | Caller and purpose |
|---|---|
| `POST https://rikowallet.com/earn/verify-code` | Authenticated partner backend validates an earning/link code against a specific provider task/session |
| `POST https://rikowallet.com/earn/verify` | Authenticated partner backend reports independently verified task completion |
| `POST /api/v1/earn/sessions` | Logged-in eligible user starts task; backend binds origin, device, country, reward and limits |
| `GET /api/v1/earn/sessions/{id}` | Owner views verification/reward status |
| `POST /api/v1/earn/claims` | Owner requests settlement if manual claiming is enabled; same unique settlement operation as auto-credit |

Canonical production payload is strict JSON over HTTPS with schema/size limits. Legacy form input may be added through a reviewed adapter, not a silent ambiguous fallback. The partner may relay from its app, but its trusted backend must verify actual task completion before signing. Never ship an HMAC secret in APK, JavaScript or browser storage.

### Partner authentication and replay defence

Each provider has separate live/test credentials and allowed tasks/packages. Example headers: `X-Riko-Key-Id`, `X-Riko-Timestamp`, `X-Riko-Nonce`, `X-Riko-Signature`.

Proposed signature: HMAC-SHA256 over UTF-8 fields joined with newline in this exact order: timestamp, nonce, uppercase HTTP method, canonical path, SHA256 hex digest of the exact raw request bytes. Signature encoding is hex; fixed-time comparison. JSON must not be parsed/reserialised before hashing. Reject query strings on these routes to avoid unsigned parameters. Prefer a provider's mature signed-webhook scheme where already available and normalize internally.

Proposed 5-minute authentication clock tolerance; timestamp/nonce uniqueness tracked per provider for at least that replay window. Retried deliveries use a new signed timestamp/nonce but the **same completion event ID**. Permanent event and reward uniqueness remain in the database. Duplicate ID with different body returns conflict and raises an alert. IP allowlists are defence-in-depth only, not the primary authentication method.

### Earning code and account linking

User has a numeric public wallet ID, also used as the referral code. This public ID may identify the user during partner account linking, but is never authentication. Prefer a separate short-lived, one-use earning pairing code minted from the authenticated task session; exchange it for a scoped connection/session token. A persistent earning identifier is only a lookup hint and never sufficient to authorise a reward. Store sensitive pairing codes hashed, expire and revoke them; never expose user PII in verification responses.

Illustrative verify-code request:

```json
{
  "code": "<one-time-pairing-code>",
  "package_name": "com.partner.example",
  "task_id": "task_example",
  "task_session_id": "session_example"
}
```

Success requires authenticated partner, package/task ownership, valid user/session, correct origin eligibility, unexpired code and no prior consumption. Return `status`, `valid`, an opaque scoped `connection_token` and expiry; do not return wallet balance, email or KYC details. Retry behaviour for a consumed pairing code is idempotent for the same signed exchange; it cannot establish a second connection.

### Completion request

```json
{
  "event_id": "partner-completion-unique-id",
  "event_type": "task.completed",
  "task_id": "task_example",
  "task_session_id": "session_example",
  "connection_token": "<scoped-opaque-token>",
  "user_code": "<opaque-earning-identifier>",
  "package_name": "com.partner.example",
  "completed_at": "2026-10-02T10:00:00Z",
  "evidence_id": "partner-proof-reference",
  "user_ip": "<optional-partner-observed-ip>",
  "user_geo": "IN"
}
```

`completed_at`, `user_ip` and `user_geo` are corroborative metadata, not authoritative credit/rate fields. Accept the old `current_time` only through an explicit compatibility mapping. Reject caller-supplied reward/rate/balance overrides. Caller-supplied package must match provider configuration and immutable session, not select a different higher-paying task.

Processing order:

1. Enforce HTTPS, payload size/content type, provider authentication and replay checks.
2. Validate schema, scoped token, provider ownership, session, genuine evidence and subject binding.
3. Durably insert deduplicated inbox event; return receipt only after durable acceptance.
4. Queue evidence verification and fraud/eligibility checks. Apply current kill switches and snapshotted economics.
5. Transactionally lock reward entitlement/budget; issue one reward and at most one eligible referral commission; persist ledger, status and outbox event together.
6. Notify user after commit. Failed workers retry safely; admin sees failures and held claims.

Receipt example:

```json
{
  "status": true,
  "event_id": "partner-completion-unique-id",
  "verification_status": "queued",
  "reward_status": "pending",
  "message": "Completion received for verification. Reward not yet credited."
}
```

HTTP contract: 202 durably accepted/queued; 200 identical known event with its existing status; 400 malformed; 401 bad authentication; 403 partner/task unauthorised; 409 conflicting event/session; 422 invalid business inputs; 429 throttled with retry guidance; 503 storage/service unavailable. Provider retries timeouts/5xx/429 with exponential backoff and jitter, not all 4xx. If a provider requires HTTP 200 acknowledgement, retain explicit pending status in body and document that exception.

Sandbox has separate hostname, credentials, accounts, budgets and nonwithdrawable test units. In production reject `is_test=true`; do not cast arbitrary strings such as `"false"` to PHP booleans and do not use the flag to select a privileged environment. Admin test button targets sandbox with scoped credentials.

## 11. Admin dashboard and metric definitions

Top cards, from current reconciled balances/counters with freshness timestamps:

1. Total registered users (separate active/closed counts where relevant).
2. Total customer wallet value in USD equivalent.
3. Pending withdrawal request count, with per-asset amounts and USD equivalent.

Customer value sums available + reserved + applicable pending categories shown separately; card funds shown as a separate subtotal, not double counted. RikoCoin is a separate reward liability, not included in fiat/crypto wallet total. Every USD conversion uses asset-specific prices, source and valuation timestamp; USDT is not hardcoded to $1. Missing/stale prices show an incomplete valuation warning, not zero. User balances are liabilities, not company cash.

Default dashboard graphs: last **30 completed reporting days**, with a separately labelled provisional Today tile. Store raw ledger/audit history and aggregates beyond 30 days according to retention requirements; 30 days is the display window, not deletion policy.

Graphs show daily:

- Successful operation count and volume by type; one internal transfer counts once, not twice for debit/credit.
- Gross RikoWallet fee income, provider costs, task reward expense, referral expense and net contribution.
- RikoCoin issued for verified tasks; separate signup bonuses, referral signup bonuses, referral earning commissions, redeemed and reversed RC.
- New verified/unverified signups.
- Daily active users: unique users with a qualifying authenticated foreground interaction, excluding admin activity, background polling and callbacks. Track transacting and earning-active users separately. Do not add DAU to calculate monthly unique users.
- Deposits, withdrawals, swaps, card loads/spend, claims approved/rejected and fraud holds.

Below graphs: **per-currency customer balance table** with available, reserved, pending, total, USD equivalent and valuation time. Separate network/provider breakdown where useful; preserve an asset's identity instead of mixing unrelated tokens with the same ticker.

## 12. Nightly statistics pipeline

Proposed reporting timezone: Asia/Kolkata; all stored event times UTC. Run nightly at 00:15 local to aggregate the previous completed local calendar day. This schedule is configurable and will be displayed in admin; task reset timezone is independently configurable.

Aggregate from authoritative immutable events/ledger, not browser analytics counters. Use idempotent upserts keyed by date, metric dimensions and calculation version. Publish a completed batch atomically with `calculated_at`, watermark and job status. Never sum mutable balance snapshots to derive revenue.

Late callbacks affect financial issue date when the reward is actually posted; retain original completion time as a separate field. Reversals are shown on reversal posting date and can be related to original cohorts. Recompute affected reporting partitions when needed, with versioned corrections and a labelled refresh timestamp. Re-run recent days for delayed nonfinancial activity and allow admin backfill for any affected historical day.

Nightly job failure preserves the last good snapshot and shows stale-data status. Recovery is safe to rerun and does not credit money. Current balance and pending-withdrawal cards do not wait until midnight. Historical currency snapshots retain historical valuation so charts do not silently change with today's FX rates.

Suggested aggregates: `daily_platform_stats`, `daily_user_stats`, `daily_asset_balance_snapshots`, `daily_referral_stats`, and `stats_job_runs`. Monthly active users require distinct-user event aggregation, not the sum of daily totals.

## 13. All Users table and user details

Main table: user ID/name, country, status/KYC, joined-at, referrer, total direct referrals, multi-currency balance summary, total task RC, referral RC, deposit total and withdrawal total. Keep it paginated/filterable; expand balances per user rather than loading every historical row. Totals across different assets need currency breakdown or timestamped USD equivalents.

User drawer/detail tabs:

- Overview: identity, joined date/time, eligibility, referrer and referral cohort.
- Balances: each fiat/crypto asset with available/reserved/pending; card balances and RC separate.
- Last 30 days: earning, referral commissions, deposits/withdrawals, activity and transaction graphs.
- Transactions: deposits, internal transfers, swaps, withdrawals, card operations, fees and receipts.
- Earning: tasks, sessions, country/rate versions, verification evidence, pending/rejected claims.
- Referrals: direct users, qualification, signup bonuses and per-source commission records.
- Security/support: sessions, risk signals, restrictions, disputes and privileged action history.

Admin permissions limit visibility/export of sensitive data. Export jobs are logged, scoped and time-limited; protect CSV exports against formula injection. Support agents cannot reveal card credentials or edit ledger entries.

## 14. Data model inventory

Logical entities; implementation schemas and migrations are not created yet:

| Domain | Entities |
|---|---|
| Identity | users, profiles, KYC cases, devices, sessions, roles, permissions |
| Assets | currencies/assets, networks, provider asset mappings, FX/price snapshots |
| Ledger | accounts, journals, ledger entries, holds, operations, idempotency records |
| Payments | deposits, withdrawal destinations, withdrawals, transfers, swap quotes/executions, fees |
| Business | merchants, payment links, invoices/items, subscription mandates/charges, disputes |
| Cards | applications, provider card references, loads, authorisations, settlements, refunds, orders |
| Earning | channel settings, tasks, package/domain targets, country tiers, rate versions, sessions, evidence, rewards, budgets, redemptions |
| Referrals | codes, attributions, policy versions, signup grants, task commissions, reversals |
| Integration | partner credentials references, connections, webhook inbox/outbox, delivery attempts |
| Admin/stats | audit events, notifications, daily aggregates, reconciliation runs, job runs |

Mandatory uniqueness includes provider event IDs, provider financial operation IDs, task reward entitlement, signup bonus qualification, referral source reward, and subscription billing period. Two different provider event IDs for the same task entitlement must not bypass reward uniqueness.

## 15. Cron & Jobs admin page

Display environment-specific command, protected URL alternative if necessary, timezone, frequency, next/last run, duration, last success, retry count, queue backlog and failure details. No public GET endpoint that executes money operations without authentication. HTTP trigger uses POST and scoped signing/token authentication, not a secret exposed in a public URL/query log.

Job inventory:

- Task eligibility/reset windows and expiry (based on period keys, no destructive counter reset).
- Reward verification/held-claim processing and pending referral settlement.
- Subscription due-date processing with unique period charges.
- Quote/request expiry and safe hold reconciliation.
- Deposit/withdrawal/swap/card provider reconciliation and webhook recovery.
- Notifications, exports and statements.
- Nightly daily statistics, valuation snapshots and reporting backfills.
- Credential/health alerts and backup verification schedules.

One scheduler trigger plus managed workers; don't expose a separate unauthenticated URL per task. Distributed overlap protection and database idempotency; retries never replay money blindly. Manual run/backfill is role-restricted and audited. Queue processing and scheduler health are distinct indicators.

## 16. Security requirements and acceptance gates

No responsible implementation can promise zero bugs or zero hacks. The objective is independently verified layered security, minimal blast radius and recoverable operations. Frontend/APK code can be inspected; secrets and authority must remain server-side.

Required controls:

- MFA for admins and step-up authentication for withdrawals, new beneficiaries, card reveals and sensitive settings. Admin roles follow least privilege; high-value payouts, reward-rate changes, balance adjustments and credential changes use dual approval where appropriate.
- Strong password hashing, secure sessions/cookies, CSRF protection for browser mutations, session revocation, rate limits and abuse monitoring. Never store private keys, provider secrets or permanent signing credentials in clients.
- Object-level authorisation on every endpoint; prevent users reading/claiming another user's tasks, invoices, card or withdrawal.
- Input schema validation; parameterised queries; output escaping/CSP; safe uploads with type/size checks, malware scanning and private signed access.
- Partner URL/domain allowlists and SSRF protections for admin previews/callback testing; prevent redirects to private/internal services. No executing arbitrary task HTML/scripts in privileged RikoWallet origin.
- HTTPS, encrypted sensitive data, secret manager, rotation, restricted service identities, private database/Redis, network segmentation and environment separation.
- Hosted issuer components for card details; avoid handling/storing PAN/CVV wherever possible. No CVV persistence or sensitive payment data in logs.
- Private KYC/evidence storage outside webroot, retention/access policies, audited admin access and redacted logs. Do not publish `.env`, `.git`, backups, debug pages, storage directories or secret-bearing source maps.
- Task-fraud controls: account verification, referral self/cycle/device-cluster checks, per-user/provider limits, budget reserves, evidence verification and review holds. Shared IP alone must not automatically prove fraud.
- Signed callbacks, permanent deduplication, out-of-order event handling and reconciliation. Payment authority never rests on a browser/mobile callback.
- Dependency/secret scanning, source review, infrastructure hardening, security alerts, encrypted backups, restore drills and incident procedures.
- Google/ad-provider compliance review for tasks; separate financial provider and Play Store requirements. Disable unsupported countries/features transparently, not to mislead review.

Prelaunch tests must include concurrent double-spend, repeated/fabricated completion, same completion under different event IDs, wrong package/provider, forged geo/rate/amount, expired/replayed pairing tokens, production `is_test`, device/channel spoofing, feature switched OFF mid-task, duplicate redemption, referral cycles, recursive commission, mode-change regrant, source reversal, provider timeout, out-of-order callbacks, stale quotes, double subscription charges, SQL/XSS/CSRF/IDOR/SSRF, malicious uploads, compromised admin permissions, report access, nightly reruns, daylight/timezone boundaries and restored-backup reconciliation.

Independent security review/pentest and fixes of material findings are launch gates. Define provider incident ownership, payout pause process and recovery runbooks before real customer funds.

## 17. Implementation phases after explicit approval

1. Finalise countries, legal/provider roles, asset/network list, card eligibility, task providers and funded commercial terms. Confirm referral mode/defaults and redemption economics.
2. Approve amended premium-v2 screen map, including website earning ON/OFF states and referral screens. Current image boards are design references, not complete technical acceptance artefacts.
3. Build identity/security and append-only ledger foundations, admin permissions and sandbox adapters.
4. Integrate deposits, internal transfers, swaps, withdrawals and reconciliation; prove invariants before live funds.
5. Integrate merchant tools, subscriptions and cards with provider-specific acceptance tests.
6. Integrate tasks, signed callbacks, country/device rates, independent channel switches, referral modes and funded redemption.
7. Build current dashboard, nightly 30-day reporting, all-user views, cron/worker administration and operational alerts.
8. Performance/accessibility review, security testing, provider certification, policy review and controlled launch.

No fixed launch date can be promised before provider access and approvals. Every phase needs testable acceptance criteria and rollback/operational readiness.

## 18. Decisions still requiring commercial/product confirmation

- Supported launch countries, custody/legal model, provider contracts and actual assets/networks.
- RikoCoin redemption rate/output currencies, minimums, budget backing and expiry policy.
- Referral mode A/B/C at launch; X/Y/P amounts; qualification window; commission duration/caps. Proposed defaults above are not final pricing.
- Whether pending verified rewards auto-credit when a channel reopens or require explicit user claim; either path shares one settlement entitlement.
- Which app/web partners can provide verifiable completion; package name alone is insufficient.
- Exact platform fees and whether charged in input, output or separately; settlement recipient and timing.
- Reporting timezone acceptance, financial retention period and data-residency obligations.

## 19. Public wallet ID and unified referral identity

Owner requirement: every user gets a unique numeric ID of at least six digits, used as their primary public RikoWallet identity for internal payment and referral. Proposed default: random 10-digit string with no leading zero; six digits alone gives a relatively small enumerable namespace. Final displayed length can be agreed before implementation. Generate cryptographically random values with a database unique constraint and collision retry. IDs are immutable and never reassigned to another person after account closure. Retain a separate private database identifier for relationships and security boundaries.

Signup assigns the wallet ID, then the optional referral-code step accepts an existing eligible user's wallet ID. UI labels: "Wallet ID / Referral code" on My Referrals, "Recipient wallet ID" on transfer. Share uses a first-party URL containing that public code; landing/app deep linking may prefill it, but server attribution validation still runs. App-install attribution does not rely on unsupported automatic deferred-link assumptions: preserve the code on web or allow manual entry after signup.

Payment flow: enter/scan wallet ID → rate-limited recipient lookup → show minimal verified display-name confirmation → choose asset/amount → review recipient, fees and total → authorise transfer. Never auto-pay just because the ID exists. Public username is a display label, not the primary routing key. A valid wallet ID does not expose balances, email, phone or KYC information. Throttle enumeration and return minimal errors. Never use wallet/referral ID as a password, API token or reward authorisation.

This supersedes username-based primary payment routing and any earlier assumption of a distinct public referral code. It does not replace one-time partner pairing codes or signed completion callbacks.

## 20. My Referrals and inactive-user reminders

Entry point: Earn → My Referrals, available only on channels with earning enabled. Screen content:

- Wallet ID/referral code, copy and share buttons, shareable referral link and QR.
- Instructions immediately below the code, generated from the user's applicable referral policy version: mode A states exact new-user and referrer bonuses plus qualification; mode B states the new-user bonus and that the referrer receives no commission; mode C states the percentage, eligible task-reward base, duration/caps and exclusions. Do not advertise recurring commission for mode A/B.
- Total direct referrals, active referrals, inactive referrals, net approved referral commission, pending commission and separately labelled signup bonuses.
- Tabs All / Active / Inactive, with privacy-limited usernames, joined date and referral reward contribution. No referred user's wallet balances, email, contact details, precise location or browsing/task history.
- Current contest shortcut showing own rank, eligible referral earnings and potential rank prize when contest is ON.

Proposed definition: active means at least one approved qualifying task reward during the preceding 30 rolling days; inactive means an eligible direct referral with no such reward in that period. This is earning activity, not merely logging in. Show the definition in UI, and keep it separate from dashboard DAU/MAU. Admin can configure the period prospectively. Closed/blocked accounts are counted separately and excluded from reminders.

"Notify inactive referrals" initiates a backend-authorised batch for eligible inactive direct referrals. It never accepts an arbitrary recipient list from the client. Use an admin-approved English template, not referrer-entered free text; no guaranteed-income claim, sensitive balance or pressure to click ads. Respect recipient promotional/referral-reminder consent, OS push permission, opt-outs and channel availability. Proposed limits: once per sender per 7 days and once per recipient per 7 days globally, with configurable daily programme cap. Record all attempts and skip reasons.

The button displays eligible recipient count and confirms sending; server rechecks consent, inactivity, ownership, limits and earning availability at dispatch. Queue, deduplicate by recipient/reminder window, support cancellation/expiry and clean up invalid push tokens. Display accepted/sent counts separately from delivered/opened; push delivery is not guaranteed. Do not auto-send SMS/email when push is unavailable. Website-origin reminders can reach a consenting user's installed app; no device token means no push. No live notifications are authorised during planning.

## 21. Monthly referral contest and reward pool

Contest ranking is based on **net approved referral commission earned from direct referrals' qualifying task rewards during the contest month**. Not total wallet balance, own task earnings, raw referral counts, deposits, transferred RC, signup bonuses or previous contest prizes. Pending, rejected and reversed commission is excluded. Label the column "Referral earnings this month", not spendable balance. Contest prizes never generate referral commission or count toward any contest score.

This metric operates when ongoing referral commission exists (mode C or a still-valid grandfathered cohort). In mode A/B with no eligible commissions, show not eligible/no score; do not silently rank signup bonuses. Admin can require compatible referral cohorts and disclose eligibility before the month starts. No paid entry or random winner selection is proposed.

Settings: global contest ON/OFF, applicable earning channels/countries, monthly prize pool in RC, winner count 10/15/20, eligible referral policy cohorts, minimum net score, rank allocation, tie rule, reporting timezone, season start/end and announcement/banner assets. Prizes can be explicit RC amounts per rank or percentages summing to 100%. Use fixed precision; assign rounding remainder by a published deterministic rule. If fewer eligible winners exist, unallocated prizes remain in the pool; no automatic payout to zero-score users. Rules and funding are frozen for each live season. Changes apply to the next season by default.

Pool funding reserves platform-owned reward budget before activation, including backing consistent with RC redemption obligations. "Admin entered 100,000 RC" alone is not proof of backing. Display pool funded/allocated/paid/remaining; zero or insufficient funds prevents activation or pauses unpaid settlement with a visible admin alert.

Proposed monthly clock: Asia/Kolkata, from first day 00:00 inclusive to next month's first day 00:00 exclusive; UTC timestamps stored. Tie rule: higher net eligible score, then earlier server posting time of reaching the final score, then stable wallet ID. Reversals trigger deterministic recalculation. Publish tie rule and cutoff. Provider completion dates cannot backdate late credited commission into a closed season; score uses trusted ledger posting time.

States: DRAFT → SCHEDULED → LIVE → CLOSED → FINALISING → PAYING → PAID; exceptional states PAUSED/CANCELLED with audited policy and notices. Proposed settlement schedule: on the 1st at 00:30 local, after previous-day statistics job, finalise the previous month from the authoritative ledger and distribute funded prizes automatically. Statistics-job delay does not silently create wrong rankings: finalisation validates its ledger watermark directly; unresolved integrity/funding problems hold affected payouts and alert admins. Do not guarantee a payout time when verification fails.

Freeze final leaderboard, score evidence, rule version and rank prizes in an immutable season result. Each `(season_id, winner_id)` has one prize entitlement and one idempotent ledger settlement. A unique rank result and funded pool reservation prevent duplicate payouts. Worker crash/retry/duplicate cron may resume remaining winners but cannot repay completed winners. Notifications run after credit commit. Maintain settlement progress and receipts.

Suspected fraud is marked under review under published rules; no hidden post-deadline discretionary score edits. If an award needs reversal after payout, append a documented adjustment and preserve original standings/audit history; don't silently rerun the old pool. Define appeals and recovery before launch.

Turning contest OFF hides the banner/page and blocks new contest participation on disabled channels. It does not confiscate accrued task/referral rewards or cancel already-promised season payouts. Preserve ongoing season settlement obligations; distinguish stop-new-seasons, emergency pause and prospective disablement. Admin must see these effects before confirming. When earning is OFF for a channel, contest entry is also hidden there. Ordinary wallet history still shows awards already credited.

### User contest page

Premium layout: compact season header and countdown; "Referral Contest Live" banner with actual funded prize pool; personal summary card with own rank, net monthly referral earnings and provisional rank prize; top-three podium; full top-10/15/20 table beneath with rank, public username and RC referral earnings and rank prize. Mark current standings/prizes as provisional until finalisation. Non-ranked users see "Not ranked yet" rather than a fabricated rank. Mask public wallet IDs and provide unique public usernames; do not expose full payment identifiers or whole wallet balances on leaderboard. Use a disclosed public-alias/participation policy.

Rules/help link, last-updated timestamp, eligibility and previous-season results are available. Personal rank can be below top N and still shown without exposing every participant publicly. Use near-real-time or short-cache provisional ranking from approved commission postings; reconcile against nightly stats and final ledger snapshot. One fixed contest timezone applies to all participants; display local deadline equivalent as a convenience.

## 22. Contact Us support chat and English-only messages

Authenticated Contact Us includes direct support-team chat with conversation history, open/closed status, agent assignment, unread count, attachments/evidence under secure upload rules and online/offline/service-hours status. Do not promise 24/7 human availability without staffing. Support is available outside Earn as a core account help route.

All user-facing support templates and agent replies are English. The composer states "Please write your message in English." Validate language on the server as well as in the UI; reject clearly non-English prose with a helpful English prompt and preserve the draft. Language detection is imperfect for short messages, names, IDs and Romanised languages: allow transaction IDs, numbers, URLs and necessary names; request clarification for uncertain prose and support human review. ASCII-only validation does not enforce English. Offer optional user-reviewed translation only if separately approved; no silent forwarding to a third-party translator. Uploaded evidence can contain original text, but the user's description is English.

Rate limits, maximum message/attachment sizes, spam/abuse reporting, safe link rendering, malware scanning, private file access and moderator audit trails protect the channel. Do not label one short message or an imperfect language detector result as an automatic permanent offence. Proposed moderation: warning → temporary support-send restriction → manual permanent restriction for confirmed abuse, with recorded reason, actor and expiry.

Authenticated real-time channels must enforce per-conversation membership and staff permission. Never broadcast conversation content or balances to other users. Avoid sensitive chat content in push notifications. Agents cannot ask users to disclose OTPs, passwords, seed phrases or CVV. Keep an accessible appeal/help route for users whose ordinary chat is blocked and an account-recovery route when login is locked.

## 23. Admin restrictions and balance adjustments

Separate admin actions with explicit reason, duration/expiry (temporary or permanent), scope, user notice, audit and revoke/appeal handling:

| Action | Effect |
|---|---|
| Support/contact restriction | Prevent new support messages/uploads; preserve read-only history and a separate appeal route |
| Earning restriction | Block new task starts/claims/redemptions as configured; preserve balances, evidence and pending liabilities |
| Account login lock | Revoke sessions, block new login except approved recovery; preserve underlying financial records |
| Financial operation restriction | Hold selected sends/swaps/withdrawals under defined risk policy; reconcile operations already submitted |
| Full account block | Combine explicit scopes; does not erase funds or authorize automatic confiscation |

Support misconduct does not automatically freeze unrelated customer funds. Full-account/financial restrictions are separate privileged decisions. Externally issued cards may require an issuer freeze request; marking the account blocked locally alone does not stop card network spending. Late deposits/provider events are still recorded into hold/review as appropriate. Existing subscriptions, pending withdrawals and card authorisations require explicit treatment on suspension.

Admin may add or reverse balances in any supported asset, but only through controlled adjustment operations:

- Credit: choose user, asset/network context, exact amount, funded source/treasury account, reason and evidence; create balanced ledger entries. If backing is insufficient, reject or leave pending, never invent withdrawable coins.
- Reversal/debit: select original operation where applicable, cap reversal to remaining reversible amount, check available/reserved funds and record compensating entries. A completed blockchain withdrawal cannot be undone by editing the ledger. Already-spent awards need recovery/holds, not unexplained negative balances or taking another user's funds.
- General corrections: reference an adjustment case and counterpart account; no direct database balance edit or deletion of transaction history. RC promotional grants draw from reward budget and do not generate referral commission/contest score.
- Cross-currency corrections are explicit conversion/adjustment entries with a documented valuation; admin cannot casually change BTC into USD by editing a currency column.
- High-risk adjustments, permanent full-account blocks and custody actions require maker/checker approval, step-up authentication, threshold rules and anti-self-approval. Admin roles can request vs approve separately. Notify affected users with an understandable reason/receipt where appropriate.

Dashboard reports adjustments separately from deposits, trading profit, earned RC and referral commission. All changes include actor, timestamps, case ID, previous/new state and review history; sensitive audit storage is tamper-evident and inaccessible to normal support staff.

## 24. Internal-first swap allocation (prior owner requirement consolidated)

Prefer internal settlement using actual company-owned free inventory, subject to provider/legal permission. Customer backing, pending withdrawals, card funding commitments and existing holds cannot be repurposed as company trading capital. Reserve source user funds and target inventory atomically. Incoming user asset becomes company inventory only as part of the executed, balanced swap; customer liabilities change correspondingly. Reconcile provider/custody holdings against customer liabilities and company positions per asset.

Admin controls: allocated funds per asset; allowed pair directions; per-swap min/max; per-user daily/monthly and global daily limits; asset outflow caps; reserve floors; price source, staleness and quote expiry; disclosed platform fee (including zero if permitted); inventory exposure limits; rebalancing budget; external provider fee/slippage cap; fallback OFF/automatic within limits/manual approval; pair/asset/global pause. Locks/checks cannot be overridden to spend unbacked funds.

If internal liquidity is insufficient and fallback OFF, offer a smaller executable quote or unavailable state. Manual fallback approval after quote expiry needs a new customer-confirmed quote. Compare total execution/rebalancing costs and protect the accepted customer output rather than secretly choosing a worse rate to increase margin. Track realised spread/fees versus inventory valuation movement, external costs and remaining reserves separately. Network charges are not automatic on internal ledger-only settlement; applicable provider custody/accounting charges may remain.

## 25. Premium-v2 consistency and visual refinement

Canonical mobile navigation is `design/premium-v2/06-responsive.png`: floating deep-aubergine pill, fixed order Home / Pay / Cards / Earn / Profile, thin consistent icons; active item expands into white capsule with lavender-peach gradient icon disk and label. Build a single shared component per client platform. Do not substitute a different menu, icon order, active shape or tab label between screens. If Earn is disabled, use one documented four-item variant and reflow evenly. Tablet/web use the corresponding sidebar. Transaction-focused detail screens may use back navigation, but any displayed bottom bar is the same canonical component.

Refinements proposed before implementation:

- One approved RikoWallet brand mark and one RikoCoin glyph/abbreviation (RC). Existing boards have inconsistent marks and "RIKO/RC" labels; normalise them.
- Consistent typography scale, spacing grid, corner radii and icon family. Strong dark text on pastel backgrounds; less low-contrast lavender text and no tiny decorative labels for financial information.
- Home: one clear available-balance focus, separate pending/card/RC values, currency breakdown and four consistent actions Deposit / Send / Swap / Withdraw. Avoid treating deposits as investment gains or showing fabricated percentage returns.
- Earn: featured contest banner, compact coin summary, My Referrals entry and well-spaced task cards with clear reward/status. A custom trophy/coin illustration may be generated later, but campaign title, dates, pool and amounts must be live text, not baked into a banner image.
- Contest: restrained trophy illustration, gold accents for top ranks, readable podium/table and highlighted personal standing; no flashing banners or casino-like wheel visuals.
- My Referrals: prominent copy/share card, mode-specific instructions, active/inactive pills and a reminder button with recipient count/cooldown.
- Support: simple familiar chat bubbles, visible English-only hint, clear agent availability and accessible attachment controls.
- Financial confirmations: large recipient/amount, explicit fee breakdown, one main CTA. Sample rates and maths must be corrected in implementation; image generation is not an accounting specification.
- Keep illustrations compressed/lazy-loaded, maintain 44–48 logical-pixel touch targets where appropriate, respect reduced motion, keyboard and screen readers. Test actual responsiveness rather than assuming rendered mockups prove it.

New screen inventory: Earn with contest ON; Earn with contest OFF; contest leaderboard/personal rank/rules/history/payout status; My Referrals all/active/inactive; referral instructions for A/B/C; reminder eligibility/cooldown/result; wallet-ID recipient confirmation; support chat/offline/English validation/restricted state; admin contest setup/rank prizes/settlement; admin restriction case; admin funded adjustment review.

## 26. Additional acceptance tests and operations

- Referral score counts only qualifying net commission, not signup/prize/admin grants; no self-referral, cycles, multi-level prize commission or cross-channel duplicate qualification.
- Month boundary, leap year, timezone, equal score ties, reversals, late events, fewer winners, insufficient funding, fraud hold, disabled contest and mode changes produce deterministic results.
- Duplicate cron, parallel settlement workers and crash after ledger commit do not duplicate prizes; original results remain auditable after corrections.
- Reminder button cannot target non-referrals, active users, opted-out recipients or exceed global recipient cooldown. Multiple referrer sessions cannot bypass limits; queued sends recheck state.
- Wallet IDs cannot collide, be reassigned, bypass recipient confirmation or disclose account data through enumeration.
- English checks handle IDs/short replies safely; chat access/attachments are private; expired temporary restrictions lift once; blocked users retain recovery/appeal access.
- Support block does not automatically block unrelated funds; full block triggers explicit card/provider actions without losing outstanding provider events.
- Funded admin credits/reversals remain balanced, cannot reverse more than allowed or replay approvals, cannot affect already-final external transfers, and never mint unsupported assets.
- Internal swap allocations respect customer liabilities under concurrency; external fallback never exceeds caps or changes an expired quote without consent.
- Every screen with navigation uses the canonical menu, including enabled/disabled Earn variants; mobile/tablet/desktop layouts and accessibility are reviewed.

## 27. Primary references

- OWASP transaction authorisation: https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html
- OWASP business logic security: https://cheatsheetseries.owasp.org/cheatsheets/Business_Logic_Security_Cheat_Sheet.html
- Android Play Integrity server verification: https://developer.android.com/google/play/integrity/standard
- Signed webhook reference: https://docs.stripe.com/webhooks/signatures
- Google rewarded-ad policy: https://support.google.com/admob/answer/7313578?hl=en
- Google paid-to-surf policy: https://support.google.com/adsense/answer/1348729?hl=en
- Swap integrator monetisation example (not a selected custody provider): https://help.0x.org/articles/4165900987-how-to-monetize-your-app-with-0x-swap-api

These references inform the design; none guarantees provider acceptance or regulatory approval for RikoWallet.

## 28. Configurable KYC access and referral qualification (3 October 2026)

Planning only; development remains unapproved. This extends the existing KYC and referral rules.

### Admin: KYC & Access Rules

- Independently require approved KYC for: opening Earn, starting tasks, claiming rewards, redeeming RC, deposits, withdrawals, internal send/receive, currency swaps, requesting/issuing cards, loading cards, and business payment features. Configure country and method/provider scope where relevant; show the effective requirement in the admin preview.
- Admin switches control platform requirements. Mandatory provider/country requirements form a non-overridable minimum; turning a platform switch OFF cannot bypass those requirements. Defaults remain restrictive for financial actions until provider requirements are established.
- Statuses: not started, submitted/pending, approved, rejected/resubmit, expired/reverification. Only current approved status satisfies a KYC gate. Submission alone is insufficient.
- A locked Earn tab opens a concise verification-required screen with Start KYC / View status; it exposes no task catalogue, start or claim action. App and web share server-side access checks. Earning channel OFF remains a separate rule and hides the feature entirely.
- Enforce every rule on the API, task start, callback qualification, reward release and withdrawal execution; UI hiding is insufficient. No rewards from client status flags or a reusable public wallet ID alone.

### Referral KYC switches

- `Require referred-user KYC before referral rewards`: applies to referrer fixed signup bonus and direct task commission; no spendable credit while the referred user is unverified.
- `Require newcomer KYC before newcomer signup bonus`: independent control for the fixed newcomer reward in modes A/B.
- `Require recipient KYC before receiving referral rewards`: independent requirement on the referrer receiving the bonus/commission.
- A valid referral relationship is linked at signup/code entry and retained during KYC. Qualification, funding and anti-fraud checks must all pass before credit. Identity verification reduces duplicate-account abuse but is not the only fraud check.
- If Earn access requires KYC, the newcomer must first be approved to open tasks and earn. Mode C commission then arises only from eligible approved task earnings, never merely from completing KYC.
- Example: newcomer enters 5826149037, sees Verify to unlock earning, completes approved KYC, then earns 100 RC from a valid task. At an illustrative 10% commission, the referrer receives 10 RC after all applicable recipient checks; the newcomer retains 100 RC. Mode A/B fixed rewards instead follow their configured one-time triggers.

### Policy changes, pending rewards and audit

- Version every change with actor, timestamp, reason, effective time and affected scope. Admin chooses new signups only or all currently unverified users; preview impact before saving. Stricter access rules can gate future actions for existing unverified users, without deleting balances or historical rewards.
- Keep legitimate pre-existing reward entitlements in a separate pending state with their original economic terms. If a new release gate applies, show Awaiting KYC; do not silently forfeit, re-price or duplicate them. Already credited rewards are not automatically reversed by changing a switch.
- Approved KYC events release qualifying pending rewards exactly once in a ledger transaction. Task completion evidence can still be recorded when a user loses access, but it cannot bypass the current release gate. Rejected/expired verification holds release and provides a resubmission/support path.
- Inbound external deposits that arrive after a restriction are reconciled into a restricted/pending balance or provider-approved return flow, never ignored. Existing balances and transaction history remain visible where permitted.
- Tests must cover each action and both clients, direct API bypass, stale sessions, KYC expiry, forged callbacks, duplicate KYC events, policy changes during task completion, blocked referrer, cross-channel reward replay and existing pending entitlements.

### Additional design screens

Admin KYC action matrix; country/method overrides and effective policy preview; referral KYC qualification switches; policy impact confirmation; mobile/web Earn locked; KYC submitted/rejected/approved; referral bonus Awaiting KYC and released receipt. Use the premium-v3 palette and canonical navigation.
