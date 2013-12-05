update: dirs upd_services upd_www restart

CUR_DIR = $(shell pwd)
WHOAMI = $(shell logname) 
CUR_DATE = $(shell date +%Y%m%d_%H%M%S)
#directories
TDIR = /var/www/tender
FDIR = /var/www/files
RTDIR = /var/www/tender/protected/runtime
ASDIR = /var/www/tender/assets
SPHDIR = /var/sphinx
CRFDIR = create_fdir
upd_services:
	cp -rf ${CUR_DIR}/etc/* /etc/
	cp -rf ${CUR_DIR}/var/lib/* /var/lib/

upd_www:
	cp -rf ${CUR_DIR}/var/www/yii /var/www/yii
dirs:
	if [ ! -d ${TDIR} ]; then ln -s ${CUR_DIR}/var/www/tender ${TDIR}; fi
	if [ ! -d ${FDIR} ]; then mkdir ${FDIR}; mkdir ${FDIR}/1.uploaded; mkdir ${FDIR}/2.processing; mkdir ${FDIR}/3.done; chmod -R 777 ${FDIR}; fi
	if [ ! -d ${RTDIR} ]; then mkdir ${RTDIR}; chmod 777 ${RTDIR}; fi
	if [ ! -d ${ASDIR} ]; then mkdir ${ASDIR}; chmod 777 ${ASDIR}; fi

restart:
	service queue_manager restart
	service stat_manager restart
	
sphinx:
	if [ ! -d ${SPHDIR} ]; then mkdir ${SPHDIR}; chown sphinx:sphinx ${SPHDIR}; fi	
	indexer --config /etc/sphinx/sphinx.conf --all

