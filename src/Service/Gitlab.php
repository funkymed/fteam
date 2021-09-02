<?php

namespace App\Service;

use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Gitlab
{
    private $token;
    private $id;
    private $url;
    private $debug = false;
    private $labels=[];

    public $workflow_backlog='';
    public $workflow_wip=[];
    public $workflow_blocked='';
    public $workflow_rejected='';
    public $workflow_accepted=[];
    public $workflow_finished=[];

    private $colors = [
        'backlog' => 'white',
        'issue' => 'cyan',
        'started' => 'green',
        'review' => 'green',
        'staging' => 'green',
        'rejected' => 'red',
        'blocked' => 'red',
        'accepted' => 'yellow',
        'preprod' => 'yellow',
        'closed' => 'yellow',
        'accepted' => 'yellow',
        'total' => 'magenta',
    ];

    public const ISSUE_BACKLOG = 'workflow::backlog';
    public const ISSUE_STARTED = 'workflow::started';
    public const ISSUE_IN_REVIEW = 'workflow::in_review';
    public const ISSUE_STAGING = 'workflow::staging_to_accept';
    public const ISSUE_BLOCKED = 'workflow::blocked';
    public const ISSUE_REJECT = 'workflow::rejected_need_fix';
    public const ISSUE_ACCEPTED = 'workflow::accepted';
    public const ISSUE_PREPROD = 'workflow::in_preprod';

    public const STATUS_CLOSED = "closed";
    public const STATUS_OPENED = "opened";

    public function __construct(ParameterBagInterface $params)
    {
        $this->token = $params->get('gitlab_token');
        $this->id = $params->get('gitlab_id');
        $this->url = $params->get('gitlab_url');
        $this->debug = $params->get('gitlab_debug') === "true";
        $this->labels = explode(",", trim($params->get('gitlab_labels')));

        $this->workflow_backlog = $params->get('gitlab_workflow_backlog');
        $this->workflow_wip = explode(",", trim($params->get('gitlab_workflow_wip')));
        $this->workflow_rejected = $params->get('gitlab_workflow_rejected');
        $this->workflow_blocked = $params->get('gitlab_workflow_blocked');
        $this->workflow_accepted = explode(",", trim($params->get('gitlab_workflow_accepted')));
        $this->workflow_finished = explode(",", trim($params->get('gitlab_workflow_finished')));
    }

    public function getLabels()
    {
        return $this->labels;
    }

    private function get($path, $args=[])
    {
        $url = $this->url . '/api/v4' . $path;
        $url .= '?access_token=' . $this->token.'&scope=all';

        foreach ($args as $key=>$value) {
            $url.=sprintf('&%s=%s', $key, $value);
        }

        if ($this->debug) {
            echo $url . "\n";
        }
        $client = new Client();
        $result = $client->request("GET", $url);
        return json_decode($result->getBody());
    }

    public function getIssues($args=[])
    {
        $issues = $this->get('/issues', $args);
        return $issues;
    }

    public function getEpic($groupe, $epic_id)
    {
        return $this->get(sprintf('/groups/%s/epics/%s', $groupe, $epic_id));
    }

    public function getMilestones($repoId = false)
    {
        $repoId = !$repoId ? $this->id : $repoId;
        $milestones = $this->get('/projects/' . $repoId . '/milestones');

        usort($milestones, function ($a, $b) {
            $date = strcmp($b->due_date, $a->due_date);

            if ($date != 0) {
                return $date;
            }

            return strcmp($b->title, $a->title);
        });

        $listMilestones = [];

        foreach ($milestones as $milestone) {
            $listMilestones[]=$milestone->title ;
        }
        return ['full'=>$milestones,'compact'=>$listMilestones];
    }

    public function getMergesRequest($repoId = false, $merged=false, $page=1, $per_page=100)
    {
        $repoId = !$repoId ? $this->id : $repoId;

        $issues = [];
        while (true) {
            $next = $this->get('/projects/' . $this->id . '/merge_requests', ['page'=>  $page , 'per_page'=>  $per_page]);
            $count = count($next);
            $issues = array_merge($issues, $next);
            $page++;
            if ($count < $per_page) {
                break;
            }
        }

        if (!$merged) {
            return $issues;
        }

        return array_reverse(array_filter($issues, function ($mr) {
            return $mr->state === "merged";//&& isset($mr->milestone);
        }));
    }

    public function getAssigned($issue)
    {
        return current($issue->assignees);
    }

    public function isIssueLabel($issue, $label, $isStateOpen = true)
    {
        if ($isStateOpen) {
            return in_array($label, $issue->labels) && $issue->state === self::STATUS_OPENED;
        } else {
            return in_array($label, $issue->labels);
        }
    }

    public function getEpicUrl($epic)
    {
        return $this->url.$epic->url;
    }

    public function getColor($issue)
    {
        if ($issue->state === self::STATUS_CLOSED) {
            return "green";
        }
        if (!isset($issue->labels)) {
            return false;
        }
        if ($this->workflow_backlog) {
            if (in_array($this->workflow_backlog, $issue->labels)) {
                return "white";
            }
        }
        foreach ($this->workflow_wip as $wip) {
            if (in_array($wip, $issue->labels)) {
                return "yellow";
            }
        }
        if ($this->workflow_blocked) {
            if (in_array($this->workflow_blocked, $issue->labels)) {
                return "red";
            }
        }
        if ($this->workflow_rejected) {
            if (in_array($this->workflow_rejected, $issue->labels)) {
                return "red";
            }
        }
        foreach ($this->workflow_accepted as $accepted) {
            if (in_array($accepted, $issue->labels)) {
                return "green";
            }
        }
        return 'white';
    }

    public function getState($issue)
    {
        if ($issue->state === self::STATUS_CLOSED) {
            return "closed";
        }
        if ($this->workflow_backlog) {
            if (in_array($this->workflow_backlog, $issue->labels)) {
                return "backlog";
            }
        }
        foreach ($this->workflow_wip as $wip) {
            if (in_array($wip, $issue->labels)) {
                return "wip";
            }
        }
        if ($this->workflow_blocked) {
            if (in_array($this->workflow_blocked, $issue->labels)) {
                return "blocked";
            }
        }
        if ($this->workflow_rejected) {
            if (in_array($this->workflow_rejected, $issue->labels)) {
                return "rejected";
            }
        }
        foreach ($this->workflow_accepted as $accepted) {
            if (in_array($accepted, $issue->labels)) {
                return "accepted";
            }
        }
        return false;
    }

    public function getMilestone($name)
    {
    }

    public function getWeight($issue)
    {
        if (isset($issue->weight) && $issue->weight) {
            return $issue->weight ? $issue->weight : 0;
        }

        $regs=[
        '/Weight:([0-9]+)/',
        '/Weight: ([0-9]+)/',
        '/Weight :([0-9]+)/',
        '/Weight : ([0-9]+)/'
        ];
        foreach ($regs as $reg) {
            $matches = array();
            preg_match($reg, $issue->description, $matches);
            if (count($matches)>0) {
                return isset($matches[1]) ? $matches[1] : 0;
            }
        }
    }
}
