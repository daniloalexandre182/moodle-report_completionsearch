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
 * Tests for the course completion report of report_completionsearch.
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

/**
 * Tests for the course completion report (search filter and dataformat downloads).
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_completionsearch\local\completion_report
 */
final class completion_report_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    protected $course;

    /** @var \context_course The course context. */
    protected $context;

    /**
     * Creates a course completed by one tracked page, with two students.
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        $this->context = \context_course::instance($this->course->id);
        $page = $generator->create_module('page', [
            'course' => $this->course->id,
            'name' => 'TrackedPage',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $criteriadata = (object) [
            'id' => $this->course->id,
            'criteria_activity' => [$page->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        // Enrolments started in the past: the tracked users query only lists enrolments started before a rounded "now".
        $timestart = time() - DAYSECS;
        $mariaparams = ['firstname' => 'Maria', 'lastname' => 'Souza'];
        $joaoparams = ['firstname' => 'Joao', 'lastname' => 'Silva'];
        $generator->create_and_enrol($this->course, 'student', $mariaparams, 'manual', $timestart);
        $generator->create_and_enrol($this->course, 'student', $joaoparams, 'manual', $timestart);
    }

    /**
     * Builds and loads the report.
     *
     * @param string $search Full name search.
     * @param array $options Report options.
     * @return completion_report
     */
    protected function get_report(string $search, array $options): completion_report {
        $filterurl = new \moodle_url('/report/completionsearch/index.php', [
            'course' => $this->course->id,
            'report' => report_base::TYPE_COMPLETION,
            'run' => 1,
        ]);
        $filter = new user_filter(user_filter::FIELD_FULLNAME, $search);
        $report = new completion_report($this->course, $this->context, $filter, $filterurl, 0, $options);
        $report->load();
        return $report;
    }

    /**
     * The search filter restricts the listed users.
     */
    public function test_output_filtered_by_search(): void {
        $report = $this->get_report('Maria', []);
        $html = $report->output();

        $this->assertSame(1, $report->get_total());
        $this->assertSame(2, $report->get_grand_total());
        $this->assertStringContainsString('Maria Souza', $html);
        $this->assertStringNotContainsString('Joao Silva', $html);
        $this->assertStringContainsString('TrackedPage', $html);
    }

    /**
     * As in core 5.3, the download is offered through the dataformat selector, not the old CSV/Excel links.
     */
    public function test_download_selector(): void {
        $report = $this->get_report('', ['sort' => 'firstname', 'start' => 25]);
        $html = $report->output();

        $this->assertFalse($report->is_export());
        $this->assertStringContainsString('name="dataformat"', $html);
        $this->assertStringContainsString('name="sort" value="firstname"', $html);
        $this->assertStringNotContainsString('name="start"', $html);
        $this->assertStringNotContainsString('format=excelcsv', $html);
        $this->assertStringNotContainsString('export-actions', $html);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A dataformat makes the report an export and loads every user (no paging).
     */
    public function test_is_export_with_dataformat(): void {
        $report = $this->get_report('', ['dataformat' => 'csv']);

        $this->assertTrue($report->is_export());
        $this->assertSame('csv', $report->get_url()->param('dataformat'));
    }

    /**
     * The old 'format' option is ignored (no CSV/Excel export any more).
     */
    public function test_old_format_option_ignored(): void {
        $report = $this->get_report('', ['format' => 'excelcsv']);

        $this->assertFalse($report->is_export());
    }
}
