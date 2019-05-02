<?php

define('POST_PAYLOAD', file_get_contents('php://input'));


/* == URLs for service access == */

/** The URL for the JIRA rest service. Usually http(s)://url:port/rest */
const JIRA_REST_URL = '';

/** The base URL for the Crucible installation in the format http(s)://url:port */
const CRUCIBLE_URL = '';

/** The base URL for the Gitlab installation in the format http(s)://url:port */
const GITLAB_URL = '';


/* == Authentication == */

/** Username for the JIRA account to handle REST API requests. */
const JIRA_USER = '';
/** Password for the JIRA account to handle REST API requests. */
const JIRA_PASS = '';

/** Auth token for the Gitlab account to handle REST API requests */
const GITLAB_TOKEN = '';

/* Crucible is protected by other means, it doesn't need separate auth */


/* == JIRA settings == */

/** Command trigger-word to react to -- transition from "In Work" to "Internal Inspection" */
const CODE_DONE_COMMAND = '';

/** Transition ID from "Internal Inspection" to "Internally accepted" */
const JIRA_TRANSITION_TO_ACCEPTED_ID = 0;


/* == Gitlab settings == */

/** Gitlab group prefix. Usually 'groupname/' (group name with a trailing slash) */
const REPOSITORY_GROUP_PREFIX = '/';

/* == RegEx (you shouldn't need to change this) == */

/** Gets the JIRA issue ID from the branch name */
const REGEX_ISSUE_ID_FROM_BRANCH = '/^(?:[A-Za-z]+_)?([A-Za-z0-9-]+)$/';
/** Gets the JIRA command from the commit message */
const REGEX_COMMAND_FROM_COMMENT = '/^[^ ]+ #([\w-]+)(?: .*)?$/';
/** Gets the Git repo name from a Git URL */
const REGEX_PATH_FROM_GIT_URL = '/^[^:]+:([^\.]+)(?:.git)?$/';
