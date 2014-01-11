#!/usr/bin/python
# -*- coding: utf-8 -*-

import os,sys,time,urllib2,xmltodict,pprint,pika,json,logging
from xml.dom import minidom
#from pymongo import MongoClient
from pymongo import MongoReplicaSetClient

MONGO_HOST = "127.0.0.1"
pp = pprint.PrettyPrinter(indent=4)
logging.basicConfig(format = u'%(levelname)-8s [%(asctime)s] %(message)s', level = logging.INFO, filename = u'/tmp/update_books_sync.log')
def ilog(msg):
	 logging.info( unicode( os.getpid() ) + ": " + msg )
	
def wlog(msg):
	 logging.warning( unicode( os.getpid() ) + ": " + msg )
#eksmo_url = "http://partners.eksmo.ru/wservices/xml/?action=products"
ast_url = "http://partners.eksmo.ru/wservices/xml/?action=products_ast"
eksmo_url = "http://partners.eksmo.ru/wservices/xml/?action=products_full"

#Пример:
# <result>
# <pages>
# <all>130</all>
# <current>1</current>
# <items>12967</items>
# </pages>
# <products>
# <product>
#     <bla-bla>..




ilog("starting  " )	 
mc = MongoReplicaSetClient('localhost:27017', replicaSet='rs0')
#mc = MongoClient(MONGO_HOST, 27017)

db = mc.tender
books_col = db.books_catalog
seq_col = db.sequences

def process_url(source_url):
	p = 1
	while True:
		url = source_url + '&page=' + `p`
		ilog("getting  " + url )
		for tries in range(1,3):
			try:
				xml_str = urllib2.urlopen(url)
			except:
				if tries < 4:
					tries = tries + 1
					continue
				wlog("error requesting " + url)		
		
			try:
				ilog("parsing")
				xml_parsed = xmltodict.parse(xml_str)
				break
			except:
				wlog("IN GETTING DATA FROM " + url)
		try:
		  xml_parsed
		except NameError:
		  x_exists = False
		else:
		  x_exists = True
	
		if x_exists and xml_parsed['result']:
			pages = xml_parsed['result']['pages']
			
			if pages:
				total_pages = pages['all']
				total_items = pages['items']
				current_page = pages['current']
				
			if current_page < p or p > total_pages:
				ilog('complete')
				exit()
			
			try:
				products = xml_parsed['result']['products']['product']
			except:
				ilog ("IN XML FROM " + url)
				continue
	
	 		for i in range( 1, len(products) ):
	  			book = products[i]#['#text']
	  			query = {'xml_id':products[i]['xml_id']}
	  			#ilog("find_one " + `message['task']` + ' book #' + `i`)
	  			book_stored = books_col.find_one(query)
	  			if book_stored:
	  				book['_seq_id'] = book_stored['_seq_id']
	  			#	ilog("updating " + `message['task']` + ' book #' + `i`)
	  				books_col.update(query,book,upsert=True)
	  			else:
	  				#book['_seq_id'] = db.system_js.getNextSequence('book_id')	- не работает, разобраться, поэтому тупо достаем последовательность руками:
	  				seq_query	= {'_id':'book_id'}
	  				doc			= {'$inc':{'seq':1}}
	  				seq = seq_col.find_and_modify(seq_query,doc)
	  				book['_seq_id'] = seq['seq']
	  			#	ilog("inserting " + `message['task']` + ' book #' + `i`)
	  				books_col.insert(book)
		p = p + 1
		ilog("complete with p = " + `p` )
		
process_url(ast_url)
process_url(eksmo_url)

