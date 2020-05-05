#!/usr/bin/env bash

PSH_PROJECT_ID=$1
PSH_ENVIRONMENT=${2-master}

if [ -z "$PSH_PROJECT_ID" ]; then
    echo ""
    echo "ERROR: Please provide Platform.sh Project Id"
    echo ""
    echo "Available projects:"
    platform project:list --columns id,title 2>/dev/null
    exit 1
fi

export PLATFORM_ROUTES=$(platform ssh -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app -q 'echo $PLATFORM_ROUTES')
platform tunnel:open -q -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app
export PLATFORM_RELATIONSHIPS="$(platform tunnel:info -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app --encode)"
#export PLATFORM_RELATIONSHIPS=$(platform ssh -p ${PSH_ENVIRONMENT} -e ${PSH_ENVIRONMENT} -A app -q 'echo $PLATFORM_RELATIONSHIPS')
export PLATFORM_VARIABLES=$(platform ssh -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app -q 'echo $PLATFORM_VARIABLES')
export PLATFORM_APPLICATION=$(platform ssh -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app -q 'echo $PLATFORM_APPLICATION')
export PLATFORM_PROJECT_ENTROPY=$(platform ssh -p ${PSH_PROJECT_ID} -e ${PSH_ENVIRONMENT} -A app -q 'echo $PLATFORM_PROJECT_ENTROPY')
export PLATFORM_APPLICATION_NAME=app
export PLATFORM_ENVIRONMENT=local