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

require_once $_SERVER['DOCUMENT_ROOT'] . '/rest/functions.php';

use function scripts\general\_log;

// ==================================================== //

function create_branch() {
	_log( '' );
	_log( 'Called at ' . date( 'c' ) );

	if ( empty( POST_PAYLOAD ) ) {
		_log( 'POST payload empty. Exiting.' );
		exit( 1 );
	}

	_log( 'POST payload from JIRA received:' );
	_log( '= = = = = =' );
	_log( POST_PAYLOAD );
	_log( '= = = = = =' );

	// get issue and project key from JIRA
	$issue_key = \scripts\jira\decode_payload\getIssueKey();
	_log( 'Issue key is ' . $issue_key );
	$project_key = \scripts\jira\decode_payload\getProjectKey();
	_log( 'Project key is ' . $project_key );
	$clean_name = \scripts\jira\decode_payload\getCleanIssueTitle();
	_log( 'Clean name is ' . $clean_name );

	if ( empty( $issue_key )
	     || empty( $project_key )
	     || empty( $clean_name )
	) {
		_log( 'One or more variables are empty. Exiting.' );
		exit( 1 );
	}

	// get repository URL from Crucible
	$repo_key = \scripts\crucible\curl\getRepoKey( $project_key );
	_log( 'Repo key is ' . $repo_key );

	if ( empty( $repo_key ) ) {
		_log( 'Repository key is empty. Exiting.' );
		exit( 1 );
	}

	$repo_id = \scripts\gitlab\curl\getProjectIdByName( $repo_key );
	_log( "Repo ID is ${repo_id}." );

	if ( empty( $repo_id ) ) {
		_log( 'Repository ID is empty. Exiting.' );
		exit( 1 );
	}

	// create branch
	\scripts\gitlab\curl\createBranch( $repo_id, $issue_key . '_' . $clean_name );

	// get branch name from GitLab
	$branch_name = \scripts\gitlab\curl\get_branch_id( $repo_id, $issue_key );
	_log( 'Branch name is ' . $branch_name );
}

create_branch();
