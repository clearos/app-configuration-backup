<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'configuration_backup';
$app['version'] = '6.1.0.beta2';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('configuration_backup_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('configuration_backup_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_backup');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_directory_manifest'] = array(
   '/var/clearos/configuration_backup' => array('mode' => '755', 'owner' => 'webconfig', 'group' => 'webconfig'),
   '/var/clearos/configuration_backup/upload' => array('mode' => '755', 'owner' => 'webconfig', 'group' => 'webconfig')
);
