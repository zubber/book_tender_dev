#!/usr/bin/python
# -*- coding: utf-8 -*-

import os,sys,time,urllib2,xmltodict,pprint,pika,json,logging
from xml.dom import minidom
from pymongo import MongoClient

NUM_PROCESSES = 4
MONGO_HOST = "192.168.1.101"
MQ_HOST = "192.168.1.111"

pp = pprint.PrettyPrinter(indent=4)
logging.basicConfig(format = u'%(levelname)-8s [%(asctime)s] %(message)s', level = logging.INFO, filename = u'mylog.log')

eksmo_url = "http://partners.eksmo.ru/wservices/xml/?action=products"
ast_url = "http://partners.eksmo.ru/wservices/xml/?action=products_ast"
all_url = "http://partners.eksmo.ru/wservices/xml/?action=products_full"

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



#2. Форкнем N потомков 
#3. Инициируем процесс загрузки потомками книг через rabbitmq


#Публикация сообщения в rabbitmq ( url книг для из разбора и помещения в монго )
def publish(channel,data):
	message = json.dumps( data )
	ilog( u'Parent publishing ' + message )		
	channel.basic_publish(exchange='books',
						routing_key='books.get_source',
						body=message,
						properties=pika.BasicProperties(
	                         delivery_mode = 2, # make message persistent
	                      ))
	
def publish_all(channel,data):
	message = json.dumps( data )
	ilog( u'Parent publishing ' + message )		
	channel.basic_publish(exchange='books_broadcast',
						routing_key='',
						body=message,
						properties=pika.BasicProperties(
	                         delivery_mode = 2, # make message persistent
	                      ))
	
def ilog(msg):
	 logging.info( unicode( os.getpid() ) + ": " + msg )
	
def wlog(msg):
	 logging.warning( unicode( os.getpid() ) + ": " + msg )
	 
def callback(ch, method, properties, body):
	message = json.loads( body )
	ilog( "processing %r" % (body,) )
	if message['action'] == 'done':
		ch.basic_ack(delivery_tag = method.delivery_tag)
		ch.stopConsuming()
	elif message['action'] == 'parse_url':
		ilog("requesting " + message['url'])
		
		
		for tries in range(1,3):
			try:
				xml_str = urllib2.urlopen(message['url'])
			except:
				wlog("error requesting " + message['url'])
				
			ilog("do task " + `message['task']` + ' try ' + `tries`)
			
			try:
				xml_parsed = xmltodict.parse(xml_str)
				break
			except:
				wlog("IN XML FROM " + message['url'])
			
		ilog("xml parsed, processing with mongodb task " + `message['task']`)
		try:
		  xml_parsed
		except NameError:
		  x_exists = False
		else:
		  x_exists = True
		  
		if x_exists and xml_parsed['result']:
			products = xml_parsed['result']['products']['product']
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
			ilog("task complete " + `message['task']`)
			ch.basic_ack(delivery_tag = method.delivery_tag)

#Делает запрос к странице, переданной родителем и сохранет продукты из нее в монго
def child():
	logging.info( u'new worker pid=' + unicode( os.getpid() ) )
	
	connection = pika.BlockingConnection(pika.ConnectionParameters(host=MQ_HOST))
	channel = connection.channel()
	channel.exchange_declare(exchange='books', exchange_type='direct')
	channel.exchange_declare(exchange='books_broadcast',  exchange_type='fanout')
	channel.basic_qos(prefetch_count=1)
	
	res_parse = channel.queue_declare('commands', durable=True)
	res_quit = channel.queue_declare(durable=True)
	
	channel.queue_bind(exchange='books', queue=res_parse.method.queue, routing_key = 'books.get_source')
	channel.queue_bind(exchange='books_broadcast', queue=res_quit.method.queue)
	
	channel.basic_consume(callback, queue=res_parse.method.queue)
	channel.basic_consume(callback, queue=res_quit.method.queue)

	ilog( 'connecting mq..' )
	
	while True: 
		try:
			channel.start_consuming()
			ilog( '.. success connect' )
			break
		except:
			ilog("try to reconnect..")
	ilog( 'end consuming' )	
	channel.stop_consuming()
	
	rabbitmq_connection.close()
	os._exit(0)

def parent(url):
#1. Запросим первую страницу, посмотрим сколько страниц
	ilog("get first page " + url)
	xml_str = urllib2.urlopen(url)
	try:
        	xml_parsed = minidom.parse(xml_str)
        except:
    	    wlog("ERROR PARENT: parsing " + xml_str)
    	    return
	pages = xml_parsed.getElementsByTagName('pages')[0]
	
	if pages:
		total_pages = pages.getElementsByTagName('all')[0].childNodes[0].nodeValue
		total_items = pages.getElementsByTagName('items')[0].childNodes[0].nodeValue
		ilog( "total pages = " + total_pages + ", total_items " + total_items )
		 
		if ( total_pages > 0 ):
			#1. Запустим потомков, чтобы те присосались к rabbitmq
			chid_proc = []
			for process in range(NUM_PROCESSES):
				child_pid = os.fork()
				if child_pid:   #parent proc
				    chid_proc.append(child_pid)
				else:           #child proc
				    child()


			connection = pika.BlockingConnection(pika.ConnectionParameters(host=MQ_HOST))
			channel = connection.channel()
			channel.exchange_declare(exchange='books', exchange_type='direct')
			channel.exchange_declare(exchange='books_broadcast', exchange_type='fanout')
			
			#2. Опубликуем сообщения о разборе урлов
			rng = [273,316,351,352,382,411,493,492,513,512,539]
  			#for p in range(1,int(total_pages)+1):
  			for p in rng:
  				message = {'action':'parse_url', 'url' : url + '&page=' + `p`, 'task' : p}
  				publish(channel,message)

			#3. И еще одно сообщение, о том что урлы закончились и потомки могут завершить работу
  			message = {'action' : 'done'}
  			publish_all(channel,message)
 			connection.close()
			
			for c in chid_proc:
				os.waitpid(c, 0)

mc = MongoClient(MONGO_HOST, 27017)
db = mc.tender
books_col = db.books_catalog
seq_col = db.sequences

parent(all_url)


