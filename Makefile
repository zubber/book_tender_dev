update: dirs upd_services upd_www restart

CUR_DIR = $(shell pwd)
WHOAMI = $(shell logname) 
CUR_DATE = $(shell date +%Y%m%d_%H%M%S)
#directories
TDIR = /var/www/tender
fdir = /var/www/files

upd_services:
	cp -rf ${CUR_DIR}/etc/* /etc/
	
upd_www:
	cp -rf ${CUR_DIR}/var/www/yii /var/www/yii
dirs:
	if [ ! -d ${TDIR} ]; then ln -s ${CUR_DIR}/var/www/tender ${TDIR}; fi
	if [ ! -d ${FDIR} ]; then create_fdir; fi
	
restart:
	service queue_manager restart
	service stat_manager restart
	
create_fdir:
	    mkdir ${FDIR}
	    mkdir ${FDIR}/1.uploaded  
	    mkdir ${FDIR}/2.processing  
	    mkdir ${FDIR}/3.done
	    chmod -R 777 ${FDIR}
