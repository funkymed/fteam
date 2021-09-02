<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\TableSeparator;

use App\Service\Gitlab;
use Symfony\Component\Console\Input\InputOption;

class IssueCommand extends Command
{
    use Helper;

    private $gitlab;
    private $input;
    private $output;

    protected static $defaultName = 'celerity:issue';

    public function __construct(Gitlab $gitlab)
    {
        $this->gitlab = $gitlab;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addOption(
                'milestone',
                null,
                InputOption::VALUE_REQUIRED,
                'The milestone',
                false
            )
            ->addOption(
                'not-assigned',
                null,
                InputOption::VALUE_NONE,
                'Only not assigned'
            )
            ->addOption(
                'assigned',
                null,
                InputOption::VALUE_NONE,
                'Only assigned'
            )
            ->addOption(
                'with-weight',
                null,
                InputOption::VALUE_NONE,
                'With weight defined'
            )
            ->addOption(
                'without-weight',
                null,
                InputOption::VALUE_NONE,
                'Without weight defined'
            )
            ->addOption(
                'state',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify the state (backlog|started|review|staging|blocked|rejected|preprod|closed)',
                ""
            )
            ->addOption(
                'label',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Get more detail on epic',
                []
            )
            ->setDescription('Display all the issues of a milestone by people')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $milestone = $input->getOption('milestone');
        $listMilestones = $this->gitlab->getMilestones(false);
        if (!$milestone) {
            $milestone = $this->askChoice('Select the milestone', $listMilestones['compact'], 'This milestone %s is invalid.');
        }

        $labels=$input->getOption('label');
        if (count($labels)<1) {
            $labels = $this->askChoice('Select the labels', $this->gitlab->getLabels(), 'This label %s is invalid.', true);
        }

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

        $tableIssues = [];
        $totalWeight = 0;
        $optionNotAssigned = $input->getOption('not-assigned');
        $optionAssigned = $input->getOption('assigned');

        $optionWithWeight = $input->getOption('with-weight');
        $optionWithoutWeight = $input->getOption('without-weight');

        $optionState = $input->getOption('state');

        $orderedIssues = [];
        foreach ($issues as $issue) {
            $assignee = $this->gitlab->getAssigned($issue);
            $orderedIssues[]=[$assignee ? $assignee->username : '', $issue, $assignee ? $assignee : null];
        }

        usort($orderedIssues, function ($a, $b) {
            return strcmp($a[0], $b[0]);
        });

        $currentUser = 'random_text';
        foreach ($orderedIssues as $k=>$data) {
            $issue = $data[1];
            $assignee = $data[2];

            $stateText = $this->gitlab->getState($issue);

            $state = false;
            if ($color = $this->gitlab->getColor($issue)) {
                $state = sprintf('<fg=%s>%s</>', $color, $stateText);
            }

            $weight =$this->gitlab->getWeight($issue);

            $totalWeight+=$weight;
            $actionAdd = false;

            if ($optionNotAssigned || $optionAssigned || $optionWithWeight || $optionWithoutWeight) {
                if ($optionNotAssigned && !$assignee) {
                    $actionAdd = true;
                } elseif ($optionAssigned && $assignee) {
                    $actionAdd = true;
                }
                if ($optionWithoutWeight && !$weight) {
                    $actionAdd = true;
                } elseif ($optionWithWeight && $weight) {
                    $actionAdd = true;
                }
            } else {
                $actionAdd = true;
            }

            if ($optionState!="") {
                $actionAdd = $optionState===$stateText ? true : false;
            }

            if ($actionAdd) {
                if ($k===0) {
                    $currentUser=$data[0];
                } elseif ($currentUser!=$data[0]) {
                    $currentUser=$data[0];
                    $tableIssues[]=new TableSeparator();
                }

                $title = $issue->title;
                $title = mb_strimwidth($title, 0, 80, "...");

                if (!$assignee || !$weight) {
                    $title = sprintf('<bg=red>%s</>', $issue->title);
                }

                $tableIssues[]=[
                    $title,
                    $state ? $state : '',
                    $weight ? $weight : '<fg=red>?</>',
                    $assignee ? $assignee->name : '<fg=red>?</>',
                    $issue->web_url,
                ];
            }
        }

        $this->output->writeln(sprintf("Issues : \t<comment>%s</comment>", count($tableIssues)));
        $this->output->writeln(sprintf("Total Weight : \t<comment>%s</comment>", $totalWeight));
        $this->output->writeln(sprintf("Label(s) : \t<comment>%s</comment>", implode(", ", $labels)));
        $this->output->writeln("");

        $this->renderTable([
            'Name',
            'State',
            'Weight',
            'Assigned to',
            'Url'
        ], $tableIssues);

        return Command::SUCCESS;
    }
}
