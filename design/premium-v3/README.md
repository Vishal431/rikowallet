# RikoWallet premium-v3 design review

17 image boards covering mobile app, website, admin and configurable KYC. Created with the built-in image generation tool. Visual concepts only; no application development has started.

| Board | Feature group |
|---|---|
| [01-onboarding](01-onboarding.png) | Welcome & account |
| [02-wallet-deposits](02-wallet-deposits.png) | Wallet & deposits |
| [03-transfers](03-transfers.png) | Internal payments |
| [04-swap-withdraw](04-swap-withdraw.png) | Swap & withdrawals |
| [05-cards](05-cards.png) | Prepaid cards |
| [06-business](06-business.png) | Business payments |
| [07-earning](07-earning.png) | Earn RikoCoin |
| [08-referrals-contest](08-referrals-contest.png) | Referrals & monthly contest |
| [09-support-profile](09-support-profile.png) | Support, activity & states |
| [10-web-public](10-web-public.png) | Website / public experience |
| [11-web-wallet](11-web-wallet.png) | Website / wallet & business |
| [12-web-earn-support](12-web-earn-support.png) | Website / earning & support |
| [13-admin-overview](13-admin-overview.png) | Admin / analytics & users |
| [14-admin-earning](14-admin-earning.png) | Admin / tasks & referrals |
| [15-admin-contest](15-admin-contest.png) | Admin / contest & liquidity |
| [16-admin-security](16-admin-security.png) | Admin / support & operations |
| [17-kyc-access](17-kyc-access.png) | KYC action requirements, referral qualification, locked Earn and pending rewards |

## Design authority and review notes

The integration guide at ../../docs/INTEGRATION-GUIDE.md is the behavioural specification. Generated images demonstrate visual hierarchy and layout, not final rates, sums, labels, eligibility or production readiness. Some sample text still needs editorial correction before implementation.

- Canonical mobile navigation is Home / Pay / Cards / Earn / Profile, with the active label in a white capsule. Earn OFF uses four items. Screens must use one shared component; some mockups still differ in selected tab, labels or logo.
- Referral modes are administrator-only choices. Website board 12's Standard/Creator wording is a generated placeholder: replace with the current configured A/B/C read-only instructions. Rank is net approved direct task-referral commission, not referral count.
- Task categories are App surf, Web surf, App install and Surveys. Any generated daily check-in, profile/transfer reward or achievement card is not an approved feature. Package identifiers and signed evidence requirements follow the guide.
- RC is an internal reward unit, not an externally withdrawable blockchain asset. Company swap allocations are per real asset; total reporting uses USD equivalent, not RC. No implied investment return or guaranteed instant/global card issuance.
- Monetary totals, rates, dates and prize allocations are illustrative and require consistent live data. Top10/15/20 means ranks 1 through the selected total; settlement is on the first of the following month, not during the same month. Cron timing uses the configured timezone and protected triggers.
- Admin dashboard must include per-currency customer balances below charts and expandable currency balances in the user list, even where the compressed board omits them.
- KYC requirements apply at the API as well as UI. Provider-required checks cannot be disabled. Changing a policy does not delete historical balances or credit rewards twice.
- Responsive behaviour, accessibility and security cannot be tested from raster mockups; these await authorised development.

## Prompt set

Shared direction: reference premium-v2/06-responsive.png; white surfaces, lilac/blush/powder-blue/butter accents, aubergine floating navigation, premium readable rounded layouts, illustrative sample data. Mobile boards request six screens; website/admin boards request four views. The KYC supplement combines admin controls with three mobile states.

### 01-onboarding — Welcome & account

Welcome/signup; Login with password biometric option; OTP verification; Enter referral wallet ID optional skip and applied preview; Country KYC document selfie checklist; Security setup PIN and two-factor recovery.

### 02-wallet-deposits — Wallet & deposits

Home wallet overview USD equivalent separately INR USD EUR BTC TRX USDT rows, four actions Deposit Send Swap Withdraw and RikoCoin separate; Asset detail available reserved pending; Country-filtered deposit methods India bank and UPI illustration labelled sample; Crypto asset/network select USDT TRON; Deposit QR masked address network warning; Deposit tracking confirmed receipt.

### 03-transfers — Internal payments

Send enter 10-digit wallet ID 5826149037 with recipient preview Sara; Amount USD40 choose available currency; Review USD40 no fee total40 step-up confirm; Success receipt USD40; Receive own wallet ID QR copy; Request money wallet ID currency amount note and incoming request actions.

### 04-swap-withdraw — Swap & withdrawals

Swap BTC USDT pair amount quote with fees and expiry; Confirm swap illustrative pay100 USDT platform fee0.50 output99.50 USDT-equivalent BTC rate clearly sample; Swap complete transaction receipt; Withdraw country-filtered bank/crypto methods; Crypto withdrawal address network amount fee review; Withdrawal pending timeline and saved beneficiary management entry.

### 05-cards — Prepaid cards

Cards overview separate wallet and card balance deep-purple branded card; Apply virtual card KYC country issuer pending; Card details masked number freeze controls; Load card source USD wallet 100 card credit fee1 total101; Physical order address issuance shipping quote; Card delivery activation and spending-limit controls.

### 06-business — Business payments

Business dashboard links invoices checkout subscriptions; Create payment link amount currency expiry share; Invoice list paid pending and create item client due date; Merchant checkout internal RikoWallet payment recipient total review; Subscription consent monthly amount next date cancel terms; Manage subscriptions payment history cancel and insufficient balance.

### 07-earning — Earn RikoCoin

Earn overview RC2500 pending150 contest live trophy banner prize pool100000RC view rankings My Referrals task tiles daily timer; Task catalogue tabs App surf Web surf Install Survey country-device eligible cards; App surf detail package com.partner.demo reward100RC instruction guide Pair app button code masked verification pending; Web surf task detail approved partner instructions no ad-click reward; Install task details Android India qualification and survey card entry; Earning history approved pending rejected and redemption quote labelled illustrative rate.

### 08-referrals-contest — Referrals & monthly contest

My Referrals own code wallet ID5826149037 Copy Share QR 12 active 8 inactive modeC10percent task-commission instructions; Inactive referrals list Notify eligible7 and consent-aware cooldown; Referral rule cards ModeA both fixed ModeB newcomer only ModeC earning percentage clearly illustration of selectable policies; Premium October monthly contest hero100000RC trophy personal rank12 earnings2400RC provisional prize1000RC podium leaderboard rows rank username monthly referralRC prizeRC; Contest full top10 table and Top10 Top15 Top20 explanatory setting preview not user choice previous month link; Previous season results prize credited receipt Nov1 distribution and rules tie cutoff.

### 09-support-profile — Support, activity & states

Contact us English-only live support chat bubbles agent available composer; Please write in English validation draft preserved; Support temporarily restricted until date read-only history Appeal; Profile KYC security sessions notifications settings; Activity filters wallet crypto cards RC receipt and create dispute; Home with earning OFF canonical FOUR nav items Home Pay Cards Profile no Earn no tasks banner.

### 10-web-public — Website / public experience

Public landing page premium wallet hero crypto cards value no false approvals and sign-in; Features and supported countries/assets page with eligibility selector; Pricing clear platform/provider fee examples marked illustrative and help; Responsive public mobile landing beside desktop contact FAQ footer.

### 11-web-wallet — Website / wallet & business

Desktop wallet sidebar overview currency balances separate RC deposit withdraw Send by walletID; Desktop swap and withdrawals quote panel asset activity history; Desktop cards virtual physical load and delivery; Desktop business invoices payment links subscriptions checkout setup.

### 12-web-earn-support — Website / earning & support

Web earning ON dashboard task catalogue RC balance My Referrals contest banner; Web referral contest monthly personal rank full leaderboard RC prizes; Web My Referrals walletID copy share active inactive notify eligible and mode-specific instructions; Web support English-only chat plus inset web earning OFF sidebar with no Earn.

### 13-admin-overview — Admin / analytics & users

Admin overview top totalusers USD-equivalent customer liabilities pendingwithdrawals 30day charts newusers activeusers platformincome taskRC referralRC nightly updated timestamp below percurrency balances table; All Users table walletID joined referrer referrals multibalance deposits withdrawals expand; User detail 30day taskreferral earning graph balances each asset deposits withdrawals tabs; Financial operations queue deposit withdrawal swap reconciliation status action reviewer.

### 14-admin-earning — Admin / tasks & referrals

Earning control independent web ON app OFF global ON category switches; Create app-surf task package name reward countrytier deviceOS origin targeting budget evidence provider; Country-tier rates grid task-country-device precedence and verification callback inbox signed duplicate status test sandbox; Referral mode selector A bothfixed X Y Bnewonly X C taskcommission percentage no recursion preview.

### 15-admin-contest — Admin / contest & liquidity

Monthly contest ONOFF season pool funding Top10 Top15 Top20 rank prize configuration; First-of-month settlement dashboard frozen ranks payout paidpendingheld rerun-safe logs; Internal-first swap allocatedcompanyfund perasset reserved safetybuffer limits maxswap fallbackOFFAUTOAPPROVAL fee caps; Deposit withdrawal method editor separate countryallowlists currencies fees limits status preview India-only UPI.

### 16-admin-security — Admin / support & operations

Support inbox English chat moderation temporarypermanent support earning financial fullaccount scopes reason expiry appeal; Funded balance adjustment credit reverse asset amount funding source originaloperation dual approval audit no direct balanceedit; Cron Jobs page schedule timezone lastnext run logs nightly stats first-month prizes copycommand protectedURL setup no visible secrets; Provider health credentials masked roles audit KYC cases merchant card operations settings. Also KYC policy entry: per-action requirements deposit withdrawal earning cards, referred-user KYC before referral rewards.

### 17 — KYC & access

Per-action KYC matrix, provider-required locks, referral KYC switches, policy scope preview, Earn verification gate, under-review identity verification and referral bonus awaiting KYC. Corrections to boards 06, 08 and 11 separate USD payments from RC rewards and make referral mode read-only.

