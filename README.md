# links.nigelhorne.com
Source of [links.nigelhorne.com](http://links.nigelhorne.com), a URL shortening system.

To install,
install the prequisites and then copy etc/links.csv/SAMPLE to etc/links.csv.

Uses .htaccess to map /l/code to cgi-bin/links.fcgi/entry=code, for even more brevity.

If you get the message "Died" from bin/generate,
you've probably forgotten to copy etc/links.csv.SAMPLE to etc/links.csv
