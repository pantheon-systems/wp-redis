#!/bin/bash

###
# Delete the Pantheon site environment after the Behat test suite has run.
###

terminus whoami > /dev/null
if [ $? -ne 0 ]; then
	echo "Terminus unauthenticated; assuming unauthenticated build"
	exit 0
fi

set -ex

if [ -z "$TERMINUS_SITE" ] || [ -z "$TERMINUS_ENV" ]; then
	echo "TERMINUS_SITE and TERMINUS_ENV environment variables must be set"
	exit 1
fi

###
# Delete the environment used for this test run.
###
terminus multidev:delete $SITE_ENV --delete-branch --yes
