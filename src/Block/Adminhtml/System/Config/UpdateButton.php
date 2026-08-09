<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders an "Update MaxMind Database Now" button in admin config.
 *
 * Clicking it POSTs to the Update controller which triggers
 * DatabaseManager::download() synchronously. Long-running (~30s); the admin
 * sees a normal Magento success/error flash on the redirect-back.
 */
class UpdateButton extends Field
{
    private const TEMPLATE = 'MagePsycho_GeoIp::system/config/update_button.phtml';

    /**
     * @inheritDoc
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(self::TEMPLATE);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element)
    {
        // Hide the per-scope "Use Default" checkbox + scope label — the button
        // is a global action, not a config value.
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * URL the button posts to.
     *
     * @return string
     */
    public function getUpdateUrl(): string
    {
        return $this->getUrl('magepsycho_geoip/maxmind/update');
    }
}
