<?php

/**
 * Configuration Backup and Restore overview.
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

///////////////////////////////////////////////////////////////////////////////
// Form open
///////////////////////////////////////////////////////////////////////////////


echo form_open_multipart('configuration_backup/restore');
echo form_header(lang('configuration_backup_restore_from_archive'));

///////////////////////////////////////////////////////////////////////////////
// Form fields and buttons
///////////////////////////////////////////////////////////////////////////////


if ($restore_ready)
    $buttons = array(
        form_submit_custom('restore', lang('configuration_backup_restore'), 'high'),
        form_submit_custom('cancel', lang('base_cancel'), 'low')
    );
else
    $buttons = array(
        form_submit_custom('upload', lang('configuration_backup_upload'), 'high')
    );

echo field_file('restore_file', $filename, lang('configuration_backup_restore_file'), $restore_ready);

if ($restore_ready) {
    echo field_file('size', $size, lang('configuration_backup_size'), $restore_ready);
}

echo field_button_set($buttons);

///////////////////////////////////////////////////////////////////////////////
// Form close
///////////////////////////////////////////////////////////////////////////////

echo form_footer();
echo form_close();
