#!/usr/bin/env bash

if [[ ! -e composer.phar ]]
then

    EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig)
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', 'composer-setup.php');")

    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
    then
        >&2 echo 'ERROR: Invalid installer signature'
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php --quiet
    RESULT=$?
    rm composer-setup.php
else
    RESULT=0
fi

if [ $RESULT ]
then
    php composer.phar install
    echo "Dependencies installed, you may now start the 'run.sh' script."
else
    echo "Could not install dependencies."
fi
