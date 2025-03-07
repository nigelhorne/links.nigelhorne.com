#!/usr/bin/env perl

# Copyright (C) 2024 Nigel Horne
#	https://github.com/nigelhorne/links.nigelhorne.com
#	njh@bandsman.co.uk

# Forward to a URL in the database - add to the database using ../bin/generate
#	e.g.	http://links.nigelhorne.com/cgi-bin/links.fcgi?entry=foo

# Based on VWF - https://github.com/nigelhorne/vwf

# Can be tested at the command line, e.g.:
#	root_dir=$(pwd)/.. ./page.fcgi entry=foo

use strict;
use warnings;
# use diagnostics;

BEGIN {
	# Sanitize environment variables
	delete @ENV{qw(IFS CDPATH ENV BASH_ENV)};
	$ENV{'PATH'} = '/usr/local/bin:/bin:/usr/bin';	# For insecurity

	if(-d '/home/hornenj/perlmods') {
		# Running at Dreamhost
		unshift @INC, (
			'/home/hornenj/perlmods/lib/perl/5.34',
			'/home/hornenj/perlmods/lib/perl/5.34.0',
			'/home/hornenj/perlmods/share/perl/5.30',
			'/home/hornenj/perlmods/share/perl/5.34.0',
			'/home/hornenj/perlmods/lib/perl5',
			'/home/hornenj/perlmods/lib/x86_64-linux-gnu/perl/5.34.0',
			'/home/hornenj/perlmods/lib/perl5/x86_64-linux-gnu-thread-multi'
		);
	}
}

no lib '.';

use Log::Log4perl qw(:levels);	# Put first to cleanup last
use CGI::ACL;	# TODO: finish
use FCGI;
use File::Basename;
use CGI::Alert $ENV{'SERVER_ADMIN'} || 'alerts@nigelhorne.com';
use CGI::Info;
use Error qw(:try);
use Log::Any::Adapter;
use HTTP::Status ':constants';
use Log::WarnDie 0.09;
# Gives Insecure dependency in require while running with -T switch in Module/Runtime.pm
# use Taint::Runtime qw($TAINT taint_env);
use autodie qw(:all);

# use File::HomeDir;
# use lib File::HomeDir->my_home() . '/lib/perl5';

use lib CGI::Info::script_dir() . '/../lib';

use Database::links;

# $TAINT = 1;
# taint_env();

Log::WarnDie->filter(\&filter);

my $info = CGI::Info->new();

my @suffixlist = ('.pl', '.fcgi');
my $script_name = basename($info->script_name(), @suffixlist);
my $tmpdir = $info->tmpdir();

if($ENV{'HTTP_USER_AGENT'}) {
	# open STDERR, ">&STDOUT";
	close STDERR;
	open(STDERR, '>>', File::Spec->catfile($tmpdir, "$script_name.stderr"));
}

my $script_dir = $info->script_dir();
Log::Log4perl::init("$script_dir/../conf/$script_name.l4pconf");
my $logger = Log::Log4perl->get_logger($script_name);
Log::WarnDie->dispatcher($logger);

my $links = eval { Database::links->new("$script_dir/../etc") };
if($@) {
	$logger->error($@);
	Log::WarnDie->dispatcher(undef);
	die $@;
}

# http://www.fastcgi.com/docs/faq.html#PerlSignals
my $requestcount = 0;
my $handling_request = 0;
my $exit_requested = 0;

# CHI->stats->enable();

my @blacklist_country_list = (
	'BY', 'MD', 'RU', 'CN', 'BR', 'UY', 'TR', 'MA', 'VE', 'SA', 'CY',
	'CO', 'MX', 'IN', 'RS', 'PK', 'UA', 'XH'
);

my $acl = CGI::ACL->new()->allow_country(country => \@blacklist_country_list)->allow_ip('127.0.0.1');

sub sig_handler {
	$exit_requested = 1;
	$logger->trace('In sig_handler');
	if(!$handling_request) {
		$logger->info('Shutting down');
		Log::WarnDie->dispatcher(undef);
		exit(0);
	}
}

$SIG{USR1} = \&sig_handler;
$SIG{TERM} = \&sig_handler;
$SIG{PIPE} = 'IGNORE';

# Sanitize environment variables
delete @ENV{qw(IFS CDPATH ENV BASH_ENV)};
$ENV{'PATH'} = '/usr/local/bin:/bin:/usr/bin';	# For insecurity

# my ($stdin, $stdout, $stderr) = (IO::Handle->new(), IO::Handle->new(), IO::Handle->new());
# https://stackoverflow.com/questions/14563686/how-do-i-get-errors-in-from-a-perl-script-running-fcgi-pm-to-appear-in-the-apach
$SIG{__DIE__} = $SIG{__WARN__} = sub {
	if(open(my $fout, '>>', File::Spec->catfile($tmpdir, "$script_name.stderr"))) {
		print $fout @_;
	# } else {
		# print $stderr @_;
	}
	Log::WarnDie->dispatcher(undef);
	die @_
};

# my $request = FCGI::Request($stdin, $stdout, $stderr);
my $request = FCGI::Request();

# It would be really good to send 429 to search engines when there are more than, say, 5 requests being handled.
# But I don't think that's possible with the FCGI module

while($handling_request = ($request->Accept() >= 0)) {
	unless($ENV{'REMOTE_ADDR'}) {
		# debugging from the command line
		$ENV{'NO_CACHE'} = 1;
		if((!defined($ENV{'HTTP_ACCEPT_LANGUAGE'})) && defined($ENV{'LANG'})) {
			my $lang = $ENV{'LANG'};
			$lang =~ s/\..*$//;
			$lang =~ tr/_/-/;
			$ENV{'HTTP_ACCEPT_LANGUAGE'} = lc($lang);
		}
		Log::Any::Adapter->set('Stdout', log_level => 'trace');
		$logger = Log::Any->get_logger(category => $script_name);
		Log::WarnDie->dispatcher($logger);
		$links->set_logger($logger);
		$info->set_logger($logger);
		$Error::Debug = 1;
		# CHI->stats->enable();
		try {
			doit(debug => 1);
		} catch Error with {
			my $msg = shift;
			warn "$msg\n", $msg->stacktrace();
			$logger->error($msg);
		};
		last;
	}

	$requestcount++;
	Log::Any::Adapter->set( { category => $script_name }, 'Log4perl');
	$logger = Log::Any->get_logger(category => $script_name);
	$logger->info("Request $requestcount: ", $ENV{'REMOTE_ADDR'});
	$links->set_logger($logger);
	$info->set_logger($logger);

	my $start = [Time::HiRes::gettimeofday()];

	try {
		doit(debug => 0);
		my $timetaken = Time::HiRes::tv_interval($start);

		$logger->info("$script_name completed in $timetaken seconds");
	} catch Error with {
		my $msg = shift;
		$logger->error($msg);
		my $mail;
		if(open($mail, '|-', '/usr/sbin/sendmail -t -odq')) {
			# my $domain = $info->domain_name();

			# print $mail "To: njh\@$domain\nFrom: webmaster\@$domain\n";
			# print $mail "To: nigel_horne\@hotmail.com\nFrom: webmaster\@$domain\n";
			print $mail "To: alerts\@nigelhorne.com\nFrom: webmaster\n",
				"Subject: $script_name failure\n\n";
		}

		unless($msg =~ /read timeout/) {
			if($mail) {
				print $mail "\n\n$msg";
			} else {
				print STDERR "\n\n$msg";
			}
		}

		if($mail) {
			print $mail "$msg\nStack Trace\n", $msg->stacktrace();
		}
		$logger->error($msg->stacktrace());
		# my $i = 1;
		# $logger->trace('Stack Trace');
		# while((my @call_details = (caller($i++)))) {
			# $logger->trace($call_details[1], ':' . $call_details[2], ' in function ', $call_details[3]);
			# if($mail) {
				# print $mail $call_details[1], ':', $call_details[2], ' in function ', $call_details[3], "\n";
			# }
		# }

		if($mail) {
			if(my $params = $info->as_string()) {
				print $mail "Params\n$params\n";
			}
			print $mail "Environment\n";
			foreach my $key(sort keys %ENV) {
				if($ENV{$key}) {
					print $mail "$key=$ENV{$key}\n";
				} else {
					print $mail "$key undefined\n";
				}
			}
			close $mail;
		}
	};

	$request->Finish();
	$handling_request = 0;
	if($exit_requested) {
		last;
	}
	if($ENV{SCRIPT_FILENAME}) {
		if(-M $ENV{SCRIPT_FILENAME} < 0) {
			last;
		}
	}
}

Log::WarnDie->dispatcher(undef);
exit(0);

sub doit
{
	CGI::Info->reset();

	$logger->debug('In doit - domain is ', $info->domain_name());

	$info = CGI::Info->new(logger => $logger);

	# Check if the request is from a bot
	if($info->is_robot()) {
		# Payment required :-)
		print 'Status: 402 ', HTTP::Status::status_message(402), "\n\n";
		return;
	}

	# Fetch the entry and process redirection or 404 handling
	if(my $entry = $info->entry()) {
		if(my $location = $links->location($entry)) {
			print 'Status: 301 ',
				HTTP::Status::status_message(301),
				"\n",
				"Location: $location\n\n";
			$logger->info("Redirected $entry to $location");
		} else {
			print_error(404, "Could not find $entry in the database");
		}
	} else {
		print_error(400, 'Invalid or missing entry parameter');
	}
}

sub print_error {
	my ($status, $message) = @_;
	print "Status: $status ", HTTP::Status::status_message($status), "\n",
		"Content-Type: text/html; charset=ISO-8859-1\n\n",
		"$message\n";
	$logger->warn($message);
}

# False positives we don't need in the logs
sub filter
{
	# return 0 if($_[0] =~ /Can't locate Net\/OAuth\/V1_0A\/ProtectedResourceRequest.pm in /);
	# return 0 if($_[0] =~ /Can't locate auto\/NetAddr\/IP\/InetBase\/AF_INET6.al in /);
	# return 0 if($_[0] =~ /S_IFFIFO is not a valid Fcntl macro at /);

	return 0 if $_[0] =~ /Can't locate (Net\/OAuth\/V1_0A\/ProtectedResourceRequest\.pm|auto\/NetAddr\/IP\/InetBase\/AF_INET6\.al) in |
		   S_IFFIFO is not a valid Fcntl macro at /x;
	return 1;
}
