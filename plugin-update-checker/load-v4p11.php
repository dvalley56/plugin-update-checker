<?php
require dirname(__FILE__) . '/Puc/v4p11/Autoloader.php';
new Puc_v4p11_Autoloader();

require dirname(__FILE__) . '/Puc/v4p11/Factory.php';
require dirname(__FILE__) . '/Puc/v4/Factory.php';

//Register classes defined in this version with the factory.
foreach (
	array(
		'Plugin_UpdateChecker' => 'Puc_v4p11_Plugin_UpdateChecker',
		'Vcs_PluginUpdateChecker' => 'Puc_v4p11_Vcs_PluginUpdateChecker',
		'AzureDevOpsApi'    => 'Puc_v4p11_Vcs_AzureDevOpsApi',
	)
	as $pucGeneralClass => $pucVersionedClass
) {
	Puc_v4_Factory::addVersion($pucGeneralClass, $pucVersionedClass, '4.11');
	//Also add it to the minor-version factory in case the major-version factory
	//was already defined by another, older version of the update checker.
	Puc_v4p11_Factory::addVersion($pucGeneralClass, $pucVersionedClass, '4.11');
}

