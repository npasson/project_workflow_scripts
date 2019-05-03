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

/**
 * Logs a message to the given file. Ends the message with a newline.
 *
 * @param string $msg The message to log.
 */
function _log(string $msg): void {
	file_put_contents('jira_send_live.log', $msg . "\n", FILE_APPEND);
}

/**
 * Converts anything (usually an object) to an array.
 *
 * @param $obj mixed An object or an array, or anything. It just casts, make sure it's able to.
 *
 * @return array The given object, converted to an array.
 */
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

/**
 * Gets the issue key (e.g. TEST-3) from the payload.
 *
 * @return string The issue key.
 */
function get_issue_key(): string {
	return json_decode(POST_PAYLOAD, true)['issue']['key'];

}

/**
 * Get the project key (e.g. TEST) from the payload.
 *
 * @return string The project key.
 */
function get_project_key(): string {
	return json_decode(POST_PAYLOAD, true)['issue']['fields']['project']['key'];
}

/**
 * Gets the repo key from the given project key. The repo key is this:
 * <pre>
 * https://gitlab.com/company/project.git
 *                            ^^^^^^^
 * </pre>
 * @param string $project_key The project key, see {@link get_project_key()}
 *
 * @return string|null Either the project repo key as string, or null if not found.
 */
function get_repo_key(string $project_key) {
	$url = CRUCIBLE_URL . '/rest-service/repositories-v1';

	$connection = curl_init();
	curl_setopt($connection, CURLOPT_URL, $url);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($connection);

	curl_close($connection);

	_log('Received answer from Crucible:');
	_log($response);

	$responseDecoded = new SimpleXMLElement($response);
	//$responseArray = object_to_array($responseDecoded);

	foreach ($responseDecoded as $repoObject) {
		$repoArray = (array)$repoObject;
		if ($repoArray['displayName'] === $project_key
			|| $repoArray['name'] === $project_key) {
			return preg_filter(REGEX_PATH_FROM_GIT_URL, '$1', $repoArray['location']);
		}
	}

	return $responseDecoded;
}

/**
 * Gets the ID of the branch we're working on. This is *technically* not necessary, but we do it
 * anyway, because we want to make sure.
 *
 * @param string $repo_key The key of the repository to use.
 * @param string $issue_key The issue ID from JIRA.
 *
 * @return string The branch ID concerning the ticket.
 */
function get_branch_id(string $repo_key, string $issue_key): string {
	$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode($repo_key) . '/repository/branches?private_token=';
	$url_suffix =
		"&search=${issue_key}\$";

	_log('Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...');

	$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

	$connection = curl_init();
	curl_setopt($connection, CURLOPT_URL, $url);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($connection);

	curl_close($connection);

	_log('Received answer from Gitlab:');
	_log($response);

	return json_decode($response, true)[0]['name'];
}

/**
 * Merges a branch in two steps.
 *
 * <ol>
 * <li> Creates a merge request.
 * <li> Accepts the merge request.
 * </ol>
 *
 * This way, merge conflicts lead to not the whole system crashing, but the MR just staying up
 * until it is manually resolved.
 *
 * @param string $repo_key The repository to work on.
 * @param string $from The source branch of the merge.
 * @param string $to The target branch of the merge.
 */
function merge(string $repo_key, string $from, string $to): void {
	$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode($repo_key) . '/merge_requests?private_token=';
	$url_suffix =
		"&source_branch=$from" .
		"&target_branch=$to" .
		'&title=' . $from .
		'&remove_source_branch=false';

	_log('Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...');

	$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

	$connection = curl_init();
	curl_setopt($connection, CURLOPT_URL, $url);
	curl_setopt($connection, CURLOPT_POST, true);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($connection);
	curl_close($connection);

	_log('Received answer from Gitlab:');
	_log($response);

	$response_decoded = json_decode($response, true);

	$id = $response_decoded['id'];
	$iid = $response_decoded['iid'];

	_log('Sleeping for 10 seconds.');
	sleep(10);
	_log('Back.');

	_log("ID is $id, IID is $iid.");

	$url_prefix = GITLAB_URL
		. '/api/v4/projects/'
		. urlencode($repo_key)
		. '/merge_requests/' . $iid
		. '/merge?private_token=';
	$url_suffix =
		'&should_remove_source_branch=true' .
		'&squash=true';

	_log('Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...');

	$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

	$connection = curl_init();
	curl_setopt($connection, CURLOPT_URL, $url);
	curl_setopt($connection, CURLOPT_PUT, true);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($connection);
	curl_close($connection);

	_log('Received answer from Gitlab:');
	_log($response);
}

_log('Called at ' . date('c'));

if (empty(POST_PAYLOAD)) {
	_log('POST payload empty. Exiting.');
	//exit(1);
}

_log('POST payload received:');
_log(POST_PAYLOAD);

// get issue key from JIRA
$issue_key = get_issue_key();
_log('Issue key is ' . $issue_key);

// get project key from JIRA
$project_key = get_project_key();
_log('Project key is ' . $project_key);

// get repository URL from Crucible
$repo_key = get_repo_key('APPL');
_log('Repo key is ' . $repo_key);

// get branch name from Gitlab
$branch_name = get_branch_id($repo_key, $issue_key);
_log('Branch name is ' . $branch_name);

// query Gitlab to merge into master
merge($repo_key, $branch_name, 'master');