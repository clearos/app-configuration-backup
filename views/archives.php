<?php

/**
 * Configuration Backup and Restore archive.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage views
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

///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

$items = array();

foreach ($archives as $archive) {
    $buttons = array(
        anchor_custom('/app/configuration_backup/download/' . $archive, lang('base_download'), 'high'),
        anchor_custom('/app/configuration_backup/archives/restore/' . $archive, lang('base_restore'), 'high'),
        anchor_custom('/app/configuration_backup/archives/delete/' . $archive, lang('base_delete'), 'low')
    );
    $item = array(
        'title' => $archive,
        'action' => '', // TODO: mobile mode
	    'anchors' => button_set($buttons),
        'details' => array($archive),
    );

    $items[] = $item;
}
echo summary_table(
    lang('configuration_backup_archives'),
    array(anchor_custom('/app/configuration_backup/create_archive', lang('configuration_backup_backup_now'), 'high')),
    array(lang('base_filename')),
    $items
);
