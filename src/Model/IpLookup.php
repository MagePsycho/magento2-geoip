<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model;

use GeoIp2\Database\Reader;
use GeoIp2\Database\ReaderFactory;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Exception\GeoIp2Exception;
use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use MagePsycho\GeoIp\Api\IpLookupInterface;
use MagePsycho\GeoIp\Api\IpLookupResultInterface;
use Magento\Framework\Locale\TranslatedLists;
use Psr\Log\LoggerInterface;

/**
 * MaxMind-backed implementation of IpLookupInterface.
 *
 * Lazily opens GeoLite2-City.mmdb and GeoLite2-ASN.mmdb (cached for the
 * lifetime of the request). Returns IpLookupResult value objects.
 *
 * Behavioural contract:
 *   - never throws to the caller
 *   - returns null when the DB isn't installed (and logs a single warning)
 *   - returns IpLookupResult::forPrivateIp() for private/reserved addresses
 *   - returns null when the DB doesn't recognise the IP
 */
class IpLookup implements IpLookupInterface
{
    /**
     * @var DatabaseManagerInterface
     */
    private $dbManager;

    /**
     * @var ReaderFactory
     */
    private $readerFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TranslatedLists
     */
    private $countryList;

    /**
     * @var Reader|null
     */
    private $cityReader = null;

    /**
     * @var Reader|null
     */
    private $asnReader = null;

    /**
     * @var bool
     */
    private $dbMissingLogged = false;

    public function __construct(
        DatabaseManagerInterface $dbManager,
        ReaderFactory $readerFactory,
        ConfigInterface $config,
        LoggerInterface $logger,
        TranslatedLists $countryList
    ) {
        $this->dbManager = $dbManager;
        $this->readerFactory = $readerFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->countryList = $countryList;
    }

    /**
     * @inheritDoc
     */
    public function lookup(string $ip): ?IpLookupResultInterface
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }

        // Forced-IP testing aid: override every real lookup with the configured IP.
        $forced = $this->config->getForcedIp();
        if ($forced !== '') {
            $ip = $forced;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if ($this->isPrivate($ip)) {
            return IpLookupResult::forPrivateIp($ip);
        }

        if (!$this->dbManager->isInstalled()) {
            if (!$this->dbMissingLogged) {
                $this->logger->warning(
                    'MagePsycho_GeoIp: MaxMind GeoLite2 database files are not installed. '
                    . 'Drop GeoLite2-City.mmdb and GeoLite2-ASN.mmdb into var/maxmind/ or '
                    . 'configure auto-download at Stores → Configuration → MagePsycho → GeoIP.'
                );
                $this->dbMissingLogged = true;
            }
            return null;
        }

        $data = [];

        $cityRecord = $this->readCity($ip);
        if ($cityRecord !== null) {
            $countryCode = $cityRecord->country->isoCode;
            $data['country_code'] = $countryCode;
            // Prefer Magento's localised country name over MaxMind's English-only one.
            $data['country_name'] = $countryCode
                ? ($this->countryList->getCountryTranslation($countryCode) ?: $cityRecord->country->name)
                : $cityRecord->country->name;
            $data['region']       = $cityRecord->mostSpecificSubdivision->name;
            $data['city']         = $cityRecord->city->name;
            $data['postal_code']  = $cityRecord->postal->code;
            $data['latitude']     = $cityRecord->location->latitude;
            $data['longitude']    = $cityRecord->location->longitude;
            $data['timezone']     = $cityRecord->location->timeZone;
        }

        $asnRecord = $this->readAsn($ip);
        if ($asnRecord !== null) {
            $data['asn']              = $asnRecord->autonomousSystemNumber;
            $data['asn_organization'] = $asnRecord->autonomousSystemOrganization;
        }

        if ($data === []) {
            return null;
        }

        return IpLookupResult::fromData($ip, $data);
    }

    /**
     * @inheritDoc
     */
    public function isPrivate(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return true;
        }
        // PHP can do this for us: FILTER_FLAG_NO_PRIV_RANGE blocks 10/8, 172.16/12,
        // 192.168/16, IPv6 ULA fc00::/7. FILTER_FLAG_NO_RES_RANGE blocks loopback,
        // link-local, broadcast, 0.0.0.0/8, and IPv6 reserved ranges.
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * @return \GeoIp2\Model\City|null
     */
    private function readCity(string $ip)
    {
        if ($this->cityReader === null) {
            try {
                $this->cityReader = $this->readerFactory->create(
                    ['filename' => $this->dbManager->getCityDatabasePath()]
                );
            } catch (\Throwable $e) {
                $this->logger->warning('MagePsycho_GeoIp: failed to open City DB: ' . $e->getMessage());
                return null;
            }
        }
        try {
            return $this->cityReader->city($ip);
        } catch (AddressNotFoundException $e) {
            return null;
        } catch (GeoIp2Exception | \Throwable $e) {
            $this->logger->warning('MagePsycho_GeoIp: City lookup failed for ' . $ip . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return \GeoIp2\Model\Asn|null
     */
    private function readAsn(string $ip)
    {
        if ($this->asnReader === null) {
            try {
                $this->asnReader = $this->readerFactory->create(
                    ['filename' => $this->dbManager->getAsnDatabasePath()]
                );
            } catch (\Throwable $e) {
                $this->logger->warning('MagePsycho_GeoIp: failed to open ASN DB: ' . $e->getMessage());
                return null;
            }
        }
        try {
            return $this->asnReader->asn($ip);
        } catch (AddressNotFoundException $e) {
            return null;
        } catch (GeoIp2Exception | \Throwable $e) {
            $this->logger->warning('MagePsycho_GeoIp: ASN lookup failed for ' . $ip . ': ' . $e->getMessage());
            return null;
        }
    }
}
