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
 * Tests for the report_completionsearch events.
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\event;

/**
 * Tests for the report_completionsearch events.
 *
 * It is not possible to use the Moodle API to simulate the viewing of the report, so the
 * events are simply created and triggered, as core report_completion does.
 *
 * @package    report_completionsearch
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_completionsearch\event\report_viewed
 */
class events_test extends \advanced_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Data provider with the valid report types.
     *
     * @return array
     */
    public static function report_type_provider(): array {
        return [
            'course completion' => ['completion'],
            'activity completion' => ['progress'],
        ];
    }

    /**
     * Test the report viewed event for each valid report type.
     *
     * @dataProvider report_type_provider
     * @param string $reporttype The value of other['report'].
     */
    public function test_report_viewed(string $reporttype): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $event = report_viewed::create([
            'context' => $context,
            'other' => ['report' => $reporttype],
        ]);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf('\report_completionsearch\event\report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($course->id, $event->courseid);
        $this->assertSame($reporttype, $event->other['report']);
        $this->assertEquals('r', $event->crud);
        $this->assertEquals(\core\event\base::LEVEL_TEACHING, $event->edulevel);

        $url = new \moodle_url('/report/completionsearch/index.php', ['course' => $course->id]);
        $this->assertEquals($url, $event->get_url());

        $this->assertSame(get_string('eventreportviewed', 'report_completionsearch'), report_viewed::get_name());
        $this->assertStringContainsString("'{$reporttype}'", $event->get_description());
        $this->assertFalse(report_viewed::get_other_mapping());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Creating the event without other['report'] must throw a coding_exception.
     */
    public function test_report_viewed_without_report_type(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('The \'report\' value must be set in other.');
        report_viewed::create(['context' => $context]);
    }

    /**
     * Creating the event with an unknown other['report'] must throw a coding_exception.
     */
    public function test_report_viewed_with_invalid_report_type(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('The \'report\' value must be either \'completion\' or \'progress\'.');
        report_viewed::create([
            'context' => $context,
            'other' => ['report' => 'invalid'],
        ]);
    }

    /**
     * Creating the event in a context other than a course context must throw a coding_exception.
     */
    public function test_report_viewed_with_invalid_context(): void {
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_COURSE.');
        report_viewed::create([
            'context' => \context_system::instance(),
            'other' => ['report' => 'completion'],
        ]);
    }
}
