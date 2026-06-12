# Security notes

This repository was sanitized for public GitHub usage.

## Removed or ignored

- `.env` and all local environment files.
- Runtime uploads.
- Temporary repair scripts.
- Hardcoded Discord webhook URL.
- Private production secrets.

## Keep private

Never commit:

- API keys
- Discord/Telegram tokens
- Webhook URLs
- Database passwords
- Pterodactyl Application API keys
- User files or database dumps

## Current protections in code

- Environment-based config.
- Secure session settings.
- CSRF helper.
- Basic security headers.
- PDO prepared statements in core DB operations.
- AES-GCM helper for stored sensitive data.

## Before production

- Add rate limiting.
- Disable error display.
- Put admin/internal endpoints behind server-level access control.
- Add structured migrations.
- Rotate any token that was ever committed or exposed.
