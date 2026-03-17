<?php

namespace App\DTOs\Invoice;

class LineItemDto
{
    public function __construct(
        public string $productCode,
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $amount,
    ) {}

    /**
     * Create from Fulfil invoice line data.
     */
    public static function fromFulfil(array $data): self
    {
        return new self(
            productCode: $data['product_code'] ?? $data['product']['code'] ?? '',
            description: $data['description'] ?? '',
            quantity: (float) ($data['quantity'] ?? 0),
            unitPrice: (float) ($data['unit_price'] ?? 0),
            amount: (float) ($data['amount'] ?? 0),
        );
    }

    /**
     * Create with a substituted customer SKU.
     */
    public function withCustomerSku(string $customerSku): self
    {
        return new self(
            productCode: $customerSku,
            description: $this->description,
            quantity: $this->quantity,
            unitPrice: $this->unitPrice,
            amount: $this->amount,
        );
    }
}
