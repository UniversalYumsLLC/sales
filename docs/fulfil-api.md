# Fulfil ERP API Reference

## Connection

- **Authentication**: Personal Access Tokens via `X-API-KEY` header
- **Base URL**: `https://{subdomain}.fulfil.io/api/v2/`
- Sandbox and Production environments supported
- Environment variables: `FULFIL_SANDBOX_SUBDOMAIN`, `FULFIL_SANDBOX_TOKEN`, `FULFIL_PRODUCTION_SUBDOMAIN`, `FULFIL_PRODUCTION_TOKEN`, `FULFIL_DEFAULT_ENV`

**Important**: GET endpoints return only `id` and `rec_name` by default -- you must specify the `fields` parameter to get additional data.

## Key Models

| Model | Purpose |
|-------|---------|
| `party.party` | Customers/Contacts |
| `party.contact_mechanism` | Contact emails, phones, custom data fields |
| `party.category` | Categories including shipping terms |
| `account.invoice` | Invoices for AR tracking |
| `account.invoice.payment_term` | Payment terms (Net 30, etc.) |
| `product.price_list` | Price lists for discount extraction |

## Contact Mechanism Patterns

- **Department contacts**: `name` = "Buyer: John Smith", `value` = email
- **Broker contacts**: `name` = "Broker (Company): Contact Name", `value` = email
- **Data fields**: `name` = "data", `value` = "shelf_life_req:180"

## Hard-Coded Filter IDs

### Channel Filter (B2B)
- Channel ID: `19` / Code: `RTL1` / Name: `Retail Channel`

### Account Filter (AR - B2B)
- Account ID: `128` (Accounts Receivable - B2B)
