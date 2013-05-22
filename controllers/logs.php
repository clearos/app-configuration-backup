<?php

/**
 * Configuration Backup default controller.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011-2012 ClearFoundation
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

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Configuration Backup restore log controller.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011-2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

class Logs extends ClearOS_Controller
{
    /**
     * Account import logs default controller
     *
     * @return view
     */

    function index()
    {
        // Load libraries
        //---------------
        $this->lang->load('configuration_backup');
        $this->load->library('configuration_backup/Configuration_Backup');

        $data = array();
        if ($this->configuration_backup->is_restore_in_progress())
            $data['in_progress'] = TRUE;

        // Load views
        //-----------

        $this->page->view_form('logs', $data, lang('configuration_backup_app_name'));
    }

    /**
     * Account import logs default controller
     *
     * @return view
     */

    function clear()
    {
        // Load libraries
        //---------------
        $this->lang->load('configuration_backup');
        $this->load->library('configuration_backup/Configuration_Backup');

        $this->configuration_backup->reset_restore_status();
        redirect('configuration_backup');
    }
}
