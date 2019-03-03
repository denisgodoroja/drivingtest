<?php

namespace App\Controller;

use App\Service\DrivingTest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppController extends AbstractController
{
    public function index(DrivingTest $test, TranslatorInterface $t)
    {
        $results = $this->buildResults($test);

        if ($test->isRunning()) {
            return $this->render('app/question.html.twig', [
                'results' => $results,
                'question' => $test->getCurrentQuestion(),
                'time_left' => $this->formatTimer($test->getTimeLeft()),
                'seconds_left' => $test->getTimeLeft(),
            ]);
        }

        return $this->render('app/list.html.twig', [
            'results' => $results,
            'num_tests' => DrivingTest::NUM_TESTS,
        ]);
    }

    public function start($test_number = 0, DrivingTest $test = null, TranslatorInterface $t = null)
    {
        if (!$test->isRunning()) {
            $test->start($test_number);
        } else {
            $this->addFlash('danger', $t->trans('message.test_already_started'));
        }

        return $this->redirectToRoute('app_index');
    }

    public function end(DrivingTest $test, TranslatorInterface $t)
    {
        if ($test->isRunning()) {
            $test->end();
        } else {
            $this->addFlash('danger', $t->trans('message.no_test_running'));
        }

        return $this->redirectToRoute('app_index');
    }

    public function answer($answer_number, DrivingTest $test, TranslatorInterface $t)
    {
        if ($test->isRunning()) {
            $answer_number = (int) $answer_number;
            if ($test->answer($answer_number)) {
                $question = $test->getCurrentQuestion();
                if ($answer_number == $question['correct_answer']) {
                    $test->nextQuestion();
                    $this->addFlash('success', $t->trans('message.correct_answer'));
                } else {
                    $this->addFlash('warning', $t->trans('message.wrong_answer', ['%i%' => $question['correct_answer']]));
                    if (!empty($question['explanation'])) {
                        $this->addFlash('warning', $question['explanation']);
                    }
                }
            } else {
                $this->addFlash('danger', $t->trans('message.invalid_answer'));
            }
        }

        return $this->redirectToRoute('app_index');
    }

    public function nextQuestion(DrivingTest $test)
    {
        $test->nextQuestion();

        return $this->redirectToRoute('app_index');
    }


    public function gotoQuestion($question_number, DrivingTest $test)
    {
        $test->nextQuestion($question_number);

        if ($test->isRunning()) {
            return $this->redirectToRoute('app_index');
        }

        return $this->render('app/question.html.twig', [
            'results' => $this->buildResults($test),
            'question' => $test->getCurrentQuestion(),
            'time_left' => 0,
            'seconds_left' => 0,
        ]);
    }

    protected function buildResults(DrivingTest $test)
    {
        $results = [];
        foreach ($test->getQuestions() as $question) {
            if ($question['answer']) {
                $results[$question['question_number']] = ($question['answer'] == $question['correct_answer'] ? 1 : -1);
            } else {
                $results[$question['question_number']] = 0;
            }
        }

        return $results;
    }

    protected function formatTimer($seconds)
    {
        $minutes = (int) ($seconds / 60);
        $seconds -= $minutes * 60;

        return sprintf("%02d:%02d", $minutes, $seconds);
    }
}
