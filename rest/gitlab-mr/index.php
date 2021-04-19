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

// ============================================== //

function gitlab_mr() {
	_log( '' );
	_log( 'Received request at ' . date( 'c' ) );
	_log( POST_PAYLOAD );

	if ( empty( POST_PAYLOAD ) ) {
		_log( 'Payload empty. Exiting.' );
		http_response_code( 402 );
		exit();
	}

	if ( scripts\gitlab\decode_payload\is_changed_to_merged() ) {
		// PR is merged

		$issue_id = scripts\gitlab\decode_payload\get_issue_id();

		if ( $issue_id === null || empty( scripts\gitlab\decode_payload\get_issue_id() ) ) {
			_log( "Branch doesn't concern an issue. Exiting." );
			http_response_code( 200 );
			exit();
		}

		if ( scripts\gitlab\decode_payload\get_target_branch() === 'dev' ) {
			scripts\jira\curl\do_jira_transition( $issue_id, JIRA_TRANSITION_TO_ACCEPTED_ID );
		}
	}
}

gitlab_mr();
