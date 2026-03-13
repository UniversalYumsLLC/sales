# Sales Application

## Overview
A tool suite supporting the sales function.

## Initial Scope
- Architecture for pulling data from our ERP Fulfil, specifically for active customers
- User Interface for reporting on Active Customers and Accounts Payable

## Technology Stack

### Backend
- PHP 8.2+
- Laravel 12.x
- Inertia.js 2.0 (server-side SPA rendering)
- Laravel Horizon (queue monitoring)
- Laravel Socialite (Google OAuth)
- Spatie Laravel Permission (RBAC)
- Predis (Redis client)
- Ziggy (type-safe routes in JavaScript)

### Frontend
- React 19
- TypeScript 5.7+
- Vite 6 (bundler)
- Tailwind CSS 4
- Radix UI (accessible components)
- Lucide React (icons)
- react-day-picker + date-fns (dates)

### Database & Caching
- SQLite (development), MySQL/PostgreSQL (production)
- Redis for caching and queues

### Testing
- Pest 4.0 with Laravel plugin
- In-memory SQLite for test isolation

### Code Quality
- Laravel Pint (PHP formatting)
- ESLint 9 + Prettier (JS/TS formatting)
- prettier-plugin-tailwindcss (class sorting)

## Architecture Patterns

### Design Patterns
- Service Layer: Business logic in `app/Services/`
- Model Observers: Reactive domain logic
- Form Requests: Combined validation + authorization
- Enums: Type-safe constants
- Contracts: Interface-based abstractions

### Directory Structure
```
app/
├── Console/Commands/     # Artisan commands
├── Contracts/            # Interfaces
├── Enums/                # Type-safe enums
├── Http/
│   ├── Controllers/      # Request handlers
│   ├── Middleware/
│   └── Requests/         # Form request validation
├── Jobs/                 # Queued jobs
├── Models/               # Eloquent models
├── Observers/            # Model observers
├── Providers/            # Service providers
├── Rules/                # Custom validation
├── Services/             # Business logic
└── Traits/               # Reusable traits
```

### Frontend Structure
```
resources/js/
├── components/
│   └── ui/               # Shadcn-style UI components
├── hooks/                # React hooks
├── layouts/              # Page layouts
├── lib/                  # Utilities
├── pages/                # Inertia page components
└── types/                # TypeScript definitions
```

### Queue Configuration
- Separate queues for different concerns (e.g., `fulfil` for ERP sync)
- Horizon supervisors per queue type
- Redis as primary queue driver

## External Integrations

### Fulfil ERP
- Sandbox and Production environments supported
- Environment variables: `FULFIL_SANDBOX_SUBDOMAIN`, `FULFIL_SANDBOX_TOKEN`, `FULFIL_PRODUCTION_SUBDOMAIN`, `FULFIL_PRODUCTION_TOKEN`, `FULFIL_DEFAULT_ENV`
- Service class: `app/Services/FulfilService.php`
- PHP client library: `fulfil-php` (https://github.com/fulfilio/fulfil-php)

#### API Details
- **Authentication**: Personal Access Tokens via HTTP Basic Auth (token as username, empty password)
- **Base URL**: `https://{subdomain}.fulfil.io/api/v2/`
- **Important**: GET endpoints return only `id` and `rec_name` by default - must specify `fields` parameter to get additional data
- **Example**: `GET /api/v2/model/sale.sale/12345?fields=id,reference,party,total_amount`

### Authentication
- Google OAuth via Socialite
- Domain restriction via `ALLOWED_AUTH_DOMAINS`
- Email whitelist via `ALLOWED_AUTH_EMAILS`
- Admin assignment via `ADMIN_EMAILS`

## Development Setup
1. Copy `.env.example` to `.env` and configure environment variables
2. `composer install && npm install`
3. `php artisan migrate`
4. `composer run dev` (runs server + horizon + logs + vite concurrently)

## Production Deployment
- **Hosting**: Laravel Forge
- **Deploy**: Automatic on push to main branch
- **Deploy Script**:
```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

npm ci || npm install
npm run build

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

Migrations run automatically during deployment. No manual migration steps needed after merging PRs.

## Commands
```bash
npm run dev           # Vite dev server
npm run build         # Production build
npm run lint          # ESLint
npm run format        # Prettier
php artisan test      # Run tests
vendor/bin/pint       # PHP formatting
```

---

## Fulfil API Reference

**Note**: This application is read-only for Fulfil data (no creates, updates, or deletes).

### Sales Orders

**Model**: `sale.sale`

#### Endpoints
- `GET /api/v2/model/sale.sale` - List sales orders
- `GET /api/v2/model/sale.sale/{id}` - Get single sales order
- `GET /api/v2/model/sale.sale/count` - Get sales order count

#### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| since_id | integer | Orders with id greater than given id |
| ids | string | Comma-separated list of IDs |
| created_at_min | string | Created at or after (format: 2020-12-25T16:15:47 UTC) |
| created_at_max | string | Created at or before |
| updated_at_min | string | Updated at or after |
| updated_at_max | string | Updated at or before |
| per_page | integer | Results per page |
| page | integer | Page number |
| state | string | Filter by state |

#### Key Fields
**Identifiers & References**
- `id` (integer, readonly) - ID
- `number` (char, readonly) - Order number
- `reference` (char) - Customer PO number
- `channel_identifier` (char, readonly) - Channel identifier

**Parties & Addresses**
- `party` (Record ID, required) - Contact (customer)
- `invoice_address` (Record ID) - Invoice address
- `shipment_address` (Record ID) - Shipment address
- `shipment_party` (Record ID) - Shipment party
- `sales_person` (Record ID) - Sales person

**Dates**
- `sale_date` (date) - Sale date
- `confirmation_time` (datetime, readonly) - Confirmation time
- `create_date` (timestamp, readonly) - Created at
- `write_date` (timestamp, readonly) - Updated at

**Amounts**
- `total_amount` (decimal, readonly) - Total
- `untaxed_amount` (decimal, readonly) - Subtotal
- `tax_amount` (decimal, readonly) - Tax
- `amount_invoiced` (decimal, readonly) - Amount invoiced
- `shipment_amount` (decimal, readonly) - Shipment amount

**Payments**
- `payment_total` (decimal, readonly) - Total payment value
- `payment_captured` (decimal, readonly) - Amount captured
- `payment_authorized` (decimal, readonly) - Amount authorized
- `payment_collected` (decimal, readonly) - Payments collected
- `payment_available` (decimal, readonly) - Payment remaining

**Status**
- `state` (selection, readonly) - ignored, provisional, failed, draft, quotation, confirmed, processing, done, cancel
- `invoice_state` (selection, readonly) - none, waiting, paid, exception, posted
- `shipment_state` (selection, readonly) - none, waiting, sent, exception, cancel

**Related Records**
- `lines` (Record IDs) - Order lines (sale.line)
- `invoices` (Record IDs, readonly) - Invoices
- `shipments` (Record IDs, readonly) - Shipments
- `payments` (Record IDs) - Payments
- `channel` (Record ID, required) - Sales channel

**Other**
- `currency` (Record ID, required) - Currency
- `warehouse` (Record ID) - Warehouse
- `company` (Record ID, required) - Company
- `total_quantity` (decimal, readonly) - Total quantity
- `weight` (float, readonly) - Weight

### Contacts

**Model**: `party.party`

#### Endpoints
- `GET /api/v2/model/party.party` - List contacts
- `GET /api/v2/model/party.party/{id}` - Get single contact
- `GET /api/v2/model/party.party/count` - Get contact count

#### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| since_id | integer | Contacts with id greater than given id |
| ids | string | Comma-separated list of IDs |
| created_at_min | string | Created at or after (format: 2020-12-25T16:15:47 UTC) |
| created_at_max | string | Created at or before |
| updated_at_min | string | Updated at or after |
| updated_at_max | string | Updated at or before |
| per_page | integer | Results per page |
| page | integer | Page number |

#### Key Fields
**Identifiers & Basic Info**
- `id` (integer, readonly) - ID
- `code` (char, required) - Code
- `name` (char, required) - Name
- `full_name` (char, readonly) - Full name
- `active` (boolean) - Active status

**Contact Info**
- `email` (char, readonly) - Email
- `phone` (char, readonly) - Phone
- `mobile` (char, readonly) - Mobile
- `fax` (char, readonly) - Fax
- `website` (char, readonly) - Website
- `contact_mechanisms` (Record IDs) - All contact mechanisms
- `addresses` (Record IDs) - Addresses

**Customer/Supplier Flags**
- `is_customer` (boolean) - Is customer
- `is_supplier` (boolean) - Is supplier

**Accounts Receivable (Customer)**
- `receivable` (decimal, readonly) - Receivable balance
- `receivable_today` (decimal, readonly) - Receivable due today
- `account_receivable` (Record ID) - AR account
- `ar_balance_details` (Record IDs, readonly) - AR balance details
- `credit_limit_amount` (decimal) - Credit limit
- `credit_amount` (decimal, readonly) - Credit amount
- `credit_available` (decimal, readonly) - Available credit

**Accounts Payable (Supplier)**
- `payable` (decimal, readonly) - Payable balance
- `payable_today` (decimal, readonly) - Payable due today
- `account_payable` (Record ID) - AP account
- `ap_balance_details` (Record IDs, readonly) - AP balance details
- `unbilled_amount` (decimal, readonly) - Unbilled amount

**Sales Metrics**
- `sale_order_count` (integer, readonly) - Total orders
- `open_sale_order_count` (integer, readonly) - Open orders
- `total_sale_order_value` (decimal, readonly) - Total spend
- `average_sale_order_value` (decimal, readonly) - Average order value
- `sale_order_frequency` (integer, readonly) - Order frequency
- `last_order_date` (date, readonly) - Last order date
- `return_order_count` (integer, readonly) - Returns
- `total_returns_value` (decimal, readonly) - Total returned

**Purchase Metrics**
- `purchase_order_count` (integer, readonly) - Total POs
- `open_purchase_order_count` (integer, readonly) - Open POs
- `total_purchase_order_value` (decimal, readonly) - Total PO value
- `average_purchase_order_value` (decimal, readonly) - Average PO value
- `purchase_order_frequency` (integer, readonly) - PO frequency

**Sales Settings**
- `sales_rep` (Record ID) - Sales rep
- `account_manager` (Record ID) - Account manager
- `customer_payment_term` (Record ID) - Customer payment term
- `sale_price_list` (Record ID) - Default price list
- `primary_channel` (Record ID) - Primary sales channel
- `customer_tax_rule` (Record ID) - Customer tax rule

**Supplier Settings**
- `supplier_payment_term` (Record ID) - Supplier payment term
- `supplier_tax_rule` (Record ID) - Supplier tax rule

**Other**
- `categories` (Record IDs) - Categories
- `accepts_marketing` (boolean) - Accepts marketing
- `lang` (Record ID) - Language
- `create_date` (timestamp, readonly) - Created at
- `write_date` (timestamp, readonly) - Updated at

### Invoices

**Model**: `account.invoice`

#### Endpoints
- `GET /api/v2/model/account.invoice` - List invoices
- `GET /api/v2/model/account.invoice/{id}` - Get single invoice
- `GET /api/v2/model/account.invoice/count` - Get invoice count

#### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| since_id | integer | Invoices with id greater than given id |
| ids | string | Comma-separated list of IDs |
| created_at_min | string | Created at or after (format: 2020-12-25T16:15:47 UTC) |
| created_at_max | string | Created at or before |
| updated_at_min | string | Updated at or after |
| updated_at_max | string | Updated at or before |
| per_page | integer | Results per page |
| page | integer | Page number |
| state | string | Filter by state |

#### Key Fields
**Identifiers & References**
- `id` (integer, readonly) - ID
- `number` (char, readonly) - Invoice number
- `reference` (char) - Reference
- `description` (char) - Description

**Type & Status**
- `type` (selection, required) - `out` (customer/AR) or `in` (supplier/AP)
- `type_name` (char, readonly) - Type display name
- `state` (selection, readonly) - draft, validated, waiting_approval, posted, paid, cancel, recurring

**Parties & Addresses**
- `party` (Record ID, required) - Contact
- `invoice_address` (Record ID, required) - Invoice address
- `company` (Record ID, required) - Company
- `company_party` (Record ID, readonly) - Company party

**Dates**
- `invoice_date` (date) - Invoice date (determines aging)
- `accounting_date` (date) - Accounting date (revenue/expense recognition)
- `earliest_due_date` (date, readonly) - Earliest due date
- `override_due_date` (date) - Due date override
- `create_date` (timestamp, readonly) - Created at
- `write_date` (timestamp, readonly) - Updated at

**Amounts**
- `total_amount` (decimal, readonly) - Total
- `untaxed_amount` (decimal, readonly) - Subtotal
- `tax_amount` (decimal, readonly) - Tax
- `balance` (decimal, readonly) - Balance (outstanding)
- `balance_due` (decimal, readonly) - Balance due

**Currency**
- `currency` (Record ID, required) - Currency
- `currency_rate` (decimal, readonly) - Exchange rate
- `total_amount_invoice_currency` (decimal, readonly) - Total in invoice currency

**Payment**
- `payment_term` (Record ID) - Payment term
- `payment_lines` (Record IDs, readonly) - Payment lines
- `payment_link` (char, readonly) - Payment link
- `reconciled` (date, readonly) - Reconciled date

**Related Records**
- `lines` (Record IDs) - Invoice lines
- `sales` (Record IDs, readonly) - Related sales orders
- `purchases` (Record IDs, readonly) - Related purchase orders
- `customer_shipments` (Record IDs, readonly) - Customer shipments
- `supplier_shipments` (Record IDs, readonly) - Supplier shipments
- `account` (Record ID, required) - A/P or A/R account

**Other**
- `employee` (Record ID) - Sales person
- `tax_identifier` (Record ID) - Tax identifier
- `origin` (reference, readonly) - Origin document
- `comment` (text) - Comment

### Products

**Model**: `product.template`

#### Endpoints
- `GET /api/v2/model/product.template` - List products
- `GET /api/v2/model/product.template/{id}` - Get single product
- `GET /api/v2/model/product.template/count` - Get product count

#### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| since_id | integer | Products with id greater than given id |
| ids | string | Comma-separated list of IDs |
| created_at_min | string | Created at or after (format: 2020-12-25T16:15:47 UTC) |
| created_at_max | string | Created at or before |
| updated_at_min | string | Updated at or after |
| updated_at_max | string | Updated at or before |
| per_page | integer | Results per page |
| page | integer | Page number |

#### Key Fields
**Identifiers & Basic Info**
- `id` (integer, readonly) - ID
- `code` (char) - SKU/Code
- `name` (char, required) - Name
- `description` (text) - Description
- `long_description` (text) - Long description
- `active` (boolean) - Active status

**Inventory**
- `quantity` (float, readonly) - Quantity on hand
- `forecast_quantity` (float, readonly) - Forecast quantity
- `cost_value` (decimal, readonly) - Cost value

**Variants & Attributes**
- `products` (Record IDs) - Variants (product.product)
- `attribute_set` (Record ID) - Attribute set
- `variation_attributes` (Record IDs) - Required attributes

**Accounting**
- `account_category` (Record ID, required) - Account category
- `accounts_category` (boolean) - Use category's accounts
- `account_revenue` (Record ID) - Revenue account override
- `account_cogs` (Record ID) - COGS account override
- `account_expense` (Record ID) - Expense account override
- `account_stock` (Record ID) - Inventory asset account override

**Taxes**
- `customer_taxes` (Record IDs) - Customer taxes (selling)
- `supplier_taxes` (Record IDs) - Supplier taxes (purchasing)
- `taxes_category` (boolean) - Use category's taxes

**Media**
- `images` (Record IDs, readonly) - Images
- `media` (Record IDs) - Media

**Other**
- `channel_listings` (Record IDs) - Channel listings
- `packaging_option` (Record ID) - Default packaging
- `create_date` (timestamp, readonly) - Created at
- `write_date` (timestamp, readonly) - Updated at

### Related Models (Discovered via API Testing)

#### Contact Mechanisms
**Model**: `party.contact_mechanism`

Used for storing contact details (emails, phones) and custom data fields for contacts.

**Key Fields**:
- `id` (integer) - ID
- `type` (char) - Type: "email", "phone", etc.
- `name` (char) - Contact name or data type identifier
- `value` (char) - Email address, phone number, or data value
- `party` (Record ID) - Parent contact ID
- `active` (boolean) - Active status

**Data Structure Patterns**:
- **Department contacts**: `name` = "Accounts Payable: Contact Name", `value` = email address
- **Data fields**: `name` = "data", `value` = "shelf_life_req:180" or "vendor_guide:url"
- **Regular contacts**: `name` = "Person Name", `value` = email address

#### Sales Order Lines
**Model**: `sale.line`

**Key Fields**:
- `id` (integer) - ID
- `product` (Record ID) - Product variant ID
- `description` (char) - Product description
- `quantity` (float) - Quantity
- `unit_price` (Decimal) - Unit price
- `amount` (Decimal) - Line total
- `rec_name` (char) - Contains SKU in brackets, e.g., "[SKU123] Product Name"

**Note**: Decimal fields return as `{"__class__": "Decimal", "decimal": "31.00"}`

#### Product Variants
**Model**: `product.product`

**Key Fields**:
- `id` (integer) - ID
- `code` (char) - SKU
- `template` (Record ID) - Parent product template
- `list_price` (Decimal) - List price
- `cost_price` (Decimal) - Cost price
- `attributes` (Record IDs) - Product attribute values
- `active` (boolean) - Active status

#### Product Attributes
**Model**: `product.product.attribute`

Stores attribute values for products.

**Key Fields**:
- `id` (integer) - ID
- `attribute` (Record ID) - Attribute definition ID
- `value` (char) - Attribute value (text representation)
- `value_selection` (Record ID) - Selection value ID (for dropdown attributes)
- `value_char` (char) - Char value

**Known Attribute IDs**:
- ID 7 = "Class" (filter for RT EV, RT SE)
- ID 8 = "Start Date"
- ID 10 = "End Date" (this is the "Discontinued On" date)
- ID 47 = "Season"
- ID 48 = "Sales Channel"

#### Product Pricing
**Model**: `product.product`

**Field**: `wholesale_list_price`
- Returns Decimal: `{"__class__": "Decimal", "decimal": "1.14"}`
- May be `null` if not set

#### Attribute Definitions
**Model**: `product.attribute`

**Key Fields**:
- `id` (integer) - ID
- `name` (char) - Attribute name

#### Party Categories
**Model**: `party.category`

Used for contact categorization including discounts and shipping terms.

**Key Fields**:
- `id` (integer) - ID
- `name` (char) - Category name
- `parent` (Record ID) - Parent category
- `rec_name` (char) - Full path, e.g., "Shipping Terms / Delivered" or "Discounts / 15% Discount"

**Note**: Shipping terms are stored here as "Shipping Terms / Pickup" or "Shipping Terms / Delivered"

#### Sale Price Lists (Discounts)
**Model**: `product.price_list`

**Field on Contact**: `sale_price_list` (Record ID)

Discounts are stored in the contact's `sale_price_list` field, linking to a price list. The price list name format is "Wholesale X% Discount" - parse to extract the percentage.

#### Payment Terms
**Model**: `account.invoice.payment_term`

**Key Fields**:
- `id` (integer) - ID
- `name` (char) - Term name (e.g., "Net 30", "Net 60", "Immediate")

#### Price Lists
**Model**: `product.price_list`

**Key Fields**:
- `id` (integer) - ID
- `name` (char) - Price list name

### Channel Filter

For B2B/Wholesale orders, filter by:
- Channel ID: `19`
- Channel Code: `RTL1`
- Channel Name: `Retail Channel`

### Account Filter (AR - B2B)

For B2B invoices, filter by:
- Account ID: `128` (Accounts Receivable - B2B)
