#!/bin/sh

if [ $1 == "start" ]
then   
    echo "do start...\n"
    /usr/bin/php ProxyServer.php &
elif [ $1 == "stop" ] 
then
    echo "do stop...\n"
    ps -ef | grep "gameproxy" | grep -v "grep" | awk '{print $2 }' | xargs kill -9
else
    echo "Please make sure the positon variable is start or stop.\n"
fi