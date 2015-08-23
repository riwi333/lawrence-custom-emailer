#!/usr/bin/python

# upload relevant files to ftp.thelawrence.org automatically
# credentials not included :)
# (8/10/2015)

FTP_USER = ""
FTP_PASS = ""

from ftplib import FTP
import sys

del sys.argv[0]

# NOTE: only files with names in total[] will be uploaded
path_to_total = '/home/trifork/lawr/plugin/lawrence-custom-emailer/'
total = [	
			'lawrence-custom-emailer.php',
			'lawrence-custom-emailer.css',
			'formats.php',
			'groups.php',
			'parse.php'
		]

upload = []

# if no command-line arguments, upload everything; otherwise, just upload what's given
if len(sys.argv) > 0:
	for x in sys.argv:
		if x in total:
			upload.append(x)
else:
	for x in total:
		upload.append(x)

print 'Starting a new FTP session...'
session = FTP('ftp.thelawrence.org')
session.login(user = FTP_USER, passwd = FTP_PASS)
#session.set_pasv(False)

print 'Navigating to the plugin directory...'
session.cwd('plugins/lawrence-custom-emailer')

print 'Uploading files...'
for i in xrange(0, len(upload)):
	session.storlines("STOR " + upload[i], open(path_to_total + upload[i], 'r'))
	print upload[i] + ' uploaded.'

print 'Closing the FTP session...'
session.quit()

