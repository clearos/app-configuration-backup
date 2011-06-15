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

// FIXME use \clearos\apps\\ClearDirectory as ClearDirectory;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\apps\openldap_directory\User_Manager_Driver as User_Manager_Driver;

// FIXME clearos_load_library('/ClearDirectory');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('network/Hostname');
clearos_load_library('openldap_directory/User_Manager_Driver');

// Exceptions
//-----------

use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Folder_Not_Found_Exception as Folder_Not_Found_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_Not_Found_Exception');
clearos_load_library('base/Folder_Not_Found_Exception');

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

    const FILE_CONFIG = '/etc/backup.conf';
    const FOLDER_BACKUP = '/var/clearos/configuration_backup';
    const FOLDER_UPLOAD = '/var/clearos/configuration_backup/upload';
    const CMD_TAR = '/bin/tar';
    const CMD_LS = '/bin/ls';
    const FILE_LIMIT = 10; // Maximum number of archives to keep
    const SIZE_LIMIT = 51200; // Maximum size of all archives

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Configuration backup/restore constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
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

        try {
            $files = $this->_read_config();

            if (! $files)
                return FALSE;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $manifest = '';

        foreach ($files as $file)
            $manifest .= $file  . ' ';

        $manifest = rtrim($manifest);

        // Determine the filename (using the hostname)
        //--------------------------------------------

        try {
            $hostname = new Hostname();
            $prefix = $hostname->get_actual();
            $prefix .= "-";
        } catch (Exception $ignore) {
            // No prefix...no fatal
            $prefix = "";
        }

        $filename = "backup-" . $prefix . strftime("%m-%d-%Y-%H-%M-%S", time()) . ".tar.gz";

        // Create the temporary folder for the archive
        //--------------------------------------------

        try {
            $folder = new Folder(self::FOLDER_BACKUP);

            if (!$folder->exists())
                $folder->create("root", "root", 700);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Dump the current LDAP database
        //-------------------------------

        if (file_exists(COMMON_CORE_DIR . "/api/ClearDirectory.class.php")) {
            include_once COMMON_CORE_DIR . "/api/ClearDirectory.class.php";

            try {
                $directory = new ClearDirectory();
                $directory->export();
            } catch (Exception $e) {
                throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            }
        }

        // Create the backup
        //------------------

        // TODO: move hard-coded excludes to /etc/backup.conf

        try {
            $shell = new Shell();
            $attr = '--exclude /etc/system/database --exclude /etc/postfix/filters --ignore-failed-read -cpzf ';

            $args = self::FOLDER_BACKUP . '/' . $filename . ' ' . $manifest;
            if ($shell->execute(self::CMD_TAR, $attr . $args, TRUE) != 0)
                throw new Engine_Exception($shell->get_first_output_line());
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            $archive = new File(self::FOLDER_BACKUP . '/' . $filename);
            $archive->chmod(600);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        return self::FOLDER_BACKUP . '/' . $filename;
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
     * @return void
     * @throws Engine_Exception
     */

    function delete_archive($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FOLDER_BACKUP . '/' . $filename, TRUE);
            $file->delete();
        } catch (Exception $e) {
            // Not fatal
        }
    }

    /**
     * Gets contents of an archive file.
     *
     * @return void
     * @throws Engine_Exception File_Not_Found_Exception
     */

    function get_contents($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FOLDER_BACKUP . '/' . $filename, TRUE);
        if (!$file->exists())
            throw new File_Not_Found_Exception(CLEAROS_ERROR);
        return $file->get_contents();
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

        try {
            $archives = $this->get_archive_list();
            $uploads = $this->get_upload_list();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

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

            if (!preg_match("/^backup-(.*)-$date_regex.tar.gz$/", $archive, $parts))
                continue;

            $stamp = mktime($parts[5], $parts[6], $parts[7], $parts[2], $parts[3], $parts[4]);

            $list[$stamp]['archive'] = $archive;

            try {

                if ($shell->execute(self::CMD_LS, '-sC1 ' . self::FOLDER_BACKUP . '/' . $archive, TRUE) != 0)
                    throw new Engine_Exception($shell->get_first_output_line());

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
     * @param string $archive filename of the archive to restore
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function restore_by_archive($archive)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_restore(self::FOLDER_BACKUP, $archive);
    }

    /**
     * Performs a restore of the system configuration files by uploading backup.
     *
     * @param string $archive filename of the upload to restore
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function restore_by_upload($archive)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_restore(self::FOLDER_UPLOAD, $archive);
        $this->purge();
    }

    /**
     * Verifies version information.
     *
     * @param string $fullpath filename of the archive
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function verify_archive($fullpath)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validate
        //---------

        try {
            $file = new File($fullpath);
            if (! $file->exists())
                throw new File_Not_Found_Exception($fullpath);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Check for /etc/release file (not stored in old versions)
        //---------------------------------------------------------

        $shell = new Shell();

        try {
            $retval = $shell->execute(self::CMD_TAR, "-tzvf $fullpath", TRUE);
            if ($retval != 0)
                throw new Engine_Exception($shell->get_first_output_line());
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $files = $shell->get_output();
        $release_found = FALSE;

        foreach ($files as $file) {
            if (preg_match("/ etc\/release$/", $file))
                $release_found = TRUE;
        }

        if (! $release_found)
            throw new Engine_Exception(lang('configuration_backup_release_missing'), CLEAROS_ERROR);

        // Check to see if release file matches
        //-------------------------------------

        try {
            $retval = $shell->execute(self::CMD_TAR, "-O -C /var/tmp -xzf $fullpath etc/release", TRUE);

            if ($retval != 0)
                throw new Engine_Exception($shell->get_first_output_line());

            $archive_version = trim($shell->get_first_output_line());

            $file = new File("/etc/release");
            $current_version = trim($file->get_contents());

            if ($current_version != $archive_version) {
                $err = lang('configuration_backup_release_mismatch') . ' (' . $archive_version . ')';
                throw new Engine_Exception($err, CLEAROS_ERROR);
            }

        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Put the backup file in the cache directory, ready for import begin.
     *
     * @filename string backup filename
     * @return void
     * @throws Engine_Exception, File_Not_Found_Exception
     */

    function set_backup_file($filename)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(CLEAROS_TEMP_DIR . '/' . $filename, TRUE);
            if (!$file->exists())
                throw new File_Not_Found_Exception(CLEAROS_ERROR);

            // Move uploaded file to cache
            $file->move_to(self::FOLDER_UPLOAD . '/' . $filename);
            $file->chown('root','root'); 
            $file->chmod(600);
        } catch (File_Not_Found_Exception $e) {
            throw new File_Not_Found_Exception(clearos_exception_message($e) . ' - ' . $filename, CLEAROS_ERROR);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
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

        try {
            $file = new File(self::FOLDER_UPLOAD . '/' . $filename, TRUE);
            if (!$file->exists())
                throw new File_Not_Found_Exception(CLEAROS_ERROR);
            $file->delete();
        } catch (File_Not_Found_Exception $e) {
            throw new File_Not_Found_Exception(clearos_exception_message($e) . ' - ' . $filename, CLEAROS_ERROR);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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

        try {
            $file = new File(self::FOLDER_UPLOAD . '/' . $filename, TRUE);
            if (!$file->exists())
                throw new File_Not_Found_Exception(CLEAROS_ERROR);
            return $file->get_size();
        } catch (File_Not_Found_Exception $e) {
            throw new File_Not_Found_Exception(clearos_exception_message($e) . ' - ' . $filename, CLEAROS_ERROR);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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

        try {
            $folder = new Folder($path);

            if (! $folder->Exists())
                throw new Folder_Not_Found_Exception($path, CLEAROS_ERROR);

            $contents = $folder->get_listing();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        if (! $contents)
            return $archives;

        foreach ($contents as $value) {
            if (! preg_match("/tgz$/", $value))
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

        $config = new File(self::FILE_CONFIG);

        try {
            if (! $config->exists())
                throw new File_Not_Found_Exception(CLEAROS_ERROR);

            $contents = $config->get_contents_as_array();

            foreach ($contents as $line) {
                if (preg_match("/^\s*#/", $line))
                    continue;

                $files[] = $line;
            }
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        return $files;
    }

    /**
     * Performs a restore for the given backup.
     *
     * @param string $path    path of the archive
     * @param string $archive filename of the archive to restore
     *
     * @access private
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    private function _restore($path, $archive)
    {
        clearos_profile(__METHOD__, __LINE__);

        $fullpath = $path . '/' . $archive;

        $this->verify_archive($fullpath);

        $file = new File($fullpath);

        try {
            if (! $file->Exists())
                throw new File_Not_Found_Exception(CLEAROS_ERROR);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        try {
            $shell = new Shell();
            if ($shell->Execute(self::CMD_TAR, "-C / -xpzf $fullpath", TRUE) != 0)
                throw new Engine_Exception($shell->get_first_output_line());
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        // Reload the LDAP database and reset LDAP-related daemons
        //--------------------------------------------------------

        if (file_exists(COMMON_CORE_DIR . "/api/ClearDirectory.class.php")) {
            include_once COMMON_CORE_DIR . "/api/ClearDirectory.class.php";

            try {
                $directory = new ClearDirectory();
                $directory->Import(FALSE);
            } catch (Exception $e) {
                throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
            }

            if (file_exists(COMMON_CORE_DIR . "/api/User_Manager_Driver.class.php")) {
                include_once COMMON_CORE_DIR . "/api/User_Manager_Driver.class.php";

                try {
                    $user_manager = new User_Manager_Driver();
                    $user_manager->synchronize();
                } catch (Exception $e) {
                    throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
                }
            }
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   R O U T I N E S
    ///////////////////////////////////////////////////////////////////////////////

}
