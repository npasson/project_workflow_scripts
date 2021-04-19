#!/usr/bin/env bash

if [[ ! -z "${SSH_PRIVATE_KEY}" ]]; then
    # if SSH key is given, use it
    # first, install openssh-client to use SSH
    which ssh-agent || ( apt-get update -y && apt-get install openssh-client -y )
    # start the SSH agent
    eval $(ssh-agent -s)
    # add our private key to the system
    echo "$SSH_PRIVATE_KEY" | tr -d '\r' | ssh-add - > /dev/null
    # create and change permissions of the .ssh directory
    mkdir -p ~/.ssh
    chmod 700 ~/.ssh
else
    # if the SSH key is empty, but user has indicated that they want to use SSH, error out
    if [[ "${DEMO_USE_SFTP}" == 'true' || "${LIVE_USE_SFTP}" == 'true' ]]; then
        echo 'SFTP is supposed to be used, but the private key is empty!'
        exit 1
    fi
fi

# if any of the vital variables are empty, error out
# vital variables are:
# JIRA_URL, JIRA_USER, JIRA_PASS - to connect to JIRA
# TODO: See issue #1, change from basic auth
if [[ "${JIRA_URL}" == "" ]]; then
    echo "JIRA URL must not be empty!"
    exit 1
fi
if [[ "${JIRA_USER}" == "" ]]; then
    echo "JIRA username must not be empty!"
    exit 1
fi
if [[ "${JIRA_PASS}" == "" ]]; then
    echo "JIRA password must not be empty!"
    exit 1
fi
