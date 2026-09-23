@availability @availability_competency
Feature: Restrict access by competency proficiency
  In order to sequence learning by competencies
  As a teacher
  I need to restrict an activity to learners rated proficient in a competency

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Framework | FW1      |
    And the following "core_competency > competencies" exist:
      | shortname     | competencyframework | idnumber |
      | Teamwork      | FW1                 | TW       |
      | Communication | FW1                 | CM       |
      | Leadership    | FW1                 | LD       |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | TW         |
      | C1     | CM         |
    And the following "core_competency > user_competency_courses" exist:
      | course | competency | user     | grade |
      | C1     | TW         | student1 | D     |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | page     | Page 1 | C1     | page1    |

  @javascript
  Scenario: Only the learner rated proficient in the course can open the restricted activity
    Given I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Competency" "button" in the "Add restriction..." "dialogue"
    And I set the field "Course competency" to "Teamwork"
    And I set the field "Proficient" to "Yes – In this course"
    And I press "Save and return to course"
    And I should see "You must be proficient in the competency Teamwork in this course"
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "Not available unless"
    And I am on the "Course 1" "course" page logged in as "student2"
    And I should see "Not available unless"

  @javascript
  Scenario: Editing an activity keeps a restriction on a competency no longer linked to the course
    Given the following "availability_competency > activity restrictions" exist:
      | activity | competency | proficient | scope  |
      | page1    | LD         | 1          | global |
    And I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then the field "Course competency" matches value "Leadership (not linked to this course)"
    And the field "Proficient" matches value "Yes – Global"
    And I press "Save and return to course"
    And I should see "You must be proficient in the competency Leadership"
    And I should not see "You must be proficient in the competency Leadership in this course"

  @javascript
  Scenario: A restriction saved before the scope option keeps reading the course rating
    Given the following "availability_competency > activity restrictions" exist:
      | activity | competency | proficient |
      | page1    | TW         | 1          |
    When I am on the "Page 1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then the field "Course competency" matches value "Teamwork"
    And the field "Proficient" matches value "Yes – In this course"
    And I press "Save and return to course"
    And I should see "You must be proficient in the competency Teamwork in this course"
    And I am on the "Course 1" "course" page logged in as "student1"
    And I should not see "Not available unless"
    And I am on the "Course 1" "course" page logged in as "student2"
    And I should see "Not available unless"
