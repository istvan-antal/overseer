<?php

namespace Overseer;

class JIRA {

    private $baseUrl;
    private $oauth;
    private $oauthConfig;

    private $projectKey;

    public function __construct($baseUrl, OAuthWrapper $oauth, array $oauthConfig) {
        $this->baseUrl = $baseUrl;
        $this->oauth = $oauth;
        $this->oauthConfig = $oauthConfig;
    }

    public function setProject($projectKey) {
        $this->projectKey = $projectKey;
    }

    public function createIssue($fields) {
        return $this->oauth->getClient(
            $this->oauthConfig['oauth_token'], $this->oauthConfig['oauth_token_secret']
        )->post('rest/api/2/issue', array(
            'Content-Type' => 'application/json'
        ), json_encode(array(
            'fields' => array(
                'project' => array( 'key' => $this->projectKey ),
                'summary' => $fields['summary'],
                'description' => $fields['description'],
                'issuetype' => array( 'name' => $fields['type'] ),
                'priority' => array( 'name' => $fields['priority'] )
            )
        )))->send()->json();
    }

    public function getIssuesWithoutFixedVersionForSprint() {
        return $this->getIssuesByJql(
            'status in ("Resolved", "Done", "Closed") AND '
            .'project = AL AND sprint in openSprints() '
            .' AND fixVersion = EMPTY '
            . 'ORDER BY '
                . 'priority DESC, '
                . 'status DESC, '
                . 'originalEstimate DESC, type DESC'
        );
    }

    public function getIssuesFixedForVersion($project, $version = 'EMPTY') {
        return $this->getIssuesByJql("project=$project AND fixVersion = $version");
    }

    public function getMyTestingIssuesForSprint() {
        return $this->getIssuesByJql(
            'status in ("Resolved", "Done") AND '
                . 'project = AL AND '
                . 'issuetype != "Support Request" AND '
                . 'reporter in (currentUser()) AND '
                . 'sprint in openSprints() '
                . 'ORDER BY priority DESC, '
                . 'status DESC, originalEstimate DESC, type DESC'
        );
    }
    
    public function getMyTestingIssues() {
        return $this->getIssuesByJql(
            'status in ("Resolved", "Done") AND '
                . 'reporter in (currentUser()) '
                . 'ORDER BY priority DESC, '
                . 'status DESC, originalEstimate DESC, type DESC'
        );
    }

    public function getTestingIssues() {
        return $this->getIssuesByJql(
            'status in ("Resolved", "Done") AND '
                . 'project = AL AND '
                . 'issuetype != "Support Request" '
                . 'ORDER BY priority DESC, '
                . 'status DESC, originalEstimate DESC, type DESC'
        );
    }

    public function getTestingIssuesForSprint() {
        return $this->getIssuesByJql(
            'status in ("Resolved", "Done") AND '
                . 'project = AL AND '
                . 'issuetype != "Support Request" AND '
                . 'sprint in openSprints() '
                . 'ORDER BY priority DESC, '
                . 'status DESC, originalEstimate DESC, type DESC'
        );
    }

    public function getIssuesForSprint() {
        return $this->getIssuesByJql(
            'project = AL AND sprint in openSprints() '
            . 'ORDER BY '
                . 'priority DESC, '
                . 'status DESC, '
                . 'originalEstimate DESC, type DESC'
        );
    }

    public function getIssuesWorkedOn($project = null) {
        return $this->getIssuesByJql(
            'status = "In Progress"',
            $project
        );
    }

    public function getMyIssuesForSprint() {
        return $this->getIssuesByJql(
            'assignee in (currentUser()) AND '
                . 'sprint in openSprints() '
            . 'ORDER BY '
                . 'priority DESC, '
                . 'status DESC, '
                . 'originalEstimate DESC, type DESC'
        );
    }

    public function getMyIssuesResolvedToday() {
        return $this->getIssuesByJql(
            'assignee in (currentUser()) AND resolved >= startOfDay() ORDER BY assignee ASC'
        );
    }

    public function getMyIssuesResolvedYesterday() {
        return $this->getIssuesByJql(
            'assignee in (currentUser()) AND resolved >= startOfDay(-1d) AND resolved < startOfDay() ORDER BY assignee ASC'
        );
    }

    public function getTodoList() {
        return $this->getIssuesByJql(
            'status in (Open, "In Progress", Reopened) AND '
                . 'assignee in (currentUser()) '
                . 'ORDER BY priority DESC, status DESC, originalEstimate DESC, type DESC'
        );
    }
    
    public function getBlockedIssues() {
        return $this->getIssuesByJql(
            'status in (Blocked) AND '
                . 'assignee in (currentUser()) '
                . 'ORDER BY priority DESC, status DESC, originalEstimate DESC, type DESC'
        );
    }

    public function getIssuesResolvedToday($project = null) {
        return $this->getIssuesByJql(
            'resolved >= startOfDay() ORDER BY assignee ASC',
            $project
        );
    }

    public function getIssuesResolvedYesterday($project = null) {
        return $this->getIssuesByJql(
            'resolved >= startOfDay(-1d) AND resolved < startOfDay() ORDER BY assignee ASC',
            $project
        );
    }
    
    public function getUnassignedIssues($project = null) {
        return $this->getIssuesByJql(
            'assignee in (EMPTY) AND status not in (Closed, Resolved)',
            $project
        );
    }

    public function getIncomingSupportTickets() {
        return $this->getIssuesByJql(
            'project = AL AND '
                . 'assignee in (EMPTY) AND '
                . 'issuetype = "Support Request" '
                . 'AND status in (Open, Reopened)'
        );
    }

    public function getUnresolvedSupportTickets() {
        return $this->getIssuesByJql(
            'project = AL AND '
                . 'issuetype = "Support Request" '
                . 'AND status in (Open, "In Progress", Reopened, "Requires clarification")'
        );
    }
    
    public function getIssues($query) {
        $conditions = array();
        
        if (isset($query['project'])) {
            $conditions []= 'project = '.$query['project'];
        }
        
        if (isset($query['status'])) {
            if (count($query['status']) === 1) {
                $conditions []= "status = '".$query['status'][0]."'";
            } else {
                //TODO
            }
        }
        
        return $this->getIssuesByJql(implode(' AND ', $conditions));
    }

    public function getIssuesByJql($jql, $project = null) {
        if ($project) {
            $jql = "project = $project AND $jql";
        }
        return array_map('Overseer\JIRA::mapIssueFields', $this->issueSearch($jql)['issues']);
    }

    public function mapIssueFields($item) {
        if (is_null($item['fields']['customfield_10007'])) {
            $sprints = array();
        } else {
            $sprints = array_map(function ($item) {
                return explode(',', explode('name=', $item)[1])[0];
            }, $item['fields']['customfield_10007']);
        }
        return array(
            'id' => $item['key'],
            'url' => $this->baseUrl.'browse/'.$item['key'],
            'type' => $item['fields']['issuetype']['name'],
            'summary' => $item['fields']['summary'],
            'status' => $item['fields']['status']['name'],
            'assignee' => $item['fields']['assignee'],
            'components' => $item['fields']['components'],
            'sprints' => $sprints,
            'created' => new \DateTime($item['fields']['created'])
        );
    }

    public function getSupportStats() {
        $result = array(
            'avgResolutionTime' => 0,
            'issues' => null
        );

        $tickets = $this->issueSearch('project = AL AND issuetype = "Support Request" ORDER BY created DESC');

        $totalSupportTicketTime = 0;

        foreach ($tickets['issues'] as $issue) {
            if ($issue['fields']['timespent'] === null) {
                if (isset($issue['fields']['resolutiondate']) && $issue['fields']['resolutiondate']) {
                    $resolved = strtotime($issue['fields']['resolutiondate']);
                } else {
                    $resolved = time();
                }
                $totalSupportTicketTime += $resolved - strtotime($issue['fields']['created']);
            } else {
                $totalSupportTicketTime += $issue['fields']['timespent'];
            }
        }

        $result['avgResolutionTime'] = $totalSupportTicketTime / count($tickets['issues']);

        $self = $this;

        $result['issues'] = array_map(function ($issue) use ($self) {
            return $self->mapIssueFields($issue);
        }, $tickets['issues']);

        return $result;
    }

    public function getVersions($project) {
        return $this->api("/rest/api/2/project/$project/versions", array());
    }

    public function getProjects() {
        return $this->api('/rest/api/2/project', array());
    }

    private function issueSearch($jql) {
        return $this->api('rest/api/2/search', array(
            'jql' => $jql,
            'maxResults' => 1000
        ));
    }

    private function api($url, $params) {
        return $this->oauth->getClient(
            $this->oauthConfig['oauth_token'], $this->oauthConfig['oauth_token_secret']
        )->get($url.'?'.http_build_query($params))->
                send()->json();
    }

}