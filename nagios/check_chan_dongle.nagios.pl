#!/usr/bin/env perl

use Data::Dumper;
use IO::Select;

my $chan = `asterisk -rx "dongle show devices"`;
my @raw_output = split(/\n/, $chan);
my @result;
my(%ERRORS) = ( OK=>0, WARNING=>1, CRITICAL=>2, UNKNOWN=>3, WARN=>1, CRIT=>2 );

foreach(@raw_output) {
  next if($_ =~ /Provider Name|Asterisk ending/);
  my @args = split(/\ +/, $_);
#  dc_2898_7403 0     Free       19   0
#  dc_0677_1539 0     Not connec 0    0
  $_ =~ /^([a-z0-9_]+)\s+\d+\s+([a-zA-Z ]+)\s+/;
  $dongle=$1;
  $status=$2;
#  print 'dongle='.$dongle."; ";
#  print 'status='.$status.'; ';
#  print 'line='.$_."\n";
  if($status !~ /Free|Incoming|Outgoing|Dialing/) {
    push(@result, $dongle.'; status:'.$status);
  }
}

my $count = scalar(grep $_, @result);
if($count gt 0) {
  print 'CRITICAL: Dongle: ';
  foreach(@result) { print $_.' '; }
  print "\n";
  exit $ERRORS{CRITICAL};
}

print "OK: All dongles work properly\n";
exit $ERRORS{OK};