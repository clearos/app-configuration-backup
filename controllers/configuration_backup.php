<?php

/**
 * Configuration Backup/Restore controller.
 *
 * @category   Apps
 * @package    Configuration_Backup
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

use \clearos\apps\configuration_backup\Configuration_Backup as Backup;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Configuration Backup and Restore controller.
 *
 * @category   Apps
 * @package    Configuration_Backup
 * @subpackage Controllers
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
     * @return view
     */

    function index()
    {
        // Load dependencies
        //------------------

        $this->load->library('configuration_backup/Configuration_Backup');
        $this->lang->load('configuration_backup');

        // Load views
        //-----------

        $views = array('configuration_backup/restore', 'configuration_backup/archives');

		$this->page->view_forms($views, lang('configuration_backup_configuration_backup'));
    }

    /**
     * Download archive file.
     *
     * @param string  $filename archive filename
     *
     * @return view
     */

    function download($filename)
    {
        header('Content-type: application/tgz');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        $this->load->library('configuration_backup/Configuration_Backup');

        ob_clean();
        echo $this->configuration_backup->get_contents($filename);
        ob_flush();
    }

    /**
     * Destroys archive file.
     *
     * @param string  $filename archive filename
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
            redirect('/configuration_backup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Restore using archive file.
     *
     * @param string  $filename archive filename
     *
     * @return view
     */

    function archive_restore($filename)
    {
        // Load libraries
        //---------------

        $this->load->library('configuration_backup/Configuration_Backup');

        // Handle form submit
        //-------------------

        try {
            $this->configuration_backup->restore_by_archive($filename);
            redirect('/configuration_backup');
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
            redirect('/configuration_backup');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

}
