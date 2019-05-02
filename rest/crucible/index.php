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
	file_put_contents('crucible.log', $msg . "\n", FILE_APPEND);
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

function getRepoUrl(string $name): string {
	$repoData = file_get_contents(CRUCIBLE_URL . "/rest-service/repositories-v1?name=$name");
	_log('Received answer getting repository names:');
	_log($repoData);
	$repoDecoded = new SimpleXMLElement($repoData);
	$repoArray = object_to_array($repoDecoded);
	$repoUrl = preg_filter('/^.+\/(.+)\.git$/', '$1', $repoArray['repoData']['location']);
	if ($repoUrl === NULL) {
		_log("No repositories with name $name found");
		header("HTTP/1.0 400 Bad Request: No repositories with name $name found");
		exit(400);
	}

	return $repoUrl;
}

function getRepoName(): string {
	$decoded = json_decode(POST_PAYLOAD, true);
	/*
	 * THIS IS A BUG IN THE CRUCIBLE REST SERVICE.
	 * The REST documentation states:
	 * "filter repositories by the repository key"
	 * THIS IS WRONG.
	 * The $name attribute filters by DISPLAY NAME, not KEY.
	 * Try it yourself:
	 * http://crucible.location/rest-service/repositories-v1?name=typehere
	 * Therefore we need to return displayName instead of name here.
	 */
	return $decoded['repository']['displayName'];
}

function getBranchName(): string {
	$decoded = json_decode(POST_PAYLOAD, true);
	return $decoded['changeset']['branches'][0];
}

function getComment(): string {
	$decoded = json_decode(POST_PAYLOAD, true);
	return $decoded['changeset']['comment'];
}

function create_mr(string $project, string $from, string $to): void {
	$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode($project) . '/merge_requests?private_token=';
	$url_suffix =
		"&source_branch=$from" .
		"&target_branch=$to" .
		'&title=' . getBranchName() .
		'&remove_source_branch=false'
	;

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
}

// ==================================================== //

_log('Called at ' . date('c'));

if (empty(POST_PAYLOAD)) {
	_log('POST payload empty. Exiting.');
	exit(1);
}

_log('POST payload received:');
_log(POST_PAYLOAD);

_log('Checking for comment...');
$comment = getComment();
$action = preg_filter(REGEX_COMMAND_FROM_COMMENT, '$1', $comment);
if ($action === NULL) {
	_log('Response is empty. Exiting.');
	exit(1);
}

// XXX DO NOT COMPARE THESE STRINGS DIRECTLY.
// Why? I do not know. (probably invisible characters)
// The line below works, don't touch it.
if(strpos($action, CODE_DONE_COMMAND) !== 0) {
	_log('Didn\'t match filter, exiting.');
	_log("Action was: $action");
	exit(1);
}

_log('Getting repo name...');
$repo_name = getRepoName();
_log('Got repo name: ' . $repo_name);

_log('Getting repo URL...');
$repo_url = getRepoUrl($repo_name);
_log('Got repo URL: ' . $repo_url);

_log('Getting branch name...');
$branch_name = getBranchName();
_log('Got branch name: ' . $branch_name);

_log('Creating merge request...');
create_mr(REPOSITORY_GROUP_PREFIX . $repo_url, $branch_name, 'dev');
_log('Created merge request. Exiting.');
