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
 * Unit tests for \report_completionsearch\local\user_filter.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_completionsearch\local;

/**
 * Unit tests for \report_completionsearch\local\user_filter.
 *
 * @package    report_completionsearch
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_completionsearch\local\user_filter
 */
final class user_filter_test extends \advanced_testcase {
    /**
     * Data provider of the three supported filter fields.
     *
     * @return array
     */
    public static function field_provider(): array {
        return [
            'fullname' => [user_filter::FIELD_FULLNAME],
            'email' => [user_filter::FIELD_EMAIL],
            'username' => [user_filter::FIELD_USERNAME],
        ];
    }

    /**
     * An empty, or whitespace-only, search text must produce no SQL fragment and no params,
     * for every supported field.
     *
     * @dataProvider field_provider
     * @param string $field One of the user_filter::FIELD_* constants.
     */
    public function test_get_sql_with_blank_text_returns_no_condition(string $field): void {
        $this->resetAfterTest();

        [$where, $params] = (new user_filter($field, ''))->get_sql();
        $this->assertSame('', $where);
        $this->assertSame([], $params);

        [$where, $params] = (new user_filter($field, '   '))->get_sql();
        $this->assertSame('', $where);
        $this->assertSame([], $params);

        // A tab/newline mix is still "blank" once trimmed.
        [$where, $params] = (new user_filter($field, " \t\n "))->get_sql();
        $this->assertSame('', $where);
        $this->assertSame([], $params);
    }

    /**
     * Constructing a filter for an unknown field must raise a coding_exception.
     */
    public function test_constructor_rejects_unknown_field(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        new user_filter('notarealfield', 'some text');
    }

    /**
     * Creates a course with three users with distinct, non-overlapping, full names, emails
     * and usernames, so each field can be searched for unambiguously.
     *
     * @return array [\stdClass $course, \stdClass $maria, \stdClass $joao, \stdClass $ana]
     */
    protected function create_course_with_users(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $maria = $generator->create_user([
            'firstname' => 'Maria',
            'lastname' => 'Souza',
            'email' => 'maria.souza@example.com',
            'username' => 'mariasouza',
        ]);
        $joao = $generator->create_user([
            'firstname' => 'João',
            'lastname' => 'Silva',
            'email' => 'joao.silva@example.com',
            'username' => 'joaosilva',
        ]);
        $ana = $generator->create_user([
            'firstname' => 'Ana',
            'lastname' => 'Costa',
            'email' => 'ana.costa@example.com',
            'username' => 'anacosta',
        ]);

        $generator->enrol_user($maria->id, $course->id, 'student');
        $generator->enrol_user($joao->id, $course->id, 'student');
        $generator->enrol_user($ana->id, $course->id, 'student');

        return [$course, $maria, $joao, $ana];
    }

    /**
     * Runs the where/params produced by user_filter::get_sql() against the {user} table and
     * returns the matching user ids.
     *
     * @param array $sql [string $where, array $params] as returned by user_filter::get_sql().
     * @return int[]
     */
    protected function query_user_ids(array $sql): array {
        global $DB;

        [$where, $params] = $sql;
        $this->assertNotSame('', $where, 'Expected a non-empty SQL condition.');

        $records = $DB->get_records_sql("SELECT u.id FROM {user} u WHERE $where", $params);
        return array_map('intval', array_keys($records));
    }

    /**
     * The fullname filter must match a partial, case-insensitive substring of the user's full
     * name, spanning first name and last name (e.g. "Maria Souza" is matched by "ria sou").
     */
    public function test_get_sql_filters_by_fullname(): void {
        $this->resetAfterTest();
        [, $maria, $joao, $ana] = $this->create_course_with_users();

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_FULLNAME, 'ria sou'))->get_sql());
        $this->assertEqualsCanonicalizing([$maria->id], $ids);

        // Case-insensitive.
        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_FULLNAME, 'RIA SOU'))->get_sql());
        $this->assertEqualsCanonicalizing([$maria->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_FULLNAME, 'oão sil'))->get_sql());
        $this->assertEqualsCanonicalizing([$joao->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_FULLNAME, 'na cos'))->get_sql());
        $this->assertEqualsCanonicalizing([$ana->id], $ids);
    }

    /**
     * The email filter must match a partial, case-insensitive substring of the user's email.
     */
    public function test_get_sql_filters_by_email(): void {
        $this->resetAfterTest();
        [, $maria, $joao, $ana] = $this->create_course_with_users();

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_EMAIL, 'oao.silva'))->get_sql());
        $this->assertEqualsCanonicalizing([$joao->id], $ids);

        // Case-insensitive.
        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_EMAIL, 'OAO.SILVA'))->get_sql());
        $this->assertEqualsCanonicalizing([$joao->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_EMAIL, 'aria.souza'))->get_sql());
        $this->assertEqualsCanonicalizing([$maria->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_EMAIL, 'na.costa'))->get_sql());
        $this->assertEqualsCanonicalizing([$ana->id], $ids);
    }

    /**
     * The username filter must match a partial, case-insensitive substring of the username.
     */
    public function test_get_sql_filters_by_username(): void {
        $this->resetAfterTest();
        [, $maria, $joao, $ana] = $this->create_course_with_users();

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_USERNAME, 'acosta'))->get_sql());
        $this->assertEqualsCanonicalizing([$ana->id], $ids);

        // Case-insensitive (usernames are stored lower-case, so this also proves case folding).
        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_USERNAME, 'ACOSTA'))->get_sql());
        $this->assertEqualsCanonicalizing([$ana->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_USERNAME, 'riasou'))->get_sql());
        $this->assertEqualsCanonicalizing([$maria->id], $ids);

        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_USERNAME, 'joaosil'))->get_sql());
        $this->assertEqualsCanonicalizing([$joao->id], $ids);
    }

    /**
     * LIKE wildcard characters (e.g. '%') present in the search text must be escaped, so they
     * are treated as literal characters and do not turn the search into a match-everything query.
     */
    public function test_get_sql_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        [$course, $maria, , $ana] = $this->create_course_with_users();

        $generator = $this->getDataGenerator();
        $percentuser = $generator->create_user([
            'firstname' => 'Desconto',
            'lastname' => '50% Off',
            'email' => 'desconto50off@example.com',
            'username' => 'desconto50off',
        ]);
        $generator->enrol_user($percentuser->id, $course->id, 'student');

        // A literal '%' in the search text must only match the user whose name actually
        // contains a '%' character, not every user in the site (which is what would happen
        // if '%' were left as an unescaped SQL LIKE wildcard).
        $ids = $this->query_user_ids((new user_filter(user_filter::FIELD_FULLNAME, '%'))->get_sql());
        $this->assertEqualsCanonicalizing([$percentuser->id], $ids);
        $this->assertNotContains((int) $maria->id, $ids);
        $this->assertNotContains((int) $ana->id, $ids);
    }

    /**
     * A user without the 'moodle/site:viewuseridentity' capability (e.g. a student) must only
     * be offered the fullname field.
     */
    public function test_get_field_options_without_viewuseridentity(): void {
        $this->resetAfterTest();
        [$course, , , $ana] = $this->create_course_with_users();
        $context = \context_course::instance($course->id);

        $this->setUser($ana);
        $options = user_filter::get_field_options($context);

        $this->assertArrayHasKey(user_filter::FIELD_FULLNAME, $options);
        $this->assertArrayNotHasKey(user_filter::FIELD_EMAIL, $options);
        $this->assertArrayNotHasKey(user_filter::FIELD_USERNAME, $options);
    }

    /**
     * A user with the 'moodle/site:viewuseridentity' capability (e.g. an editing teacher) must
     * be offered all three filter fields.
     */
    public function test_get_field_options_with_viewuseridentity(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $context = \context_course::instance($course->id);

        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/site:viewuseridentity', $context));

        $options = user_filter::get_field_options($context);

        $this->assertArrayHasKey(user_filter::FIELD_FULLNAME, $options);
        $this->assertArrayHasKey(user_filter::FIELD_EMAIL, $options);
        $this->assertArrayHasKey(user_filter::FIELD_USERNAME, $options);
        $this->assertCount(3, $options);
    }
}
