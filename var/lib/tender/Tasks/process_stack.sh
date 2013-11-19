#!/bin/sh

{
echo publish ProcessStack 1
sleep 1
echo quit
} | telnet 127.0.0.1   6379
