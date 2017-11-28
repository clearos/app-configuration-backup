<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'configuration_backup';
$app['version'] = '2.4.2';
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
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['configuration_backup']['title'] = $app['name'];

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-registration',
);

$app['core_requires'] = array(
    'app-base-core >= 1:1.5.32',
    'app-network-core >= 1:2.2.4',
    'app-tasks-core'
);

$app['core_file_manifest'] = array(
    'app-configuration-backup.cron' => array('target' => '/etc/cron.d/app-configuration-backup'),
    'configuration-restore' => array(
        'target' => '/usr/sbin/configuration-restore',
        'mode' => '0755',
    ),
    'run-app-upgrades' => array(
        'target' => '/usr/sbin/run-app-upgrades',
        'mode' => '0755',
    )
);

$app['core_directory_manifest'] = array(
   '/var/clearos/configuration_backup' => array(),
   '/var/clearos/configuration_backup/upload' => array('mode' => '775', 'owner' => 'root', 'group' => 'webconfig')
);

$app['delete_dependency'] = array(
    'app-configuration-backup-core',
);
