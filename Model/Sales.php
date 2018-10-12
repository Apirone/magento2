<?php 

namespace Apirone\Merchant\Model;

use Magento\Framework\Model\AbstractModel;

class Sales extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Apirone\Merchant\Model\ResourceModel\Sales');
    }
}