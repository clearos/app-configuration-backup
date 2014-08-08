<?php

/**
 * Configuration backup/restore class.
 *
 * @category   apps
 * @package    configuration-backup
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2014 ClearFoundation
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
use \clearos\apps\base\Script as Script;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\mode\Mode_Engine as Mode_Engine;
use \clearos\apps\mode\Mode_Factory as Mode_Factory;
use \clearos\apps\network\Hostname as Hostname;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Script');
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
 * @category   apps
 * @package    configuration-backup
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2014 ClearFoundation
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
    const FILE_INSTALLED_APPS = '/var/clearos/configuration_backup/installed_apps.txt';
    const FOLDER_BACKUP = '/var/clearos/configuration_backup';
    const FOLDER_UPLOAD = '/var/clearos/configuration_backup/upload';
    const FOLDER_RESTORE = '/var/clearos/configuration_backup/restore';
    const CMD_TAR = '/bin/tar';
    const CMD_RPM = '/bin/rpm';
    const CMD_LS = '/bin/ls';
    const CMD_RESTORE = '/usr/sbin/configuration-restore';
    const CMD_PS = '/bin/ps';

    const FILE_LIMIT = 10; // Maximum number of archives to keep
    const SIZE_LIMIT = 512000; // Maximum size of all archives

    const RELEASE_MATCH = 'match';
    const RELEASE_MIGRATE = 'migrate';
    const RELEASE_UPGRADE_52 = 'upgrade52';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $file_config = NULL;
    protected $file_list = NULL;

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
        $this->file_list = $this->_read_config();
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

        // Generate file manifest
        //-----------------------

        if (! count($this->file_list))
            return FALSE;

        $manifest = '';

        foreach ($this->file_list as $file)
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

        $this->update_installed_apps();

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
     * Updates list of installed app RPMs.
     *
     * @throws Engine_Exception
     * @return void
     */

    function update_installed_apps()
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $args = "-qa --queryformat='%{NAME}\n' | grep ^app- | sort";
        $shell->execute(self::CMD_RPM, $args, TRUE);
        $output = $shell->get_output();

        $file = new File(self::FILE_INSTALLED_APPS);

        if ($file->exists())
            $file->delete();

        $file->create('root', 'root', '0644');
        $file->add_lines(implode($output, "\n") . "\n");
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
        $import_found = FALSE;

        foreach ($files as $file) {
            if (preg_match("/ etc\/clearos-release$/", $file))
                $release_found = 'etc/clearos-release';

            if (preg_match("/ etc\/release$/", $file))
                $release_found = 'etc/release';

            if (preg_match("/ etc\/clearos\/base\/import$/", $file))
                $import_found = TRUE;
        }

        if ($import_found)
            return self::RELEASE_MIGRATE;
        
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
        } else if (preg_match('/release 5.2/', $archive_version)) {
            return self::RELEASE_UPGRADE_52;
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
     * Returns backup file list.
     *
     * @return array Array of backup files from configuration file.
     * @throws Engine_Exception
     */

    function get_file_list()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->file_list;
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
     * @param boolean $live    boolean denoting use of live restore
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    function restore($archive, $upload = FALSE, $live = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($upload)
            $filename = self::FOLDER_UPLOAD . '/' .$archive;
        else
            $filename = self::FOLDER_BACKUP . '/' .$archive;

        try {
            $options = array(
                'background' => TRUE
            );

            $shell = new Shell();
            if ($live) {
                $shell->execute(self::CMD_RESTORE, '-L', TRUE, $options);
            } else {
                $shell->execute(self::CMD_RESTORE, "-f=" . $filename, TRUE, $options);
            }

        } catch (Exception $e) {
            throw new Engine_Exception(
                lang('configuration_backup_unable_to_start_restore') .
                ' - ' . clearos_exception_message($e), CLEAROS_WARNING
            );
        }
    }

    /**
     * Performs a restore from version 5.2.
     *
     * @param string $full_path full path of configuration archive
     *
     * @return void
     * @throws Engine_Exception
     */

    function restore_52($full_path)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Unpack tar.gz
        //--------------

        $folder = new Folder(self::FOLDER_RESTORE);

        if ($folder->exists())
            $folder->delete(TRUE);

        $folder->create('root', 'root', '0755');

        $shell = new Shell();
        $shell->execute(self::CMD_TAR, '-C ' . self::FOLDER_RESTORE . ' -xpzf ' . $full_path, TRUE);

        // Prepare LDAP import
        //--------------------
        // TODO: the openldap->import() should have the filename as the first parameter

        if (!clearos_library_installed('openldap_directory/OpenLDAP'))
            return;

        clearos_load_library('openldap/LDAP_Driver');
        clearos_load_library('openldap_directory/OpenLDAP');

        $openldap = new \clearos\apps\openldap_directory\OpenLDAP();
        $ldap_driver = new \clearos\apps\openldap\LDAP_Driver();

        // Grab domain and LDAP password for import
        //-----------------------------------------

        $file = new File(self::FOLDER_RESTORE . '/etc/kolab/kolab.conf');
        $lines = $file->get_contents_as_array();
        
        $domain = '';
        $ldap_password = '';

        foreach ($lines as $line) {
            $matches = array();

            if (preg_match('/^base_dn\s*:\s*(.*)/', $line, $matches)) {
                $domain = preg_replace('/,dc=/', '.', $matches[1]);
                $domain = preg_replace('/^dc=/', '', $domain);
            }

            $matches = array();

            if (preg_match('/^bind_pw\s*:\s*(.*)/', $line, $matches))
                $ldap_password = $matches[1];
        }

        // Set mode
        //---------

        clearos_log('configuration-backup', 'setting mode');
        $sysmode = Mode_Factory::create();
        $mode = $sysmode->set_mode(Mode_Engine::MODE_STANDALONE);

        // Prep Samba domain SID
        //----------------------

        // TODO: move this to samba->initialize(sid);
        if (clearos_library_installed('samba_common/Samba')) {
            clearos_log('configuration-backup', 'prepping Samba SID');

            $file = new File(self::FOLDER_RESTORE . '/etc/samba/domainsid');
            $file->copy_to('/etc/samba/domainsid');

            $file = new File(self::FOLDER_RESTORE . '/etc/samba/domainsid');
            $file->copy_to('/etc/samba/localid');

            $file = new File(self::FOLDER_RESTORE . '/etc/samba/smb.conf');
            $file->copy_to('/etc/samba/smb.conf');
        }

        // Intialize the underlying LDAP system
        //-------------------------------------

        if (empty($domain) || empty($ldap_password))
            throw new Engine_Exception(lang('configuration_backup_directory_could_not_be_converted'));

        clearos_log('configuration-backup', 'initializing base LDAP: ' . $domain);
        $ldap_driver->initialize_standalone($domain, $ldap_password, TRUE);

        // Initialize the accounts LDAP layer
        //-----------------------------------

        clearos_log('configuration-backup', 'initializing LDAP accounts');
        $openldap->initialize($domain, TRUE);

        // Initialize Samba
        //-----------------

        if (clearos_library_installed('samba/OpenLDAP_Driver') && clearos_library_installed('samba_common/Samba')) {
            clearos_load_library('samba/OpenLDAP_Driver'); 
            clearos_load_library('samba_common/Samba'); 

            $samba = new \clearos\apps\samba_common\Samba();
            $netbios = $samba->get_netbios_name();
            $workgroup = $samba->get_workgroup();

            clearos_log('configuration-backup', 'initializing Samba directory');
            $samba_ldap = new \clearos\apps\samba\OpenLDAP_Driver();
            $samba_ldap->initialize(TRUE, $workgroup);
            $samba_ldap->update_group_mappings();

            clearos_log('configuration-backup', 'initializing Samba local settings');
            $samba_ldap->initialize_samba_as_master_or_standalone($netbios, $workgroup, '', TRUE);
        }

        // Remove customizable default groups, use the import data instead
        //----------------------------------------------------------------

        if (clearos_library_installed('openldap_directory/Group_Driver')) {
            clearos_load_library('openldap_directory/Group_Driver');

            $remove_groups = array('domain_users', 'allusers', 'domain_admins');

            foreach ($remove_groups as $group_name) {
                $group = new \clearos\apps\openldap_directory\Group_Driver($group_name);

                if ($group->exists())
                    $group->delete();
            }
        }

        // Import the old LDIF
        //--------------------

        $file = new File(self::FOLDER_RESTORE . '/etc/openldap/backup.ldif');

        $lines = $file->get_contents_as_array();
        $lines[] = "\n"; // Make sure there's an empty line at the end

        $ignore_objects_list = array(
            'cn=Windows Administrator',
            'cn=No Members',
            'cn=Flexshare System',
            'cn=Email Archive',
            'cn=Guest Account',
            'cn=Master',
            'k=kolab',
            'cn=manager',
            'cn=nobody',
            'cn=calendar',
            'cn=guests',
            'cn=domain_guests',
            'cn=domain_computers',
            'cn=administrators',
            'cn=users',
            'cn=power_users',
            'cn=account_operators',
            'cn=server_operators',
            'cn=print_operators',
            'cn=backup_operators',
        );

        // 'entryUUID',
        $ignore_attributes_list = array(
            'createTimestamp',
            'creatorsName',
            'cyrus-userquota',
            'entryCSN',
            'hordePrefs',
            'impPrefs',
            'ingoPrefs',
            'kolabHomeServer',
            'kolabInvitationPolicy',
            'kronolithPrefs',
            'mnemoPrefs',
            'modifiersName',
            'modifyTimestamp',
            'nagPrefs',
            'pcnMailAliases',
            'pcnMicrosoftLanmanPassword',
            'pcnWebconfigFlag',
            'pcnFTPPassword',
            'pcnMailPassword',
            'pcnGoogleAppsPassword',
            'pcnOpenVPNPassword',
            'pcnPPTPPassword',
            'pcnProxyPassword',
            'pcnWebconfigPassword',
            'pcnWebPassword',
            'turbaPrefs',
            'objectClass: kolabInetOrgPerson',
            'objectClass: hordePerson',
            'objectClass: pcnWebconfigAccount',
            'objectClass: pcnProxyAccount',
            'objectClass: pcnOpenVPNAccount',
            'objectClass: pcnWebAccount',
            'objectClass: pcnFTPAccount',
            'objectClass: pcnMailAccount',
            'objectClass: pcnGoogleAppsAccount',
            'objectClass: pcnPPTPAccount',
        );

        $all_plugins = array();
        $in_object = FALSE;
        $keep_ignoring = FALSE;
        $import_ldif = '';

        foreach ($lines as $line) {
            if (preg_match('/^dn:/', $line)) {
                $object_dn = $line;
                $object_base = preg_replace('/^dn:\s*/', '', $line);
                $object_base = preg_replace('/,.*/', '', $object_base);

                if (!in_array($object_base, $ignore_objects_list) && !preg_match('/=calendar@/', $object_base)) {
                    $in_object = TRUE;
                    $is_a_keeper = FALSE;
                    $object_data = '';
                    $object_uid = '';
                    $object_cn = '';
                    $object_plugins = array();
                }
            } else if ($in_object && preg_match('/^\s*$/', $line)) {
                if ($is_a_keeper) {
                    $object_log_id = empty($object_uid) ? "common name - $object_cn" : "username - $object_uid";
                    clearos_log('configuration-backup', 'converting 5.x object: ' . $object_log_id);

                    $import_ldif .= $object_data . "\n";

                    foreach ($object_plugins as $plugin)
                        $all_plugins[$plugin][] = $object_uid;
                } else {
                    clearos_log('configuration-backup', 'skipping 5.x object: ' . $object_dn);
                }

                $in_object = FALSE;
                $is_a_keeper = FALSE;
                $object_data = '';
                $object_uid = '';
                $object_cn = '';
                $object_plugins = array();
            }

            if ($in_object) {
                $key = preg_replace('/: .*/', '', $line);

                if (preg_match('/^cn:/', $line))
                    $object_cn = preg_replace('/^cn:\s*/', '', $line);

                if (preg_match('/^\s+/', $line)) {
                    if ($keep_ignoring)
                        continue;
                } else {
                    $keep_ignoring = FALSE;
                }

                // Skip unwanted attributes,
                // Convert flags to policies,
                // Dump the rest of the attributes
                if (in_array($key, $ignore_attributes_list) || in_array(trim($line), $ignore_attributes_list)) {
                    $keep_ignoring = TRUE;
                    continue;
                } else if (preg_match('/^pcnProxyFlag: TRUE/', $line)) {
                    $object_plugins[] = 'web_proxy';
                } else if (preg_match('/^pcnOpenVPNFlag: TRUE/', $line)) {
                    $object_plugins[] = 'openvpn';
                    $object_plugins[] = 'user_certificates';
                } else if (preg_match('/^pcnPPTPFlag: TRUE/', $line)) {
                    $object_plugins[] = 'pptpd';
                } else if (preg_match('/^pcnFTPFlag: TRUE/', $line)) {
                    $object_plugins[] = 'ftp';
                } else if (preg_match('/^pcnMailFlag: TRUE/', $line)) {
                    $object_plugins[] = 'smtp';
                } else if (preg_match('/^pcn.*Flag:/', $line)) {
                    continue;
                } else {
                    $line = preg_replace('/^pcnSHAPassword:/', 'clearSHAPassword:', $line);
                    $line = preg_replace('/^pcnMicrosoftNTPassword:/', 'clearMicrosoftNTPassword:', $line);
                    $line = preg_replace(
                        '/^objectClass: pcnAccount/', 
                        "objectClass: clearAccount\nobjectClass: clearContactAccount\nclearAccountStatus: enabled", 
                        $line
                    );

                    // uid: captures users and computers
                    // gidNumber captures groups 
                    if (preg_match('/^uid:/', $line)) {
                        $object_uid = preg_replace('/^uid:\s*/', '', $line);
                        $is_a_keeper = TRUE;
                    } else if (preg_match('/^gidNumber:/', $line)) {
                        $is_a_keeper = TRUE;
                    }

                    $object_data .= $line . "\n";
                }
            }
        }

        // Import the new LDIF file
        //-------------------------

        $import_filename = self::FOLDER_RESTORE . '/etc/openldap/clear5x.ldif';
        $file = new File($import_filename);

        if ($file->exists())
            $file->delete();

        $file->create('root', 'root', '0600');
        $file->add_lines($import_ldif);

        $ldap_driver->import_ldif($import_filename);

        // Update the plugin groups
        //-------------------------

        if (clearos_library_installed('openldap_directory/Group_Driver')) {
            clearos_load_library('openldap_directory/Group_Driver');

            foreach ($all_plugins as $group_name => $members) {
    
                $group = new \clearos\apps\openldap_directory\Group_Driver($group_name . '_plugin');

                if ($group->exists())
                    $group->set_members($members);
            }
        }

        // FIXME - preserve Master object
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

        $script = new Script(basename(self::CMD_RESTORE));
        return $script->is_running();
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
     * Returns JSON-encoded data indicating progress of restore operation.
     *
     * @return array
     * @throws Engine_Exception
     */

    function get_restore_progress()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(CLEAROS_TEMP_DIR . "/" . self::FILE_STATUS, FALSE);
            $status = array();
            if (!$file->exists())
                throw new Engine_Exception(lang('configuration_backup_status_data_not_available'));

            $lines = $file->get_contents_as_array();

            if (empty($lines))
                throw new Engine_Exception(lang('configuration_backup_status_data_not_available'));
            else
                $lines = array_reverse($lines);

            foreach ($lines as $line)
                $status[] = json_decode($line);
            
            return $status;
        } catch (Exception $e) {
            return '...';
            // TODO: this gets triggered on a timing issue.
            // throw new Engine_Exception(clearos_exception_message($e));
        }
    }

    /**
     * Resets status.
     *
     * @throws Engine_Exception
     * @return void
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

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
