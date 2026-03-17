# AR Automation - Session 1: Data Foundation

## Overview

This session establishes the data layer required for the Accounts Receivable Automation feature. All subsequent sessions depend on the database structures and Fulfil integrations built here.

## Scope

1. Database migrations for new customer fields
2. Database migrations for new invoice fields
3. New `email_records` table
4. New `customer_skus` table
5. Fulfil metafield sync for new customer fields

---

## 1. New Customer Data Fields

Add the following fields to the customer data model. All fields sync bidirectionally with Fulfil as metafields.

### 1.1 EDI

| Property | Value |
|----------|-------|
| Type | Boolean |
| Description | Do we communicate with customer via EDI? |
| Sync to Fulfil | Yes |
| Fulfil data format | Metafield "EDI" |

### 1.2 Consolidated Invoicing

| Property | Value |
|----------|-------|
| Type | Dropdown (enum) |
| Options | "One invoice per shipment", "Consolidate invoices shipped same day" |
| Description | How does customer request to receive invoices? |
| Sync to Fulfil | Yes |
| Fulfil data format | Metafield "Consolidated Invoicing" |

### 1.3 Invoice Requires Customer SKUs

| Property | Value |
|----------|-------|
| Type | Boolean |
| Description | Does customer require us to reference their SKUs when invoicing? |
| Sync to Fulfil | Yes |
| Fulfil data format | Metafield "Invoice Requires Customer SKUs" |

### 1.4 Invoice Discount

| Property | Value |
|----------|-------|
| Type | Float (Percentage, e.g., 0.15 for 15%) |
| Description | Discounts applied at the invoice level |
| Sync to Fulfil | Yes |
| Fulfil data format | Metafield "Invoice Discount" |

---

## 2. New Invoice Record Fields

Add the following fields to track invoice metadata locally. These fields are fetched from Fulfil but stored locally.

### 2.1 Created Date

| Property | Value |
|----------|-------|
| Type | Date |
| Description | When was the invoice record created? |
| Sync to Fulfil | No (field is not editable in Fulfil) |
| Notes | Fetch from Fulfil when invoice is first imported, store locally. Field cannot change so no re-fetch needed. |

### 2.2 Last Modified Date

| Property | Value |
|----------|-------|
| Type | Datetime |
| Description | When was the invoice record last modified? |
| Sync to Fulfil | No |
| Notes | Fetch `write_date` from Fulfil. Store locally to compare against future fetches. When Fulfil's `write_date` differs from local `last_modified_date`, the invoice was edited in Fulfil. |

---

## 3. Email Records Table

Create a new table to track all outbound AR emails. This table is local-only and does not sync to Fulfil.

### Schema: `email_records`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto-increment |
| customer_id | bigint (FK) | Reference to customer |
| invoice_id | bigint (FK, nullable) | Reference to invoice (if applicable) |
| email_type | string | Type of email sent (see enum below) |
| sent_at | timestamp | When the email was sent |
| pdf_path | string (nullable) | Path to stored PDF file |
| created_at | timestamp | Laravel timestamp |
| updated_at | timestamp | Laravel timestamp |

### Email Types Enum

The `email_type` field should support the following values:

- `initial_invoice` - First invoice email sent to customer
- `initial_invoice_ap_portal` - First invoice notification for AP Portal customers
- `invoice_modified` - Invoice was modified after initial send
- `invoice_modified_ap_portal` - Modified invoice notification for AP Portal customers
- `due_reminder` - 7 days before due date reminder
- `overdue_notification` - 1 day after due date notification
- `overdue_followup` - Weekly follow-up for overdue invoices
- `sku_mapping_error` - Internal notification about missing SKU mappings

### Indexes

- Index on `customer_id`
- Index on `invoice_id`
- Composite index on `(invoice_id, email_type)` for checking if specific email type was sent

---

## 4. Customer SKUs Table

Create a new table for mapping Yums SKUs to customer-specific SKUs. This table is local-only and does not sync to Fulfil.

### Schema: `customer_skus`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto-increment |
| customer_id | bigint (FK) | Reference to customer |
| yums_sku | string | Yums product SKU (from active Retail SKUs list) |
| customer_sku | string | Customer's internal SKU |
| created_at | timestamp | Laravel timestamp |
| updated_at | timestamp | Laravel timestamp |

### Constraints

- Unique constraint on `(customer_id, yums_sku)` - each Yums SKU can only be mapped once per customer

### Indexes

- Index on `customer_id`
- Index on `yums_sku`

---

## 5. Fulfil Metafield Configuration

The following metafields need to be created in Fulfil (or handled if they already exist). The application should sync these fields when saving customer data.

### Metafields to Create/Sync

| Metafield Name | Type | Notes |
|----------------|------|-------|
| EDI | Boolean | |
| Consolidated Invoicing | Selection | Options: "One invoice per shipment", "Consolidate invoices shipped same day" |
| Invoice Requires Customer SKUs | Boolean | |
| Invoice Discount | Float | Stored as decimal (e.g., 0.15 for 15%) |

### Implementation Notes

- Update the Fulfil service to handle reading/writing these metafields
- Ensure metafields are included in customer data fetch operations
- Ensure metafields are updated when customer data is saved

---

## Deliverables Checklist

- [ ] Migration: Add `edi` boolean column to customers table
- [ ] Migration: Add `consolidated_invoicing` enum column to customers table
- [ ] Migration: Add `invoice_requires_customer_skus` boolean column to customers table
- [ ] Migration: Add `invoice_discount` decimal column to customers table
- [ ] Migration: Add `created_date` date column to invoices table
- [ ] Migration: Add `last_modified_date` datetime column to invoices table
- [ ] Migration: Create `email_records` table
- [ ] Migration: Create `customer_skus` table
- [ ] Model: Update Customer model with new fields and casts
- [ ] Model: Update Invoice model with new fields
- [ ] Model: Create EmailRecord model with relationships
- [ ] Model: Create CustomerSku model with relationships
- [ ] Service: Update FulfilService to sync new customer metafields
- [ ] Service: Update invoice fetch to capture `create_date` and `write_date`

---

## Dependencies

- None (this is the foundational session)

## Blocks

- Session 2: Customer & UI
- Session 3: PDF Generation
- Session 4: Email Infrastructure
- Session 5: Automation & Polling
