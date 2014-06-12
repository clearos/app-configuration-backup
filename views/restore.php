<?php

/**
 * Configuration Backup restore view.
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
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($restore_ready) {
    $buttons = array(
        anchor_custom('/app/configuration_backup/start_restore/' . $filename . '/1', lang('base_restore'), 'high'),
        anchor_cancel('/app/configuration_backup')
    );
} else {
    $buttons = array(
        form_submit_custom('upload', lang('base_upload'), 'high'),
    );
    if ($show_cancel)
        $buttons[] = anchor_cancel('/app/configuration_backup');
}

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

echo form_open_multipart('configuration_backup/restore');
echo form_header(lang('configuration_backup_restore_from_archive'), array('id' => 'upload_form'));

echo field_file('restore_file', $filename, lang('configuration_backup_restore_file'), $restore_ready);

if ($restore_ready)
    echo field_file('size', $size, lang('base_file_size'), $restore_ready);

echo field_button_set($buttons);

echo form_footer();
echo form_close();
