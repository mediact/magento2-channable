<?php
/**
 * Copyright © 2017 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Channable\Model\ResourceModel\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     *
     */
    protected function _construct()
    {
        $this->_init(
            'Magmodules\Channable\Model\Item',
            'Magmodules\Channable\Model\ResourceModel\Item'
        );
    }
}
