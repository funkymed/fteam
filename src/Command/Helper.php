<?php

namespace App\Command;

use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;

trait Helper
{
    public function askChoice($title, array $options, $error, $multiple=false)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            $title,
            $options,
            0
        );

        $question->setMultiselect($multiple);
        // $question->setMultiline(true);
        // $question->setAutocompleterValues($options);

        $question->setErrorMessage($error);
        $color = $helper->ask($this->input, $this->output, $question);
        return $color;
    }

    private function renderTable($headers, $rows)
    {
        $table = new Table($this->output);
        $table->setStyle('box');
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;

        $table->render();
    }

    private function displayProgressBar($title, $current, $max)
    {
        $progressBar = new ProgressBar($this->output, $max);
        $progressBar->setFormat($title.' : %current%/%max% [%bar%] %percent:3s%%');
        $progressBar->setProgress($current);
        $this->output->writeln("\n");
    }

    public function getWeekdayDifference(\DateTime $startDate, \DateTime $endDate)
    {
        $isWeekday = function (\DateTime $date) {
            return $date->format('N') < 6;
        };

        $days = $isWeekday($endDate) ? 1 : 0;

        while ($startDate->diff($endDate)->days > 0) {
            $days += $isWeekday($startDate) ? 1 : 0;
            $startDate = $startDate->add(new \DateInterval("P1D"));
        }

        return $days;
    }

    public function getWorkflowName($name)
    {
        $name = str_replace('_', '-', $name);
        $name = str_replace('::', ':', $name);
        $name = explode(':', $name);
        $name = count($name) > 1 ? $name[1] : $name[0];
        $name = mb_strimwidth($name, 0, 10, "...");
        return UCFirst(strtolower($name));
    }

    public function getMilestoneDetailByName($listMilestones, $name)
    {
        foreach ($listMilestones as $itemMilestone) {
            if ($itemMilestone->title===$name) {
                $days = $this->getWeekdayDifference(
                    new \DateTime($itemMilestone->start_date),
                    new \DateTime($itemMilestone->due_date)
                );
                return ['start'=>$itemMilestone->start_date,'due'=>$itemMilestone->due_date,'days'=>$days];
            }
        }
    }
}
