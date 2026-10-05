# RikoWallet provider applications

Research checked: 3 October 2026; launch scope updated 4 October 2026. Initial launch: INR, USD, EUR and RikoCoin rewards only. Crypto is deferred; cards show Coming soon. These are application candidates, not approved integrations. Company incorporation, customer residency, business model and individual product approval must be confirmed separately. An API account is not approval to operate a custodial wallet, exchange or card program.

| Candidate | Ask about | Official source |
| --- | --- | --- |
| CoinPayments | Business wallet API, per-customer BTC/TRX/USDT addresses and payouts; written permission for holding customer balances and internal transfers | https://docs.coinpayments.net/knowledge/integration-options/ |
| Rain | Branded stablecoin-funded card program, virtual/physical issuance, cardholder countries and minimum commitments | https://www.rain.xyz/ |
| EnKash | India general-purpose reloadable prepaid card / wallet co-issuing partnership, customer KYC and permitted funding | https://docs.enkash.com/folder-1769242 |
| Cashfree | INR collection/payout and appropriate PPI partnership for the exact wallet use case | https://www.cashfree.com/support/ |
| Striga | European custody, exchange, IBAN and card program; request exact eligible business and user countries | https://docs.striga.com/reference/getting-started-with-your-api |
| Fireblocks | Wallet/custody infrastructure and payment partners if a broader enterprise setup is viable | https://www.fireblocks.com/products/payments |

Cashfree Pay PPI terms specifically prohibit crypto/VDA-related use. Do not assume an INR or prepaid approval permits crypto funding. Source: https://www.cashfree.com/ppi-tnc/

Rain's merchant acceptance footprint does not establish which countries' residents can be issued cards. Striga's published demo describes EEA and Switzerland coverage, not an India launch: https://www.portal.striga.com/demo

## Message to send to provider sales

Subject: RikoWallet — business eligibility and API partnership

We are developing RikoWallet, a web and mobile wallet. Our company is incorporated in [COUNTRY], with entity type [TYPE]. Our intended initial customer countries are [COUNTRIES].

The initial proposed product includes INR/USD/EUR deposits and withdrawals, internal user-to-user transfers and currency conversion. A separate task-reward program grants RikoCoin points redeemable into wallet balances, with referral incentives. These points are internal reward units, not a blockchain token. Crypto and virtual/physical prepaid cards are future roadmap features and will not launch under this application unless separately approved. We will enable only the features and territories explicitly approved for the relevant provider program.

Please confirm:

1. Whether you accept this business model, including stored customer balances, P2P transfers, crypto and task/referral rewards.
2. Which services you can provide, eligible company jurisdictions and eligible customer-residency countries. Please distinguish card issuance countries from merchant acceptance countries.
3. Required licences, issuer/sponsor arrangements, KYB/KYC/AML obligations and responsibility for safeguarding funds.
4. Supported currencies and blockchain networks (especially BTC, TRX and USDT-TRC20), deposit address ownership, refunds and withdrawal support.
5. Setup fees, monthly minimums, prefunding/reserves, deposit/payout/conversion/network/card fees and any revenue share.
6. Sandbox API access, authentication/webhook documentation, idempotency support, reconciliation reports and expected onboarding steps.

Our indicative user count and monthly volumes are [HONEST ESTIMATE / PRE-LAUNCH]. Please provide written eligibility confirmation and product-specific terms before we integrate live money movement.

## Development boundary

`app/Payments` defines a vendor-neutral interface and response contract. The provider registry defaults to no enabled providers. A new migration defines payment intent tracking; it does not issue deposit addresses, submit payments or settle balances. Provider-specific adapters, webhook controllers, queue submission and settlement logic are still to be implemented after selection. Existing public deposit/withdraw/card actions remain unavailable. No live transaction or provider application has been submitted by the agent.
