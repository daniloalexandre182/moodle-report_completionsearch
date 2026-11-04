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
 * Course completion report (port of report/completion/index.php) filtered by the user search.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Course completion report.
 *
 * Port of the core report/completion/index.php script (Moodle 5.3) where the first/last name initials bar
 * has been replaced by {@see user_filter}. The markup, the export files and the "mark as complete" links
 * (role criteria) are kept identical to core.
 *
 * Supported options (constructor): 'sort', 'dataformat', 'start'.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_report extends report_base {
    /** Whether the criteria titles row is shown (COMPLETION_REPORT_COL_TITLES in core). */
    protected const COL_TITLES = true;

    /** @var int Offset of the first user shown. */
    protected $start;

    /** @var \completion_criteria[] Criteria in display order, set by load(). */
    protected $criteria = [];

    /** @var int|null Id of the role criterion the current user may mark, set by load(). */
    protected $allowmarkingcriteria = null;

    /** @var array Identity fields shown for each user. */
    protected $extrafields;

    /** @var \course_modinfo|null Course module information, set by load(). */
    protected $modinfo = null;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param \context_course $context The course context.
     * @param user_filter $filter The user filter.
     * @param moodle_url $filterurl URL of the report page holding the filter parameters.
     * @param int $groupid The active group (as returned by groups_get_course_group()).
     * @param array $options 'sort', 'dataformat', 'start'.
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
        $this->start = max(0, (int) ($options['start'] ?? 0));

        // Whether to show extra user identity information.
        $this->extrafields = \core_user\fields::get_identity_fields($context, true);
    }

    /**
     * Returns the report type key.
     *
     * @return string
     */
    public function get_type(): string {
        return self::TYPE_COMPLETION;
    }

    /**
     * Returns the URL of the current page.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        $url = new moodle_url($this->filterurl);
        if ($this->sort !== '') {
            $url->param('sort', $this->sort);
        }
        if ($this->is_download()) {
            $url->param('dataformat', $this->dataformat);
        }
        if ($this->start > 0) {
            $url->param('start', $this->start);
        }
        return $url;
    }

    /**
     * Returns the page title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('coursecompletion');
    }

    /**
     * Loads the criteria and the progress of the users matching the filter.
     *
     * @return void
     * @throws \moodle_exception nocriteriaset when the course has no completion criteria (as core)
     */
    public function load(): void {
        global $USER;

        // Retrieve course_module data for all modules in the course.
        $this->modinfo = get_fast_modinfo($this->course);

        // Get criteria for course.
        $this->completion = new \completion_info($this->course);

        if (!$this->completion->has_criteria()) {
            // Core links back to the course reports; link back to the (empty) search page instead, so that the
            // user can still choose the activity completion report.
            $returnurl = new moodle_url('/report/completionsearch/index.php', ['course' => $this->course->id]);
            throw new \moodle_exception('nocriteriaset', 'completion', $returnurl);
        }

        // Get criteria and put in correct order.
        $this->criteria = [];
        foreach ($this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
            $this->criteria[] = $criterion;
        }
        foreach ($this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
            $this->criteria[] = $criterion;
        }
        foreach ($this->completion->get_criteria() as $criterion) {
            if (!in_array($criterion->criteriatype, [COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY])) {
                $this->criteria[] = $criterion;
            }
        }

        // Can logged in user mark users as complete?
        // (if the logged in user has a role defined in the role criteria).
        $this->allowmarkingcriteria = null;
        if (!$this->is_download()) {
            $rcriteria = $this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_ROLE);
            foreach ($rcriteria as $rcriterion) {
                $users = get_role_users($rcriterion->role, $this->context, true);

                // If logged in user has this role, allow marking complete.
                if ($users && in_array($USER->id, array_keys($users))) {
                    $this->allowmarkingcriteria = $rcriterion->id;
                    break;
                }
            }
        }

        $this->load_totals();

        // Get user data.
        $this->progress = [];
        if ($this->grandtotal && $this->total) {
            [$where, $params] = $this->filter->get_sql();
            $this->progress = $this->completion->get_progress_all(
                $where,
                $params,
                $this->groupid,
                $this->sort === 'firstname' ? 'u.firstname ASC' : 'u.lastname ASC',
                $this->is_download() ? 0 : self::PAGE_SIZE,
                $this->is_download() ? 0 : $this->start,
                $this->context
            );
        }
        $this->loaded = true;
    }

    /**
     * Whether an export was requested and there are tracked users.
     *
     * @return bool
     */
    public function is_export(): bool {
        $this->require_loaded();
        return $this->is_download() && $this->grandtotal;
    }

    /**
     * Sends the file in the requested dataformat through \core\dataformat and exits, exactly as core 5.3 does.
     *
     * @return void
     */
    public function export(): void {
        $this->require_loaded();
        if (!$this->is_export()) {
            throw new \coding_exception('Nothing to export.');
        }

        // Download column names.
        $row = [];
        $row[] = get_string('id', 'report_completion');
        $row[] = get_string('name', 'report_completion');
        foreach ($this->extrafields as $field) {
            $row[] = \core_user\fields::get_display_name($field);
        }

        // Add activity headers.
        foreach ($this->criteria as $criterion) {
            if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                // Handle activity completion differently.
                $mod = $criterion->get_mod_instance();
                $row[] = $formattedname = format_string(
                    $mod->name,
                    true,
                    ['context' => \context_module::instance($criterion->moduleinstance)]
                );
                $row[] = $formattedname . ' - ' . get_string('completiondate', 'report_completion');
            } else {
                // Handle all other criteria.
                $row[] = strip_tags($criterion->get_title_detailed());
            }
        }
        $row[] = get_string('coursecomplete', 'completion');
        $columnnames = $row;

        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);
        $alldata = [];
        foreach ($this->progress as $user) {
            $row = [];
            $row[] = $user->id;
            $row[] = fullname($user, $viewfullnames);
            foreach ($this->extrafields as $field) {
                $row[$field] = $user->{$field};
            }

            foreach ($this->criteria as $criterion) {
                $cell = $this->get_criterion_cell($user, $criterion);
                if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                    $row[] = $cell->describe;
                }
                $row[] = $cell->date;
            }

            $course = $this->get_course_cell($user);
            $row[] = $course->date;

            $alldata[] = $row;
        }

        $extrafields = $this->extrafields;
        \core\dataformat::download_data(
            clean_filename('completion-' . $this->get_export_shortname() . '-' . gmdate('Ymd_Hi')),
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
     * Returns the format of the completion dates: ISO-like in the exports, the short date and time otherwise (as core).
     *
     * @return string
     */
    protected function get_date_format(): string {
        return $this->is_download() ? '%F %T' : get_string('strftimedatetimeshort', 'langconfig');
    }

    /**
     * Works out the state of a user for a criterion, as core does.
     *
     * @param \stdClass $user User record with the 'progress' array.
     * @param \completion_criteria $criterion The criterion.
     * @return \stdClass with iscomplete, completiontype, completionicon, describe, date and fulldescribe.
     */
    protected function get_criterion_cell(\stdClass $user, \completion_criteria $criterion): \stdClass {
        $criteriacompletion = $this->completion->get_user_completion($user->id, $criterion);
        $iscomplete = $criteriacompletion->is_complete();
        if ($iscomplete) {
            $date = userdate($criteriacompletion->timecompleted, $this->get_date_format());
        } else {
            $date = '';
        }

        if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
            // Handle activity completion differently.
            $activity = $this->modinfo->cms[$criterion->moduleinstance];

            // Get progress information and state.
            if (array_key_exists($activity->id, $user->progress)) {
                $state = $user->progress[$activity->id]->completionstate;
            } else if ($iscomplete) {
                $state = COMPLETION_COMPLETE;
            } else {
                $state = COMPLETION_INCOMPLETE;
            }

            // Work out how it corresponds to an icon.
            $completiontype = '';
            switch ($state) {
                case COMPLETION_INCOMPLETE:
                    $completiontype = 'n';
                    break;
                case COMPLETION_COMPLETE:
                    $completiontype = 'y';
                    break;
                case COMPLETION_COMPLETE_PASS:
                    $completiontype = 'pass';
                    break;
                case COMPLETION_COMPLETE_FAIL:
                    $completiontype = 'fail';
                    break;
            }

            $auto = $activity->completion == COMPLETION_TRACKING_AUTOMATIC;
            $completionicon = 'completion-' . ($auto ? 'auto' : 'manual') . '-' . $completiontype;
            $activityname = $activity->get_formatted_name();
        } else {
            // Handle all other criteria.
            $completiontype = $iscomplete ? 'y' : 'n';
            $completionicon = 'completion-auto-' . $completiontype;
            $activityname = strip_tags($criterion->get_title());
        }

        $describe = get_string('completion-' . $completiontype, 'completion');
        $a = new \stdClass();
        $a->state = $describe;
        $a->date = $date;
        $a->user = fullname($user);
        $a->activity = $activityname;

        return (object) [
            'iscomplete' => $iscomplete,
            'completiontype' => $completiontype,
            'completionicon' => $completionicon,
            'describe' => $describe,
            'date' => $date,
            'fulldescribe' => get_string('progress-title', 'completion', $a),
        ];
    }

    /**
     * Works out the overall course completion state of a user, as core does.
     *
     * @param \stdClass $user User record.
     * @return \stdClass with completiontype, date and fulldescribe.
     */
    protected function get_course_cell(\stdClass $user): \stdClass {
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $this->course->id]);
        $iscomplete = $ccompletion->is_complete();
        $completiontype = $iscomplete ? 'y' : 'n';

        $a = new \stdClass();
        if ($iscomplete) {
            $a->date = userdate($ccompletion->timecompleted, $this->get_date_format());
        } else {
            $a->date = '';
        }
        $a->state = get_string('completion-' . $completiontype, 'completion');
        $a->user = fullname($user);
        $a->activity = strip_tags(get_string('coursecomplete', 'completion'));

        return (object) [
            'completiontype' => $completiontype,
            'date' => $a->date,
            'fulldescribe' => get_string('progress-title', 'completion', $a),
        ];
    }

    /**
     * Returns the HTML of the report (group menu, totals, paging bar, table and download links).
     *
     * @return string
     */
    public function output(): string {
        global $OUTPUT;

        $this->require_loaded();

        // Handle groups (if enabled).
        $html = (string) groups_print_course_menu($this->course, $this->get_filter_url(), true);

        // If no users in this course what-so-ever.
        if (!$this->grandtotal) {
            $html .= $OUTPUT->container(get_string('err_nousers', 'completion'), 'errorbox errorboxcontent');
            return $html;
        }

        $html .= '<br class="clearer"/>';

        $totalheader = ($this->total == $this->grandtotal) ? $this->total : "{$this->total}/{$this->grandtotal}";
        $html .= $OUTPUT->heading(get_string('allparticipants') . ": {$totalheader}", 3);

        // The initials bar of core is replaced by the search form, so only the paging bar is left.
        $html .= $this->output_paging_bar();

        if (!$this->total) {
            $html .= $OUTPUT->notification(get_string('nothingtodisplay'), 'info', false);
            return $html;
        }

        $html .= $this->output_table();

        // Download in any enabled dataformat, as core 5.3 (the whole filtered list, in the current sort order).
        $downloadurl = $this->get_url();
        $downloadurl->remove_params('start');
        $html .= $OUTPUT->download_dataformat_selector(
            get_string('downloadas', 'table'),
            $downloadurl->out_omit_querystring(),
            'dataformat',
            $downloadurl->params()
        );

        return $html;
    }

    /**
     * Returns the URL of a page of the report, keeping the filter and the sort.
     *
     * @param int $start Offset of the first user of the page.
     * @return moodle_url
     */
    protected function get_page_url(int $start): moodle_url {
        $url = $this->get_filter_url();
        if ($this->sort !== '') {
            $url->param('sort', $this->sort);
        }
        $url->param('start', $start);
        return $url;
    }

    /**
     * Returns the HTML of the core-like paging bar (empty when a single page is enough).
     *
     * @return string
     */
    protected function output_paging_bar(): string {
        if ($this->total <= self::PAGE_SIZE) {
            return '';
        }

        $pagingbar = '<div class="paging">';
        $pagingbar .= get_string('page') . ': ';

        // Display previous link.
        if ($this->start > 0) {
            $pstart = max($this->start - self::PAGE_SIZE, 0);
            $pagingbar .= '(<a class="previous" href="' . $this->get_page_url($pstart)->out() . '">' .
                get_string('previous') . '</a>)&nbsp;';
        }

        // Create page links.
        $curstart = 0;
        $curpage = 0;
        while ($curstart < $this->total) {
            $curpage++;
            if ($curstart == $this->start) {
                $pagingbar .= '&nbsp;' . $curpage . '&nbsp;';
            } else {
                $pagingbar .= '&nbsp;<a href="' . $this->get_page_url($curstart)->out() . '">' . $curpage . '</a>&nbsp;';
            }
            $curstart += self::PAGE_SIZE;
        }

        // Display next link.
        $nstart = $this->start + self::PAGE_SIZE;
        if ($nstart < $this->total) {
            $pagingbar .= '&nbsp;(<a class="next" href="' . $this->get_page_url($nstart)->out() . '">' .
                get_string('next') . '</a>)';
        }

        $pagingbar .= '</div>';
        return $pagingbar;
    }

    /**
     * Returns the HTML of the criteria group header cells (criteria group names or aggregation methods).
     *
     * @param bool $aggregation True for the aggregation methods row, false for the criteria group names row.
     * @return string
     */
    protected function output_group_header_cells(bool $aggregation): string {
        $html = '';
        $currentgroup = false;
        $colcount = 0;
        $count = count($this->criteria);
        for ($i = 0; $i <= $count; $i++) {
            if (isset($this->criteria[$i])) {
                $criterion = $this->criteria[$i];
                if ($currentgroup && $criterion->criteriatype === $currentgroup->criteriatype) {
                    ++$colcount;
                    continue;
                }
            }

            // Print header cell.
            if ($colcount) {
                if (!$aggregation) {
                    $html .= '<th scope="col" colspan="' . $colcount . '" class="colheader criteriagroup">' .
                        $currentgroup->get_type_title() . '</th>';
                } else {
                    $hasagg = [
                        COMPLETION_CRITERIA_TYPE_COURSE,
                        COMPLETION_CRITERIA_TYPE_ACTIVITY,
                        COMPLETION_CRITERIA_TYPE_ROLE,
                    ];
                    if (in_array($currentgroup->criteriatype, $hasagg)) {
                        // Try load a aggregation method.
                        $method = $this->completion->get_aggregation_method($currentgroup->criteriatype);
                        $method = $method == 1 ? get_string('all') : get_string('any');
                    } else {
                        $method = '-';
                    }
                    $html .= '<th scope="col" colspan="' . $colcount . '" class="colheader aggheader">' . $method . '</th>';
                }
            }

            if (isset($this->criteria[$i])) {
                // Move to next criteria type.
                $currentgroup = $this->criteria[$i];
                $colcount = 1;
            }
        }
        return $html;
    }

    /**
     * Returns the HTML of the completion table.
     *
     * @return string
     */
    protected function output_table(): string {
        global $DB, $OUTPUT;

        $leftcols = 1 + count($this->extrafields);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);

        $html = '<table id="completion-progress" class="table table-bordered generaltable flexible boxaligncenter ' .
            'completionreport table-hover" style="text-align: left" cellpadding="5" border="1">';

        // Print criteria group names.
        $html .= PHP_EOL . '<thead><tr style="vertical-align: top">';
        $html .= '<th scope="row" class="rowheader" colspan="' . $leftcols . '">' .
            get_string('criteriagroup', 'completion') . '</th>';
        $html .= $this->output_group_header_cells(false);

        // Overall course completion status.
        $html .= '<th style="text-align: center;">' . get_string('course') . '</th>';
        $html .= '</tr>';

        // Print aggregation methods.
        $html .= PHP_EOL . '<tr style="vertical-align: top">';
        $html .= '<th scope="row" class="rowheader" colspan="' . $leftcols . '">' .
            get_string('aggregationmethod', 'completion') . '</th>';
        $html .= $this->output_group_header_cells(true);

        // Overall course aggregation method.
        $html .= '<th scope="col" class="colheader aggheader aggcriteriacourse">';
        $method = $this->completion->get_aggregation_method();
        $html .= $method == 1 ? get_string('all') : get_string('any');
        $html .= '</th>';
        $html .= '</tr>';

        // Print criteria titles.
        if (self::COL_TITLES) {
            $html .= PHP_EOL . '<tr>';
            $html .= '<th scope="row" class="rowheader" colspan="' . $leftcols . '">' .
                get_string('criteria', 'completion') . '</th>';

            foreach ($this->criteria as $criterion) {
                // Get criteria details.
                $details = $criterion->get_title_detailed();
                $html .= '<th scope="col" class="colheader criterianame">';
                $html .= '<div class="rotated-text-container"><span class="rotated-text">' . $details . '</span></div>';
                $html .= '</th>';
            }

            // Overall course completion status.
            $html .= '<th scope="col" class="colheader criterianame">';
            $html .= '<div class="rotated-text-container"><span class="rotated-text">' .
                get_string('coursecomplete', 'completion') . '</span></div>';
            $html .= '</th></tr>';
        }

        // Print user heading and icons.
        $html .= '<tr>';

        // User heading / sort option.
        $html .= '<th scope="col" class="completion-sortchoice" style="clear: both;">';
        $sorturl = $this->get_filter_url();
        if ($this->sort === 'firstname') {
            $html .= get_string('firstname') . ' / ' . html_writer::link($sorturl, get_string('lastname'));
        } else {
            $sorturl->param('sort', 'firstname');
            $html .= html_writer::link($sorturl, get_string('firstname')) . ' / ' . get_string('lastname');
        }
        $html .= '</th>';

        // Print user identity columns.
        foreach ($this->extrafields as $field) {
            $html .= '<th scope="col" class="completion-identifyfield">' .
                \core_user\fields::get_display_name($field) . '</th>';
        }

        // Print criteria icons.
        foreach ($this->criteria as $criterion) {
            // Generate icon details.
            $iconlink = null;
            $iconalt = ''; // Required.
            $iconattributes = ['class' => 'icon'];
            switch ($criterion->criteriatype) {
                case COMPLETION_CRITERIA_TYPE_ACTIVITY:
                    // Display icon.
                    $iconlink = new moodle_url('/mod/' . $criterion->module . '/view.php', ['id' => $criterion->moduleinstance]);
                    $iconattributes['title'] = $this->modinfo->cms[$criterion->moduleinstance]->get_formatted_name();
                    $iconalt = get_string('modulename', $criterion->module);
                    break;

                case COMPLETION_CRITERIA_TYPE_COURSE:
                    // Load course.
                    $crs = $DB->get_record('course', ['id' => $criterion->courseinstance]);
                    $crscontext = \context_course::instance($crs->id, MUST_EXIST);

                    // Display icon.
                    $iconlink = new moodle_url('/course/view.php', ['id' => $criterion->courseinstance]);
                    $iconattributes['title'] = format_string($crs->fullname, true, ['context' => $crscontext]);
                    $iconalt = format_string($crs->shortname, true, ['context' => $crscontext]);
                    break;

                case COMPLETION_CRITERIA_TYPE_ROLE:
                    // Load role.
                    $role = $DB->get_record('role', ['id' => $criterion->role]);

                    // Display icon.
                    $iconalt = $role->name;
                    break;
            }

            // Create icon alt if not supplied.
            if (!$iconalt) {
                $iconalt = $criterion->get_title();
            }

            // Print icon and cell.
            $html .= '<th class="criteriaicon">';
            $icon = $OUTPUT->render($criterion->get_icon($iconalt, $iconattributes));
            if ($iconlink) {
                $html .= html_writer::link($iconlink, $icon, ['title' => $iconattributes['title']]);
            } else {
                $html .= $icon;
            }
            $html .= '</th>';
        }

        // Overall course completion status.
        $html .= '<th class="criteriaicon">';
        $html .= $OUTPUT->pix_icon('i/course', get_string('coursecomplete', 'completion'));
        $html .= '</th>';

        $html .= '</tr></thead>';

        $html .= '<tbody>';

        // Display a row for each user.
        foreach ($this->progress as $user) {
            $html .= PHP_EOL . '<tr id="user-' . $user->id . '">';

            if (completion_can_view_data($user->id, $this->course)) {
                $userurl = new moodle_url(
                    '/blocks/completionstatus/details.php',
                    ['course' => $this->course->id, 'user' => $user->id]
                );
            } else {
                $userurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $this->course->id]);
            }

            $html .= '<th scope="row"><a href="' . $userurl->out() . '">' . fullname($user, $viewfullnames) . '</a></th>';
            foreach ($this->extrafields as $field) {
                $html .= '<td>' . s($user->{$field}) . '</td>';
            }

            // Progress for each course completion criteria.
            foreach ($this->criteria as $criterion) {
                $cell = $this->get_criterion_cell($user, $criterion);

                // Activity criteria are never markable (core skips them before this check).
                $canmark = $criterion->criteriatype != COMPLETION_CRITERIA_TYPE_ACTIVITY
                    && $this->allowmarkingcriteria === $criterion->id;

                $html .= '<td class="completion-progresscell">';
                if ($canmark) {
                    $toggleurl = new moodle_url('/course/togglecompletion.php', [
                        'user' => $user->id,
                        'course' => $this->course->id,
                        'rolec' => $this->allowmarkingcriteria,
                        'sesskey' => sesskey(),
                    ]);
                    $html .= '<a href="' . $toggleurl->out() . '" title="' .
                        s(get_string('clicktomarkusercomplete', 'report_completion')) . '">' .
                        $OUTPUT->pix_icon('i/completion-manual-' . ($cell->iscomplete ? 'y' : 'n'), $cell->describe) . '</a>';
                } else {
                    $html .= $OUTPUT->pix_icon('i/' . $cell->completionicon, $cell->fulldescribe);
                }
                $html .= '</td>';
            }

            // Handle overall course completion.
            $coursecell = $this->get_course_cell($user);
            $html .= '<td class="completion-progresscell">';

            // Display course completion status icon.
            $html .= $OUTPUT->pix_icon('i/completion-auto-' . $coursecell->completiontype, $coursecell->fulldescribe);
            $html .= '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }
}
