update: upd_services upd_www restart dirs

CUR_DIR = $(shell pwd)
WHOAMI = $(shell logname) 
CUR_DATE = $(shell date +%Y%m%d_%H%M%S)

upd_services:
#	rsync -pr $(CUR_DIR)/etc/* /etc
	cp -f $(CUR_DIR)/etc/rc.d/init.d/*manager /etc/rc.d/init.d/

	
upd_www:
	cp -rf ${CUR_DIR}/var/www/yii /var/www/yii
dirs:
	mkdir /var/www/files
	mkdir /var/www/files/1.uploaded  
	mkdir /var/www/files/2.processing  
	mkdir /var/www/files/3.done
	chmod -R 777 /var/www/files
restart:
	service queue_manager restart
	service stat_manager restart
