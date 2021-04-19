#!/usr/bin/env bash

# download JQ for JSON parsing in answers
# see: <https://github.com/stedolan/jq/>
echo 'Downloading JQ...'
curl -L https://github.com/stedolan/jq/releases/download/jq-1.6/jq-linux64 > jq

# make it executable, and else, because we're throwing it away afterwards
# and we don't want to deal with permission errors
echo 'Setting permissions...'
chmod 777 ./jq

# query JIRA for:
# * Project == project_id
# * Status == demo_current_status
echo 'Getting results...'

request_url="${JIRA_URL}/rest/api/2/search?jql=" # search the JIRA API
request_url+="status=${DEMO_CURRENT_STATUS}"     # for status = 'internally approved'
request_url+="%20and%20"
request_url+="project=${PROJECT_ID}"             # choose correct project
request_url+="&fields=key"                       # only get the ticket keys
request_url+="&maxResults=100"                   # to prevent timeout

curl --user "${JIRA_USER}:${JIRA_PASS}" "${request_url}" > results.json
#    └───────────────┬────────────────┘
#           use JIRA basic auth

echo 'Got results.'

# get the total number of results for the FOR loop
total=$(cat results.json | ./jq -r '.total')

echo "Found ${total} tickets to be moved."

for (( i=0; i<${total}; ++i )); do

    #                                         VAR is our loop variable
    #                                   in all issues    │       get the issue key
    #                                       ┌──┴──┐   ┌──┴──┐   ┌─┴┐
    issue_key=$(cat results.json | ./jq -r ".issues | .[${i}] | .key")
    echo "Issue key is ${issue_key}"

    transition_url="${JIRA_URL}/rest/api/2/issue/${issue_key}/transitions?expand=transitions.fields"

    transition_json="{\"transition\": {\"id\": \"${DEMO_TRANSITION_ID}\"}}"

    curl \
        --header "Content-Type: application/json" \
        --request POST \
        --user "${JIRA_USER}:${JIRA_PASS}" \
        --data "${transition_json}" \
        "${transition_url}"
 done
