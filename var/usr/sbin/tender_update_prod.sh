#!/bin/bash

nodes=(78.46.243.226 46.36.217.180 46.36.218.223)
#nodes=(46.36.217.180)
for node in ${nodes[*]}
do
    printf "\n\n***********************  updating %s *****************************\n\n" $node
    command="cd /var/www/book_tender_dev && sudo -u www /usr/bin/git pull && sudo /usr/bin/make update && sleep 1 && exit"
    ssh -t updater@$node -i ./.ssh/id_rsa_updater ${command}
done
