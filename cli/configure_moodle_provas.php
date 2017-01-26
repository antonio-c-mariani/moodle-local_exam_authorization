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
 * Um script que realiza a configuração inicial.
 *
 * @package    local_exam_authorization
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');

$plugin_manager = core_plugin_manager::instance();

$installed = $plugin_manager->get_installed_plugins('local');
if (!isset($installed['exam_authorization'])) {
    echo "\n=> Plugin 'local/exam_authorization' is not installed.\n";
    exit;
}

$installed = $plugin_manager->get_installed_plugins('block');
if (!isset($installed['exam_actions'])) {
    echo "\n=> Plugin 'block/exam_actions' is not installed.\n";
    exit;
}

$installed = $plugin_manager->get_installed_plugins('auth');
if (!isset($installed['exam'])) {
    echo "\n=> Plugin 'auth/exam' is not installed.\n";
    exit;
}

$systemcontext = context_system::instance();

echo "\n=> Creating roles:\n";
if ($roleid = $DB->get_field('role', 'id', array('shortname' => 'supervisor'))) {
    echo "      - role 'supervisor' already exists\n";
} else {
    $roleid = create_role('Responsible for applying exams', 'supervisor', 'Responsible for applying exams');
    set_role_contextlevels($roleid, array(CONTEXT_COURSE));
    echo "      - created role 'supervisor'\n";
}
assign_capability('local/exam_authorization:supervise_exam', CAP_ALLOW, $roleid, $systemcontext->id);
assign_capability('moodle/course:managegroups', CAP_ALLOW, $roleid, $systemcontext->id);
set_config('supervisor_roleid', $roleid, 'local_exam_authorization');

if ($roleid = $DB->get_field('role', 'id', array('shortname' => 'monitor'))) {
    echo "      - role 'monitor' already exists\n";
} else {
    $roleid = create_role('Monitor the application of exams', 'monitor', 'Monitor the application of exams');
    set_role_contextlevels($roleid, array(CONTEXT_COURSE));
    echo "      - created role 'monitor'\n";
}
assign_capability('local/exam_authorization:monitor_exam', CAP_ALLOW, $roleid, $systemcontext->id);
set_config('monitor_roleid', $roleid, 'local_exam_authorization');

echo "\n=> Removing permitions to assign, override and switch roles from 'editingteacher' and 'teacher' roles\n";
if ($roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'))) {
    $DB->delete_records('role_allow_assign',   array('roleid' => $roleid));
    $DB->delete_records('role_allow_override', array('roleid' => $roleid));
    $DB->delete_records('role_allow_switch',   array('roleid' => $roleid));
    assign_capability('local/exam_authorization:write_exam', CAP_ALLOW, $roleid, $systemcontext->id);
    assign_capability('moodle/backup:backupactivity', CAP_ALLOW, $roleid, $systemcontext->id);
}
if ($roleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'))) {
    $DB->delete_records('role_allow_assign',   array('roleid' => $roleid));
    $DB->delete_records('role_allow_override', array('roleid' => $roleid));
    $DB->delete_records('role_allow_switch',   array('roleid' => $roleid));
}
if ($roleid = $DB->get_field('role', 'id', array('shortname' => 'student'))) {
    assign_capability('local/exam_authorization:take_exam', CAP_ALLOW, $roleid, $systemcontext->id);
}

$del_caps = array('student'=>array(
                          'moodle/blog:manageexternal',
                          'moodle/blog:search',
                          'moodle/blog:view',
                          'moodle/user:readuserblogs',
                          'moodle/user:readuserposts',
                          'enrol/self:unenrolself',
                          'gradereport/overview:view',
                          'gradereport/user:view',
                          'moodle/comment:post',
                          'moodle/comment:view',
                          'moodle/course:isincompletionreports',
                          'moodle/course:viewscales',
                          'moodle/portfolio:export',
                          'moodle/course:viewparticipants',
                          'moodle/rating:viewany',
                          'moodle/user:viewdetails',
                          'moodle/search:query',
                          'moodle/competency:coursecompetencygradable',
                          'mod/assign:exportownsubmission',
                          'mod/chat:chat',
                          'mod/chat:readlog',
                          'mod/forum:allowforcesubscribe',
                          'mod/forum:createattachment',
                          'mod/forum:deleteownpost',
                          'mod/forum:exportownpost',
                          'mod/forum:replypost',
                          'mod/forum:startdiscussion',
                          'mod/forum:viewdiscussion',
                          'mod/forum:viewrating',
                          'mod/wiki:createpage',
                          'mod/wiki:editcomment',
                          'mod/wiki:editpage',
                          'mod/wiki:viewcomment',
                          'block/online_users:viewlist',
                          ),
                  'user'=>array(
                          'block/admin_bookmarks:myaddinstance',
                          'block/badges:myaddinstance',
                          'block/calendar_month:myaddinstance',
                          'block/calendar_upcoming:myaddinstance',
                          'block/comments:myaddinstance',
                          'block/community:myaddinstance',
                          'block/glossary_random:myaddinstance',
                          'block/mentees:myaddinstance',
                          'block/messages:myaddinstance',
                          'block/mnet_hosts:myaddinstance',
                          'block/myprofile:myaddinstance',
                          'block/navigation:myaddinstance',
                          'block/news_items:myaddinstance',
                          'block/online_users:myaddinstance',
                          'block/private_files:myaddinstance',
                          'block/rss_client:myaddinstance',
                          'block/settings:myaddinstance',
                          'block/tags:myaddinstance',
                          'moodle/blog:create',
                          'moodle/blog:manageexternal',
                          'moodle/blog:search',
                          'moodle/blog:view',
                          'moodle/course:request',
                          'moodle/portfolio:export',
                          'moodle/site:sendmessage',
                          'moodle/tag:create',
                          'moodle/tag:edit',
                          'moodle/tag:flag',
                          'moodle/user:changeownpassword',
                          'moodle/user:editownmessageprofile',
                          'moodle/user:editownprofile',
                          'moodle/user:manageownblocks',
                          'moodle/user:manageownfiles',
                          'moodle/webservice:createmobiletoken',
                          'moodle/badges:manageownbadges',
                          'moodle/badges:viewotherbadges',
                          'moodle/badges:earnbadge',
                          'moodle/badges:viewbadges',
                          'moodle/calendar:manageownentries',
                          'moodle/comment:post',
                          'moodle/comment:view',
                          'moodle/rating:rate',
                          'moodle/rating:view',
                          'moodle/rating:viewall',
                          'moodle/rating:viewany',
                          'repository/dropbox:view',
                          'repository/equella:view',
                          'repository/alfresco:view',
                          'repository/flickr:view',
                          'repository/flickr_public:view',
                          'repository/googledocs:view',
                          'repository/merlot:view',
                          'repository/picasa:view',
                          'repository/s3:view',
                          'repository/skydrive:view',
                          'repository/url:view',
                          'repository/wikimedia:view',
                          'repository/youtube:view',
                          'block/online_users:viewlist',
                          ),
                  'editingteacher'=>array(
                          'moodle/blog:manageentries',
                          'moodle/blog:manageexternal',
                          'moodle/blog:search',
                          'moodle/blog:view',
                          'moodle/community:add',
                          'moodle/community:download',
                          'moodle/portfolio:export',
                          'moodle/site:doclinks',
                          'moodle/site:readallmessages',
                          'moodle/tag:editblocks',
                          'moodle/tag:manage',
                          'moodle/user:readuserblogs',
                          'moodle/user:readuserposts',
                          'enrol/cohort:config',
                          'enrol/guest:config',
                          'enrol/manual:enrol',
                          'enrol/manual:manage',
                          'enrol/manual:unenrol',
                          'enrol/meta:config',
                          'enrol/paypal:manage',
                          'enrol/self:config',
                          'enrol/self:manage',
                          'enrol/self:unenrol',
                          'gradeexport/ods:view',
                          'gradeexport/txt:view',
                          'gradeexport/xls:view',
                          'gradeexport/xml:view',
                          'gradeimport/csv:view',
                          'mod/assignment:addinstance',
                          'mod/chat:addinstance',
                          'mod/forum:addinstance',
                          'mod/wiki:addinstance',
                          'moodle/badges:awardbadge',
                          'moodle/badges:configurecriteria',
                          'moodle/badges:configuredetails',
                          'moodle/badges:configuremessages',
                          'moodle/badges:createbadge',
                          'moodle/badges:deletebadge',
                          'moodle/badges:viewawarded',
                          'moodle/calendar:managegroupentries',
                          'moodle/cohort:view',
                          'moodle/comment:delete',
                          'moodle/comment:post',
                          'moodle/comment:view',
                          'moodle/course:bulkmessaging',
                          'moodle/course:changecategory',
                          'moodle/course:changefullname',
                          'moodle/course:changeidnumber',
                          'moodle/course:changeshortname',
                          'moodle/course:changesummary',
                          'moodle/course:enrolconfig',
                          'moodle/course:markcomplete',
                          'moodle/course:useremail',
                          'moodle/grade:import',
                          'moodle/grade:lock',
                          'moodle/notes:manage',
                          'moodle/notes:view',
                          'moodle/role:assign',
                          'moodle/role:review',
                          'moodle/role:safeoverride',
                          'moodle/role:switchroles',
                          'mod/chat:chat',
                          'mod/chat:deletelog',
                          'mod/chat:readlog',
                          'mod/chat:exportparticipatedsession',
                          'mod/chat:exportsession',
                          'mod/forum:addnews',
                          'mod/forum:addquestion',
                          'mod/forum:allowforcesubscribe',
                          'mod/forum:canposttomygroups',
                          'mod/forum:createattachment',
                          'mod/forum:deleteanypost',
                          'mod/forum:deleteownpost',
                          'mod/forum:editanypost',
                          'mod/forum:exportdiscussion',
                          'mod/forum:exportownpost',
                          'mod/forum:exportpost',
                          'mod/forum:managesubscriptions',
                          'mod/forum:movediscussions',
                          'mod/forum:pindiscussions',
                          'mod/forum:postwithoutthrottling',
                          'mod/forum:rate',
                          'mod/forum:replynews',
                          'mod/forum:replypost',
                          'block/search_forums:addinstance',
                          'mod/forum:splitdiscussions',
                          'mod/forum:startdiscussion',
                          'mod/forum:viewallratings',
                          'mod/forum:viewanyrating',
                          'mod/forum:viewdiscussion',
                          'mod/forum:viewhiddentimedposts',
                          'mod/forum:viewsubscribers',
                          'mod/forum:viewqandawithoutposting',
                          'mod/forum:viewrating',
                          'mod/wiki:createpage',
                          'mod/wiki:editcomment',
                          'mod/wiki:editpage',
                          'mod/wiki:managecomment',
                          'mod/wiki:managefiles',
                          'mod/wiki:managewiki',
                          'mod/wiki:overridelock',
                          'mod/wiki:viewcomment',
                          'mod/wiki:viewpage',
                          ),
            );
echo "\n=> Unassigning capabilities:\n";
foreach ($del_caps AS $role=>$caps) {
    echo "      - from: {$role}\n";
    if ($roleid = $DB->get_field('role', 'id', array('shortname' => $role))) {
        foreach ($caps AS $cap) {
            unassign_capability($cap, $roleid);
        }
    }
}

$configs = array(
                 array('enrol_plugins_enabled', 'manual'),

                 array('defaulthomepage', 1),
                 array('navshowfrontpagemods', false),
                 array('navadduserpostslinks', false),
                 array('enablewebservices', true),
                 array('forcelogin', false),
                 array('forceloginforprofiles', true),
                 array('forceloginforprofileimage', true),
                 array('profilesforenrolledusersonly', true),
                 array('cronclionly', true),
                 array('disableuserimages', true),
                 array('navsortmycoursessort', 'shortname'),
                 array('showuseridentity', ''),

                 array('enablegravatar', false),
                 array('allowattachments', false),
                 array('enableoutcomes', false),
                 array('enablecourserequests', false),
                 array('usecomments', false),
                 array('usetags', false),
                 array('enablenotes', false),
                 array('enableportfolios', false),
                 array('messaging', false),
                 array('enablestats', false),
                 array('enablerssfeeds', false),
                 array('enableblogs', false),
                 array('enablecompletion', false),
                 array('enableavailability', false),
                 array('enableplagiarism', false),
                 array('enablebadges', false),
                 array('opentogoogle', false),
                 array('gradepublishing', false),
                 array('registerauth', false),
                 array('guestloginbutton', false),
                 array('allowuserblockhiding', false),
                 array('enabledevicedetection', false),
                 array('allowguestmymoodle', false),
                 array('navshowcategories', false),
                 array('autologinguests', false),

                 array('grade_displaytype', 1),
                 array('grade_decimalpoints', 1),

                 array('backup_general_comments', false, 'backup'),
                 array('backup_general_blocks', false, 'backup'),
                 array('backup_general_filters', false, 'backup'),
                 array('backup_general_badges', false, 'backup'),
                 array('backup_auto_badges', false, 'backup'),
                 array('backup_general_users', true, 'backup'),
                 array('backup_general_anonymize', false, 'backup'),
                 array('backup_general_userscompletion', false, 'backup'),
                 array('backup_general_logs', false, 'backup'),
                 array('backup_general_histories', false, 'backup'),
                 array('visible', false, 'moodlecourse'),
                 array('format', 'topics', 'moodlecourse'),
                 array('numsections', 2, 'moodlecourse'),
                 array('hiddensections', 1, 'moodlecourse'),
                 array('showgrades', 0, 'moodlecourse'),
                 array('showreports', 0, 'moodlecourse'),
                 array('enablecompletion', 0, 'moodlecourse'),
                 array('enablecompletion', 0, 'moodlecourse'),

                 array('enabled', 0, 'core_competency'),
                 array('pushcourseratingstouserplans', 0, 'core_competency'),

                 array('field_lock_firstname', 'locked', 'auth/manual'),
                 array('field_lock_lastname', 'locked', 'auth/manual'),
                 array('field_lock_email', 'locked', 'auth/manual'),
                 array('field_lock_idnumber', 'locked', 'auth/manual'),

                );

$modules = array('chat',
                 'feedback',
                 'imscp',
                 'lti',
                 'wiki',
                );

$blocks = array(
                'activity_modules',
                'admin_bookmarks',
                'badges',
                'blog_menu',
                'blog_recent',
                'blog_tags',
                'calendar_month',
                'calendar_upcoming',
                'comments',
                'community',
                'completionstatus',
                'course_overview',
                'course_summary',
                'feedback',
                'glossary_random',
                'mentees',
                'messages',
                'myprofile',
                'mnet_hosts',
                'news_items',
                'private_files',
                'quiz_results',
                'recent_activity',
                'rss_client',
                'search_forums',
                'section_links',
                'selfcompletion',
                'social_activities',
                'tag_flickr',
                'tag_youtube',
                'tags',
                );

echo "\n=> disabling message processors\n";
$DB->set_field('message_processors', 'enabled', '0');      // Disable output

echo "\n=> hidding some modules:\n";
foreach ($modules AS $mod_name) {
    echo "      - {$mod_name}\n";
    if ($module = $DB->get_record("modules", array("name"=>$mod_name))) {
        $DB->set_field("modules", "visible", "0", array("id"=>$module->id));

        $sql = "UPDATE {course_modules}
                   SET visibleold=visible, visible=0
                 WHERE module=?";
        $DB->execute($sql, array($module->id));
    }
}

echo "\n=> hidding some blocks:\n";
foreach ($blocks AS $blk_name) {
    echo "      - {$blk_name}\n";
    if ($block = $DB->get_record('block', array('name' => $blk_name))) {
        $DB->set_field('block', 'visible', '0', array('id' => $block->id));
    }
}

echo "\n=> changing some global settings:\n";
foreach ($configs AS $cfg) {
    if (count($cfg) == 2) {
        set_config($cfg[0], $cfg[1]);
    } else {
        set_config($cfg[0], $cfg[1], $cfg[2]);
    }
}

$auth = get_config('moodle', 'auth');
$exam = false;
if (empty($auth)) {
    if (exists_auth_plugin('exam')) {
        $auth = 'exam';
        set_config('auth', $auth);
        echo "\n=> Authentication plugin: '{$auth}' was enabled.\n";
        $exam = true;
    } else {
        echo "\n=> 'Moodle Exam' authentication plugin is not avaliable. Keeped 'manual' authentication as the only one option.\n";
        set_config('auth_plugin', 'manual', 'local_exam_authorization');
    }
} else {
    echo "\n=> Authentication plugin is already seted to: '{$auth}'\n";
    if ($auth == 'exam') {
        echo "\n=> Authentication plugin: '{$auth}' is enabled.\n";
        $exam = true;
    }
}

if ($exam) {
    set_config('authpreventaccountcreation', 0);
    set_config('auth_plugin', $auth, 'local_exam_authorization');
    echo "\n=> Config 'authpreventaccountcreation' was changed to 'false' to allow automatic creation of users in the Moodle Exam after they autenticate themselves.\n";
}
echo "\n=> end\n";
