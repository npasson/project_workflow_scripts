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

namespace {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/rest/vars.php';
}

namespace scripts\general {
	/**
	 * Converts anything (usually an object) to an array.
	 *
	 * @param $obj mixed An object or an array, or anything. It just casts, make sure it's able to.
	 *
	 * @return array The given object, converted to an array.
	 */
	function object_to_array( $obj ): array {
		$array = (array) $obj;
		foreach ( $array as $sub => $attribute ) {
			if ( is_array( $attribute ) ) {
				$array[ $sub ] = object_to_array( $attribute );
			}
			if ( !is_string( $attribute ) ) {
				$array[ $sub ] = (array) $attribute;
			}
		}

		return $array;
	}

	/**
	 * Logs a message to the given file. Ends the message with a newline.
	 *
	 * @param string $msg The message to log.
	 */
	function _log( string $msg ): void {
		// creates a stack trace to get the calling function
		$dbt    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
		$caller = $dbt[1]['function'] ?? '(unknown)';

		$message = "[$caller] $msg";

		file_put_contents( $_SERVER['DOCUMENT_ROOT'] . '/rest/rest.log', $message . "\n", FILE_APPEND );
	}

	/**
	 * Truncates a string at a certain position without "cutting" words into senseless pieces.
	 * Found at: https://www.sitepoint.com/community/t/echo-first-few-words-of-a-string/2533/3
	 *
	 * @param $string
	 * @param $maxlength
	 * @param $extension
	 *
	 * @return string
	 */
	function truncate_string( $string, $maxlength, $extension ): string {

		// Set the replacement for the "string break" in the wordwrap function
		$cutmarker = '**CUT_MARKER**';

		// Checking if the given string is longer than $maxlength
		if ( strlen( $string ) > $maxlength ) {

			// Using wordwrap() to set the cutmarker
			// NOTE: wordwrap (PHP 4 >= 4.0.2, PHP 5)
			$string = wordwrap( $string, $maxlength, $cutmarker );

			// Exploding the string at the cutmarker, set by wordwrap()
			$string = explode( $cutmarker, $string );

			// Adding $extension to the first value of the array $string, returned by explode()
			$string = $string[0] . $extension;
		}

		// returning $string
		return $string;
	}

	function make_safe_string( $input ): string {
		if ( is_string( $input ) ) {
			return $input;
		}

		return '';
	}

	function curl_get( string $url ) {
		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		return $response;
	}
}

namespace scripts\jira\decode_payload {
	/**
	 * Decodes a JIRA POST payload.
	 * Gets the issue key (e.g. TEST-3) from the payload.
	 *
	 * @return string The issue key.
	 */
	function getIssueKey(): string {
		try {
			$rv = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['issue']['key'];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $rv );
	}

	/**
	 * Get the project key (e.g. TEST) from the payload.
	 *
	 * @return string The project key.
	 */
	function getProjectKey(): string {
		try {
			$rv = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['issue']['fields']['project']['key'];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $rv );
	}

	function getCleanIssueTitle(): string {
		try {
			$title = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['issue']['fields']['summary'];
		} catch ( \JsonException $e ) {
			return '';
		}

		if ( empty( \scripts\general\make_safe_string( $title ) ) ) {
			return '';
		}

		// Replaces all spaces with hyphens and converts umlaute.
		$title = str_replace(
			[ 'Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß' ],
			[ 'ae', 'OE', 'UE', 'ae', 'oe', 'ue', 'ss' ],
			$title
		);

		$title = \scripts\general\truncate_string( $title, 40, '' );

		// Replaces all spaces with hyphens and converts umlaute.
		$string = str_replace(
			[ ' ' ],
			[ '-' ],
			$title
		);

		// Removes special chars.
		$string = preg_replace(
			'/[^A-Za-z0-9\-]/',
			'-',
			$string
		);

		preg_replace( '/-+/', '-', $string ); // Replaces multiple hyphens with single one.

		return $string;
	}
}

namespace scripts\jira\curl {
	/**
	 * Invokes a transition on a JIRA ticket.
	 *
	 * @param string $issue_id The ID of the issue, e.g. TEST-3
	 * @param int $transition_id The transition ID to invoke.
	 *
	 * @return string
	 */
	function do_jira_transition( string $issue_id, int $transition_id ): string {
		\scripts\general\_log( "Attempting to invoke transition $transition_id on issue $issue_id..." );
		$ch = curl_init();

		$url = JIRA_REST_URL . "/api/2/issue/${issue_id}/transitions?expand=transitions.fields";
		$payload
		     = /** @lang JSON */
			<<<EOF
{
    "transition": {
        "id": "${transition_id}"
    }
}
EOF;

		\scripts\general\_log( 'URL: ' . $url );
		\scripts\general\_log( 'Payload:' );
		\scripts\general\_log( $payload );

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
		curl_setopt( $ch, CURLOPT_USERPWD, JIRA_USER . ':' . JIRA_PASS );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $ch );

		curl_close( $ch );

		if ( $response === false ) {
			\scripts\general\_log( 'cURL request failed.' );
		} else {
			\scripts\general\_log( 'Received answer from JIRA:' );
			\scripts\general\_log( var_export( $response, true ) );
		}

		return $response;
	}
}

namespace scripts\crucible\decode_payload {
	/**
	 * Gets the repository name as saved by Crucible.
	 *
	 * @return string The repo name.
	 */
	function getRepoName(): string {
		try {
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
			$decoded = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['repository']['displayName'];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $decoded );
	}

	/**
	 * Gets the name of the branch that was changed.
	 *
	 * @return string The name of the branch.
	 */
	function getBranchName(): string {
		try {
			$decoded = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['changeset']['branches'][0];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $decoded );
	}

	/**
	 * Gets the comment of the commit.
	 *
	 * @return string The comment.
	 */
	function getComment(): string {
		try {
			$decoded = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['changeset']['comment'];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $decoded );
	}
}

namespace scripts\crucible\curl {
	/**
	 * Gets the repo key from the given project key. The repo key is this:
	 * <pre>
	 * https://gitlab.com/company/project.git
	 *                            ^^^^^^^
	 * </pre>
	 *
	 * @param string $project_key The project key, see {@link getProjectKey()}
	 *
	 * @return string|null Either the project repo key as string, or null if not found.
	 */
	function getRepoKey( string $project_key ): ?string {
		$url = CRUCIBLE_URL . '/rest-service/repositories-v1';

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );

		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Crucible:' );
		\scripts\general\_log( $response );

		$responseDecoded = null;
		try {
			$responseDecoded = new \SimpleXMLElement( $response );
		} catch ( \Exception $e ) {
			return null;
		}

		//$responseArray = object_to_array($responseDecoded);

		foreach ( $responseDecoded as $repoObject ) {
			$repoArray = (array) $repoObject;
			if ( $repoArray['displayName'] === $project_key
			     || $repoArray['name'] === $project_key ) {
				return preg_filter( REGEX_PATH_FROM_GIT_URL, '$1', $repoArray['location'] );
			}
		}

		return '';
	}

	/**
	 * Gets the URL of the repository, given a project name (usually the JIRA key)
	 *
	 * @param string $name The Crucible project name. See {@link getRepoName()}
	 *
	 * @return string|null The Repo URL (really just the repo name, but it's a locator anyway)
	 */
	function getRepoUrl( string $name ): ?string {
		$url = CRUCIBLE_URL . "/rest-service/repositories-v1?name=${name}";
		\scripts\general\_log( 'Calling: ' . $url );
		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$repoData = curl_exec( $connection );

		curl_close( $connection );
		\scripts\general\_log( 'Received answer getting repository names:' );
		\scripts\general\_log( $repoData );

		$repoDecoded = null;
		try {
			$repoDecoded = new \SimpleXMLElement( $repoData );
		} catch ( \Exception $e ) {
			return null;
		}


		$repoArray = \scripts\general\object_to_array( $repoDecoded );
		$repoUrl   = preg_filter( '/^.+\/(.+)\.git$/', '$1', $repoArray['repoData']['location'] );
		if ( $repoUrl === null ) {
			\scripts\general\_log( "No repositories with name $name found" );
			header( "HTTP/1.0 400 Bad Request: No repositories with name $name found" );
			exit( 400 );
		}

		return $repoUrl;
	}
}

namespace scripts\gitlab\decode_payload {
	/**
	 * Gets the issue key (e.g. TEST-3) from the payload.
	 *
	 * @return string The issue key.
	 */
	function get_issue_id(): string {
		try {
			$branch_name = json_decode( \POST_PAYLOAD, true, 512,
				JSON_THROW_ON_ERROR )['object_attributes']['source_branch'];
		} catch ( \JsonException $e ) {
			return '';
		}

		$branch_name = \scripts\general\make_safe_string( $branch_name );

		$filter = preg_filter( REGEX_ISSUE_ID_FROM_BRANCH, '$1', $branch_name );

		return \scripts\general\make_safe_string( $filter );
	}

	/**
	 * Gets the included command (e.g. #comment) from the commit message.
	 *
	 * @return string The command.
	 */
	function get_issue_command(): string {
		try {
			$branch_name = json_decode( \POST_PAYLOAD, true, 512,
				JSON_THROW_ON_ERROR )['object_attributes']['last_commit']['message'];
		} catch ( \JsonException $e ) {
			return '';
		}

		$branch_name = \scripts\general\make_safe_string( $branch_name );

		$filter = preg_filter( REGEX_COMMAND_FROM_COMMENT, '$1', $branch_name );

		return \scripts\general\make_safe_string( $filter );
	}

	/**
	 * @return string The target branch of the merge action.
	 */
	function get_target_branch(): string {
		try {
			$rv = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['object_attributes']['target_branch'];
		} catch ( \JsonException $e ) {
			return '';
		}

		return \scripts\general\make_safe_string( $rv );
	}

	/**
	 * @return bool `true` if the merge action was "merge succeeded"
	 */
	function is_changed_to_merged(): bool {
		try {
			$state = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['object_attributes']['state'];
		} catch ( \JsonException $e ) {
			return false;
		}

		try {
			$action = json_decode( \POST_PAYLOAD, true, 512, JSON_THROW_ON_ERROR )['object_attributes']['action'];
		} catch ( \JsonException $e ) {
			return false;
		}

		return (
			( \scripts\general\make_safe_string( $state ) === 'merged' )
			&& ( \scripts\general\make_safe_string( $action ) === 'merge' )
		);
	}
}

namespace scripts\gitlab\curl {

	use function scripts\general\_log;

	function getProjectIdByName( string $repo_name ): int {
		$url = GITLAB_URL . '/api/v4/projects/?private_token=' . GITLAB_TOKEN;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );

		try {
			$decoded = json_decode( $response, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return '';
		}

		foreach ( $decoded as $project ) {
			if ( $project['path_with_namespace'] === REPOSITORY_GROUP_PREFIX . $repo_name ) {
				return $project['id'];
			}
		}

		exit();
	}

	/**
	 * Creates a branch in the given project with the given name.
	 *
	 * The branch is forked from `master`.
	 *
	 * @param string $repo_id The repo key, see {@link getRepoKey()}
	 * @param string $branch_name The name of the branch to be created.
	 */
	function createBranch( string $repo_id, string $branch_name ) {
		$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode( $repo_id ) . '/repository/branches?private_token=';
		$url_suffix
		            = "&branch=$branch_name" .
		              '&ref=master';

		\scripts\general\_log( 'Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...' );

		$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_POST, true );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );
	}

	/**
	 * Gets the ID of the branch we're working on. This is *technically* not necessary, but we do it
	 * anyway, because we want to make sure.
	 *
	 * @param string $repo_id The key of the repository to use.
	 * @param string $issue_key The issue ID from JIRA.
	 *
	 * @return string The branch ID concerning the ticket.
	 */
	function get_branch_id( string $repo_id, string $issue_key ): string {
		$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode( $repo_id ) . '/repository/branches?private_token=';
		$url_suffix
		            = "&search=^${issue_key}_";

		\scripts\general\_log( 'Calling ' . $url_prefix . '[redacted]' . $url_suffix . '...' );

		$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );

		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );

		$result = null;
		try {
			$result = json_decode( $response, true, 512, JSON_THROW_ON_ERROR )[0]['name'];
		} catch ( \JsonException $e ) {
			$result = '';
		}

		return \scripts\general\make_safe_string( $result );
	}

	/**
	 * Creates a merge request in the given project, from the branch $from to the branch $to.
	 *
	 * @param string $repo_key The key of the affected repository. See {@link getRepoKey()} and
	 *                         {@link REPOSITORY_GROUP_PREFIX}.
	 * @param string $from The source branch to merge from.
	 * @param string $to The target branch to merge to.
	 */
	function create_mr( string $repo_key, string $from, string $to ): void {
		$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode( $repo_key ) . '/merge_requests?private_token=';
		$url_suffix
		            = "&source_branch=$from" .
		              "&target_branch=$to" .
		              "&title=$from" .
		              '&remove_source_branch=false';

		\scripts\general\_log( 'Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...' );

		$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_POST, true );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );
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
	function merge( string $repo_key, string $from, string $to ): void {
		$url_prefix = GITLAB_URL . '/api/v4/projects/' . urlencode( $repo_key ) . '/merge_requests?private_token=';
		$url_suffix
		            = "&source_branch=$from" .
		              "&target_branch=$to" .
		              '&title=' . $from .
		              '&remove_source_branch=false';

		\scripts\general\_log( 'Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...' );

		$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_POST, true );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );

		$response_decoded = null;

		try {
			$response_decoded = json_decode( $response, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			_log( "Can't decode JSON, Exception thrown" );
		}


		$id  = $response_decoded['id'];
		$iid = $response_decoded['iid'];

		\scripts\general\_log( 'Sleeping for 10 seconds.' );
		sleep( 10 );
		\scripts\general\_log( 'Back.' );

		\scripts\general\_log( "ID is $id, IID is $iid." );

		$url_prefix = GITLAB_URL
		              . '/api/v4/projects/'
		              . urlencode( $repo_key )
		              . '/merge_requests/' . $iid
		              . '/merge?private_token=';
		$url_suffix
		            = '&should_remove_source_branch=true' .
		              '&squash=true';

		\scripts\general\_log( 'Calling ' . $url_prefix . 'XXXXX' . $url_suffix . '...' );

		$url = $url_prefix . GITLAB_TOKEN . $url_suffix;

		$connection = curl_init();
		curl_setopt( $connection, CURLOPT_URL, $url );
		curl_setopt( $connection, CURLOPT_PUT, true );
		curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $connection );
		curl_close( $connection );

		\scripts\general\_log( 'Received answer from Gitlab:' );
		\scripts\general\_log( $response );
	}
}