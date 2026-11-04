@local @local_recertify
Feature: See who needs to recertify and switch it on
  In order to keep mandatory training current
  As a teacher
  I need to see each learner's certification status and set how long a completion stays valid

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tess      | Teacher  |
      | learner1 | Lea       | Learner  |
      | learner2 | Liam      | Late     |
    And the following "courses" exist:
      | fullname    | shortname | enablecompletion |
      | Fire safety | FS        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | FS     | editingteacher |
      | learner1 | FS     | student        |
      | learner2 | FS     | student        |
    And "learner1" completed the course "FS" 350 days ago

  Scenario: Preview while off, then switch it on
    Given I am on the "FS" "local_recertify > Status" page logged in as "teacher1"
    Then I should see "Recertification is off in this course"
    And I should see "expiring soon" in the "Lea Learner" "table_row"
    And I should see "not completed" in the "Liam Late" "table_row"
    And I should see "If you turn it on, 0 learner(s) would be reset in the next 7 days."
    When I set the following fields to these values:
      | Recertification on      | 1    |
      | Valid for (days)        | 355  |
      | Reminders (days before) | 30,7 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should not see "Recertification is off in this course"
    And I should see "Certifications last 355 days"
    And I should see "1 learner(s) will be reset in the next 7 days."
