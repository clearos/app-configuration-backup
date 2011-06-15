<?php

/**
 * Configuration Backup and Restore archive.
 *
 * @category   Apps
 * @package    Configuration_Backup
 * @subpackage Views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearcenter.com/support/documentation/clearos/configuration_backup/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.  
//  
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('configuration_backup');

$items = array();

foreach ($archives as $archive) {
    $item = array(
        'title' => "example$i.lan",
        'action' => anchor_edit('/app/devel', 'high'),
        'anchors' => anchor_custom('/app/configuration_backup/archives/download/' . $archive, lang('configuration_backup_download'), 'high') .
            anchor_custom('/app/configuration_backup/archives/restore/' . $archive, lang('configuration_backup_restore'), 'high') .
            anchor_custom('/app/configuration_backup/archives/delete/' . $archive, lang('configuration_backup_delete'), 'low'),
        'details' => array($archive),
    );

    $items[] = $item;
}
echo summary_table(
    lang('configuration_backup_archives'),
    array(anchor_custom('/app/configuration_backup/create_archive', lang('configuration_backup_backup_now'), 'high')),
    array(lang('configuration_backup_filename')),
    $items
);
