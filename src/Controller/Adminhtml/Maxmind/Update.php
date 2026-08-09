<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Controller\Adminhtml\Maxmind;

use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Backs the "Update Database Now" button on the GeoIP admin config page.
 *
 * Synchronous — downloads, extracts, atomically swaps in the new .mmdb files
 * for both editions. ~30 seconds at typical network speed.
 */
class Update extends Action
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'MagePsycho_GeoIp::config';

    /**
     * @var DatabaseManagerInterface
     */
    private $dbManager;

    public function __construct(Context $context, DatabaseManagerInterface $dbManager)
    {
        parent::__construct($context);
        $this->dbManager = $dbManager;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->dbManager->download();
            $installedAt = $this->dbManager->getInstalledAt();
            $this->messageManager->addSuccessMessage(
                __(
                    'MaxMind GeoLite2 databases updated successfully (build %1).',
                    $installedAt ? $installedAt->format('Y-m-d H:i:s') : 'unknown'
                )
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $result->setUrl($this->_redirect->getRefererUrl());
    }
}
