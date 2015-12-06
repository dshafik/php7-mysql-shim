#!/bin/sh
export PATH=vendor/bin:$PATH
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
if [[ $# -eq 1 ]]
then 
    RUNDIR=$@
else
    RUNDIR=$(cd $DIR && cd ../ && pwd)/src
fi
RESULT=$(phpcs --colors --standard=PSR1,PSR2 $RUNDIR)
echo "$RESULT"
echo $RESULT | grep "PHPCBF CAN FIX" > /dev/null
if [[ $? -eq 0 ]]
then
    printf "Would you like to fix errors? [Y/n] "
    read answer
    if [[ $answer != "n" ]]
    then
        echo "Running phpcbf: "
        phpcbf --standard=PSR1,PSR2 $RUNDIR 
    fi
fi
echo "Running php-cs-fixer: "
php-cs-fixer fix $RUNDIR --level=psr2
