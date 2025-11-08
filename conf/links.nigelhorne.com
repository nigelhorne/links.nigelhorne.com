<?xml version="1.0"?>
<config>
	<logger>
		<syslog>
			<host>108.44.193.70</host>
			<port>514</port>
			<type>udp</type>
		</syslog>
		<file>/tmp/links.log</file>
		<level>notice</level>
		<sendmail>
			<to>alerts@nigelhorne.com</to>
			<from>webmaster@nigelhorne.com</from>
			<subject>links: error in links.fcgi</subject>
			<level>warn</level>
		</sendmail>
	</logger>
</config>
