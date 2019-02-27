# Description #
---------------

 * 35 tests with 20 questions each.
 * Each question is represented by a specific image `resources/images/<TEST_NO>-<QUESTION_NO>.jpg`.
 * The right answers are encoded in a json file: `resources\tests.json`.
 * The text and answers of each question is embeeded in its image.
 * Each question has a variable number of potential answers (up to 5) but only one is correct.
 * The administrator is allowed to override the question's parameters: number of answers, the correct answer, the explanation.

Question's structure:
 - test's number
 - order number
 - title,
 - image,
 - number of potential answers,
 - the right answer
 - some explanation of the right answer

## Statistics ##
----------------

Anonymous users may enter a name to keep their statistics. Registered users have their uid as point of linkage. If the users does not introduce the name, only the last test will be kept in the session, while for the rest of users, the data will be kept in the database.

autotest_tests (
  id,
  uid,
  username,
  start_time,
  end_time,
  time_limit,
  current, // May be calculatet from the end
)

autotest_test_questions (
  id,
  test_id, // REFERENCE to autotest_tests.id
  test_number,
  question_number,
  filepath,
  correct_answer,
  answer,
  answered_at
)

autotest_alters (
  question, // 'TEST_NO-QUESTION-NO'
  correct_answer,
  num_answers,
  explanation, // Some explanation to show when a wrong answer was given.
)

# TODO #
--------

+ Come back to some question for later view or answer (even after test is over)
+ The timer should be animated by JavaScript
+ Administrative question alter and override from db
- User's statistics
