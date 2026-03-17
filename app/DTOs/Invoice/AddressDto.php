<?php

namespace App\DTOs\Invoice;

class AddressDto
{
    public function __construct(
        public string $partyName,
        public string $street,
        public ?string $street2,
        public string $city,
        public string $subdivisionCode,
        public string $zip,
        public string $countryName,
    ) {}

    /**
     * Create from Fulfil address data.
     */
    public static function fromFulfil(array $data): self
    {
        return new self(
            partyName: $data['party_name'] ?? $data['party']['name'] ?? '',
            street: $data['street'] ?? '',
            street2: $data['street2'] ?? null,
            city: $data['city'] ?? '',
            subdivisionCode: $data['subdivision_code'] ?? $data['subdivision']['code'] ?? '',
            zip: $data['zip'] ?? '',
            countryName: $data['country_name'] ?? $data['country']['name'] ?? '',
        );
    }

    /**
     * Create an empty/placeholder address.
     */
    public static function empty(): self
    {
        return new self(
            partyName: '',
            street: '',
            street2: null,
            city: '',
            subdivisionCode: '',
            zip: '',
            countryName: '',
        );
    }
}
