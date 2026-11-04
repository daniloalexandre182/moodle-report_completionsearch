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

/**
 * Completion search report: course completion and activity completion reports filtered by a user search.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;
use report_completionsearch\form\filter_form;
use report_completionsearch\local\completion_report;
use report_completionsearch\local\progress_report;
use report_completionsearch\local\report_base;
use report_completionsearch\local\user_filter;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Get course.
$courseid = required_param('course', PARAM_INT);

// Filter.
$searchfield = optional_param('searchfield', user_filter::FIELD_FULLNAME, PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$reporttype = optional_param('report', report_base::TYPE_COMPLETION, PARAM_ALPHA);
$run = optional_param('run', false, PARAM_BOOL);

// Export, sorting and paging, as in core: the course completion report exports CSV files ('format') and pages
// with 'start', the activity completion report exports any dataformat ('dataformat') and pages with 'page'.
$format = optional_param('format', '', PARAM_ALPHA);
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);
$sort = optional_param('sort', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$start = optional_param('start', 0, PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$activityinclude = optional_param('activityinclude', 'all', PARAM_ALPHANUMEXT);
$activityorder = optional_param('activityorder', 'orderincourse', PARAM_ALPHA);
$activitysection = optional_param('activitysection', -1, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/report/completionsearch/index.php', ['course' => $course->id]));
$PAGE->set_pagelayout('report');

require_login($course);

// Check basic permission.
require_capability('report/completionsearch:view', $context);

// Normalise the report type (needed to pick the group the same way as the matching core report).
$reportoptions = report_base::get_type_options();
if (!array_key_exists($reporttype, $reportoptions)) {
    $reporttype = report_base::TYPE_COMPLETION;
}

// Get group mode. As the core activity completion report does in Moodle 5.1, any visible group can be picked
// through the 'group' parameter unless the course is in separate groups mode.
if ($reporttype === report_base::TYPE_PROGRESS && $course->groupmode != SEPARATEGROUPS) {
    // Unlike core, also make sure the group belongs to this course.
    $groupincourse = $groupid && ($grouprecord = groups_get_group($groupid)) && $grouprecord->courseid == $course->id;
    $group = ($groupincourse && groups_group_visible($groupid, $course)) ? $groupid : 0;
} else {
    $group = groups_get_course_group($course, true); // Supposed to verify group.
}
if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups', $context);
}

// Normalise the filter.
$fieldoptions = user_filter::get_field_options($context);
if (!array_key_exists($searchfield, $fieldoptions)) {
    $searchfield = user_filter::FIELD_FULLNAME;
}
$filter = new user_filter($searchfield, $search);

$filterurl = new moodle_url('/report/completionsearch/index.php', [
    'course' => $course->id,
    'searchfield' => $searchfield,
    'search' => $search,
    'report' => $reporttype,
    'run' => 1,
]);

$filterform = new filter_form(new moodle_url('/report/completionsearch/index.php'), [
    'courseid' => $course->id,
    'fieldoptions' => $fieldoptions,
    'reportoptions' => $reportoptions,
], 'get', '', ['id' => 'report-completionsearch-filter']);
$filterform->set_data([
    'course' => $course->id,
    'run' => 1,
    'searchfield' => $searchfield,
    'search' => $search,
    'report' => $reporttype,
]);

$pluginname = get_string('pluginname', 'report_completionsearch');
$PAGE->set_heading($course->fullname);

if (!$run) {
    // Nothing has been searched yet: only show the filter, do not load any user.
    $PAGE->set_title($pluginname);
    echo $OUTPUT->header();
    report_helper::print_report_selector($pluginname);
    $filterform->display();
    echo $OUTPUT->notification(get_string('submittoview', 'report_completionsearch'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$options = [
    'sort' => $sort,
];
if ($reporttype === report_base::TYPE_PROGRESS) {
    $options += [
        'dataformat' => $dataformat,
        'page' => $page,
        'activityinclude' => $activityinclude,
        'activityorder' => $activityorder,
        'activitysection' => $activitysection,
    ];
    $report = new progress_report($course, $context, $filter, $filterurl, $group, $options);
} else {
    $options['format'] = $format;
    $options['start'] = $start;
    $report = new completion_report($course, $context, $filter, $filterurl, $group, $options);
}
$PAGE->set_url($report->get_url());
$PAGE->navigation->override_active_url(new moodle_url('/report/completionsearch/index.php', ['course' => $course->id]));

// Load the report data (throws the same errors as core, e.g. no completion criteria set).
$report->load();

// Trigger a report viewed event.
\report_completionsearch\event\report_viewed::create([
    'context' => $context,
    'other' => ['report' => $report->get_type()],
])->trigger();

// CSV/Excel export: sends the file and exits before any HTML output.
if ($report->is_export()) {
    $report->export();
}

// Navigation and header.
$PAGE->set_title($report->get_title());
echo $OUTPUT->header();

// Print the selected dropdown.
report_helper::print_report_selector($pluginname);

// The search form replaces the first/last name initials bars of the core reports.
$filterform->display();

echo $report->output();

echo $OUTPUT->footer();
