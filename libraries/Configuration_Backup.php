<?php

/**
 * Configuration backup/restore class.
 *
 * @category   Apps
 * @package    Configuration_Backup
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\configuration_backup;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('configuration_backup');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\mode\Mode_Engine as Mode_Engine;
use \clearos\apps\mode\Mode_Factory as Mode_Factory;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('mode/Mode_Engine');
clearos_load_library('mode/Mode_Factory');
clearos_load_library('network/Hostname');
clearos_load_library('openldap/LDAP_Driver');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_Not_Found_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Configuration backup and restore class.
 *
 * @category   Apps
 * @package    Configuration_Backup
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/configuration_backup/
 */

class Configuration_Backup extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = 'backup.conf';
    const FILE_STATUS = 'configuration_backup.json';
    const FILE_INSTALLED_APPS = 'installed_apps.txt';
    const FOLDER_BACKUP = '/var/clearos/configuration_backup';
    const FOLDER_UPLOAD = '/var/clearos/configuration_backup/upload';
    const FOLDER_RESTORE = '/var/clearos/configuration_backup/restore';
    const CMD_TAR = '/bin/tar';
    const CMD_RPM = '/bin/rpm';
    const CMD_LS = '/bin/ls';
    const CMD_RESTORE = '/usr/sbin/configuration-restore';
    const CMD_PS = '/bin/ps';

    const FILE_LIMIT = 10; // Maximum number of archives to keep
    const SIZE_LIMIT = 51200; // Maximum size of all archives

    const RELEASE_MATCH = 'match';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $file_config = NULL;

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Configuration backup/restore constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->file_config = clearos_app_base('configuration_backup') . '/deploy/' . self::FILE_CONFIG;
    }

    /**
     * Performs a backup of the system configuration files.
     *
     * @return string path/filename of the backup
     * @throws Engine_Exception
     */

    function backup()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load the file manifest
        //-----------------------

        $files = $this->_read_config();

        if (! $files)
            return FALSE;

        $manifest = '';

        foreach ($files as $file)
            $manifest .= $file  . ' ';

        $manifest = rtrim($manifest);

        // Determine the filename (using the hostname)
        //--------------------------------------------

        try {
            $hostname = new Hostname();
            // Codeigniter upload doesn't behave as nice with dots in filename
            $prefix = preg_replace('/\./', '_', $hostname->get_actual());
            $prefix .= "-";
        } catch (Exception $ignore) {
            // No prefix...no fatal
            $prefix = "";
        }

        $filename = "backup-" . $prefix . strftime("%m-%d-%Y-%H-%M-%S", time()) . ".tgz";

        // Create the temporary folder for the archive
        //--------------------------------------------

        $folder = new Folder(self::FOLDER_BACKUP);

        if (!$folder->exists())
            $folder->create("root", "root", 700);

        // Dump the app RPM list
        //----------------------
        $shell = new Shell();
        $args = "-qa --queryformat='%{NAME}\n' | grep ^app- | sort > " .
            CLEAROS_TEMP_DIR . "/" . self::FILE_INSTALLED_APPS;
        $shell->execute(self::CMD_RPM, $args);

        // Dump the current LDAP database
        //-------------------------------

        if (clearos_library_installed('openldap/LDAP_Driver')) {
            clearos_load_library('openldap/LDAP_Driver');

            $openldap = new LDAP_Driver();
            $openldap->export();
        }

        // Create the backup
        //------------------

        $shell = new Shell();
        $attr = '--exclude=*.rpmnew --exclude=*.rpmsave --exclude=*blacklists* --ignore-failed-read -cpzf ';

        $args = self::FOLDER_BACKUP . '/' . $filename . ' ' . $manifest;
        $shell->execute(self::CMD_TAR, $attr . $args, TRUE);

        $archive = new File(self::FOLDER_BACKUP . '/' . $filename);
        $archive->chmod(600);

        return self::FOLDER_BACKUP . '/' . $filename;
    }

    /**
     * Verifies version information.
     *
     * @param string $full_path filename of the archive
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function check_archive_version($full_path)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        $file = new File($full_path);

        if (! $file->exists())
            throw new File_Not_Found_Exception($full_path);

        // Check for /etc/release file (not stored in old versions)
        //---------------------------------------------------------

        $shell = new Shell();

        $shell->execute(self::CMD_TAR, "-tzvf $full_path", TRUE);
        $files = $shell->get_output();

        $release_found = '';

        foreach ($files as $file) {
            if (preg_match("/ etc\/clearos-release$/", $file))
                $release_found = 'etc/clearos-release';

            if (preg_match("/ etc\/release$/", $file))
                $release_found = 'etc/release';
        }

        if (empty($release_found))
            throw new Engine_Exception(lang('configuration_backup_release_missing'), CLEAROS_ERROR);

        // Check to see if release file matches
        //-------------------------------------

        $retval = $shell->execute(self::CMD_TAR, "-O -C /var/tmp -xzf $full_path $release_found", TRUE);

        $archive_version = trim($shell->get_first_output_line());

        $file = new File('/etc/clearos-release');
        $current_version = trim($file->get_contents());

        if ($current_version == $archive_version) {
            return self::RELEASE_MATCH;
        } else {
            $error = lang('configuration_backup_release_mismatch') . ' (' . $archive_version . ')';
            throw new Engine_Exception($error, CLEAROS_ERROR);
        }
    }

    /**
     * Returns an array of archived backups on the server.
     *
     * @return array a list of archives
     * @throws Engine_Exception
     */

    function get_archive_list()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->_get_list(self::FOLDER_BACKUP);
    }

    /**
     * Returns an array of uploaded backups on the server.
     *
     * @return array a list of uploads
     * @throws Engine_Exception
     */

    function get_upload_list()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->_get_list(self::FOLDER_UPLOAD);
    }

    /**
     * Deletes an archive file.
     *
     * @param string $filename filename
     *
     * @return void
     * @throws Engine_Exception
     */

    function delete_archive($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_filename($filename));

        try {
            $file = new File(self::FOLDER_BACKUP . '/' . $filename, TRUE);
            $file->delete();
        } catch (Exception $e) {
            // Not fatal
        }
    }

    /**
     * Returns full path name.
     *
     * @param string $filename filename
     *
     * @return void
     * @throws Engine_Exception File_Not_Found_Exception
     */

    function prepare_download($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_filename($filename));

        $file = new File(self::FOLDER_BACKUP . '/' . $filename);

        $file->chown('root', 'webconfig');
        $file->chmod('0660');

        return self::FOLDER_BACKUP . '/' . $filename;
    }

    /**
     * Purges archives based on date of creation and/or size restrictions.
     *
     * @return void
     * @throws Engine_Exception
     */

    function purge()
    {
        clearos_profile(__METHOD__, __LINE__);

        $files = 0;
        $tally = 0;

        $list = array();
        $uploads = array();
        $archives = array();

        $archives = $this->get_archive_list();
        $uploads = $this->get_upload_list();

        // Clean out any upload files
        //---------------------------

        foreach ($uploads as $archive) {
            try {
                $file = new File(self::FOLDER_UPLOAD . "/" . $archive);
                $file->delete();
            } catch (Exception $e) {
                // Nnt fatal
            }
        }

        // Clean out old archives
        //-----------------------

        $shell = new Shell();
        $parts = array();

        foreach ($archives as $archive) {
            $date_regex = '([0-9]{2})-([0-9]{2})-([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})';

            if (!preg_match("/^backup-(.*)-$date_regex.tgz$/", $archive, $parts))
                continue;

            $stamp = mktime($parts[5], $parts[6], $parts[7], $parts[2], $parts[3], $parts[4]);

            $list[$stamp]['archive'] = $archive;

            try {
                $shell->execute(self::CMD_LS, '-sC1 ' . self::FOLDER_BACKUP . '/' . $archive, TRUE);
                unset($list[$stamp]);
            } catch (Exception $e) {
                // Not fatal
                continue;
            }

            list($size, $name) = preg_split("/ /", $shell->get_first_output_line(), 2);
            $list[$stamp]["size"] = $size;
            $list[$stamp]["archive"] = $name;

            $files++;
            $tally += $size;
        }

        ksort($list, SORT_NUMERIC);

        while ($files > self::FILE_LIMIT || $tally > self::SIZE_LIMIT) {
            $archive = array_shift($list);
            $files--;
            $tally -= $archive["size"];

            try {
                $file = new File($archive["archive"]);
                $file->Delete();
            } catch (Exception $e) {
                // Not fatal
            }
        }
    }

    /**
     * Performs a restore of the system configuration files from an archive backup.
     *
     * @param string  $archive filename of the archive to restore
     * @param boolean $upload  boolean denoting use upload file path
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function restore($archive, $upload = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $filename = self::FOLDER_BACKUP . '/' .$archive;

        if ($upload)
            $filename = self::FOLDER_UPLOAD . '/' .$archive;

        try {
            $options = array(
                'background' => TRUE
            );

            $shell = new Shell();
            $shell->execute(self::CMD_RESTORE, "-f=" . $filename, TRUE, $options);

        } catch (Exception $e) {
            throw new Engine_Exception(
                lang('configuration_backup_unable_to_start_restore') . ": " . clearos_exception_message($e),
                CLEAROS_WARNING
            );
        }
    }

    /**
     * Returns boolean indicating whether restore is currently running.
     *
     * @return boolean
     * @throws Engine_Exception
     */

    function is_restore_in_progress()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $shell = new Shell();
            $exe = pathinfo(self::CMD_RESTORE, PATHINFO_FILENAME);
            $exitcode = $shell->execute(self::CMD_PS, " afx | grep $exe 2>&1 & echo $!", FALSE);
            if ($exitcode != 0)
                throw new Engine_Exception(
                    lang('configuration_backup_unable_to_determine_running_state'),
                    CLEAROS_WARNING
                );
            $rows = $shell->get_output();
            $pid = -1;
            foreach ($rows as $row) {
                if (preg_match('/^([0-9]+)$/', $row, $match)) {
                    $pid = trim($match[1]);
                    continue;
                }
                if (preg_match('/^\s*([0-9]+)\s+.*' . $exe . '.*$/', $row, $match)) {
                    if ((intval($match[1]) + 4) < $pid || $match[1] > $pid)
                        return TRUE;
                }
            }
            return FALSE;
        } catch (Exception $e) {
            throw new Engine_Exception(lang('configuration_backup_unable_to_determine_running_state'), CLEAROS_WARNING);
        }
    }

    /**
     * Resets (deletes) the backup file.
     *
     * @param string $filename filename
     *
     * @return void
     * @throws Engine_Exception, File_Not_Found_Exception
     */

    function delete_backup_file($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_filename($filename));

        $file = new File(self::FOLDER_UPLOAD . '/' . $filename, TRUE);

        $file->delete();
    }

    /**
     * Fetches the size of a backup file.
     *
     * @param string $filename filename
     *
     * @return integer size 
     * @throws Engine_Exception, File_Not_Found_Exception
     */

    function get_backup_size($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_filename($filename));

        $file = new File(self::FOLDER_UPLOAD . '/' . $filename, TRUE);

        return $file->get_size();
    }

    /**
     * Returns JSON-encoded data indicating status of restore operation.
     *
     * @return string
     * @throws Engine_Exception
     */

    function get_restore_status()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(CLEAROS_TEMP_DIR . "/" . self::FILE_STATUS, FALSE);
            $status = array();
            if (!$file->exists()) {
                $status['code'] = 0;
                $status['timestamp'] = time();
                $status['progress'] = 0;
                $status['msg'] = 'not_running';
                return $status;
            }

            $lines = $file->get_contents_as_array();

            if (empty($lines))
                throw new Engine_Exception(lang('configuration_backup_no_data'));

            return json_decode(end($lines));
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e));
        }
    }

    /**
     * Reset status.
     *
     * @return void
     * @throws Engine_Exception
     */

    function reset_restore_status()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(CLEAROS_TEMP_DIR . "/" . self::FILE_STATUS);
        if ($file->exists())
            $file->delete();
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for filename.
     *
     * @param string $filename filename
     *
     * @return string error message if filename is invalid
     */

    public function validate_filename($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^backup-[0-9a-zA-Z\.\-_]+\.tgz$/', $filename))
            return lang('configuration_backup_backup_file_invalid');
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Returns an array of archived backups on the server.
     *
     * @param string $path path
     *
     * @access private
     * @return array a list of archives
     * @throws Engine_Exception
     */

    private function _get_list($path)
    {
        clearos_profile(__METHOD__, __LINE__);

        $archives = array();

        $folder = new Folder($path);

        $contents = $folder->get_listing();

        if (! $contents)
            return $archives;

        foreach ($contents as $value) {
            // CodeIgniter adds underscores in part of the file cleanup process.
            // Shrug.  It's probably best to leave it alone.

            if (! preg_match("/(tar_.gz|tar.gz|tgz)$/", $value))
                continue;

            $archives[] = $value;
        }

        return array_reverse($archives);
    }

    /**
     * Reads configuration file.
     * 
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    private function _read_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        $files = array();

        $config = new File($this->file_config);

        $contents = $config->get_contents_as_array();

        foreach ($contents as $line) {
            if (preg_match("/^\s*#/", $line))
                continue;

            $files[] = $line;
        }

        return $files;
    }

    /**
     * Put the backup file in the cache directory, ready for import begin.
     *
     * @param string $filename filename
     *
     * @filename string backup filename
     * @return void
     * @throws Engine_Exception, File_Not_Found_Exception
     */

    function set_backup_file($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(CLEAROS_TEMP_DIR . '/' . $filename, TRUE);

        // Move uploaded file to cache
        $file->move_to(self::FOLDER_UPLOAD . '/' . $filename);
        $file->chown('root', 'root'); 
        $file->chmod(600);
    }
}
