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
 * Filter form of the completion search report.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Filter form (search field, search text and report type) of the completion search report.
 *
 * The form is submitted with GET so that the resulting URL can be bookmarked, paged and sorted.
 * The values are read back in index.php through optional_param() and pushed into the form with set_data().
 *
 * Custom data:
 *  - courseid (int) the course id;
 *  - fieldoptions (array) search field key => label, see {@see \report_completionsearch\local\user_filter::get_field_options()};
 *  - reportoptions (array) report key => label.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $fieldoptions = $this->_customdata['fieldoptions'] ?? [];
        $reportoptions = $this->_customdata['reportoptions'] ?? [];

        $mform->addElement('header', 'filterheader', get_string('filter'));
        $mform->setExpanded('filterheader', true);

        $mform->addElement('hidden', 'course', (int) ($this->_customdata['courseid'] ?? 0));
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'run', 1);
        $mform->setType('run', PARAM_BOOL);

        $mform->addElement('select', 'searchfield', get_string('searchfield', 'report_completionsearch'), $fieldoptions);
        $mform->setType('searchfield', PARAM_ALPHA);

        $mform->addElement('text', 'search', get_string('search', 'report_completionsearch'), ['size' => 30]);
        $mform->setType('search', PARAM_TEXT);

        $mform->addElement('select', 'report', get_string('reporttype', 'report_completionsearch'), $reportoptions);
        $mform->setType('report', PARAM_ALPHA);

        $mform->addElement('submit', 'submitbutton', get_string('runreport', 'report_completionsearch'));

        $mform->disable_form_change_checker();
    }

    /**
     * Removes the automatic sesskey and submission marker fields.
     *
     * This is a read-only GET form: keeping them would leak the sesskey into the URL (browser history,
     * server logs, shared links) and would make moodleform demand a sesskey when a bookmarked URL is opened.
     *
     * @return void
     */
    protected function after_definition() {
        parent::after_definition();
        $mform = $this->_form;
        foreach (['sesskey', '_qf__' . $this->_formname] as $elementname) {
            if ($mform->elementExists($elementname)) {
                $mform->removeElement($elementname);
            }
        }
    }
}
