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
 * The report_completionsearch report viewed event.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\event;

/**
 * The report_completionsearch report viewed event class.
 *
 * Fired when the activity completion or course completion view of this report is
 * displayed for a course. The `other['report']` field records which of the two
 * underlying report types ('progress' or 'completion') was shown.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'report_completionsearch');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $report = $this->other['report'];
        return "The user with id '$this->userid' viewed the completion search report ('$report' view) " .
            "for the course with id '$this->courseid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/report/completionsearch/index.php', ['course' => $this->courseid]);
    }

    /**
     * No mapping is required when restoring the logs: 'other' only holds the report type key, not ids.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }

    /**
     * Custom validations.
     *
     * @throws \coding_exception when validation fails.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_COURSE) {
            throw new \coding_exception('Context level must be CONTEXT_COURSE.');
        }

        if (!isset($this->other['report'])) {
            throw new \coding_exception('The \'report\' value must be set in other.');
        }

        if (!in_array($this->other['report'], ['completion', 'progress'])) {
            throw new \coding_exception('The \'report\' value must be either \'completion\' or \'progress\'.');
        }
    }
}
