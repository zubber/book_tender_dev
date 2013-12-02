#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys,json
reload(sys)
sys.setdefaultencoding('utf-8')

import xml.etree.ElementTree as ET
from xml.dom import minidom
from pymongo import MongoClient



MONGO_HOST = "127.0.0.1"

connection = MongoClient(MONGO_HOST, 27017)
db = connection.tender
books_col = db.books_catalog

query = {}
books_cursor = books_col.find(query)

root = ET.Element("sphinx:docset")
schema = ET.SubElement(root, "sphinx:schema")
schema_field1 = ET.SubElement(schema, "sphinx:field")
schema_field1.set('name', 'name')
schema_field2 = ET.SubElement(schema, "sphinx:field")
schema_field2.set('name', 'author')

for book in books_cursor:
	doc = ET.SubElement(root, "sphinx:document")
	try:
		id = int(book['_seq_id'])
	except:
		raise Exception('Incorrect data in' + book['_id'])
	doc.set('id', `id`)
	name =  ET.SubElement(doc, "name")
	name.text = book['name']
	author =  ET.SubElement(doc, "author")
	author.text = book['price_authors']
	
	#print book
	
#tree = ET.ElementTree(root)
print """<?xml version="1.0" encoding="utf-8"?>"""

print ET.tostring(root)
# reparsed = minidom.parseString(rough_string)
# print reparsed.toprettyxml(indent="  ")




#http://sphinxsearch.com/docs/current.html#xmlpipe2
#Пример:
# <?xml version="1.0" encoding="utf-8"?>
# <sphinx:docset>
# 
# <sphinx:document id="1234">
# <content>this is the main content <![CDATA[[and this <cdata> entry
# must be handled properly by xml parser lib]]></content>
# <published>1012325463</published>
# <subject>note how field/attr tags can be
# in <b class="red">randomized</b> order</subject>
# <misc>some undeclared element</misc>
# </sphinx:document>
# 
# <sphinx:document id="1235">
# <subject>another subject</subject>
# <content>here comes another document, and i am given to understand,
# that in-document field order must not matter, sir</content>
# <published>1012325467</published>
# </sphinx:document>
# 
# <!-- ... even more sphinx:document entries here ... -->
# 
# </sphinx:docset>