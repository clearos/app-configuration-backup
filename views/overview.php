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

if ($error)
   echo infobox_critical(lang('configuration_backup_upload_error'), $error);

///////////////////////////////////////////////////////////////////////////////
// Form open
///////////////////////////////////////////////////////////////////////////////


echo form_open_multipart('configuration_backup/upload');
echo form_header(lang('configuration_backup_TODO'));

///////////////////////////////////////////////////////////////////////////////
// Form fields and buttons
///////////////////////////////////////////////////////////////////////////////


if ($import_ready)
    $buttons = array(
        form_submit_custom('start', lang('configuration_backup_start_import'), 'high'),
        form_submit_custom('reset', lang('base_reset'), 'high')
    );
else
    $buttons = array(
        form_submit_custom('upload', lang('configuration_backup_upload'), 'high'),
        anchor_custom('configuration_backup/template', lang('configuration_backup_download_template'), 'low')
    );

echo field_file('csv_file', $filename, lang('configuration_backup_csv_file'), $import_ready);

if ($import_ready) {
    echo field_file('size', $size, lang('configuration_backup_size'), $import_ready);
    echo field_file('number', $number_of_records, lang('configuration_backup_number_of_records'), $import_ready);
}

echo button_set($buttons);

///////////////////////////////////////////////////////////////////////////////
// Form close
///////////////////////////////////////////////////////////////////////////////

echo form_footer();
echo form_close();
