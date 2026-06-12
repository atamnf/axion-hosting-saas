# Architecture

Axion Hosting is a PHP SaaS application that connects user authentication, balance/payment logic and automated server provisioning.

## Main flow

1. User logs in via Discord or Telegram.
2. If the account is new, the user completes a profile.
3. The app creates or links a Pterodactyl user.
4. User tops up balance through ENOT.io.
5. ENOT webhook confirms payment and updates transaction status.
6. User orders a server plan.
7. The backend creates a server through the Pterodactyl Application API.
8. Server details are saved in the database and shown in the dashboard.

## External services

- Discord OAuth2 for login.
- Telegram OAuth for login.
- ENOT.io for payments.
- Pterodactyl for hosting panel automation.
- Discord webhooks for optional admin notifications.

## Important files

- `config.php` — environment loading, DB connection, helpers, Pterodactyl client.
- `api.php` — main app actions: deposits, promos, orders, tickets, renewals.
- `enot/webhook.php` — payment callback processing.
- `complete-profile.php` — onboarding after OAuth.
