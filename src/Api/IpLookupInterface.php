<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Api;

/**
 * Resolves IP addresses to geolocation + ASN data.
 *
 * Stable contract — consumer modules (SalesOrderGrid, Cart, Customer Registration,
 * future fraud signals, etc.) should code against this interface, never against
 * the MaxMind-specific implementation.
 */
interface IpLookupInterface
{
    /**
     * Look up an IP address and return a structured result.
     *
     * Returns null on unresolvable input — never throws. Reasons for null:
     *   - empty / malformed IP
     *   - private or reserved IP (RFC1918, loopback, IPv6 ULA, link-local)
     *   - public IP that the database can't resolve
     *   - the database files aren't installed yet
     *
     * Implementations MUST log (warning-level) when the DB isn't installed so
     * the admin can diagnose without callers having to differentiate failure
     * modes.
     *
     * @param string $ip
     * @return IpLookupResultInterface|null
     */
    public function lookup(string $ip): ?IpLookupResultInterface;

    /**
     * Whether the IP is private / reserved / loopback and therefore
     * not externally resolvable.
     *
     * Exposed separately so consumers can short-circuit UI / messaging
     * before incurring a lookup call.
     *
     * @param string $ip
     * @return bool
     */
    public function isPrivate(string $ip): bool;
}
