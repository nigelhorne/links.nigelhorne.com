#!/usr/bin/env perl

# Copyright (C) 2024 Nigel Horne
#	https://github.com/nigelhorne/links.nigelhorne.com
#	njh@bandsman.co.uk

# TODO: DBD::RAM
use strict;
use warnings;

BEGIN {
	if(-d '/home/hornenj/perlmods') {
		# Running at Dreamhost
		unshift @INC, ('/home/hornenj/perlmods/lib/perl/5.30');
		unshift @INC, ('/home/hornenj/perlmods/lib/perl/5.30.0');
		unshift @INC, ('/home/hornenj/perlmods/share/perl/5.30');
		unshift @INC, ('/home/hornenj/perlmods/share/perl/5.30.0');
		unshift @INC, ('/home/hornenj/perlmods/lib/perl5');
		unshift @INC, ('/home/hornenj/perlmods/lib/x86_64-linux-gnu/perl/5.30.0');
	}
}

no lib '.';

use String::Random;
use CGI::Alert 'alerts@nigelhorne.com';
use CGI::Info;
use HTTP::Status ':constants';

print 'Status: ', HTTP::Status::status_message(HTTP_OK), "\n\n",
	String::Random->new()->randpattern('s' x 8), "\n";
