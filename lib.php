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
 * This file contains functions used by the completion search report.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This function extends the navigation with the report items.
 *
 * Mirrors the logic used by report_progress_extend_navigation_course(), but the
 * navigation node is only shown when completion is enabled for the course and the
 * course has either trackable activities or completion criteria, since this report
 * can display both the activity completion and the course completion views.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object for the report.
 * @param stdClass $context The context of the course.
 * @return void
 */
function report_completionsearch_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    $showonnavigation = has_capability('report/completionsearch:view', $context);
    $group = groups_get_course_group($course, true); // Supposed to verify group.
    if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
        $showonnavigation = ($showonnavigation && has_capability('moodle/site:accessallgroups', $context));
    }

    $completion = new completion_info($course);
    $showonnavigation = ($showonnavigation && $completion->is_enabled() &&
        ($completion->has_activities() || $completion->has_criteria()));

    if ($showonnavigation) {
        $url = new moodle_url('/report/completionsearch/index.php', ['course' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'report_completionsearch'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Return a list of page types.
 *
 * @param string $pagetype Current page type.
 * @param stdClass $parentcontext Block's parent context.
 * @param stdClass $currentcontext Current context of block.
 * @return array
 */
function report_completionsearch_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = [
        '*' => get_string('page-x', 'pagetype'),
        'report-*' => get_string('page-report-x', 'pagetype'),
        'report-completionsearch-*' => get_string('pluginname', 'report_completionsearch'),
        'report-completionsearch-index' => get_string('pluginname', 'report_completionsearch'),
    ];
    return $array;
}
