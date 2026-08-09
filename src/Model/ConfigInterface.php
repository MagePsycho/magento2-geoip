<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model;

interface ConfigInterface
{
    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * When non-empty, every lookup() call is overridden with this IP.
     * Convenience for end-to-end testing without faking data.
     *
     * @return string
     */
    public function getForcedIp(): string;

    /**
     * @return string
     */
    public function getAccountId(): string;

    /**
     * License key — stored encrypted in `core_config_data`, decrypted here.
     *
     * @return string
     */
    public function getLicenseKey(): string;
}
