# API overview

Most frontend actions are handled by `api.php` using JSON requests.

## Common actions

| Action | Purpose |
|---|---|
| `csrf` | Returns CSRF token |
| `create_deposit` | Creates payment invoice |
| `apply_promo` | Applies promo/referral grant |
| `order_server` | Purchases and provisions a server |
| `renew_server` | Renews an existing server |
| `delete_server` | Deletes a server |
| `create_ticket` | Opens support ticket |
| `list_tickets` | Lists user's support tickets |
| `get_ticket` | Returns ticket messages |
| `send_ticket_message` | Sends ticket reply |
| `transaction_status` | Checks transaction status |

## Example request

```http
POST /api.php?action=order_server
Content-Type: application/json
X-CSRF-Token: <csrf_token>

{
  "plan_id": 1,
  "server_type": "minecraft"
}
```

## Internal API

`api2.php` is protected by `API_PASS` from `.env` and should not be publicly exposed without additional protection.
