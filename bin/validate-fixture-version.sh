#!/bin/bash
set -euo pipefail
IFS=$'\n\t'

main(){
    export TERMINUS_HIDE_GIT_MODE_WARNING=1
    local DIRNAME=$(dirname "$0")

    if [ -z "${TERMINUS_SITE}" ]; then
        echo "TERMINUS_SITE environment variable must be set"
        exit 1
    fi

    if ! terminus whoami > /dev/null; then
        if [ -z "${TERMINUS_TOKEN}" ]; then
            echo "TERMINUS_TOKEN environment variable must be set or terminus already logged in."
            exit 1
        fi
        terminus auth:login --machine-token="${TERMINUS_TOKEN}"
    fi


    # Use find to locate the file with a case-insensitive search
    README_FILE_PATH=$(find ${DIRNAME}/.. -iname "readme.txt" -print -quit)
    if [[ -z "$README_FILE_PATH" ]]; then
        echo "readme.txt not found."
        exit 1
    fi

    local TESTED_UP_TO
    TESTED_UP_TO=$(grep -i "Tested up to:" "${README_FILE_PATH}" | tr -d '\r\n' | awk -F ': ' '{ print $2 }')
    echo "Tested Up To: ${TESTED_UP_TO}"
    local FIXTURE_VERSION
    FIXTURE_VERSION=$(terminus wp "${TERMINUS_SITE}.dev" -- core version)
    echo "Fixture Version: ${FIXTURE_VERSION}"

    if ! php -r "exit(version_compare('${TESTED_UP_TO}', '${FIXTURE_VERSION}'));"; then
        echo "${FIXTURE_VERSION} is less than ${TESTED_UP_TO}"
        echo "Please update ${TERMINUS_SITE} to at least WordPress ${TESTED_UP_TO}"
        exit 1
    fi
}

main
