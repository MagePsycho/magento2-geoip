<?php
/**
 * This file is part of the MagePsycho_GeoIp package.
 *
 * @author   Raj KB <magepsycho@gmail.com>
 * @license  Open Software License (OSL 3.0)
 */
namespace MagePsycho\GeoIp\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Backend model that deletes the persisted row when the admin saves an empty
 * value, instead of persisting NULL.
 *
 * Why: Magento's config.xml `<default>` values are only consulted when there
 * is NO row at all in core_config_data for a given path. As soon as the admin
 * saves the form with the field empty, Magento writes NULL — and from then on
 * the default is masked forever.
 *
 * Wire via `<backend_model>` on any system.xml field that has a meaningful
 * default in config.xml that you want to fall through whenever the user
 * clears the input.
 */
class DefaultableField extends Value
{
    /**
     * @inheritDoc
     */
    public function beforeSave()
    {
        if ((string) $this->getValue() === '') {
            // Suppress the impending save AND remove any existing row so the
            // config.xml default kicks back in on the next read.
            $this->_dataSaveAllowed = false;
            if ($this->getId()) {
                $this->_getResource()->delete($this);
            }
        }
        return parent::beforeSave();
    }
}
