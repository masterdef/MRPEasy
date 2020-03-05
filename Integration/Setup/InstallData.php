<?php

namespace MRPEasy\Integration\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallData implements InstallDataInterface
{
	public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		$connection = $setup->getConnection();
		
		$data =  array(
	        array('status' => 'mrpeasy_posted', 'label' => 'In manufacturing'),
	        array('status' => 'mrpeasy_ready', 'label' => 'Ready for shipment')
	    );
		$connection->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $data);
		
		$data = array(
	        array(
	            'status' => 'mrpeasy_posted',
	            'state' => 'processing',
	            'is_default' => 0
	        ),
	        array(
	            'status' => 'mrpeasy_ready',
	            'state' => 'processing',
	            'is_default' => 0
	        )
	    );
		$connection->insertArray($setup->getTable('sales_order_status_state'), ['status', 'state', 'is_default'], $data);
	}
}