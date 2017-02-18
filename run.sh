#!/usr/bin/env bash

# Configuration Variables
PHP_PORT=8080

# Auto-Configuration Variables
CHENARD_ENABLED=$(php -r '$CONFIG = require "app/configuration.php"; echo (int)$CONFIG["chenard"]["enabled"];')
CHENARD_PORT=$(php -r '$CONFIG = require "app/configuration.php"; echo isset($CONFIG["chenard"]["port"]) ? (int)$CONFIG["chenard"]["port"] : 12345;')

# Do not touch - State Variables
CHENARD_CONTROL=1

# Some Functions
runPHP(){
    # Run the PHP Server blocking, if it gets closed we just clean up afterwards
    pushd web > /dev/null
        php -S localhost:${PHP_PORT} -t $(pwd)
    popd > /dev/null
}

runChenardServer() {
    # Run Chenard if it does not exist
    if [[ ! -e .chenard.pid ]]; then
        echo "Starting Chenard on localhost:${CHENARD_PORT}"
        chenserver -p ${CHENARD_PORT} &
        CHENARD_PID=$!

        echo ${CHENARD_PID} > .chenard.pid
    fi
}

checkChenardPid() {
    # Pid file Handling
    if [[ -e .chenard.pid ]]; then
        if ps -p $(cat .chenard.pid); then
            echo "Found running Chenard instance, won't start my own."

            # We didn't started it so we won't kill it then...
            CHENARD_CONTROL=0
        else
            echo "Found stale Chenard pid file, removing."
            rm .chenard.pid
        fi
    fi
}

cleanupChenardPid() {
    # Only clean up chenard process if we started it.
    if [[ ${CHENARD_CONTROL} -eq 1 ]]; then
        echo "Killing Chenard..."
        kill $(cat .chenard.pid) > /dev/null
        rm .chenard.pid
    fi
}

# Vendors check
if [[ ! -e vendor ]]; then
    echo "You need to run 'install-vendors.sh' first."
    exit 1
fi

if [[ ${CHENARD_ENABLED} -eq 1 ]]; then
    if ! which chenserver > /dev/null 2>&1; then
        echo "You need to have chenserver in your PATH, you may install it via install-chenard.sh script (build tools needed!).";
        exit 1
    fi

    checkChenardPid
    runChenardServer
    runPHP
    cleanupChenardPid
else
    echo "Run without Chenard"

    runPHP;
fi
