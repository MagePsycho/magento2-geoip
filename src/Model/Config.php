<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config implements ConfigInterface
{
    public const XML_PATH_ENABLED      = 'magepsycho_geoip/general/enabled';
    public const XML_PATH_FORCED_IP    = 'magepsycho_geoip/general/forced_ip';
    public const XML_PATH_ACCOUNT_ID   = 'magepsycho_geoip/maxmind/account_id';
    public const XML_PATH_LICENSE_KEY  = 'magepsycho_geoip/maxmind/license_key';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function getForcedIp(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_FORCED_IP));
    }

    /**
     * @inheritDoc
     */
    public function getAccountId(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_ACCOUNT_ID));
    }

    /**
     * @inheritDoc
     */
    public function getLicenseKey(): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY);
        if ($encrypted === '') {
            return '';
        }
        return trim($this->encryptor->decrypt($encrypted));
    }
}
