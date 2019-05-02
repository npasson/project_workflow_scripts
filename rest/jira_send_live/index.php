<?php

require_once '../vars.php';

// ==================================================== //

function _log(string $msg): void {
	file_put_contents('jira_send_live.log', $msg . "\n", FILE_APPEND);
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

function get_issue_key(): string {
	return json_decode(POST_PAYLOAD, true)['issue']['key'];

}

function get_project_key(): string {
	return json_decode(POST_PAYLOAD, true)['issue']['fields']['project']['key'];
}

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

function get_branch_id(string $project, string $issue_key): string {
	$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode($project) . '/repository/branches?private_token=';
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

function merge(string $project, string $from, string $to): void {
	$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode($project) . '/merge_requests?private_token=';
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
		. urlencode($project)
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