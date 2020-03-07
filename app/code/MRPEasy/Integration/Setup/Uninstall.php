<?php

namespace MRPEasy\Integration\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class Uninstall implements UninstallInterface
{
	public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$setup->startSetup();
		
		$connection = $setup->getConnection();
		$orderTable = $setup->getTable('sales_order');
		$orderLineTable = $setup->getTable('sales_order_item');
		$orderStatusTable = $setup->getTable('sales_order_status');
		$orderStateTable = $setup->getTable('sales_order_status_state');

		$connection->dropColumn($orderTable, 'mrpeasy_cust_ord_code');
		$connection->dropColumn($orderTable, 'mrpeasy_cust_ord_id');
		$connection->dropColumn($orderLineTable, 'mrpeasy_cust_ord_id');
		$connection->dropColumn($orderLineTable, 'mrpeasy_cust_line_id');
		$connection->dropColumn($orderLineTable, 'mrpeasy_shipment_id');

		$connection->delete($orderStatusTable, array('status LIKE ?' => 'mrpeasy_%'));
		$connection->delete($orderStateTable, array('status LIKE ?' => 'mrpeasy_%'));
		
		$setup->endSetup();
	}
}