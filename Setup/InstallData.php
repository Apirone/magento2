<?php
namespace Apirone\Merchant\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
            $callback_secret = sha1(openssl_random_pseudo_bytes(20));
                $data_secret = [
                    'scope' => 'default',
                    'scope_id' => 0,
                    'path' => 'payment/apirone_merchant/callback_secret',
                    'value' => $callback_secret,
                ];
                $setup->getConnection()
                    ->insertOnDuplicate($setup->getTable('core_config_data'), $data_secret, ['value']);
    }
}