# RikoWallet development status

Development authorised by the owner on 3 October 2026. Laravel is at the project root; Flutter source is in `flutter_apk/`. This document distinguishes implemented behaviour from provider or release dependencies.

## Working development flows

- Current launch scope (5 October 2026): RikoCoin-only earning with manual payout requests. `RIKO_REWARDS_ONLY` defaults to true; prior fiat features remain in source but are gated. Admin > Manual withdrawals manages payout currencies/rates, country-scoped methods, Cards/Crypto/Wallet categories, versioned images, minimum/maximum RC and flat/percentage/capped fees. Requests reserve RC, lock their rate/fee snapshot and require assigned-admin processing before external payment is marked paid. Rejected or confirmed-unpaid requests return held RC. See root `PENDING-WORK.txt` for the remaining launch work and limitations.

- Initial release scope (4 October 2026): INR, USD, EUR and RikoCoin. Crypto is disabled for new user operations and hidden from balance/method selectors; historical ledger data is preserved. Cards show Coming soon with no application or load action. Fiat provider approval and live settlement integration are still pending.
- Deposit/withdrawal amount entry fetches a server-calculated fee estimate on web and Flutter: gross, processing, platform, fee tax, total fees and net credit. Admin > Methods configures separate schedules per method. Unconfigured schedules show unavailable rather than fabricated free pricing. Tax applies to combined processing/platform fees, not principal; fixed fees require currency-specific methods. No FX conversion or live settlement is implied by a quote. Provider rates remain unset until confirmed; 2.99% + 18% is tested as an example, not installed as a live fee.

- Laravel 12, PHP 8.2, MySQL-compatible InnoDB schema; local XAMPP database uses MariaDB 10.4 via Laravel's MySQL driver.
- Premium white/lilac/blush/blue UI: responsive Laravel Blade website and admin; Flutter native widgets sharing the same visual palette and five-item navigation.
- Registration, password login, regenerated web sessions, CSRF, hashed expiring app tokens in platform secure storage, role-gated admin.
- Currency balances; append-only balanced ledger services; exact monetary arithmetic; account row locks; idempotent internal transfers; insufficient balance protection.
- Sandbox inventory swaps with expiring quotes, explicit platform fees and funded output inventory. Live market pricing is intentionally unavailable.
- Country-filtered deposit/withdrawal method catalogues. Unconnected providers return unavailable without moving funds or fabricating deposit addresses.
- KYC private document submission, review queue, audited reviewer decisions and per-action KYC checks.
- Independent web/app earning switches; task targeting and country reward overrides; reward reservations; signed verification callbacks; fixed referral modes and direct task commissions; pending KYC-gated referral release.
- Expired unverified task reservations return reward and commission allocations exactly once. Verified rewards awaiting KYC retain their allocation and support callback retries after the original session expiry.
- RikoCoin redemption into USD backed by an allocated platform balance.
- Monthly contest pool reservation, frozen rankings and idempotent prize posting; initial editor supports equal prizes for 10/15/20 places.
- Payment requests, links and invoices, with authenticated internal checkout and password confirmation. Subscription records are drafts and do not charge anyone.
- Persistent support messages and admin replies, scoped account restrictions, private documents, funded dual-admin credit adjustments.
- Durable daily activity records, India-calendar-day statistics, 30-day admin graphs for signups, active users, transactions, task rewards, referral commissions and USD fees. Protected signed HTTP cron endpoint is optional. Historical activity before this migration cannot be reconstructed.
- Android release signing configuration rejects missing release credentials instead of falling back to debug keys.
- Local sandbox seed with generated credentials; no secrets embedded in Flutter or committed source.

## Still required before a production launch

These are not represented as completed live integrations:

- Select and onboard fiat, crypto custody/KYT, KYC, card, pricing, task attribution and push providers. Implement and certify their individual adapters, reconciliation, reversals and failure recovery.
- Email verification/delivery and password recovery, MFA and recovery workflows, administrator step-up authentication, native integrity verification, operational risk reviews and rate controls beyond baseline.
- Subscription consent and billing engine; recipient QR scanning; card controls/delivery lifecycle; dispute resolution workflows beyond submission.
- Reusable country-tier editor and device-integrity verification, reminder delivery, custom per-rank prizes, individual user earning graphs, provider-aware country overrides.
- Automated fund allocations/rebalancing, provider fallback, reversal approvals, business exports/statements, production exchange prices.
- Production deployment, HTTPS, dedicated least-privilege DB credentials, managed Redis queues/cache, malware scanning, encrypted document lifecycle/retention, monitoring and tested backups.
- Multi-session/concurrency load tests, independent penetration test and dependency review; provider approval and applicable operating permissions.
- Physical Android/iOS device testing, release signing, store metadata and store approval.

The app and website are functional local development builds, not production-ready financial services. The integration guide remains the target specification; this status file describes the current implementation honestly.
