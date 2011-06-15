<?php

/**
 * Configuration backup archives controller.
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

class Archives extends ClearOS_Controller
{

    function __construct()
    {
        parent::__construct();
    }

    function index($message = NULL)
    {
        // Load dependencies
        //------------------

        $this->load->helper('number');
        $this->load->library('configuration_backup/Configuration_Backup');
        $this->lang->load('configuration_backup');

        $data['archives'] = $this->configuration_backup->get_archive_list();

        $this->page->view_form('archives', $data, lang('configuration_backup_configuration_backup'));
    }

    /**
     * Delete archive file.
     *
     * @param string  $filename filename
     *
     * @return view
     */

    function delete($filename)
    {
        $confirm_uri = '/app/configuration_backup/destroy/' . $filename;
        $cancel_uri = '/app/configuration_backup';
        $items = array($filename);

        $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    /**
     * Restore from archive file.
     *
     * @param string  $filename filename
     *
     * @return view
     */

    function restore($filename)
    {
        $this->lang->load('configuration_backup');
        $confirm_uri = '/app/configuration_backup/archive_restore/' . $filename;
        $cancel_uri = '/app/configuration_backup';
        $items = array($filename);

        $this->page->view_confirm(lang('configuration_backup_confirm_restore') . ' ' . $filename . '.', $confirm_uri, $cancel_uri, $items);
    }
}
