<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\OfferTool;

@trigger_error(
    'Interface Pimcore\Bundle\EcommerceFrameworkBundle\OfferTool\IService is deprecated since version 6.0.0 and will be removed in 7.0.0. ' .
    ' Use ' . ServiceInterface::class . ' class instead.',
    E_USER_DEPRECATED
);

class_exists(ServiceInterface::class);

if (false) {
    /**
     * @deprecated use ServiceInterface instead.
     */
    interface IService
    {
    }
}
