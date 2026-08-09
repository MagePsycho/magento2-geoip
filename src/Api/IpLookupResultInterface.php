<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Api;

/**
 * Structured result of an IP geolocation lookup.
 *
 * All getters MAY return null when the underlying DB has no value for that
 * field. Consumers should handle null gracefully (display "—" or omit).
 */
interface IpLookupResultInterface
{
    /**
     * @return string The IP that was looked up (echoed back).
     */
    public function getIp(): string;

    /**
     * @return string|null Two-letter ISO country code (e.g. "AE", "US").
     */
    public function getCountryCode(): ?string;

    /**
     * @return string|null Full country name (e.g. "United Arab Emirates").
     */
    public function getCountryName(): ?string;

    /**
     * @return string|null Country flag emoji (e.g. "🇦🇪") — computed from the country code.
     */
    public function getCountryFlagEmoji(): ?string;

    /**
     * @return string|null Most-specific subdivision name (state / region).
     */
    public function getRegion(): ?string;

    /**
     * @return string|null City name.
     */
    public function getCity(): ?string;

    /**
     * @return string|null Postal code.
     */
    public function getPostalCode(): ?string;

    /**
     * @return float|null
     */
    public function getLatitude(): ?float;

    /**
     * @return float|null
     */
    public function getLongitude(): ?float;

    /**
     * @return string|null IANA timezone name (e.g. "Asia/Dubai").
     */
    public function getTimezone(): ?string;

    /**
     * @return int|null Autonomous System Number.
     */
    public function getAsn(): ?int;

    /**
     * @return string|null AS Organization name (e.g. "GOOGLE", "EMIRATES TELECOMMUNICATIONS CORPORATION").
     */
    public function getAsnOrganization(): ?string;

    /**
     * @return bool Whether the IP was a private / reserved address.
     *              (For private IPs the geolocation fields will be null.)
     */
    public function getIsPrivate(): bool;

    /**
     * Serialise the result as a JSON-friendly snake_case array.
     *
     * Designed to be returned directly from AJAX controllers.
     *
     * @return array{
     *   ip: string,
     *   country_code: string|null,
     *   country_name: string|null,
     *   country_flag_emoji: string|null,
     *   region: string|null,
     *   city: string|null,
     *   postal_code: string|null,
     *   latitude: float|null,
     *   longitude: float|null,
     *   timezone: string|null,
     *   asn: int|null,
     *   asn_organization: string|null,
     *   is_private: bool
     * }
     */
    public function toArray(): array;
}
