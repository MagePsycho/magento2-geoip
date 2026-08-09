<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Cron;

use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use MagePsycho\GeoIp\Model\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron job — refreshes the MaxMind GeoLite2 databases on the schedule set in
 * Stores → Configuration → MagePsycho → GeoIP → MaxMind → Auto-update Cron Expression.
 *
 * A blank cron expression in admin config implicitly disables this job (Magento
 * cron only fires jobs with a resolvable schedule). No-op early-exit when the
 * module itself is disabled or credentials are missing — easier than failing.
 */
class UpdateDatabase
{
    /**
     * @var DatabaseManagerInterface
     */
    private $dbManager;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DatabaseManagerInterface $dbManager,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->dbManager = $dbManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        if ($this->config->getLicenseKey() === '') {
            return;
        }
        try {
            $this->dbManager->download();
            $this->logger->info('MagePsycho_GeoIp: MaxMind GeoLite2 databases updated via cron.');
        } catch (\Throwable $e) {
            $this->logger->error('MagePsycho_GeoIp cron update failed: ' . $e->getMessage());
        }
    }
}
