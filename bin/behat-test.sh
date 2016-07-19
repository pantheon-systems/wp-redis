#!/bin/bash

###
# Execute the Behat test suite against a prepared Pantheon site environment.
###

set -ex

export BEHAT_PARAMS='{"extensions" : {"Behat\\MinkExtension" : {"base_url" : "http://'$TERMINUS_ENV'-'$TERMINUS_SITE'.pantheonsite.io"} }}'

./vendor/bin/behat "$@"
