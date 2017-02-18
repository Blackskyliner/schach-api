#!/usr/bin/env bash

if [[ -e bin/chenserver ]]; then
    echo "Chenserver is already installed to $(pwd)/bin/chenserver"
    exit
fi

if [[ ! -e chenard ]]; then
    git clone https://github.com/cosinekitty/chenard
    if [[ ! $? ]]; then
        echo "Could not clone chenard from git. Is 'git' installed or is the repository missing?"
    fi
fi

if [[ ! -e bin ]]; then
    mkdir bin
fi

pushd chenard/chenserver > /dev/null
    echo "Running build process for chenserver..."
    ./build
    if [[ $? ]]; then
        mv chenserver ../../bin
    else
        echo "The build process of chenserv failed. Are you missing 'build-essentials' or 'g++'?"
        exit
    fi
popd > /dev/null

echo "Cleanup build environment"
rm -fR chenard

echo "Add the following line to you .bashrc if you want to automatically have chenserver in your environment."
echo '    export PATH="$PATH:'$(pwd)/bin'"'
