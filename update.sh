#!/bin/bash
. settings

cd public/data
curl -u "$AUTH" "$URL/rest/api/2/search/?jql=project%20=%20UI%20AND%20status%20=%20%22In%20Progress%22%20ORDER%20BY%20rank%20DESC" > in-progress.json
curl -u "$AUTH" "$URL/rest/api/2/search/?jql=project%20=%20UI%20AND%20status%20=%20%22Ready%20for%20QA%22%20ORDER%20BY%20rank%20DESC" > ready-for-qa.json