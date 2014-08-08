<?php

/**
 * Configuration Backup/Restore controller.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage controllers
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

use \clearos\apps\configuration_backup\Configuration_Backup as Backup;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Configuration Backup and Restore controller.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

class Configuration_Backup extends ClearOS_Controller
{

    /**
     * Configuration_Backup default controller
     *
     * @param String $start force start of import
     *
     * @return view
     */

    function index($start)
    {
        // Load dependencies
        //------------------

        $this->load->library('configuration_backup/Configuration_Backup');
        $this->lang->load('configuration_backup');

        // Check registration
        //
        // System should be registered in order to access paid apps
        // that might need to be restored as well.
        //---------------------------------------------------------

        // TODO - Add registration 'is registered' function 
        if (!file_exists('/var/clearos/registration/registered')) {
            $this->page->set_message(lang('configuration_backup_not_registered'), 'warning');
            redirect('registration/register');
            return;
        }

        // Load views
        //-----------

        if ($start || $this->configuration_backup->is_restore_in_progress()) {
            $views = array('configuration_backup/progress', 'configuration_backup/logs');
        } else {
            $views = array('configuration_backup/restore', 'configuration_backup/archives');
            if (file_exists(CLEAROS_TEMP_DIR . "/" . Backup::FILE_STATUS))
                $views[] = 'configuration_backup/logs';
        }

        $this->page->view_forms($views, lang('configuration_backup_app_name'));
    }

    /**
     * Download archive file.
     *
     * @param string $filename archive filename
     *
     * @return view
     */

    function download($filename)
    {
        $this->load->library('configuration_backup/Configuration_Backup');

        $sanity_check = $this->configuration_backup->validate_filename($filename);

        if (! empty($sanity_check)) {
            $this->page->view_exception(new \Exception($sanity_check));
            return;
        }

        @apache_setenv('no-gzip', 1); 
        $prepared_filename = $this->configuration_backup->prepare_download($filename);
        header('Content-type: application/tgz');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . filesize($prepared_filename));
        ob_clean();
        flush();
        readfile($prepared_filename);
    }

    /**
     * Destroys archive file.
     *
     * @param string $filename archive filename
     *
     * @return view
     */

    function destroy($filename)
    {
        // Load libraries
        //---------------

        $this->load->library('configuration_backup/Configuration_Backup');

        // Handle form submit
        //-------------------

        try {
            $this->configuration_backup->delete_archive($filename);

            $this->page->set_status_deleted();

            redirect('/configuration_backup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Restore using uploaded file.
     *
     * @param string $filename file upload
     *
     * @return view
     */

    function start_restore($filename, $upload = FALSE)
    {
        // Load libraries
        //---------------

        $this->load->library('configuration_backup/Configuration_Backup');

        // Handle form submit
        //-------------------

        try {
            $this->configuration_backup->restore($filename, $upload);
            sleep(3);
            redirect('/configuration_backup/index/start');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Take a backup snaphot.
     *
     * @return view
     */

    function create_archive()
    {
        // Load libraries
        //---------------

        $this->load->library('configuration_backup/Configuration_Backup');

        // Handle form submit
        //-------------------

        try {
            $this->configuration_backup->backup();

            $this->page->set_status_updated();

            redirect('/configuration_backup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Get restore progress.
     *
     * @return JSON
     */

    function get_restore_progress()
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        // Load dependencies
        //------------------

        $this->load->library('configuration_backup/Configuration_Backup');
        $this->lang->load('configuration_backup');

        try {
            $logs = $this->configuration_backup->get_restore_progress();
            echo json_encode(Array('code' => 0, 'logs' => $logs));
        } catch (Exception $e) {
            echo json_encode(Array('code' => clearos_exception_code($e), 'errmsg' => clearos_exception_message($e)));
        }
    }
}
