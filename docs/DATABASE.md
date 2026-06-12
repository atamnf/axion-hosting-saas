# Database notes

The project expects a MySQL/MariaDB database. Exact production schema can differ, but the main entities are:

## Main tables

- `users` — accounts, OAuth IDs, profile data, balance, Pterodactyl user data.
- `plans` — server tariff plans: price, CPU, RAM, disk.
- `servers` — purchased servers and next payment date.
- `transactions` — deposits, purchases, renewals, referral bonuses.
- `tickets` — support ticket headers.
- `ticket_messages` — ticket conversation messages.
- `promocodes` / promo-related tables — optional promo and grant logic.

## Recommended practice

For production, use migrations instead of manual SQL changes. Do not commit database dumps with real users, emails, IDs, transactions or passwords.
