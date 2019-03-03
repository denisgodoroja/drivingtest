<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DrivingTest
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

        $test_data = [
            'start_time' => $_SERVER['REQUEST_TIME'],
            'end_time' => 0,
            'time_limit' => $this->time_limit,
            'current' => 1,
            'questions' => [],
        ];

        for ($question = 1; $question <= self::NUM_QUESTIONS; $question++) {
            $test = $test_number ?: mt_rand(1, self::NUM_TESTS);
            $test_data['questions'][$question] = [
                'test_number' => $test,
                'question_number' => $question,
                'num_answers' => self::DEFAULT_NUM_ANSWERS,
                'correct_answer' => $details[$test]['v' . $question] ?? 0,
                'filepath' => '/images/' . $test . '-' . $question . '.jpg',
                'answer' => 0,
                'answered_at' => 0,
            ];
        }

        $this->saveData($test_data);
    }

    public function end()
    {
        $test_data = $this->loadData();
        if (empty($test_data['end_time'])) {
            $test_data['end_time'] = $_SERVER['REQUEST_TIME'];
        }
        $this->saveData($test_data);
    }

    public function isRunning()
    {
        if ($test_data = $this->loadData()) {
            if ($test_data['time_limit']) {
                if ($_SERVER['REQUEST_TIME'] - $test_data['start_time'] >= $test_data['time_limit']) {
                    $this->end();
                }
            }

            if (!$test_data['end_time']) {
                return true;
            }
        }

        return false;
    }

    public function getQuestions()
    {
        $test_data = $this->loadData();

        return $test_data['questions'] ?? [];
    }

    public function getQuestion($number)
    {
        $test_data = $this->loadData();
        if (empty($test_data['questions'][$number])) {
            return [];
        }

        return $test_data['questions'][$number];
    }

    public function getCurrentQuestionNumber()
    {
        $test_data = $this->loadData();
        return $test_data['current'] ?? 0;
    }

    public function getCurrentQuestion()
    {
        return $this->getQuestion($this->getCurrentQuestionNumber());
    }

    public function getTimeLeft()
    {
        $test_data = $this->loadData();
        if (empty($test_data['time_limit'])) {
            return 0;
        }

        return max(0, $test_data['time_limit'] - ($_SERVER['REQUEST_TIME'] - $test_data['start_time']));
    }

    public function answer($answer_number, $question_number = 0)
    {
        $test_data = $this->loadData();
        if ($this->isRunning() && $answer_number) {
            if (!$question_number) {
                $question_number = $this->getCurrentQuestionNumber();
            }

            if (isset($test_data['questions'][$question_number])) {
                $test_data['questions'][$question_number]['answer'] = $answer_number;
            }

            $this->saveData($test_data);
            return true;
        }
        return false;
    }

    public function nextQuestion($question_number = 0)
    {
        $test_data = $this->loadData();
        $question_number = max(0, min($question_number, self::NUM_QUESTIONS));
        if ($question_number) {
            $test_data['current'] = $question_number;
        } elseif ($this->isRunning()) {
            $ids = array_keys($this->getUnansweredQuestions());

            // End test only if all questions are answered
            if (!$ids) {
                $this->end();
                return;
            }

            // Get next unanswered question
            foreach ($ids as $id) {
                if ($id > $test_data['current']) {
                    $question_number = $id;
                    break;
                }
            }

            // Rewind unanswered question from beginning
            if (!$question_number) {
                $question_number = array_shift($ids);
            }

            $test_data['current'] = $question_number;
        } else {
            $test_data['current']++;
            if ($test_data['current'] > self::NUM_QUESTIONS) {
                $test_data['current'] = self::NUM_QUESTIONS;
            }
        }

        $this->saveData($test_data);
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

    protected function buildQuestionId($question)
    {
        return $question['test_number'] . '-' . $question['question_number'];
    }

    /**
     * @return array
     */
    protected function loadData()
    {
        return $this->session->get('autotest', []);
    }

    protected function saveData(array $test_data)
    {
        $this->session->set('autotest', $test_data);
    }
}
