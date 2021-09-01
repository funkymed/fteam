<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use App\Service\Gitlab;

class CelerityCommand extends Command
{
    use Helper;

    private $gitlab;
    private $input;
    private $output;

    protected static $defaultName = 'celerity:summary';

    public function __construct(Gitlab $gitlab)
    {
        $this->gitlab = $gitlab;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Display celerity by user from selected milestones.')
            ->addOption(
                'milestone',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Select the milestones you want the celerity',
                []
            )
            ->addOption(
                'label',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Get more detail on epic',
                []
            )
            ->setDescription('Display celerity by user from selected milestones.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $milestones = $input->getOption('milestone');
        $listMilestones = $this->gitlab->getMilestones(false);
        if (!$milestones) {
            $milestones = $this->askChoice('Select the milestone', $listMilestones['compact'], 'This milestone %s is invalid.', true);
        }

        $labels=$input->getOption('label');
        if (count($labels)<1) {
            $labels = $this->askChoice('Select the labels', $this->gitlab->getLabels(), 'This label %s is invalid.', true);
        }

        $assignees=[];
        foreach ($milestones as $milestone) {
            $this->output->writeln("");
            $this->output->writeln(sprintf("Milestone : \t<comment>%s</comment>", $milestone));

            $MilestoneDates = $this->getMilestoneDetailByName($listMilestones['full'], $milestone);
            $days = $MilestoneDates['days'];
            $startDate = $MilestoneDates['start'];
            $dueDate = $MilestoneDates['due'];
            $this->output->writeln(sprintf("Start date : \t<comment>%s</comment>", $startDate));
            $this->output->writeln(sprintf("Due date : \t<comment>%s</comment>", $dueDate));
            $this->output->writeln(sprintf("Days : \t\t<comment>%s</comment>", $days));

            $issues = $this->gitlab->getIssues([
                'milestone'=>$milestone,
                'per_page'=>500,
                'labels'=>implode(',', $labels),
            ]);
            // Collect milestone data by people
            foreach ($issues as $issue) {
                $assignee = $this->gitlab->getAssigned($issue);
                if (!$assignee) {
                    continue;
                }

                if (!isset($assignees[$assignee->username])) {
                    $assignees[$assignee->username]=[
                        'name' => $assignee->name,
                        'milestones'=>[],
                    ];
                }

                if (!isset($assignees[$assignee->username]['milestones'][$milestone])) {
                    $assignees[$assignee->username]['milestones'][$milestone]=[
                        'weights'=>0,
                        'days'=>$days,
                    ];
                }

                $weight = $issue->weight ? $issue->weight : 0;
                $closed = $issue->state === Gitlab::STATUS_CLOSED ? 1 : 0;
                if ($closed) {
                    $assignees[$assignee->username]['milestones'][$milestone]['weights']+= $weight;
                } else {
                    // finished
                    foreach ($this->gitlab->workflow_finished as $finished) {
                        if ($this->gitlab->isIssueLabel($issue, $finished)) {
                            $assignees[$assignee->username]['milestones'][$milestone]['weights']+= $weight;
                            break;
                        }
                    }
                }
            }
        }

        // Caculate celerity by milestone by people
        foreach ($assignees as $assignee=>$assigneeData) {
            $totalCelerity = 0;
            $totalDays = 0;
            $totalWeights=0;
            foreach ($assigneeData['milestones'] as $milestone=>$milestoneData) {
                $weights = $milestoneData['weights'];
                $days = $milestoneData['days'];
                $totalWeights+=$weights;
                $assignees[$assignee]['milestones'][$milestone]['celerity']=$weights/$days;
                $totalCelerity += $assignees[$assignee]['milestones'][$milestone]['celerity'];
                $totalDays += $days;
            }
            $assignees[$assignee]['celerity']=number_format($totalCelerity/count($assigneeData['milestones']), 1);
            $assignees[$assignee]['nb_sprints']=count($assigneeData['milestones']);
            $assignees[$assignee]['weights']=$totalWeights;
            unset($assignees[$assignee]['milestones']);
        }

        ksort($assignees);

        $this->renderTable(['Peoples','Celerity','NB Sprints','Total Weights'], $assignees);


        return Command::SUCCESS;
    }
}
