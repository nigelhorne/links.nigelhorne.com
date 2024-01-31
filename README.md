# links.nigelhorne.com
Source of [links.nigelhorne.com](http://links.nigelhorne.com), a URL shortening system.

To install,
install the prequisites and then copy etc/links.csv/SAMPLE to etc/links.csv.
* CGI::ACL
* CGI::Alert
* FCGI
* Log::WarnDie
* String::Random

Uses .htaccess to map /l/code to cgi-bin/links.fcgi/entry=code, for even more brevity.
