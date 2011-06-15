<?php

/**
 * Configuration backup controller.
 *
 * @category   Apps
 * @package    Configuration Backup
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
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
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\configuration_backup\Configuration_Backup as Configuration_Backup;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Configuration restore controller.
 *
 * @category   Apps
 * @package    Configuration Backup
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

class Restore extends ClearOS_Controller
{

    function __construct()
    {
        parent::__construct();
    }

    function index()
    {
        // Load dependencies
        //------------------

        $this->load->helper('number');
        $this->load->library('configuration_backup/Configuration_Backup');
        $this->lang->load('configuration_backup');

        // Handle form submit
        //-------------------

        if ($this->input->post('cancel')) {
            try {
                $this->configuration_backup->delete_backup_file(CLEAROS_TEMP_DIR . '/' . $_POST['filename']);
                redirect('/configuration_backup');
            } catch (Exception $e) {
                redirect('/configuration_backup');
            }
        }
        $config['upload_path'] = CLEAROS_TEMP_DIR;
        $config['allowed_types'] = 'tgz';
        $config['overwrite'] = TRUE;

        $this->load->library('upload', $config);

        if (isset($_POST['upload']) && !$this->upload->do_upload('restore_file')) {
            $data['error'] = $this->upload->display_errors();
        } else if (isset($_POST['upload'])) {
            $upload = $this->upload->data();
            $this->configuration_backup->set_backup_file($upload['file_name']);
            $data['filename'] = $upload['file_name'];
            $data['restore_ready'] = TRUE;
            $data['size'] = byte_format($this->configuration_backup->get_backup_size(), 1);
        }
        $this->page->view_form('restore', $data, lang('configuration_backup_configuration_backup'));
    }
}
