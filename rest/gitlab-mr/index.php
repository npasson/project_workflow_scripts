<?php

/*==================================================================================*\
 *
 *   Project Workflow Scripts
 *   Copyright (C) 2019  Nicholas Passon
 *   Documentation: Coming Soon
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as published
 *   by the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Affero General Public License for more details.
 *
 *   You should have received a copy of the GNU Affero General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
\*==================================================================================*/

require_once '../vars.php';

// ==================================================== //

function _log(string $msg): void {
	file_put_contents('gitlab-mr.log', $msg . "\n", FILE_APPEND);
}

function object_to_array($obj): array {
	$array = (array)$obj;
	foreach ($array as $sub => $attribute) {
		if (is_array($attribute)) {
			$array[$sub] = object_to_array($attribute);
		}
		if (!is_string($attribute)) {
			$array[$sub] = (array)$attribute;
		}
	}
	return $array;
}

function get_issue_id(): string {
	$branch_name = json_decode(POST_PAYLOAD, true)['object_attributes']['source_branch'];
	return preg_filter(REGEX_ISSUE_ID_FROM_BRANCH, '$1', $branch_name);
}

function get_issue_command(): string {
	$branch_name = json_decode(POST_PAYLOAD, true)['object_attributes']['last_commit']['message'];
	return preg_filter(REGEX_COMMAND_FROM_COMMENT, '$1', $branch_name);
}

function get_target_branch(): string {
	return json_decode(POST_PAYLOAD, true)['object_attributes']['target_branch'];
}

function is_changed_to_merged(): bool {
	return (
		(json_decode(POST_PAYLOAD, true)['changes']['state']['current'] ?? '')
		=== 'merged'
	);
}

function do_jira_transition(string $issue_id, int $transition_id): string {
	_log("Attempting to invoke transition $transition_id on issue $issue_id...");
	$ch = curl_init();

	$url = JIRA_REST_URL . "/api/2/issue/${issue_id}/transitions?expand=transitions.fields";
	$payload = /** @lang JSON */
		<<<EOF
{
    "transition": {
        "id": "${transition_id}"
    }
}
EOF;

	_log('URL: ' . $url);
	_log('Payload:');
	_log($payload);

	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_USERPWD, JIRA_USER . ':' . JIRA_PASS);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($ch);

	curl_close($ch);

	if ($response === FALSE) {
		_log('cURL request failed.');
	} else {
		_log('Received answer from JIRA:');
		_log(var_export($response, true));
	}

	return $response;
}

// ============================================== //

_log('');
_log('Received request at ' . date('c'));
_log(POST_PAYLOAD);

if(empty(POST_PAYLOAD)) {
	_log('Payload empty. Exiting.');
	http_response_code(402);
	exit();
}

if (is_changed_to_merged()) {
	// PR is merged

	if (get_issue_id() === null || empty(get_issue_id())) {
		_log('Branch doesn\'t concern an issue. Exiting.');
		http_response_code(200);
		exit();
	}

	if (get_target_branch() === 'dev') {
		do_jira_transition(get_issue_id(), JIRA_TRANSITION_TO_ACCEPTED_ID);
	}


}

