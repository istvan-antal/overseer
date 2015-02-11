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
    
    public function getSupportStats() {
        $result = array(
            'avgResolutionTime' => 0,
            'issues' => null
        );
        
        $tickets = $this->oauth->getClient(
            $this->oauthConfig['oauth_token'], $this->oauthConfig['oauth_token_secret']
        )->get('rest/api/2/search?jql=project%20%3D%20AL%20AND%20issuetype%20%3D%20"Support%20Request"%20ORDER%20BY%20created%20DESC')->
                send()->json();
        
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
    
}