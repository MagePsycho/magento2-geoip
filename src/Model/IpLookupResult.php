<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model;

use MagePsycho\GeoIp\Api\IpLookupResultInterface;

/**
 * Immutable value object — created by IpLookup, consumed everywhere else.
 *
 * Construct via the named constructors below rather than `new`; the constructor
 * is intentionally restrictive so callers can't end up with half-populated
 * objects.
 */
class IpLookupResult implements IpLookupResultInterface
{
    /** @var string */
    private $ip;
    /** @var string|null */
    private $countryCode;
    /** @var string|null */
    private $countryName;
    /** @var string|null */
    private $region;
    /** @var string|null */
    private $city;
    /** @var string|null */
    private $postalCode;
    /** @var float|null */
    private $latitude;
    /** @var float|null */
    private $longitude;
    /** @var string|null */
    private $timezone;
    /** @var int|null */
    private $asn;
    /** @var string|null */
    private $asnOrganization;
    /** @var bool */
    private $isPrivate;

    private function __construct(string $ip, bool $isPrivate)
    {
        $this->ip = $ip;
        $this->isPrivate = $isPrivate;
    }

    /**
     * Construct for a private / reserved IP — geolocation fields stay null.
     */
    public static function forPrivateIp(string $ip): self
    {
        return new self($ip, true);
    }

    /**
     * Construct for a resolved public IP.
     *
     * @param array<string,mixed> $data
     */
    public static function fromData(string $ip, array $data): self
    {
        $r = new self($ip, false);
        $r->countryCode      = self::asString($data['country_code'] ?? null);
        $r->countryName      = self::asString($data['country_name'] ?? null);
        $r->region           = self::asString($data['region'] ?? null);
        $r->city             = self::asString($data['city'] ?? null);
        $r->postalCode       = self::asString($data['postal_code'] ?? null);
        $r->latitude         = self::asFloat($data['latitude'] ?? null);
        $r->longitude        = self::asFloat($data['longitude'] ?? null);
        $r->timezone         = self::asString($data['timezone'] ?? null);
        $r->asn              = self::asInt($data['asn'] ?? null);
        $r->asnOrganization  = self::asString($data['asn_organization'] ?? null);
        return $r;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    /**
     * Compute the country flag emoji from the country code, if any.
     *
     * Regional Indicator Symbol Letter A is U+1F1E6; each ASCII letter A–Z
     * maps to the corresponding RIS. A flag emoji is the pair of RIS letters
     * for the country's ISO-3166-1 alpha-2 code.
     */
    public function getCountryFlagEmoji(): ?string
    {
        if ($this->countryCode === null || strlen($this->countryCode) !== 2) {
            return null;
        }
        $code = strtoupper($this->countryCode);
        if (!ctype_alpha($code)) {
            return null;
        }
        return mb_chr(0x1F1E6 + (ord($code[0]) - ord('A')), 'UTF-8')
            . mb_chr(0x1F1E6 + (ord($code[1]) - ord('A')), 'UTF-8');
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getAsn(): ?int
    {
        return $this->asn;
    }

    public function getAsnOrganization(): ?string
    {
        return $this->asnOrganization;
    }

    public function getIsPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function toArray(): array
    {
        return [
            'ip'                 => $this->ip,
            'country_code'       => $this->countryCode,
            'country_name'       => $this->countryName,
            'country_flag_emoji' => $this->getCountryFlagEmoji(),
            'region'             => $this->region,
            'city'               => $this->city,
            'postal_code'        => $this->postalCode,
            'latitude'           => $this->latitude,
            'longitude'          => $this->longitude,
            'timezone'           => $this->timezone,
            'asn'                => $this->asn,
            'asn_organization'   => $this->asnOrganization,
            'is_private'         => $this->isPrivate,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function asString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     */
    private static function asInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @param mixed $value
     */
    private static function asFloat($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
