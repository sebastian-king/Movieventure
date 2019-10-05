#!/usr/bin/perl
use strict;
use warnings;

my $INPUT_ID = "a";
my $INPUT_NAME = "b";
my $INPUT_HASH = "c";
my $INPUT_DIR = "d";
my $timestamp = "1";

exec("/home/encode/push.sh 'Begun encoding: $INPUT_NAME' 'ID: $INPUT_ID\\nNAME: $INPUT_NAME\\nHASH: $INPUT_HASH\\nDIR: $INPUT_DIR\\nTIME: $timestamp'");
