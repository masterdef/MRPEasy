<?php

namespace MRPEasy\Integration\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface
{
	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$setup->startSetup();
		$connection = $setup->getConnection();
		$orderTable = $setup->getTable('sales_order');
		$orderLineTable = $setup->getTable('sales_order_item');
		
		$connection->addColumn($orderTable, 'mrpeasy_cust_ord_code', array(
		    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    'nullable'  => true,
		    'length'    => 255,
		    'after'     => null,
		    'comment'   => 'MRPEasy CO number'
	    ));
		
		$connection->addColumn($orderTable, 'mrpeasy_cust_ord_id', array(
		    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
		    'nullable'  => true,
		    'unsigned'  => true,
		    'after'     => null,
		    'comment'   => 'MRPEasy CO ID'
	    ));
		
		$connection->addColumn($orderLineTable, 'mrpeasy_cust_ord_id', array(
		    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
		    'nullable'  => true,
		    'unsigned'  => true,
		    'after'     => null,
		    'comment'   => 'MRPEasy CO ID'
	    ));
		
		$connection->addColumn($orderLineTable, 'mrpeasy_cust_line_id', array(
		    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
		    'nullable'  => true,
		    'unsigned'  => true,
		    'after'     => null,
		    'comment'   => 'MRPEasy CO line ID'
	    ));
		
		$connection->addColumn($orderLineTable, 'mrpeasy_shipment_id', array(
		    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
		    'nullable'  => true,
		    'unsigned'  => true,
		    'after'     => null,
		    'comment'   => 'MRPEasy shipment ID'
	    ));
		
		$connection->addIndex($orderLineTable,
				$setup->getIdxName('sales_order_item', array('mrpeasy_shipment_id')),
				array('mrpeasy_shipment_id'));
		
		$setup->endSetup();
	}
}