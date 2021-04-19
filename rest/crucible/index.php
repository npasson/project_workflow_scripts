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


// ==================================================== //

function crucible() {
	_log( 'Called at ' . date( 'c' ) );

	if ( empty( POST_PAYLOAD ) ) {
		_log( 'POST payload empty. Exiting.' );
		exit( 1 );
	}

	_log( 'POST payload received:' );
	_log( POST_PAYLOAD );

	_log( 'Checking for comment...' );
	$comment = scripts\crucible\decode_payload\getComment();
	$action  = preg_filter( REGEX_COMMAND_FROM_COMMENT, '$1', $comment );
	if ( $action === null ) {
		_log( 'Response is empty. Exiting.' );
		exit( 1 );
	}

	// XXX DO NOT COMPARE THESE STRINGS DIRECTLY.
	// Why? I do not know. (probably invisible characters)
	// The line below works, don't touch it.
	if ( strpos( $action, CODE_DONE_COMMAND ) !== 0 ) {
		_log( "Didn't match filter, exiting." );
		_log( "Action was: $action" );
		exit( 1 );
	}

	_log( 'Getting repo and branch name...' );
	$repo_name   = scripts\crucible\decode_payload\getRepoName();
	$branch_name = scripts\crucible\decode_payload\getBranchName();
	_log( 'Got repo name: ' . $repo_name );
	_log( 'Got branch name: ' . $branch_name );

	_log( 'Getting repo URL...' );
	$repo_url = scripts\crucible\curl\getRepoUrl( $repo_name );
	_log( 'Got repo URL: ' . $repo_url );

	_log( 'Getting repo ID...' );
	$repo_id = scripts\gitlab\curl\getProjectIdByName( $repo_url );
	_log( "Repo ID: ${repo_id}" );

	_log( 'Creating merge request...' );
	scripts\gitlab\curl\create_mr( $repo_id, $branch_name, 'dev' );
	_log( 'Created merge request. Exiting.' );
}

crucible();