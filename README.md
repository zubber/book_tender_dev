book_tender_dev
===============

Требования к ПО:
    ОС: CentOS release 6.4 (Final) с настроенным sendmail
    mongodb v2.4.6
    redis  v2.6.14
    mysql  Ver 14.14 Distrib 5.5.33, for Linux (x86_64) using readline 5.1
    PHP 5.5.3 (cli) (built: Aug 21 2013 18:12:49)
    обязательные модули ( ставилось из pear ):
    + redis 2.2.3
    + mongo 1.4.3
    + mysql, PDO
    + SimpleXML
    + json
    + System_Daemon


Установка.
    
    1. Создать пользователя www с sudo, выкачать под ним репозитарий. 
    2. Выполнить от корня репо sudo make install.

Обновление.
    sudo make update