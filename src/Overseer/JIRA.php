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
    
    public function getTodoList() {
        $data = $this->issueSearch(
            'status in (Open, "In Progress", Reopened, "Ticket Open") AND '
                . 'assignee in (currentUser()) AND '
                . 'sprint in openSprints() '
                . 'ORDER BY priority DESC, status DESC, originalEstimate DESC, type DESC'
        );
        
        return array_map(function ($item) {
            return array(
                'id' => $item['key'],
                'summary' => $item['fields']['summary'],
                'status' => $item['fields']['status']['name'],
                'created' => new \DateTime($item['fields']['created'])
            );
        }, $data['issues']);
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
        
        $result['issues'] = array_map(function ($item) { 
            return array(
                'id' => $item['key'],
                'summary' => $item['fields']['summary'],
                'created' => new \DateTime($item['fields']['created'])
            );
        }, $tickets['issues']);
        
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