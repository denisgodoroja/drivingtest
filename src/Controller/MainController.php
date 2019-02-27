<?php

namespace App\Controller;

use App\Service\Autotest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

class MainController extends AbstractController
{
    public function index(Autotest $autotest, TranslatorInterface $t)
    {
        $results = $this->buildResults($autotest);

        if ($autotest->isRunning()) {
            return $this->render('main/question.html.twig', [
                'results' => $results,
                'question' => $autotest->getCurrentQuestion(),
                'time_left' => $this->formatTimer($autotest->getTimeLeft()),
                'seconds_left' => $autotest->getTimeLeft(),
            ]);
        }

        return $this->render('main/list.html.twig', [
            'results' => $results,
            'num_tests' => Autotest::NUM_TESTS,
        ]);
    }

    public function start($test_number = 0, Autotest $autotest = null, TranslatorInterface $t = null)
    {
        if (!$autotest->isRunning()) {
            $autotest->start($test_number);
        } else {
            $this->addFlash('error', $t->trans('message.quiz_already_started'));
        }

        return $this->redirectToRoute('app_index');
    }

    public function end(Autotest $autotest, TranslatorInterface $t)
    {
        if ($autotest->isRunning()) {
            $autotest->end();
        } else {
            $this->addFlash('error', $t->trans('message.no_quiz_running'));
        }

        return $this->redirectToRoute('app_index');
    }

    public function answer($answer_number, Autotest $autotest, TranslatorInterface $t)
    {
        if ($autotest->isRunning()) {
            $answer_number = (int) $answer_number;
            if ($autotest->answer($answer_number)) {
                $question = $autotest->getCurrentQuestion();
                if ($answer_number == $question['correct_answer']) {
                    $autotest->nextQuestion();
                    $this->addFlash('notice', $t->trans('message.correct_answer'));
                } else {
                    $this->addFlash('warning', $t->trans('message.wrong_answer', ['%i%' => $question['correct_answer']]));
                    if (!empty($question['explanation'])) {
                        $this->addFlash('warning', $question['explanation']);
                    }
                }
            } else {
                $this->addFlash('error', $t->trans('message.invalid_answer'));
            }
        }

        return $this->redirectToRoute('app_index');
    }

    public function nextQuestion(Autotest $autotest)
    {
        $autotest->nextQuestion();

        return $this->redirectToRoute('app_index');
    }


    public function gotoQuestion($question_number, Autotest $autotest)
    {
        $autotest->nextQuestion($question_number);

        if ($autotest->isRunning()) {
            return $this->redirectToRoute('app_index');
        }

        return $this->render('main/question.html.twig', [
            'results' => $this->buildResults($autotest),
            'question' => $autotest->getCurrentQuestion(),
            'time_left' => 0,
            'seconds_left' => 0,
        ]);
    }

    protected function buildResults(Autotest $autotest)
    {
        $results = [];
        foreach ($autotest->getQuestions() as $question) {
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
