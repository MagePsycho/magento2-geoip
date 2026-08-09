<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Console\Command;

use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento magepsycho:geoip:update`
 *
 * Pulls the GeoLite2-City and GeoLite2-ASN .mmdb files from MaxMind, extracts
 * them, replaces var/maxmind/{City,ASN}.mmdb. Requires Account ID + License Key
 * to be set in admin config.
 */
class UpdateDatabase extends Command
{
    /**
     * @var DatabaseManagerInterface
     */
    private $dbManager;

    /**
     * @param DatabaseManagerInterface $dbManager
     * @param string|null $name
     */
    public function __construct(DatabaseManagerInterface $dbManager, ?string $name = null)
    {
        parent::__construct($name);
        $this->dbManager = $dbManager;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('magepsycho:geoip:update');
        $this->setDescription('Download / refresh the MaxMind GeoLite2-City + GeoLite2-ASN databases.');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Downloading MaxMind GeoLite2-City and GeoLite2-ASN…</info>');
        try {
            $this->dbManager->download();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $installedAt = $this->dbManager->getInstalledAt();
        $output->writeln(sprintf(
            '<info>Success.</info>  City: %s  ASN: %s  (built: %s)',
            $this->dbManager->getCityDatabasePath(),
            $this->dbManager->getAsnDatabasePath(),
            $installedAt ? $installedAt->format('Y-m-d H:i:s') : 'unknown'
        ));
        return Command::SUCCESS;
    }
}
