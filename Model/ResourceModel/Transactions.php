<?php

namespace Apirone\Merchant\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Transactions extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('apirone_transactions', 'id'); //hello is table of module
    }
}