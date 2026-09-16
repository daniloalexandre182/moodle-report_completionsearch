@report @report_completionsearch
Feature: Teacher can search users in the course and activity completion reports
  In order to find the completion data of specific students
  As a teacher
  I need to filter the course completion and activity completion reports by a user search

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "users" exist:
      | username   | firstname | lastname | email                   |
      | teacher1   | Teacher   | One      | teacher1@example.com    |
      | mariasouza | Maria     | Souza    | maria.souza@example.com |
      | joaosilva  | Joao      | Silva    | joao.silva@example.com  |
      | anacosta   | Ana       | Costa    | ana.costa@example.com   |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | teacher1   | C1     | editingteacher |
      | mariasouza | C1     | student        |
      | joaosilva  | C1     | student        |
      | anacosta   | C1     | student        |
    And the following "activities" exist:
      | activity | name      | intro     | course | idnumber | completion | completionview |
      | page     | PageName1 | PageDesc1 | C1     | PAGE1    | 1          | 0              |

  Scenario: Opening the report only shows the filter form until the search is submitted
    Given I am on the "C1" "Course" page logged in as "teacher1"
    When I navigate to "Reports" in current page administration
    And I click on "Completion search" "link" in the "region-main" "region"
    Then "id_searchfield" "field" should exist
    And "id_search" "field" should exist
    And "id_report" "field" should exist
    And the "id_searchfield" select box should contain "Full name"
    And the "id_searchfield" select box should contain "Email"
    And the "id_searchfield" select box should contain "Username"
    And the "id_report" select box should contain "Course completion"
    And the "id_report" select box should contain "Activity completion"
    And the field "id_report" matches value "Course completion"
    And I should see "Leave the text empty to list all users."
    And "completion-progress" "table" should not exist
    And I should not see "Maria Souza"
    And I should not see "Joao Silva"
    And I should not see "Ana Costa"

  @javascript
  Scenario: Searching by partial full name in the course completion report shows only the matching user
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Page - PageName1 | 1 |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Completion search" "link" in the "region-main" "region"
    When I set the field "id_searchfield" to "Full name"
    And I set the field "id_search" to "ria sou"
    And I set the field "id_report" to "Course completion"
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    Then "completion-progress" "table" should exist
    And I should see "All participants: 1/3"
    And I should see "Maria Souza" in the "completion-progress" "table"
    And I should not see "Joao Silva" in the "completion-progress" "table"
    And I should not see "Ana Costa" in the "completion-progress" "table"
    And the field "id_search" matches value "ria sou"
    And the field "id_report" matches value "Course completion"
    # The search is case-insensitive.
    And I set the field "id_search" to "SILVA"
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    And I should see "Joao Silva" in the "completion-progress" "table"
    And I should not see "Maria Souza" in the "completion-progress" "table"
    And I should not see "Ana Costa" in the "completion-progress" "table"
    # An empty search lists every tracked user.
    And I set the field "id_search" to ""
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    And I should see "All participants: 3"
    And I should see "Maria Souza" in the "completion-progress" "table"
    And I should see "Joao Silva" in the "completion-progress" "table"
    And I should see "Ana Costa" in the "completion-progress" "table"

  Scenario: Searching by email in the activity completion report shows only the matching user
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Completion search" "link" in the "region-main" "region"
    When I set the field "id_searchfield" to "Email"
    And I set the field "id_search" to "joao.silva@"
    And I set the field "id_report" to "Activity completion"
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    Then "completion-progress" "table" should exist
    And I should see "PageName1" in the "completion-progress" "table"
    And I should see "Joao Silva" in the "completion-progress" "table"
    And I should not see "Maria Souza" in the "completion-progress" "table"
    And I should not see "Ana Costa" in the "completion-progress" "table"
    And the field "id_searchfield" matches value "Email"
    And the field "id_report" matches value "Activity completion"

  Scenario: An empty search in the activity completion report lists all the students
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Completion search" "link" in the "region-main" "region"
    When I set the field "id_search" to ""
    And I set the field "id_report" to "Activity completion"
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    Then "completion-progress" "table" should exist
    And I should see "Maria Souza" in the "completion-progress" "table"
    And I should see "Joao Silva" in the "completion-progress" "table"
    And I should see "Ana Costa" in the "completion-progress" "table"
    And I should not see "Teacher One" in the "completion-progress" "table"

  Scenario: A search without matches in the activity completion report shows nothing to display
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Completion search" "link" in the "region-main" "region"
    When I set the field "id_searchfield" to "Username"
    And I set the field "id_search" to "nobodyhasthisusername"
    And I set the field "id_report" to "Activity completion"
    And I click on "Search" "button" in the "#report-completionsearch-filter" "css_element"
    Then I should see "Nothing to display"
    And "completion-progress" "table" should not exist
    And I should not see "Maria Souza"
    And I should not see "Joao Silva"
    And I should not see "Ana Costa"

  Scenario: The report is not listed for a teacher without the view capability
    Given the following "permission overrides" exist:
      | capability                   | permission | role           | contextlevel | reference |
      | report/completionsearch:view | Prevent    | editingteacher | Course       | C1        |
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    Then "Activity completion" "link" should exist in the "region-main" "region"
    And "Completion search" "link" should not exist in the "region-main" "region"

  Scenario: The report is not listed in a course without completion tracking enabled
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 2 | C2        | 0        | 0                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | name      | intro     | course | idnumber |
      | page     | PageName2 | PageDesc2 | C2     | PAGE2    |
    When I am on the "C2" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    Then "Logs" "link" should exist in the "region-main" "region"
    And "Completion search" "link" should not exist in the "region-main" "region"
