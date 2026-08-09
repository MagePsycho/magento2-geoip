<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Api;

/**
 * Manages the MaxMind GeoLite2 .mmdb files on disk.
 *
 * Separated from IpLookupInterface so consumer modules don't have to know how
 * the underlying database gets there — they only need to call `lookup()`.
 * Admin UI and the CLI command depend on THIS interface.
 */
interface DatabaseManagerInterface
{
    /**
     * Whether the City and ASN database files are both present on disk.
     *
     * @return bool
     */
    public function isInstalled(): bool;

    /**
     * Most-recent file mtime among the installed DB files, or null when none
     * are present. Used as the single source of truth for "last update".
     *
     * @return \DateTimeInterface|null
     */
    public function getInstalledAt(): ?\DateTimeInterface;

    /**
     * Download + extract the GeoLite2-City and GeoLite2-ASN .mmdb files from
     * MaxMind into var/maxmind/. Requires Account ID + License Key in config.
     *
     * @throws \RuntimeException When credentials are missing, the HTTP request
     *                           fails, or the archive can't be extracted.
     * @return void
     */
    public function download(): void;

    /**
     * Absolute path where the City database is expected to live.
     *
     * @return string
     */
    public function getCityDatabasePath(): string;

    /**
     * Absolute path where the ASN database is expected to live.
     *
     * @return string
     */
    public function getAsnDatabasePath(): string;
}
