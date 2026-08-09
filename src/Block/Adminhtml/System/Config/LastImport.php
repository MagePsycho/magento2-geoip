<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Block\Adminhtml\System\Config;

use MagePsycho\GeoIp\Api\DatabaseManagerInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the read-only "Last Database Import" admin field.
 *
 * Shows the most recent .mmdb file mtime, or a hint to install the DB when
 * neither file is present.
 */
class LastImport extends Field
{
    /**
     * @var DatabaseManagerInterface
     */
    private $dbManager;

    public function __construct(
        Context $context,
        DatabaseManagerInterface $dbManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dbManager = $dbManager;
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $installedAt = $this->dbManager->getInstalledAt();
        if ($installedAt === null) {
            return '<span style="color:#b94a48;">'
                . __('Not installed — run <code>bin/magento magepsycho:geoip:update</code> or drop the .mmdb files into var/maxmind/ manually.')
                . '</span>';
        }
        return $this->escapeHtml($installedAt->format('Y-m-d H:i:s'));
    }
}
