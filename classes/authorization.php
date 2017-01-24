<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Este bloco é parte do Moodle Provas - http://tutoriais.moodle.ufsc.br/provas/
// Este projeto é financiado pela
// UAB - Universidade Aberta do Brasil (http://www.uab.capes.gov.br/)
// e é distribuído sob os termos da "GNU General Public License",
// como publicada pela "Free Software Foundation".

/**
 * The Authorization class
 *
 * @package    local_exam_authorization
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_exam_authorization;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

class authorization {

    public static $userfields = array('username', 'firstname', 'lastname', 'idnumber', 'email');

    private static $moodles = null;
    private static $config = null;
    private static $capabilities = null;

    /**
     * Returns a mapping from Moodle Exam capabilities to roles
     *
     * @return array Mapping capabilities to roles
     */
    public static function capabilities() {
        global $DB;

        if (!isset(self::$capabilities)) {
            self::$capabilities = array(
                'local/exam_remote:take_exam' =>
                    $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST),
                'local/exam_remote:write_exam' =>
                    $DB->get_record('role', array('shortname' => 'editingteacher'), '*', MUST_EXIST),
                'local/exam_remote:supervise_exam' =>
                    $DB->get_record('role', array('id' => self::config('supervisor_roleid')), '*', MUST_EXIST),
                'local/exam_remote:monitor_exam' =>
                    $DB->get_record('role', array('id' => self::config('monitor_roleid')), '*', MUST_EXIST),
                );
        }
        return self::$capabilities;
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     *
     * @return bool Authentication success or failure.
     */
    public static function authenticate($username, $password) {
        $ws_function = 'local_exam_remote_authenticate';
        $params = array('username' => $username, 'password' => $password);

        foreach (self::moodles() AS $m) {
            if (self::call_remote_function($m->identifier, $ws_function, $params) === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the user data or false if the user doesn't exist
     *
     * @param string $username The username (with system magic quotes)
     *
     * @return mixed array with user data or false the username is unknown
     */
    public static function userinfo($username) {
        $ws_function = 'core_user_get_users_by_field';
        $params = array('field' => 'username',  'values' => array($username));

        foreach (self::moodles() AS $m) {
            $users = self::call_remote_function($m->identifier, $ws_function, $params);
            if (!empty($users)) {
                return reset($users);
            }
        }
        return false;
    }

    /**
     * Process the user_loggedin event. Check the user permissions and sync enrols
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     *
     * @return bool Always return true
     */

    // process the user_loggedin event
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $SESSION;

        if (is_siteadmin($event->userid) || isguestuser($event->userid)) {
            return true;
        }

        $user = new \stdClass();
        $user->id = $event->userid;
        $user->username = $event->other['username'];

        if (!isset($SESSION->exam)) {
            $SESSION->exam = new \stdClass();
        }
        $SESSION->exam->roleids_by_courses = array();
        $SESSION->exam->write_exam = false;
        $SESSION->exam->take_exam = false;
        $SESSION->exam->supervise_exam = false;
        $SESSION->exam->monitor_exam = false;
        $SESSION->exam->messages = array();

        self::check_sessions($user->id);
        self::check_permissions($user);
        self::sync_enrols($user->id);

        return true;
    }

    /**
     * Check number of active sessions (must be only one)
     *
     * @param stdclass $user The user data
     *
     * @return null
     */
    private static function check_sessions($userid) {
        global $SESSION;

        $sessions = self::user_sessions($userid);
        $numsessions = count($sessions);
        if ($numsessions > 1) {
            foreach ($sessions as $ses) {
                if ($ses->is_taking_exam) {
                    if (isset($SESSION->exam->access_key)) { // ready to take exam
                        self::add_to_log($SESSION->exam->access_key, $userid, 'more_than_one_session');
                        unset($SESSION->exam->access_key);
                    }
                    self::print_error('has_student_session');
                    exit;
                }
            }

            \core\session\manager::kill_user_sessions($userid, session_id());

            if ($numsessions == 2) {
                self::warning('session_removed', $numsessions-1);
            } else {
                self::warning('sessions_removed', $numsessions-1);
            }
        }
    }

    public static function kill_user_sessions($userid) {
        $count = count(self::sessions($userid));
        \core\session\manager::kill_user_sessions($userid);
        return $count;
    }

    private static function user_sessions($userid) {
        global $DB;

        $sql = "SELECT s.*, l.id IS NOT NULL AS is_taking_exam
                  FROM {sessions} s
             LEFT JOIN {exam_access_keys_log} l ON (l.userid = s.userid AND l.sessionid = s.sid)
                 WHERE s.userid = :userid";
        return $DB->get_records_sql($sql, array('userid' => $userid));
    }

    /**
     * Check the user permissions
     *
     * @param stdclass $user The user data
     *
     * @return null
     */
    private static function check_permissions($user) {
        global $SESSION;

        if (isset($SESSION->exam->access_key)) { // ready to take exam
            $access_key = $SESSION->exam->access_key;
            unset($SESSION->exam->access_key);
            $SESSION->exam->take_exam = true;
            self::check_student_permission($user, $access_key);
        } else {
            $SESSION->exam->take_exam = false;
            self::warning('no_access_key');
            self::check_general_user_permission($user->username);
        }
    }

    /**
     * Check the student permission
     *
     * @param stdclass $user The user data
     * @param string $access_key The session access key
     *
     * @return null
     */
    private static function check_student_permission($user, $access_key) {
        global $DB, $SESSION;

        if (!$rec_key = $DB->get_record('exam_access_keys', array('access_key' => $access_key))) {
            self::add_to_log($access_key, $user->id, 'access_key_unknown');
            self::error('access_key_unknown');
            return;
        }
        if ($rec_key->timecreated + $rec_key->timeout*60 < time()) {
            self::add_to_log($access_key, $user->id, 'access_key_timedout');
            self::error('access_key_timedout');
            return;
        }

        try {
            self::check_ip_range_student();
            self::check_version_header();
            self::check_ip_header();
            self::check_network_header();
            self::check_client_host($rec_key);
        } catch(\Exception $e) {
            self::add_to_log($access_key, $user->id, $e->getMessage());
            self::error($e->getMessage());
            return;
        }

        $course = $DB->get_record('course', array('id' => $rec_key->courseid), 'id, shortname, visible');
        if ($course && $course->visible) {
            list($identifier, $shortname) = explode('_', $course->shortname, 2);
            $capabilities = self::user_capabilities($user->username, $shortname, $identifier);
            if (!in_array('local/exam_remote:take_exam', $capabilities)) {
                self::add_to_log($access_key, $user->id, 'no_student_permission');
                self::error('no_student_permission');
            } else if (count($capabilities) > 1) {
                self::add_to_log($access_key, $user->id, 'more_than_student_permission');
                self::error('more_than_student_permission');
            } else {
                self::add_to_log($access_key, $user->id, 'ok', session_id());
                $role = self::capabilities()['local/exam_remote:take_exam'];
                $SESSION->exam->roleids_by_courses[$course->id][] = $role->id;
            }
        } else {
            self::add_to_log($access_key, $user->id, 'course_not_avaliable');
            self::error('course_not_avaliable');
        }
    }

    /**
     * Check the general user permission (other but student)
     *
     * @param string $username The username
     *
     * @return null
     */
    private static function check_general_user_permission($username) {
        global $DB, $SESSION;

        if ($SESSION->exam->take_exam) {
            return;
        }

        $ip_range_editor_ok = self::check_ip_range_editor(false);
        $out_of_editor_ip_range = false;

        $remote_courses = self::user_courses($username);

        foreach ($remote_courses AS $identifier => $rcourses) {
            foreach ($rcourses AS $rcourse) {
                $shortname = "{$identifier}_{$rcourse->shortname}";
                if ($courseid = $DB->get_field('course', 'id', array('shortname' => $shortname))) {
                    foreach ($rcourse->capabilities AS $capability) {
                        if ($capability == 'local/exam_remote:write_exam') {
                            if ($ip_range_editor_ok) {
                                $role = self::capabilities()[$capability];
                                $SESSION->exam->roleids_by_courses[$courseid][] = $role->id;
                                $SESSION->exam->write_exam = true;
                            } else {
                                $out_of_editor_ip_range = true;
                            }
                        } else if ($capability == 'local/exam_remote:supervise_exam') {
                            $role = self::capabilities()[$capability];
                            $SESSION->exam->roleids_by_courses[$courseid][] = $role->id;
                            $SESSION->exam->supervise_exam = true;
                        } else if ($capability == 'local/exam_remote:monitor_exam') {
                            $role = self::capabilities()[$capability];
                            $SESSION->exam->roleids_by_courses[$courseid][] = $role->id;
                            $SESSION->exam->monitor_exam = true;
                        }
                    }
                }
            }
        }
        if ($out_of_editor_ip_range) {
            self::warning('out_of_editor_ip_range');
        }
    }

    /**
     * Enrol an unenrol users from courses depending on their permissions
     *
     * @param ing $userid The user id
     *
     * @return null
     */
    private static function sync_enrols($userid) {
        global $DB, $SESSION;

        if (!isset($SESSION->exam->roleids_by_courses)) {
            return;
        }

        if (!enrol_is_enabled('manual')) {
            self::error('enrol_not_active');
        }
        if (!$plugin = enrol_get_plugin('manual')) {
            self::error('enrol_not_active');
        }

        // suspend/role_unassign all unnecessary user enrolments
        foreach ($DB->get_records('user_enrolments', array('userid' => $userid))  AS $ue) {
            if ($enrol = $DB->get_record('enrol', array('id' => $ue->enrolid, 'enrol' => 'manual'))) {
                $context = \context_course::instance($enrol->courseid, MUST_EXIST);
                foreach ($DB->get_records('role_assignments', array('contextid' => $context->id, 'userid' => $userid)) as $ra) {
                    if (isset($SESSION->exam->roleids_by_courses[$enrol->courseid])) {
                        if (!in_array($ra->roleid, $SESSION->exam->roleids_by_courses[$enrol->courseid])) {
                            role_unassign($ra->roleid, $userid, $context->id);
                        }
                    }
                }
                if (!isset($SESSION->exam->roleids_by_courses[$enrol->courseid])) {
                    $plugin->update_user_enrol($enrol, $userid, ENROL_USER_SUSPENDED);
                }
            }
        }

        // activate only the necessary enrolments
        foreach ($SESSION->exam->roleids_by_courses AS $courseid => $roleids) {
            $instances = $DB->get_records('enrol', array('enrol' => 'manual', 'courseid' => $courseid), 'id ASC');
            if ($instance = reset($instances)) {
                if ($instance->status != ENROL_INSTANCE_ENABLED) {
                    $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
                }
            } else {
                $course = get_course($courseid);
                $enrolid = $plugin->add_instance($course);
                $instance = $DB->get_record('enrol', array('id' => $enrolid));
            }

            foreach ($roleids AS $roleid) {
                $plugin->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
            }
        }
    }

    /**
     * Get the remote courses the user has some exam capabilities
     *
     * @param string $username The username
     * @param string $identifier The remote Moodle identifier or blank for all remote Moodles
     *
     * @return array
     */

    public static function user_courses($username, $identifier='') {
        global $DB;

        $ws_function = 'local_exam_remote_user_courses';
        $params = array('username' => $username);

        $moodles = empty($identifier) ? self::moodles() : array(self::moodle($identifier));

        $courses = array();
        foreach ($moodles AS $m) {
            $courses[$m->identifier] = array();
            foreach (self::call_remote_function($m->identifier, $ws_function, $params) as $course) {
                $courses[$m->identifier][$course->shortname] = $course;
            }
        }
        return $courses;
    }

    /**
     * Get the capabilities a user has on a remote course
     *
     * @param string $username The username
     * @param string $shortname The course shortname
     * @param string $identifier The remote Moodle identifier or blank for all remote Moodles
     *
     * @return array
     */
    public static function user_capabilities($username, $shortname, $identifier) {
        $ws_function = 'local_exam_remote_user_capabilities';
        $params = array('username' => $username, 'shortname' => $shortname);
        return self::call_remote_function($identifier, $ws_function, $params);
    }

    /**
     * Review the user permissions, except the student case
     *
     * @param stdclass $user The user
     *
     * @return boolean
     */

    public static function review_permissions($user) {
        global $SESSION;

        if ($SESSION->exam->take_exam) {
            return false;
        }

        $SESSION->exam->roleids_by_courses = array();
        $SESSION->exam->write_exam = false;
        $SESSION->exam->supervise_exam = false;
        $SESSION->exam->monitor_exam = false;

        self::check_general_user_permission($user->username);
        self::sync_enrols($user->id);

        return true;
    }

    // ---------------------------------------------------------------------------
    // A rever
    // ---------------------------------------------------------------------------


    // Get users enrolled in a course
    public static function enrolled_users($shortname, $identifier) {
        $ws_function = 'core_enrol_get_enrolled_users_with_capability';
        $params = array('coursecapabilities[0][courseid]' => 2,
                        'options[0][name]' => 'onlyactive', 'options[0][value]' => 1,
                        'options[1][name]' => 'userfields', 'options[1][value]' => implode(',', self::$userfields),
                       );
        $count = 0;
        foreach (self::capabilities() as $cap=>$role) {
            $params["coursecapabilities[0][capabilities][{$count}]"] = $cap;
            $count++;
        }

        $result = self::call_remote_function($identifier, $ws_function, $params);

        $users = array();
        $capabilities = self::capabilities();
        foreach ($result as $r) {
            $role = $capabilities[$r->capability];
            foreach ($r->users as $u) {
                if (!isset($users[$u->username])) {
                    $u->roleids = array();
                    $users[$u->username] = $u;
                }
                $users[$u->username]->roleids[] = $role->id;
            }
        }
        return $users;
    }

    // ========================================================================================

    public static function remote_addr() {
        $remoteaddrfield = self::config('remoteaddrfield');
        if (empty($remoteaddrfield) || !isset($_SERVER[$remoteaddrfield])) {
            $remoteaddrfield = 'REMOTE_ADDR';
        }
        return $_SERVER[$remoteaddrfield];
    }

    public static function http_headers() {
        $allheaders = getallheaders();
        $headers = new \stdClass();

        if (isset($allheaders['MOODLE_PROVAS_VERSION'])) {
            $headers->version = $allheaders['MOODLE_PROVAS_VERSION'];
            $headers->ip = isset($allheaders['MOODLE_PROVAS_IP']) ? $allheaders['MOODLE_PROVAS_IP'] : '';
            $headers->network = isset($allheaders['MOODLE_PROVAS_NETWORK']) ? $allheaders['MOODLE_PROVAS_NETWORK'] : '';
        } else if (isset($allheaders['EXAM-VERSION'])) {
            $headers->version = $allheaders['EXAM-VERSION'];
            $headers->ip = isset($allheaders['EXAM-IP']) ? $allheaders['EXAM-IP'] : '';
            $headers->network = isset($allheaders['EXAM-NETWORK']) ? $allheaders['EXAM-NETWORK'] : '';
        } else {
            $headers->version = '';
            $headers->ip = '';
            $headers->network = '';
        }

        return $headers;
    }

    public static function add_to_log($access_key, $userid, $info='', $sessionid=0) {
        global $DB;

        $rec = new \stdClass();
        $rec->access_key = $access_key;
        $rec->userid = $userid;
        $rec->ip = self::remote_addr();
        $rec->time = time();
        $rec->info = $info;
        $rec->sessionid = $sessionid;

        $headers = self::http_headers();
        $rec->header_version = $headers->version;
        $rec->header_ip = $headers->ip;
        $rec->header_network = $headers->network;

        $DB->insert_record('exam_access_keys_log', $rec);
    }


    public static function moodle($identifier) {
        self::moodles();
        if (isset(self::$moodles[$identifier])) {
            return self::$moodles[$identifier];
        } else {
            return false;
        }
    }

    public static function moodles() {
        global $DB;

        if (self::$moodles == null) {
            self::$moodles = $DB->get_records('exam_authorization', array('enable' => 1), null, 'identifier, description, url, token');
        }

        return self::$moodles;
    }

    public static function call_remote_function($identifier, $ws_function, $params) {
        global $DB;

        if (!$moodle = self::moodle($identifier)) {
            throw new \Exception(get_string('unknown_identifier', 'local_exam_authorization', $identifier));
        }

        $curl = new \curl;
        $curl->setopt(array('CURLOPT_SSL_VERIFYHOST' => 0, 'CURLOPT_SSL_VERIFYPEER' => 0));
        $serverurl = "{$moodle->url}/webservice/rest/server.php?wstoken={$moodle->token}&wsfunction={$ws_function}&moodlewsrestformat=json";

        // formating (not recursive) an array in POST parameter.
        // We try to use 'format_array_postdata_for_curlcall' from filelib.php, but there's some troubles with stored_files
        foreach ($params AS $key => $value) {
            if (is_array($value)) {
                unset($params[$key]);
                foreach ($value AS $i => $v) {
                    $params[$key.'['.$i.']'] = $v;
                }
            }
        }

        $result = json_decode($curl->post($serverurl, $params));
        if (is_object($result) && isset($result->exception)) {
            throw new \Exception($result->message);
        } else if (is_null($result)) {
            throw new \Exception(get_string('return_null', 'local_exam_authorization', $moodle->description));
        }
        return $result;
    }

    private static function load_config() {
        if (self::$config == null) {
            self::$config = get_config('local_exam_authorization');
            if (!isset(self::$config->disable_header_check)) {
                self::$config->disable_header_check = false;
            }
            if (!isset(self::$config->header_version)) {
                self::print_error('not_configured');
            }
            if (!isset(self::$config->client_host_timeout)) {
                self::$config->client_host_timeout = '10';
            }
            if (!isset(self::$config->ip_ranges_editors)) {
                self::$config->ip_ranges_editors = '';
            }
            if (!isset(self::$config->ip_ranges_students)) {
                self::$config->ip_ranges_students = '';
            }
            if (!isset(self::$config->supervisor_roleid)) {
                self::$config->supervisor_roleid = 0;
            }
            if (!isset(self::$config->monitor_roleid)) {
                self::$config->monitor_roleid = 0;
            }
            if (!isset(self::$config->auth_plugin)) {
                self::$config->auth_plugin = '';
            }
        }
    }

    public static function config($key) {
        self::load_config();
        if (isset(self::$config->$key)) {
            return self::$config->$key;
        } else {
            return false;
        }
    }

    public static function is_header_check_disabled() {
        return self::config('disable_header_check');
    }

    public static function check_version_header($throw_exception=true) {
        if (self::is_header_check_disabled()) {
            return true;
        }

        $version = self::$config->header_version;
        $pattern = '/^[0-9]+\.[0-9]+$/';

        $headers = self::http_headers();

        if (empty($headers->version)) {
            if ($throw_exception) {
                throw new \Exception('browser_no_version_header');
            } else {
                return false;
            }
        }
        if (!preg_match($pattern, $headers->version)) {
            if ($throw_exception) {
                throw new \Exception('browser_invalid_version_header');
            } else {
                return false;
            }
        }
        if (!empty($version) && $headers->version < $version) {
            if ($throw_exception) {
                throw new \Exception('browser_old_version');
            } else {
                return false;
            }
        }

        return true;
    }

    public static function check_ip_header($throw_exception=true, $force_check=false) {
        if (self::is_header_check_disabled() && !$force_check) {
            return true;
        }

        $headers = self::http_headers();

        if (empty($headers->ip)) {
            if ($throw_exception) {
                throw new \Exception('browser_unknown_ip_header');
            } else {
                return false;
            }
        }
        if (filter_var($headers->ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false) {
            if ($throw_exception) {
                throw new \Exception('browser_invalid_ip_header');
            } else {
                return false;
            }
        }

        return true;
    }

    public static function check_network_header($throw_exception=true, $force_check=false) {
        if (self::is_header_check_disabled() && !$force_check) {
            return true;
        }

        $netmask_octet_pattern = "[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5]";
        $netmask_pattern = "({$netmask_octet_pattern})(\.({$netmask_octet_pattern})){3}";
        $pattern = "/^{$netmask_pattern}\/[1-9][0-9]?$/";

        $headers = self::http_headers();

        if (empty($headers->network)) {
            if ($throw_exception) {
                throw new \Exception('browser_unknown_network_header');
            } else {
                return false;
            }
        }
        if (!preg_match($pattern, $headers->network)) {
            if ($throw_exception) {
                throw new \Exception('browser_invalid_network_header');
            } else {
                return false;
            }
        }

        return true;
    }

    public static function check_ip_range_student($throw_exception=true) {
        return self::check_ip_range(self::config('ip_ranges_students'), $throw_exception);
    }

    public static function check_ip_range_editor($throw_exception=true) {
        return self::check_ip_range(self::config('ip_ranges_editors'), $throw_exception);
    }

    private static function check_ip_range($str_ranges='', $throw_exception=true) {
        $str_ranges = trim($str_ranges);
        $ranges = explode(';', $str_ranges);
        if (!empty($str_ranges) && !empty($ranges)) {
            foreach ($ranges AS $range) {
                if (IPTools::ip_in_range(self::remote_addr(), trim($range))) {
                    return true;
                }
            }
            if ($throw_exception) {
                throw new \Exception('out_of_ip_ranges');
            } else {
                return false;
            }
        }

        return true;
    }

    public static function check_client_host($access_key, $throw_exception=true) {
        global $DB;

        if (self::is_header_check_disabled()) {
            return true;
        }

        $timeout = self::config('client_host_timeout');

        if (!empty($access_key->verify_client_host) && !empty($timeout)) {
            $remote_addr = self::remote_addr();
            $headers = self::http_headers();

            $params = array('ip'=>$headers->ip, 'network'=>$headers->network, 'real_ip'=>$remote_addr);
            if (!$client = $DB->get_record('exam_client_hosts', $params)) {
                if ($throw_exception) {
                    throw new \Exception('unknown_client_host');
                } else {
                    return false;
                }
            }
            if ($client->timemodified + $timeout * 60 < time()) {
                if ($throw_exception) {
                    throw new \Exception('client_host_timedout');
                } else {
                    return false;
                }
            }

            if ($access_key->ip != $client->real_ip && !self::ipCIDRCheck($access_key->ip, $client->network)) {
                if ($throw_exception) {
                    throw new \Exception('client_host_out_of_subnet');
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    // Check if $ip belongs to $cidr
    public static function ipCIDRCheck($ip, $cidr='0.0.0.0/24') {
        list ($net, $mask) = explode("/", $cidr);

        $ip_net = ip2long ($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long ($ip);
        $ip_ip_net = $ip_ip & $ip_mask;
        return ($ip_ip_net == $ip_net);
    }

    private static function error($error_code, $param=null) {
        global $SESSION;

        $SESSION->exam->messages['errors'][$error_code] = get_string($error_code, 'local_exam_authorization', $param);
    }

    private static function warning($warning_code, $param=null) {
        global $SESSION;

        $SESSION->exam->messages['warnings'][$warning_code] = get_string($warning_code, 'local_exam_authorization', $param);
    }

    public static function print_error($errorcode, $print_error=true) {
        if ($print_error) {
            $user = guest_user();
            \core\session\manager::set_user($user);
            redirect(new \moodle_url('/local/exam_authorization/print_error.php', array('errorcode' => $errorcode)));
        } else {
            return false;
        }
    }
}
