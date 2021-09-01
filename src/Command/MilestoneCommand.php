<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use App\Service\Gitlab;
use DateTime;

class MilestoneCommand extends Command
{
    use Helper;

    private $gitlab;
    private $input;
    private $output;
    private $isMarkDown=false;

    protected static $defaultName = 'celerity:milestone';

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
                'markdown',
                null,
                InputOption::VALUE_NONE,
                'Output a markdown version of the board'
            )
            ->addOption(
                'snapshot',
                null,
                InputOption::VALUE_NONE,
                'Display a simple and mardown board for snapshot purpose'
            )
            ->addOption(
                'epics',
                null,
                InputOption::VALUE_NONE,
                'Get the epics of the milestone'
            )
            ->addOption(
                'epics_detail',
                null,
                InputOption::VALUE_NONE,
                'Get more detail on epic'
            )
            ->addOption(
                'label',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Get more detail on epic',
                []
            )
            ->setDescription('Display all the peoples stats from a milestone')
        ;
    }

    private function flatten(array $array)
    {
        $return = array();
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    private function renderTable($headers, $rows)
    {
        $table = new Table($this->output);

        if ($this->isMarkDown) {
            $tableStyle = new TableStyle();
            $tableStyle
                ->setHorizontalBorderChars('-')
                ->setVerticalBorderChars('|')
                ->setDefaultCrossingChar('|')
            ;
            $table->setStyle($tableStyle);
        } else {
            $table->setStyle('box');
        }
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }

    private function formatNumber($number, $space=3)
    {
        $space= str_repeat(" ", $space-strlen($number));
        $number = $space.sprintf("%s", $number);
        return $number;
    }
    private function count_sum_tostring($array)
    {
        if (is_array($array) && count($array)>0) {
            return sprintf(
                "%s (%s)",
                $this->formatNumber(array_sum($array)),
                count($array)
            );
        } else {
            return '  0';
        }
    }

    private function alignCell($content, $align="left", $color="white")
    {
        return  new TableCell($content, ['style' => new TableCellStyle([
            'align' => $align ? $align : 'left',
            'fg' => $color,
        ])]);
    }

    private function initField($assignees, $total, $username, $label)
    {
        $assignees[$username][$label] = "  0";
        if (!isset($total['stats'][$username][$label])) {
            $total['stats'][$username][$label]=[];
        }
        return [$assignees, $total];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $this->isMarkDown = $input->getOption('markdown');

        $snapshot = $input->getOption('snapshot');
        if ($snapshot) {
            $this->isMarkDown = true;
        }

        $listMilestones = $this->gitlab->getMilestones(false);
        $milestone = $input->getOption('milestone');
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

        $assignees=[];

        $issues = $this->gitlab->getIssues([
            'milestone'=>$milestone,
            'per_page'=>500,
            'labels'=>implode(',', $labels),
        ]);

        $total = [
            'in_progress' => [],
            'finished' =>[],
            'nb_issues' => [],
            'total_weights' => [],
            'closed' => [],
            'stats'=>[],
        ];

        if ($this->gitlab->workflow_backlog) {
            $total[$this->gitlab->workflow_backlog] = [];
        }
        foreach ($this->gitlab->workflow_wip as $wip) {
            $total[$wip] = [];
        }
        if ($this->gitlab->workflow_blocked) {
            $total[$this->gitlab->workflow_blocked] = [];
        }
        if ($this->gitlab->workflow_rejected) {
            $total[$this->gitlab->workflow_rejected] = [];
        }
        if ($this->gitlab->workflow_backlog) {
            $total[$this->gitlab->workflow_backlog] = [];
        }
        foreach ($this->gitlab->workflow_accepted as $accepted) {
            $total[$accepted] = [];
        }

        $epics=[];
        if (count($issues)===0) {
            $this->output->writeln('');
            $this->output->writeln('<error>This milestone has no issue yet !</error>');
            return Command::SUCCESS;
        }

        foreach ($issues as $issue) {
            $assignee = $this->gitlab->getAssigned($issue);

            if (!$issue->weight || !$assignee) {
                continue;
            }

            $total['nb_issues'][]=$issue->weight;

            if ($issue->epic) {
                $epics[$issue->epic->iid]=$issue->epic;
            }

            // First entry in array for people
            if (!isset($assignees[$assignee->username])) {
                if ($snapshot) {
                    $assignees[$assignee->username]=[
                        'name' => $assignee->name,
                        'nb_issues' => '',
                    ];
                } else {
                    $assignees[$assignee->username]=[
                        'name' => $assignee->name,
                        'nb_issues' => '',

                        'avg_weight' => 0,
                    ];
                }

                $total['stats'][$assignee->username]=[];
                if ($this->gitlab->workflow_backlog) {
                    @list($assignees, $total) = $this->initField($assignees, $total, $assignee->username, $this->gitlab->workflow_backlog);
                }
                foreach ($this->gitlab->workflow_wip as $wip) {
                    @list($assignees, $total) = $this->initField($assignees, $total, $assignee->username, $wip);
                }
                if ($this->gitlab->workflow_blocked) {
                    @list($assignees, $total) = $this->initField($assignees, $total, $assignee->username, $this->gitlab->workflow_blocked);
                }
                if ($this->gitlab->workflow_rejected) {
                    @list($assignees, $total) = $this->initField($assignees, $total, $assignee->username, $this->gitlab->workflow_rejected);
                }
                foreach ($this->gitlab->workflow_accepted as $accepted) {
                    @list($assignees, $total) = $this->initField($assignees, $total, $assignee->username, $accepted);
                }

                $assignees[$assignee->username]['nb_issues_closed']= "  0";
                if (!$snapshot) {
                    $assignees[$assignee->username]['nb_issues_finished']= "  0";
                    $assignees[$assignee->username]['celerity']="  0.0";
                    $assignees[$assignee->username]['progress']="  0%";
                }
            }

            $weight = $issue->weight ? $issue->weight : 0;
            $total['total_weights'][] = $weight;
            $total['stats'][$assignee->username]['issues'][]=$weight;

            // Stats
            if (!isset($total['stats'][$assignee->username]['issues'])) {
                $total['stats'][$assignee->username]['issues']=[];
            }

            $assignees[$assignee->username]['nb_issues'] = $this->count_sum_tostring($total['stats'][$assignee->username]['issues']);
            if (!$snapshot) {
                $assignees[$assignee->username]['avg_weight'] = $this->alignCell(isset($days)
                                    ? number_format(array_sum($total['stats'][$assignee->username]['issues'])/$days, 1)
                                    : 0, 'center') ;
            }

            // backlog
            if ($this->gitlab->workflow_backlog) {
                $is_backlog = $this->gitlab->isIssueLabel($issue, $this->gitlab->workflow_backlog) ? 1 : 0;
                if ($is_backlog) {
                    $total[$this->gitlab->workflow_backlog][]=$weight;
                    $total['stats'][$assignee->username][$this->gitlab->workflow_backlog][]=$weight;
                }
                $assignees[$assignee->username][$this->gitlab->workflow_backlog] = $this->count_sum_tostring(
                    $total['stats'][$assignee->username][$this->gitlab->workflow_backlog]
                );
            }

            // wip
            foreach ($this->gitlab->workflow_wip as $wip) {
                $is_wip = $this->gitlab->isIssueLabel($issue, $wip) ? 1 : 0;
                if ($is_wip) {
                    $total['stats'][$assignee->username][$wip][]=$weight;
                    $total[$wip][] = $weight;
                    $total['in_progress'][] = $weight;
                }
                $assignees[$assignee->username][$wip] = $this->count_sum_tostring(
                    $total['stats'][$assignee->username][$wip]
                );
            }

            // blocked
            if ($this->gitlab->workflow_blocked) {
                $is_blocked = $this->gitlab->isIssueLabel($issue, $this->gitlab->workflow_blocked) ? 1 : 0;
                if ($is_blocked) {
                    $total['stats'][$assignee->username][$this->gitlab->workflow_blocked][]=$weight;
                    $total[$this->gitlab->workflow_blocked][]=$weight;
                }
                $assignees[$assignee->username][$this->gitlab->workflow_blocked] = $this->count_sum_tostring(
                    $total['stats'][$assignee->username][$this->gitlab->workflow_blocked]
                );
            }

            // rejected
            if ($this->gitlab->workflow_rejected) {
                $is_rejected = $this->gitlab->isIssueLabel($issue, $this->gitlab->workflow_rejected) ? 1 : 0;
                if ($is_rejected) {
                    $total['stats'][$assignee->username][$this->gitlab->workflow_rejected][]=$weight;
                    $total[$this->gitlab->workflow_rejected][]=$weight;
                }
                $assignees[$assignee->username][$this->gitlab->workflow_rejected] = $this->count_sum_tostring(
                    $total['stats'][$assignee->username][$this->gitlab->workflow_rejected]
                );
            }

            // accepted
            if (!isset($total['stats'][$assignee->username]['finished'])) {
                $total['stats'][$assignee->username]['finished']=[];
            }
            $foundAccepted = false;
            foreach ($this->gitlab->workflow_finished as $accepted) {
                $is_accepted = $this->gitlab->isIssueLabel($issue, $accepted) ? 1 : 0;
                if ($is_accepted) {
                    $total[$accepted][] = $weight;
                    $total['stats'][$assignee->username][$accepted][]=$weight;
                    $total['stats'][$assignee->username]['finished'][]=$weight;
                    $total['finished'][] = $weight;
                    $foundAccepted = true;
                    $assignees[$assignee->username][$accepted] = $this->count_sum_tostring($total['stats'][$assignee->username][$accepted]);
                    break;
                }
            }

            // closed
            $closed = $issue->state === Gitlab::STATUS_CLOSED ? 1 : 0;
            if ($closed) {
                // Don't count 2 times accepted
                if (!$foundAccepted) {
                    $total['finished'][] = $weight;
                    $total['stats'][$assignee->username]['finished'][]=$weight;
                }
                $total['closed'][] = $weight;
                $total['stats'][$assignee->username]['closed'][]=$weight;
                $assignees[$assignee->username]['nb_issues_closed'] = $this->count_sum_tostring($total['stats'][$assignee->username]['closed']);
            }

            if (!$snapshot) {
                // finished stats
                $assignees[$assignee->username]['nb_issues_finished'] = $this->count_sum_tostring($total['stats'][$assignee->username]['finished']);

                $assignees[$assignee->username]['celerity'] = $this->alignCell(isset($days)
                                                    ? number_format(array_sum($total['stats'][$assignee->username]['finished'])/$days, 1)
                                                    : 0, 'center');

                $assignees[$assignee->username]['progress'] = $this->alignCell(
                    round(
                        array_sum($total['stats'][$assignee->username]['finished']) / array_sum($total['stats'][$assignee->username]['issues'])*100
                    )." %",
                    'right'
                );
            }
        }

        $nb_peoples = count($assignees);
        $this->output->writeln(sprintf("Peoples : \t<comment>%s</comment>", $nb_peoples));
        $this->output->writeln(sprintf("Label(s) : \t<comment>%s</comment>", implode(", ", $labels)));

        // Display table
        $average =  count($total['total_weights']) ? number_format(array_sum($total['total_weights'])/$days, 1) : 0;
        //$averageFinished = $totalFinisheddWeight ? round(array_sum($totalFinisheddWeight)/count($totalFinisheddWeight)) : 0;

        ksort($assignees);
        if (!$this->isMarkDown) {
            $assignees[] = new TableSeparator();
        }

        $headerLastLine=[
            'people' => ['Peoples','white', 'Total']];


        // Cyan
        $headerLastLine['nb_issues'] = ['Issues', 'cyan', $this->count_sum_tostring($total['nb_issues'])];
        if (!$snapshot) {
            $headerLastLine['avg_weight'] = ['Celerity', 'cyan',
                number_format(array_sum($total['nb_issues'])/$nb_peoples/$days, 1)
            , 'center'];
        }

        // Workflow Backlog
        if ($this->gitlab->workflow_backlog) {
            $headerLastLine[$this->gitlab->workflow_backlog] = [$this->getWorkflowName($this->gitlab->workflow_backlog), 'green', $this->count_sum_tostring($total[$this->gitlab->workflow_backlog])];
        }
        // Workflow Work in Progress
        foreach ($this->gitlab->workflow_wip as $wip) {
            $headerLastLine[$wip] = [$this->getWorkflowName($wip), 'green', $this->count_sum_tostring($total[$wip])];
        }
        // Workflow Blocked
        if ($this->gitlab->workflow_blocked) {
            $headerLastLine[$this->gitlab->workflow_blocked] = [$this->getWorkflowName($this->gitlab->workflow_blocked), 'red', $this->count_sum_tostring($total[$this->gitlab->workflow_blocked])];
        }
        // Workflow Rejected
        if ($this->gitlab->workflow_rejected) {
            $headerLastLine[$this->gitlab->workflow_rejected] = [$this->getWorkflowName($this->gitlab->workflow_rejected), 'red', $this->count_sum_tostring($total[$this->gitlab->workflow_rejected])];
        }
        // Workflow Accepted
        foreach ($this->gitlab->workflow_accepted as $accepted) {
            $headerLastLine[$accepted] = [$this->getWorkflowName($accepted), 'yellow', $this->count_sum_tostring($total[$accepted])];
        }
        $headerLastLine['closed'] = ['Closed', 'yellow', $this->count_sum_tostring($total['closed'])];

        if (!$snapshot) {
            // Magenta
            $celerityMagenta = number_format(array_sum($total['finished'])/$nb_peoples/$days, 1);
            $progressMagenta = round(array_sum($total['finished']) / array_sum($total['nb_issues'])*100)." %";
            $finishedMagenta = $this->count_sum_tostring($total['finished']);

            $headerLastLine['nb_issues_finished']=['Finished', 'magenta', $finishedMagenta];
            $headerLastLine['celerity']=['Celerity', 'magenta', $celerityMagenta, 'center'];
            $headerLastLine['progress']=['% Progress', 'magenta', $progressMagenta,'right'];
        }

        $header = [];
        $lastLine = [];

        foreach ($headerLastLine as $data) {
            @list($headerText, $color, $footerText, $align) = $data;
            $header[]=$this->alignCell($headerText, $align, $color);
            $lastLine[]=$this->alignCell($footerText, $align, $color);
        }

        $assignees[] = $lastLine;

        // Display epics
        $optionEpics = $input->getOption('epics');

        $optionEpicsDetail = $input->getOption('epics_detail');
        if ($optionEpics || $optionEpicsDetail) {
            $this->output->writeln('');
            $this->output->writeln('<comment>*********************</comment>');
            $this->output->writeln('<comment>* Epics</comment>');

            $contentEpics = [];
            if ($optionEpicsDetail) {
                foreach ($epics as $epic) {
                    $issues = $this->gitlab->getIssues([
                    'epic_id'=>$epic->id,
                    'per_page'=>100,
                    'labels'=>implode(',', $labels),
                ]);
                    $epic_detail = $this->gitlab->getEpic($epic->group_id, $epic->iid);
                    $contentEpics[]=[
                    $epic->id,
                    $epic->title,
                    count($issues),
                    $epic_detail->state==="closed" ? '<fg=green>closed</>' : '<fg=red>opened</>',
                    $this->gitlab->getEpicUrl($epic),
                ];
                }
                $this->renderTable(['ID','Name', 'Issues','State','Url'], $contentEpics);
            } else {
                foreach ($epics as $epic) {
                    $contentEpics[]=[
                    $epic->id,
                    $epic->title,
                    $this->gitlab->getEpicUrl($epic),
                ];
                }
                $this->renderTable(['ID','Name', 'Url'], $contentEpics);
            }
        }

        $this->output->writeln("");

        $this->output->writeln('<comment>*********************</comment>');
        $this->output->writeln('<comment>* Boards</comment>');

        $this->renderTable($header, $assignees);

        $this->output->writeln("");
        $this->displayProgressBar('In-progress', array_sum($total['in_progress']), array_sum($total['nb_issues']));
        $this->displayProgressBar('Finished', array_sum($total['finished']), array_sum($total['nb_issues']));

        return Command::SUCCESS;
    }
}
