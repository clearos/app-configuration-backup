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
 * File upload controller.
 *
 * @category   Apps
 * @package    Configuration Backup
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

class Upload extends ClearOS_Controller
{

    function __construct()
    {
        parent::__construct();
        //$this->load->helper(array('form', 'url'));
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

        if ($this->input->post('reset')) {
            try {
                $this->configuration_backup->delete_backup_file();
                redirect('/configuration_backup');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        } if ($this->input->post('start')) {
            redirect('/configuration_backup/progress');
        }
        $config['upload_path'] = CLEAROS_TEMP_DIR;
        $config['allowed_types'] = 'backup';
        $config['overwrite'] = TRUE;
        $config['file_name'] = Configuration_Backup::FILE_CSV;

        $this->load->library('upload', $config);

        if ( ! $this->upload->do_upload('backup_file')) {
            $data['error'] = $this->upload->display_errors();
        } else {
            $upload = $this->upload->data();
            $this->configuration_backup->set_backup_file($upload['file_name']);
            $data['filename'] = $upload['file_name'];
            $data['import_ready'] = TRUE;
            $data['size'] = byte_format($this->configuration_backup->get_backup_size(), 1);
//            $data['number_of_records'] = $this->configuration_backup->get_number_of_records();
        }
        $this->page->view_form('overview', $data, lang('configuration_backup_configuration_backup'));
    }
}
