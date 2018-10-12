<?php

namespace Apirone\Merchant\Model\ResourceModel\Sales;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'Apirone\Merchant\Model\Sales',
            'Apirone\Merchant\Model\ResourceModel\Sales'
        );
    }
}