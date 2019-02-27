<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Autotest
{
    const NUM_TESTS = 35;
    const NUM_QUESTIONS = 20;
    const DEFAULT_NUM_ANSWERS = 5;

    protected $questions_cache = [];
    protected $time_limit = 0;
    protected $data_filepath = '';

    /**
     * @var SessionInterface
     */
    protected $session;

    public function __construct($time_limit, $data_filepath, SessionInterface $session)
    {
        $this->time_limit = $time_limit * 60;
        $this->data_filepath = $data_filepath;
        $this->session = $session;
    }

    public function start($test_number = 0)
    {
        $test_number = (int) max(0, min($test_number, self::NUM_TESTS));
        $details = json_decode(file_get_contents($this->data_filepath), TRUE);

        $quiz = [
            'start_time' => $_SERVER['REQUEST_TIME'],
            'end_time' => 0,
            'time_limit' => $this->time_limit,
            'current' => 1,
            'questions' => [],
        ];

        for ($question = 1; $question <= self::NUM_QUESTIONS; $question++) {
            $test = $test_number ?: mt_rand(1, self::NUM_TESTS);
            $quiz['questions'][$question] = [
                'test_number' => $test,
                'question_number' => $question,
                'num_answers' => self::DEFAULT_NUM_ANSWERS,
                'correct_answer' => $details[$test]['v' . $question] ?? 0,
                'filepath' => '/images/' . $test . '-' . $question . '.jpg',
                'answer' => 0,
                'answered_at' => 0,
            ];
        }

        $this->session->set('autotest', $quiz);
    }

    public function end()
    {
        $quiz = $this->session->get('autotest', []);
        if (empty($quiz['end_time'])) {
            $quiz['end_time'] = $_SERVER['REQUEST_TIME'];
        }
        $this->session->set('autotest', $quiz);
    }

    public function isRunning()
    {
        if ($quiz = $this->session->get('autotest')) {
            if ($quiz['time_limit']) {
                if ($_SERVER['REQUEST_TIME'] - $quiz['start_time'] >= $quiz['time_limit']) {
                    $this->end();
                }
            }

            if (!$quiz['end_time']) {
                return true;
            }
        }

        return false;
    }

    public function getQuestions()
    {
        $quiz = $this->session->get('autotest');

        return $quiz['questions'] ?? [];
    }

    public function getQuestion($number)
    {
        $quiz = $this->session->get('autotest');
        if (empty($quiz['questions'][$number])) {
            return [];
        }

        //return $this->overrideQuestion($quiz['questions'][$number]);
        return $quiz['questions'][$number];
    }

    public function getCurrentQuestionNumber()
    {
        $quiz = $this->session->get('autotest');
        return $quiz['current'] ?? 0;
    }

    public function getCurrentQuestion()
    {
        return $this->getQuestion($this->getCurrentQuestionNumber());
    }

    public function getTimeLeft()
    {
        $quiz = $this->session->get('autotest');
        if (empty($quiz['time_limit'])) {
            return 0;
        }

        return max(0, $quiz['time_limit'] - ($_SERVER['REQUEST_TIME'] - $quiz['start_time']));
    }

    public function answer($answer_number, $question_number = 0)
    {
        $quiz = $this->session->get('autotest');
        if ($this->isRunning() && $answer_number) {
            if (!$question_number) {
                $question_number = $this->getCurrentQuestionNumber();
            }

            if (isset($quiz['questions'][$question_number])) {
                $quiz['questions'][$question_number]['answer'] = $answer_number;
            }

            $this->session->set('autotest', $quiz);
            return true;
        }
        return false;
    }

    public function nextQuestion($question_number = 0)
    {
        $quiz = $this->session->get('autotest');
        $question_number = max(0, min($question_number, self::NUM_QUESTIONS));
        if ($question_number) {
            $quiz['current'] = $question_number;
        } elseif ($this->isRunning()) {
            $ids = array_keys($this->getUnansweredQuestions());

            // End test only if all questions are answered
            if (!$ids) {
                $this->end();
                return;
            }

            // Get next unanswered question
            foreach ($ids as $id) {
                if ($id > $quiz['current']) {
                    $question_number = $id;
                    break;
                }
            }

            // Rewind unanswered question from beginning
            if (!$question_number) {
                $question_number = array_shift($ids);
            }

            $quiz['current'] = $question_number;
        } else {
            $quiz['current']++;
            if ($quiz['current'] > self::NUM_QUESTIONS) {
                $quiz['current'] = self::NUM_QUESTIONS;
            }
        }

        $this->session->set('autotest', $quiz);
    }

    protected function getUnansweredQuestions()
    {
        $list = [];
        foreach ($this->getQuestions() as $id => $question) {
            if ($question['answer'] == 0) {
                $list[$id] = $question;
            }
        }

        return $list;
    }

    public function overrideQuestion($question, $reset = false)
    {
        $id = $this->buildQuestionId($question);

        if (!isset($this->questions_cache[$id]) || $reset) {
            $connection = \Drupal::database();
            $data = $connection->select('autotest_alters', 'a')
                ->fields('a')
                ->condition('question', $id)
                ->execute()
                ->fetchAssoc();
            if ($data) {
                $this->questions_cache[$id] = array_merge($question, $data);
            } else {
                $this->questions_cache[$id] = FALSE;
            }
        }

        return $this->questions_cache[$id] ?: $question;
    }

    public function saveQuestion($question)
    {
        $fields = [
            'correct_answer' => $question['correct_answer'],
            'num_answers' => $question['num_answers'],
            'explanation' => $question['explanation'],
        ];
        if (empty($question['question'])) {
            $fields['question'] = $this->buildQuestionId($question);
            $connection = \Drupal::database();
            $connection->insert('autotest_alters')
                ->fields($fields)
                ->execute();
        } else {
            $connection = \Drupal::database();
            $connection->update('autotest_alters')
                ->fields($fields)
                ->condition('question', $question['question'])
                ->execute();
        }
    }

    public function resetQuestion($question)
    {
        if (empty($question['question'])) {
            return false;
        }
        $connection = \Drupal::database();
        $connection->delete('autotest_alters')
            ->condition('question', $question['question'])
            ->execute();
        return true;
    }

    protected function buildQuestionId($question)
    {
        return $question['test_number'] . '-' . $question['question_number'];
    }
}
