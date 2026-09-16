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
 * Base class of the reports rendered by the completion search report.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Common behaviour of the ported "Course completion" and "Activity completion" reports.
 *
 * Usage from index.php (after require_login() and the capability/group checks):
 * <code>
 *   $report = new progress_report($course, $context, $filter, $filterurl, $groupid, $options);
 *   $PAGE->set_url($report->get_url());   // Optionally done before require_login().
 *   $report->load();
 *   if ($report->is_export()) {
 *       $report->export();                // Sends headers + file and exits.
 *   }
 *   echo $OUTPUT->header();
 *   echo $report->output();
 *   echo $OUTPUT->footer();
 * </code>
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class report_base {
    /** Report type key of the course completion report. */
    public const TYPE_COMPLETION = 'completion';

    /** Report type key of the activity completion report. */
    public const TYPE_PROGRESS = 'progress';

    /** Number of users shown per page (same as core reports). */
    public const PAGE_SIZE = 25;

    /** @var \stdClass The course. */
    protected $course;

    /** @var \context_course The course context. */
    protected $context;

    /** @var user_filter The user filter. */
    protected $filter;

    /** @var \moodle_url URL holding the filter parameters (course, searchfield, search, report, run). */
    protected $filterurl;

    /** @var int The active group id (0 for all participants). */
    protected $groupid;

    /** @var string Requested sort ('' or 'firstname'/'lastname'). */
    protected $sort;

    /** @var string Requested export format ('', 'csv' or 'excelcsv'). */
    protected $format;

    /** @var bool Whether the Excel (UTF-16LE, tab separated) CSV was requested. */
    protected $excel;

    /** @var bool Whether any CSV export was requested. */
    protected $csv;

    /** @var \completion_info|null Completion info, set by load(). */
    protected $completion = null;

    /** @var int Number of users matching the filter, set by load(). */
    protected $total = 0;

    /** @var int Number of tracked users regardless of the filter, set by load(). */
    protected $grandtotal = 0;

    /** @var array Users with their progress, as returned by completion_info::get_progress_all(), set by load(). */
    protected $progress = [];

    /** @var bool Whether load() has been called. */
    protected $loaded = false;

    /**
     * Constructor. Does not touch the database: call {@see load()} once access has been checked.
     *
     * @param \stdClass $course The course record.
     * @param \context_course $context The course context.
     * @param user_filter $filter The user filter.
     * @param \moodle_url $filterurl URL of the report page holding the filter parameters.
     * @param int $groupid The active group (as returned by groups_get_course_group()).
     * @param array $options Report options: 'sort' (string), 'format' (string) plus report specific ones.
     */
    public function __construct(
        \stdClass $course,
        \context_course $context,
        user_filter $filter,
        \moodle_url $filterurl,
        int $groupid,
        array $options = []
    ) {
        $this->course = $course;
        $this->context = $context;
        $this->filter = $filter;
        $this->filterurl = new \moodle_url($filterurl);
        $this->groupid = $groupid;
        $this->sort = (string) ($options['sort'] ?? '');
        $this->format = (string) ($options['format'] ?? '');
        $this->excel = $this->format === 'excelcsv';
        $this->csv = $this->format === 'csv' || $this->excel;
    }

    /**
     * Returns the report type key (one of the TYPE_* constants).
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * Returns the URL of the current page (filter parameters plus the report specific ones).
     *
     * @return \moodle_url
     */
    abstract public function get_url(): \moodle_url;

    /**
     * Returns the page title, the same used by the matching core report.
     *
     * @return string
     */
    abstract public function get_title(): string;

    /**
     * Loads the report data (criteria/activities, user counts and progress).
     *
     * @return void
     * @throws \moodle_exception when the report cannot be displayed for the course
     */
    abstract public function load(): void;

    /**
     * Whether an export was requested and there is something to export.
     *
     * Must be called after {@see load()}. When false the HTML report must be printed instead.
     *
     * @return bool
     */
    abstract public function is_export(): bool;

    /**
     * Sends the CSV/Excel file to the browser and terminates the script.
     *
     * Must be called before any output ($OUTPUT->header()) has been sent.
     *
     * @return void
     */
    abstract public function export(): void;

    /**
     * Returns the HTML of the report, to be printed between $OUTPUT->header() and $OUTPUT->footer().
     *
     * @return string
     */
    abstract public function output(): string;

    /**
     * Returns the list of available report types as key => label.
     *
     * @return array
     */
    public static function get_type_options(): array {
        return [
            self::TYPE_COMPLETION => get_string('reporttype_completion', 'report_completionsearch'),
            self::TYPE_PROGRESS => get_string('reporttype_progress', 'report_completionsearch'),
        ];
    }

    /**
     * Returns the number of users matching the filter (after {@see load()}).
     *
     * @return int
     */
    public function get_total(): int {
        return $this->total;
    }

    /**
     * Returns the number of tracked users regardless of the filter (after {@see load()}).
     *
     * @return int
     */
    public function get_grand_total(): int {
        return $this->grandtotal;
    }

    /**
     * Returns a copy of the filter URL.
     *
     * @return \moodle_url
     */
    public function get_filter_url(): \moodle_url {
        return new \moodle_url($this->filterurl);
    }

    /**
     * Throws a coding exception when load() has not been called yet.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function require_loaded(): void {
        if (!$this->loaded) {
            throw new \coding_exception('The report data must be loaded before being used, call load() first.');
        }
    }

    /**
     * Counts the tracked users matching the filter and in total, like the core reports do.
     *
     * @return void
     */
    protected function load_totals(): void {
        [$where, $params] = $this->filter->get_sql();

        // Get user match count.
        $this->total = $this->completion->get_num_tracked_users($where, $params, $this->groupid);

        // Total user count.
        if ($where === '') {
            $this->grandtotal = $this->total;
        } else {
            $this->grandtotal = $this->completion->get_num_tracked_users('', [], $this->groupid);
        }
    }

    /**
     * Returns the sanitised course short name used in export file names, like core does.
     *
     * @return string
     */
    protected function get_export_shortname(): string {
        $shortname = format_string($this->course->shortname, true, ['context' => $this->context]);
        return preg_replace('/[^a-z0-9-]/', '_', \core_text::strtolower(strip_tags($shortname)));
    }
}
