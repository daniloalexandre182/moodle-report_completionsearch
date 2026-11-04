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
 * Tests for the activity completion report of report_completionsearch.
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

/**
 * Tests for the activity completion report (section and group selectors, dataformat downloads).
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_completionsearch\local\progress_report
 */
final class progress_report_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    protected $course;

    /** @var \context_course The course context. */
    protected $context;

    /** @var \stdClass The group holding only Maria. */
    protected $group;

    /**
     * Creates a course with one tracked page in sections 1 and 2, two students and a group holding one of them.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1, 'numsections' => 2, 'groupmode' => NOGROUPS]);
        $this->context = \context_course::instance($this->course->id);
        $generator->create_module('page', [
            'course' => $this->course->id,
            'name' => 'PageInSectionOne',
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $generator->create_module('page', [
            'course' => $this->course->id,
            'name' => 'PageInSectionTwo',
            'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Enrolments started in the past: the tracked users query only lists enrolments started before a rounded "now".
        $timestart = time() - DAYSECS;
        $mariaparams = ['firstname' => 'Maria', 'lastname' => 'Souza'];
        $joaoparams = ['firstname' => 'Joao', 'lastname' => 'Silva'];
        $maria = $generator->create_and_enrol($this->course, 'student', $mariaparams, 'manual', $timestart);
        $generator->create_and_enrol($this->course, 'student', $joaoparams, 'manual', $timestart);
        $this->group = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Group Maria']);
        $generator->create_group_member(['groupid' => $this->group->id, 'userid' => $maria->id]);
    }

    /**
     * Builds and loads the report.
     *
     * @param int $groupid The active group.
     * @param array $options Report options.
     * @return progress_report
     */
    protected function get_report(int $groupid, array $options): progress_report {
        $filterurl = new \moodle_url('/report/completionsearch/index.php', [
            'course' => $this->course->id,
            'report' => report_base::TYPE_PROGRESS,
            'run' => 1,
        ]);
        $filter = new user_filter(user_filter::FIELD_FULLNAME, '');
        $report = new progress_report($this->course, $this->context, $filter, $filterurl, $groupid, $options);
        $report->load();
        return $report;
    }

    /**
     * Without a section only the "Do not filter by section" option is selected and every activity is listed.
     */
    public function test_output_without_section(): void {
        $report = $this->get_report(0, []);
        $html = $report->output();

        $this->assertStringContainsString('PageInSectionOne', $html);
        $this->assertStringContainsString('PageInSectionTwo', $html);
        $this->assertStringContainsString('name="activitysection"', $html);
        $this->assertSame(-1, (int) $report->get_url()->param('activitysection'));
        $this->assertSame(2, $report->get_total());
    }

    /**
     * The section selector restricts the activities to the chosen section and is kept in the page URL.
     */
    public function test_output_filtered_by_section(): void {
        $report = $this->get_report(0, ['activitysection' => 2]);
        $html = $report->output();

        $this->assertStringNotContainsString('PageInSectionOne', $html);
        $this->assertStringContainsString('PageInSectionTwo', $html);
        $this->assertSame(2, (int) $report->get_url()->param('activitysection'));
    }

    /**
     * Outside separate groups mode the group selector is shown and the active group restricts the users.
     */
    public function test_output_filtered_by_group_without_group_mode(): void {
        $all = $this->get_report(0, [])->output();
        $this->assertStringContainsString('Group Maria', $all);
        $this->assertStringContainsString('Maria Souza', $all);
        $this->assertStringContainsString('Joao Silva', $all);

        $report = $this->get_report((int) $this->group->id, []);
        $html = $report->output();
        $this->assertSame(1, $report->get_total());
        $this->assertStringContainsString('Maria Souza', $html);
        $this->assertStringNotContainsString('Joao Silva', $html);
        $this->assertSame((int) $this->group->id, (int) $report->get_url()->param('group'));
    }

    /**
     * The download is offered through the dataformat selector, not the old CSV/Excel links.
     */
    public function test_download_selector(): void {
        $report = $this->get_report(0, []);
        $html = $report->output();

        $this->assertFalse($report->is_export());
        $this->assertStringContainsString('name="dataformat"', $html);
        $this->assertStringNotContainsString('format=excelcsv', $html);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A dataformat makes the report an export and loads every user (no paging).
     */
    public function test_is_export_with_dataformat(): void {
        $report = $this->get_report(0, ['dataformat' => 'csv']);

        $this->assertTrue($report->is_export());
        $this->assertSame('csv', $report->get_url()->param('dataformat'));
    }
}
