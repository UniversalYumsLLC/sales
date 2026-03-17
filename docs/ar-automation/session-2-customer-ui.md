# AR Automation - Session 2: Customer & UI

## Overview

This session implements customer-facing UI changes and validation rules for the AR Automation feature. It includes requiring AP contacts for active customers, the Customer SKU Mapping interface, and Invoice Details UI enhancements.

## Prerequisites

- **Session 1: Data Foundation** must be completed first
  - `customer_skus` table must exist
  - New customer fields (EDI, Consolidated Invoicing, etc.) must exist

## Scope

1. Require AP contacts for all active customers (form validation)
2. Customer SKU Mapping UI on Customer Details page
3. Invoice Details UI (PDF download, regenerate button)
4. Update customer forms to include new AR fields

---

## 1. AP Contact Requirement for Active Customers

### Business Rules

1. All **Active Customers** must have at least 1 Accounts Payable Contact (Name & Email address)
2. Retain existing AP Portal functionality:
   - Track whether customer uses an AP Portal (boolean)
   - If yes, capture the AP Portal URL
3. **Important**: Even if a customer uses an AP Portal, they still need at least 1 AP contact
4. AP contacts are in addition to the portal URL, not a replacement

### Implementation Requirements

#### New Active Customer Form

- Add validation: Cannot save/create an active customer without at least 1 AP contact
- Display clear error message if validation fails
- AP contact fields: Name (required), Email (required)
- Allow adding multiple AP contacts

#### Edit Active Customer Form

- Same validation as above
- Cannot save changes if it would result in 0 AP contacts
- Allow adding/removing AP contacts (but not below 1)

#### Validation Logic

```
IF customer.status == 'active':
    REQUIRE customer.ap_contacts.count >= 1
    EACH ap_contact MUST have:
        - name (not empty)
        - email (valid email format)
```

### UI Notes

- Show AP Portal fields (uses_ap_portal checkbox, portal_url field)
- Show AP Contacts section below/alongside portal fields
- Make it clear that both can coexist

---

## 2. New Customer AR Fields UI

Add the following fields to both the new customer form and edit customer form. These fields were created in Session 1.

### 2.1 EDI

| Field | EDI |
|-------|-----|
| Type | Checkbox |
| Label | "EDI Enabled" |
| Help text | "Check if we communicate with this customer via EDI" |
| Default | Unchecked |

### 2.2 Consolidated Invoicing

| Field | Consolidated Invoicing |
|-------|------------------------|
| Type | Dropdown/Select |
| Label | "Invoice Consolidation" |
| Options | "One invoice per shipment" (default), "Consolidate invoices shipped same day" |
| Help text | "How does customer prefer to receive invoices?" |

### 2.3 Invoice Requires Customer SKUs

| Field | Invoice Requires Customer SKUs |
|-------|-------------------------------|
| Type | Checkbox |
| Label | "Requires Customer SKUs on Invoice" |
| Help text | "Check if customer requires their internal SKUs on invoices" |
| Default | Unchecked |

### 2.4 Invoice Discount

| Field | Invoice Discount |
|-------|------------------|
| Type | Number input (percentage) |
| Label | "Invoice Discount (%)" |
| Help text | "Discount percentage applied at invoice level (e.g., 15 for 15%)" |
| Validation | 0-100, decimal allowed |
| Default | 0 |

### Form Section Placement

Consider grouping these fields in an "Accounts Receivable Settings" or "Invoicing Preferences" section on the customer form.

---

## 3. Customer SKU Mapping UI

Add a section to the Customer Details page for managing SKU mappings.

### Location

- Customer Details page
- Suggest placing in a collapsible section or tab labeled "Customer SKU Mapping"

### UI Components

#### Table Display

| Column | Description |
|--------|-------------|
| Yums SKU | Dropdown populated from active Retail SKUs list |
| Customer SKU | Text input for customer's internal SKU |
| Actions | Delete button |

#### Actions

- **Add Row**: Button to add a new mapping row
- **Delete Row**: Button on each row to remove that mapping
- **Save**: Save all mappings (can be auto-save or explicit save button)

### Data Source for Yums SKU Dropdown

Use the same list of active Retail SKUs that populates the prospect "Products of interest" dropdown. This ensures consistency across the application.

### Validation

- Yums SKU is required (must select from dropdown)
- Customer SKU is required (cannot be empty)
- Cannot duplicate Yums SKU for same customer (unique constraint)

### Example UI Mockup

```
+--------------------------------------------------+
| Customer SKU Mapping                        [+Add]|
+--------------------------------------------------+
| Yums SKU          | Customer SKU      | Actions  |
+-------------------|-------------------|----------|
| [SNACK-BOX-12 v]  | [ABC-12345     ]  | [Delete] |
| [CANDY-MIX-6  v]  | [XYZ-67890     ]  | [Delete] |
+--------------------------------------------------+
```

---

## 4. Invoice Details UI

Enhance the invoice display with PDF functionality.

### 4.1 PDF Download Button

- Add a PDF icon/button for each invoice in the list
- Clicking initiates a download of the invoice PDF
- PDF is generated on-demand if not already cached
- Use the PDF generation system from Session 3

### 4.2 Regenerate Invoice Button

- Add a "Regenerate Invoice" button on invoice detail view
- **Behavior**:
  - Triggers fresh data fetch from Fulfil
  - Regenerates the PDF regardless of last modified date
  - Useful for: template changes, manual refresh, troubleshooting
- **Important**: Regenerate does NOT trigger email send
- Show loading state during regeneration
- Show success/error feedback

### UI Placement

```
Invoice #12345                    [Download PDF] [Regenerate]
-----------------------------------------------------------
Invoice details here...
```

### Implementation Notes

- PDF download should work even if PDF doesn't exist yet (generate on first download)
- Regenerate should overwrite existing stored PDF
- Consider showing "Last generated: [timestamp]" for transparency

---

## 5. Form Controller Updates

Update the customer form controllers to handle the new fields and validation.

### Create Customer Controller

1. Add new fields to validation rules
2. Add AP contact validation for active customers
3. Include new fields in Fulfil sync

### Update Customer Controller

1. Same validation as create
2. Ensure AP contact count doesn't drop below 1 for active customers
3. Sync updated fields to Fulfil

### Customer SKU Controller

Create new controller/routes for managing customer SKU mappings:

- `GET /customers/{id}/skus` - List mappings
- `POST /customers/{id}/skus` - Add mapping
- `DELETE /customers/{id}/skus/{skuId}` - Remove mapping

---

## Deliverables Checklist

- [ ] Validation: AP contact requirement for active customers (create form)
- [ ] Validation: AP contact requirement for active customers (edit form)
- [ ] UI: Add EDI checkbox to customer forms
- [ ] UI: Add Consolidated Invoicing dropdown to customer forms
- [ ] UI: Add Invoice Requires Customer SKUs checkbox to customer forms
- [ ] UI: Add Invoice Discount input to customer forms
- [ ] UI: Customer SKU Mapping section on Customer Details page
- [ ] UI: Add/Delete row functionality for SKU mappings
- [ ] UI: Yums SKU dropdown populated from active Retail SKUs
- [ ] API: CRUD endpoints for customer SKU mappings
- [ ] UI: PDF download button on invoice list/details
- [ ] UI: Regenerate Invoice button on invoice details
- [ ] Controller: Update customer create/edit to handle new fields
- [ ] Controller: Create CustomerSkuController

---

## Dependencies

- Session 1: Data Foundation (database tables and fields)

## Blocks

- Session 5: Automation & Polling (uses SKU mapping for invoice processing)
