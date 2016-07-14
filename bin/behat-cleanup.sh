#!/bin/bash

###
# Delete the Pantheon site environment after the Behat test suite has run.
###

set -ex

if [ -z "$TERMINUS_TOKEN" ]; then
	echo "TERMINUS_TOKEN environment variables missing; assuming unauthenticated build"
	exit 0
fi

if [ -z "$TERMINUS_SITE" ] || [ -z "$TERMINUS_ENV" ]; then
	echo "TERMINUS_SITE and TERMINUS_ENV environment variables must be set"
	exit 1
fi

###
# Delete the environment used for this test run.
###
yes | terminus site delete-env --remove-branch
