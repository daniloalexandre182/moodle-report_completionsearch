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
 * Activity completion report (port of report/progress/index.php) filtered by the user search.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

use html_writer;
use moodle_url;
use report_progress\local\helper;

/**
 * Activity completion report.
 *
 * Port of the core report/progress/index.php script (Moodle 5.2) where the first/last name initials bar
 * has been replaced by {@see user_filter}. The markup, the section and group selectors, the dataformat
 * downloads and the manual completion override (AMD module report_progress/completion_override) are kept
 * identical to core.
 *
 * Supported options (constructor): 'sort', 'dataformat', 'page', 'activityinclude', 'activityorder',
 * 'activitysection'.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_report extends report_base {
    /** @var int Current page (0 based). */
    protected $page;

    /** @var string Activity type to include ('all' or a module name). */
    protected $activityinclude;

    /** @var string Activity order ('orderincourse' or 'alphabetical'). */
    protected $activityorder;

    /** @var int Section number the activities are filtered by (-1 for every section). */
    protected $activitysection;

    /** @var string Requested download dataformat ('' for the HTML report). */
    protected $dataformat;

    /** @var array Available activity types, set by load(). */
    protected $activitytypes = [];

    /** @var \cm_info[] Activities to show, set by load(). */
    protected $activities = [];

    /** @var string[] Identity fields shown for each user. */
    protected $extrafields;

    /** @var \stdClass[] Cache of the users who overrode completion states, indexed by id. */
    protected $overrideusers = [];

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param \context_course $context The course context.
     * @param user_filter $filter The user filter.
     * @param moodle_url $filterurl URL of the report page holding the filter parameters.
     * @param int $groupid The active group (as returned by groups_get_course_group()).
     * @param array $options 'sort', 'dataformat', 'page', 'activityinclude', 'activityorder', 'activitysection'.
     */
    public function __construct(
        \stdClass $course,
        \context_course $context,
        user_filter $filter,
        moodle_url $filterurl,
        int $groupid,
        array $options = []
    ) {
        parent::__construct($course, $context, $filter, $filterurl, $groupid, $options);
        $this->page = max(0, (int) ($options['page'] ?? 0));
        $this->activityinclude = (string) ($options['activityinclude'] ?? 'all');
        $this->activityorder = (string) ($options['activityorder'] ?? 'orderincourse');
        $this->activitysection = (int) ($options['activitysection'] ?? -1);
        $this->dataformat = (string) ($options['dataformat'] ?? '');

        // Whether to show extra user identity information.
        $userfields = \core_user\fields::for_identity($context);
        $this->extrafields = $userfields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
    }

    /**
     * Returns the report type key.
     *
     * @return string
     */
    public function get_type(): string {
        return self::TYPE_PROGRESS;
    }

    /**
     * Returns the URL of the current page, built like core report_progress does.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        $url = new moodle_url($this->filterurl);
        if ($this->sort !== '') {
            $url->param('sort', $this->sort);
        }
        if ($this->dataformat !== '') {
            $url->param('dataformat', $this->dataformat);
        }
        if ($this->page !== 0) {
            $url->param('page', $this->page);
        }
        if ($this->groupid !== 0) {
            $url->param('group', $this->groupid);
        }
        if ($this->activityinclude !== '') {
            $url->param('activityinclude', $this->activityinclude);
        }
        if ($this->activityorder !== '') {
            $url->param('activityorder', $this->activityorder);
        }
        $url->param('activitysection', $this->activitysection);
        return $url;
    }

    /**
     * Returns the page title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('activitycompletion', 'completion');
    }

    /**
     * Loads the activities and the progress of the users matching the filter.
     *
     * @return void
     */
    public function load(): void {
        // Get data on activities and progress of all users.
        $this->completion = new \completion_info($this->course);
        [$this->activitytypes, $this->activities] = helper::get_activities_to_show(
            $this->completion,
            $this->activityinclude,
            $this->activityorder,
            $this->activitysection
        );

        $this->load_totals();

        // Get user data.
        $this->progress = [];
        if ($this->total) {
            [$where, $params] = $this->filter->get_sql();
            $this->progress = $this->completion->get_progress_all(
                $where,
                $params,
                $this->groupid,
                $this->sort === 'firstname' ? 'u.firstname ASC, u.lastname ASC' : 'u.lastname ASC, u.firstname ASC',
                $this->dataformat !== '' ? 0 : helper::COMPLETION_REPORT_PAGE,
                $this->dataformat !== '' ? 0 : $this->page * helper::COMPLETION_REPORT_PAGE,
                $this->context
            );
        }
        $this->loaded = true;
    }

    /**
     * Whether an export was requested and there are users and activities to export (as core).
     *
     * @return bool
     */
    public function is_export(): bool {
        $this->require_loaded();
        return $this->dataformat !== '' && $this->grandtotal && count($this->activities) > 0;
    }

    /**
     * Sends the file in the requested dataformat through \core\dataformat and exits, exactly as core does.
     *
     * @return void
     */
    public function export(): void {
        $this->require_loaded();
        if (!$this->is_export()) {
            throw new \coding_exception('Nothing to export.');
        }

        $columnnames = [''];
        foreach ($this->extrafields as $field) {
            $columnnames[] = \core_user\fields::get_display_name($field);
        }
        foreach ($this->activities as $activity) {
            if ($activity->completionexpected) {
                $datetext = userdate($activity->completionexpected, "%F %T");
            } else {
                $datetext = '';
            }
            $columnnames[] = format_string($activity->name, true, ['context' => $activity->context]);
            $columnnames[] = $datetext;
        }

        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);
        $alldata = [];
        foreach ($this->progress as $user) {
            $row = [];
            $row['fullname'] = fullname($user, $viewfullnames);
            foreach ($this->extrafields as $field) {
                $row[$field] = $user->{$field};
            }
            $i = 0;
            foreach ($this->activities as $activity) {
                $cell = $this->get_cell_state($user, $activity);
                $row[$i++] = $cell->describe;
                $row[$i++] = $cell->progress ? userdate($cell->progress->timemodified, "%F %T") : '';
            }
            $alldata[] = $row;
        }

        $extrafields = $this->extrafields;
        \core\dataformat::download_data(
            'progress.' . $this->get_export_shortname(),
            $this->dataformat,
            $columnnames,
            $alldata,
            function (array $record, bool $supportshtml) use ($extrafields): array {
                if ($supportshtml) {
                    foreach ($extrafields as $extrafield) {
                        $record[$extrafield] = s($record[$extrafield]);
                    }
                }
                return $record;
            },
        );
        die;
    }

    /**
     * Returns the sections that hold at least one activity with completion tracking, as core does.
     *
     * @return array section number => section name
     */
    protected function get_section_options(): array {
        $modinfo = get_fast_modinfo($this->course);
        $cmids = array_keys($this->completion->get_activities());
        $sections = [];
        foreach ($modinfo->get_sections() as $sectionnum => $section) {
            if (empty(array_intersect($section, $cmids))) {
                continue;
            }
            $sectionname = get_section_name($this->course, $sectionnum);
            if (empty($sectionname)) {
                $sectionname = get_string('section') . ' ' . $sectionnum;
            }
            $sections[$sectionnum] = $sectionname;
        }
        return $sections;
    }

    /**
     * Works out the completion state of a user in an activity, as core does.
     *
     * @param \stdClass $user User record with the 'progress' array.
     * @param \cm_info $activity The activity.
     * @return \stdClass with state, overrideby, date, progress (record or null), completiontype,
     *                   completiontracking, completionicon and describe.
     */
    protected function get_cell_state(\stdClass $user, \cm_info $activity): \stdClass {
        // Get progress information and state.
        if (array_key_exists($activity->id, $user->progress)) {
            $thisprogress = $user->progress[$activity->id];
            $state = $thisprogress->completionstate;
            $overrideby = $thisprogress->overrideby;
            $date = userdate($thisprogress->timemodified);
        } else {
            $thisprogress = null;
            $state = COMPLETION_INCOMPLETE;
            $overrideby = 0;
            $date = '';
        }

        // Work out how it corresponds to an icon.
        $completiontype = '';
        switch ($state) {
            case COMPLETION_INCOMPLETE:
                $completiontype = 'n' . ($overrideby ? '-override' : '');
                break;
            case COMPLETION_COMPLETE:
                $completiontype = 'y' . ($overrideby ? '-override' : '');
                break;
            case COMPLETION_COMPLETE_PASS:
                $completiontype = 'pass';
                break;
            case COMPLETION_COMPLETE_FAIL:
                $completiontype = 'fail';
                break;
        }
        $completiontracking = $activity->completion == COMPLETION_TRACKING_AUTOMATIC ? 'auto' : 'manual';

        if ($overrideby) {
            if (!isset($this->overrideusers[$overrideby])) {
                $this->overrideusers[$overrideby] = \core_user::get_user($overrideby, '*', MUST_EXIST);
            }
            $describe = get_string('completion-' . $completiontype, 'completion', fullname($this->overrideusers[$overrideby]));
        } else {
            $describe = get_string('completion-' . $completiontype, 'completion');
        }

        return (object) [
            'state' => $state,
            'overrideby' => $overrideby,
            'date' => $date,
            'progress' => $thisprogress,
            'completiontype' => $completiontype,
            'completiontracking' => $completiontracking,
            'completionicon' => 'completion-' . $completiontracking . '-' . $completiontype,
            'describe' => $describe,
        ];
    }

    /**
     * Returns the HTML of the report (selectors, paging bar, table and download links).
     *
     * @return string
     */
    public function output(): string {
        global $OUTPUT, $PAGE, $USER;

        $this->require_loaded();

        $url = $this->get_url();
        /** @var \report_progress\output\renderer $output */
        $output = $PAGE->get_renderer('report_progress');

        $html = '';
        $PAGE->requires->js_call_amd('report_progress/completion_override', 'init', [fullname($USER)]);

        // Handle groups (if enabled).
        $html .= $output->render_groups_select($url, $this->course, $this->groupid);

        // Display include activity filter.
        $html .= $output->render_include_activity_select($url, $this->activitytypes, $this->activityinclude);

        // Display activity order options.
        $html .= $output->render_activity_order_select($url, $this->activityorder);

        // Display section selector.
        $html .= $output->render_activity_section_select($url, (string) $this->activitysection, $this->get_section_options());

        if (count($this->activities) == 0) {
            $html .= $OUTPUT->container(get_string('err_noactivities', 'completion'), 'errorbox errorboxcontent');
            return $html;
        }

        // If no users in this course what-so-ever.
        if (!$this->grandtotal) {
            $html .= $OUTPUT->container(get_string('err_nousers', 'completion'), 'errorbox errorboxcontent');
            return $html;
        }

        // The initials bar of core is replaced by the search form, so only the paging bar is left.
        $pagingbar = $OUTPUT->paging_bar($this->total, $this->page, helper::COMPLETION_REPORT_PAGE, $url);

        $html .= '<br class="clearer"/>';
        $html .= $pagingbar;

        if (!$this->total) {
            $html .= $OUTPUT->notification(get_string('nothingtodisplay'), 'info', false);
            return $html;
        }

        $html .= $this->output_table($url);

        // Add buttons for exporting report.
        $html .= $OUTPUT->download_dataformat_selector(
            get_string('downloadas', 'table'),
            $url->out_omit_querystring(),
            'dataformat',
            $url->params()
        );

        return $html;
    }

    /**
     * Returns the HTML of the progress table.
     *
     * @param moodle_url $url The current page URL.
     * @return string
     */
    protected function output_table(moodle_url $url): string {
        global $OUTPUT;

        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);
        $canoverride = has_capability('moodle/course:overridecompletion', $this->context);

        $html = '<div id="completion-progress-wrapper" class="no-overflow">';
        $html .= '<table id="completion-progress" class="table generaltable flexible table-striped table-hover">' .
            '<thead><tr style="vertical-align:top">';

        // User heading / sort option.
        $html .= '<th scope="col" class="completion-sortchoice">';
        $sorturl = new moodle_url($url);
        if ($this->sort === 'firstname') {
            $sorturl->param('sort', 'lastname');
            $sortlink = html_writer::link($sorturl, get_string('lastname'));
            $html .= get_string('firstname') . " / $sortlink";
        } else {
            $sorturl->param('sort', 'firstname');
            $sortlink = html_writer::link($sorturl, get_string('firstname'));
            $html .= "$sortlink / " . get_string('lastname');
        }
        $html .= '</th>';

        // Print user identity columns.
        foreach ($this->extrafields as $field) {
            $html .= '<th scope="col" class="completion-identifyfield">' .
                \core_user\fields::get_display_name($field) . '</th>';
        }

        // Activities.
        $formattedactivities = [];
        foreach ($this->activities as $activity) {
            $datepassed = $activity->completionexpected && $activity->completionexpected <= time();
            $datepassedclass = $datepassed ? 'completion-expired' : '';

            if ($activity->completionexpected) {
                $datetext = userdate($activity->completionexpected, get_string('strftimedate', 'langconfig'));
            } else {
                $datetext = '';
            }

            // Some names (labels) come URL-encoded and can be very long, so shorten them.
            $displayname = format_string($activity->name, true, ['context' => $activity->context]);
            $shortenedname = shorten_text($displayname);
            $activityurl = new moodle_url('/mod/' . $activity->modname . '/view.php', ['id' => $activity->id]);

            $html .= '<th scope="col" class="completion-header ' . $datepassedclass . '">' .
                '<a href="' . $activityurl->out() . '" title="' . s($displayname) . '">' .
                '<div class="rotated-text-container"><span class="rotated-text">' . $shortenedname . '</span></div>' .
                '<div class="modicon">' .
                $OUTPUT->image_icon('monologo', get_string('modulename', $activity->modname), $activity->modname) .
                '</div>' .
                '</a>';
            if ($activity->completionexpected) {
                $html .= '<div class="completion-expected"><span>' . $datetext . '</span></div>';
            }
            $html .= '</th>';

            $formattedactivities[$activity->id] = (object) [
                'datepassedclass' => $datepassedclass,
                'displayname' => $displayname,
            ];
        }

        $html .= '</tr></thead><tbody>';

        // Row for each user.
        foreach ($this->progress as $user) {
            $userurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $this->course->id]);
            $userfullname = fullname($user, $viewfullnames);
            $html .= '<tr><th scope="row"><a href="' . $userurl->out() . '">' . $userfullname . '</a></th>';
            foreach ($this->extrafields as $field) {
                $html .= '<td>' . s($user->{$field}) . '</td>';
            }

            // Progress for each activity.
            foreach ($this->activities as $activity) {
                $cell = $this->get_cell_state($user, $activity);

                $a = new \stdClass();
                $a->state = $cell->describe;
                $a->date = $cell->date;
                $a->user = $userfullname;
                $a->activity = $formattedactivities[$activity->id]->displayname;
                $fulldescribe = get_string('progress-title', 'completion', $a);

                $celltext = $OUTPUT->pix_icon('i/' . $cell->completionicon, s($fulldescribe));
                if ($canoverride && $cell->state != COMPLETION_COMPLETE_PASS && $cell->state != COMPLETION_COMPLETE_FAIL) {
                    $newstate = ($cell->state == COMPLETION_COMPLETE) ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE;
                    $changecompl = $user->id . '-' . $activity->id . '-' . $newstate;
                    $changeurl = new moodle_url($url, ['sesskey' => sesskey()]);
                    $celltext = html_writer::link($changeurl, $celltext, [
                        'class' => 'changecompl',
                        'data-changecompl' => $changecompl,
                        'data-activityname' => $a->activity,
                        'data-userfullname' => $a->user,
                        'data-completiontracking' => $cell->completiontracking,
                        'role' => 'button',
                    ]);
                }
                $html .= '<td class="completion-progresscell ' . $formattedactivities[$activity->id]->datepassedclass . '">' .
                    $celltext . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }
}
