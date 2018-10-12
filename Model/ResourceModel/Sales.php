<?php

namespace Apirone\Merchant\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Sales extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('apirone_sale', 'id');
    }
}