<?php

namespace Overseer;

use Atlassian\OAuthWrapper;

class JIRA {
    
    private $oauth;
    private $oauthConfig;
    
    public function __construct(OAuthWrapper $oauth, array $oauthConfig) {
        $this->oauth = $oauth;
        $this->oauthConfig = $oauthConfig;
    }
    
    public function createIssue($fields) {
        return $this->oauth->getClient(
            $this->oauthConfig['oauth_token'], $this->oauthConfig['oauth_token_secret']
        )->post('rest/api/2/issue', array(
            'Content-Type' => 'application/json'
        ), json_encode(array(
            'fields' => array(
                'project' => array( 'key' => 'AL' ),
                'summary' => $fields['summary'],
                'description' => $fields['description'],
                'issuetype' => array( 'name' => $fields['type'] ),
                'priority' => array( 'name' => $fields['priority'] )
            )
        )))->send()->json();
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

    public function getTodoList() {
        return $this->getIssuesByJql(
            'status in (Open, "In Progress", Reopened, "Ticket Open") AND '
                . 'assignee in (currentUser()) AND '
                . 'sprint in openSprints() '
                . 'ORDER BY priority DESC, status DESC, originalEstimate DESC, type DESC'
        );
    }
    
    public function getIssuesResolvedToday() {
        return $this->getIssuesByJql(
            'project = AL AND resolved >= startOfDay() ORDER BY assignee ASC'
        );
    }
    
    public function getIssuesResolvedYesterday() {
        return $this->getIssuesByJql(
            'project = AL AND resolved >= startOfDay(-1d) AND resolved < startOfDay() ORDER BY assignee ASC'
        );
    }
    
    public function getUnresolvedSupportTickets() {
        return $this->getIssuesByJql(
            'project = AL AND '
                . 'issuetype = "Support Request" '
                . 'AND status in (Open, "In Progress", Reopened, "Requires clarification")'
        );
    }
    
    public function getIssuesByJql($jql) {
        return array_map('Overseer\JIRA::mapIssueFields', $this->issueSearch($jql)['issues']);
    }

    public static function mapIssueFields($item) {
        return array(
            'id' => $item['key'],
            'type' => $item['fields']['issuetype']['name'],
            'summary' => $item['fields']['summary'],
            'status' => $item['fields']['status']['name'],
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
                $totalSupportTicketTime += strtotime($issue['fields']['resolutiondate']) - strtotime($issue['fields']['created']);
            } else {
                $totalSupportTicketTime += $issue['fields']['timespent'];
            }
        }
        
        $result['avgResolutionTime'] = $totalSupportTicketTime / count($tickets['issues']);
        
        $result['issues'] = array_map('Overseer\JIRA::mapIssueFields', $tickets['issues']);
        
        return $result;
    }
    
    private function issueSearch($jql) {
        return $this->api('rest/api/2/search', array(
            'jql' => $jql
        ));
    }
    
    private function api($url, $params) {
        return $this->oauth->getClient(
            $this->oauthConfig['oauth_token'], $this->oauthConfig['oauth_token_secret']
        )->get($url.'?'.http_build_query($params))->
                send()->json();
    }
    
}