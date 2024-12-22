#!/usr/bin/perl
use strict;
use warnings;

# CPU fallback multi-bitrate encoder. Designed for headless servers without
# an NVIDIA GPU; the Rust nvenc pipeline does the same job with HLS output and
# h264_nvenc when one is available.
#
# Input is communicated by environment variable so the same script can be
# driven by any ingest worker via encode/hook.sh, which normalises the
# variable names to:
#
#     INPUT_NAME, INPUT_HASH, INPUT_DIR
#
# All paths and the log prefix are configurable via env. See defaults below.

use Cwd 'abs_path';
use File::Copy;
use Time::HiRes qw/gettimeofday/;
use File::Find;
use Sort::Naturally;
use File::Basename;
use IO::Handle;
use File::Copy "cp";
use Scalar::Util qw(looks_like_number);

sub trim       { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s }
sub rtrim      { my $r = shift; $r =~ s/\s+$//;       return $r }
sub escape_shell_param($) {
	my ($par) = @_;
	$par =~ s/'/'"'"'/g;
	return "'$par'";
}

my $date = localtime();
my $timestamp = gettimeofday;

# Configuration. Override any of these in the environment before invoking.
my $CONF_DEBUG          = $ENV{ENCODE_DEBUG}        // 1;
my $CONF_PREFIX         = $ENV{ENCODE_LOG_PREFIX}   // 'STREAM';
my $CONF_FFMPEG_BIN     = $ENV{ENCODE_FFMPEG_BIN}   // '/usr/bin/ffmpeg';
my $CONF_OUTPUT_ROOT    = $ENV{ENCODE_OUTPUT_ROOT}  // '/var/lib/movieventure/encodes';
my $CONF_LOGS_ROOT      = $ENV{ENCODE_LOGS_ROOT}    // '/var/log/movieventure/encode';
my $CONF_CALL_LOG_FILE  = $ENV{ENCODE_CALL_LOG}     // "$CONF_LOGS_ROOT/call.log";

# Read input from the normalised env vars. hook.sh will have populated these
# regardless of which downloader actually triggered us.
my $INPUT_NAME = $ENV{INPUT_NAME};
my $INPUT_HASH = $ENV{INPUT_HASH};
my $INPUT_DIR  = $ENV{INPUT_DIR};

# stdbuf wrapper so ffmpeg's progress lines flush as they're produced rather
# than buffering and arriving in a wall. Interchangeable with `unbuffer`.
my $FFMPEG_CMD_WORK = " 2>&1 | stdbuf -i0 -o0 -eL tr '\\r' '\\n'";

if ($CONF_DEBUG) {
	if (open(my $fh, '>>', $CONF_CALL_LOG_FILE)) {
		print $fh "env INPUT_NAME='" . ($INPUT_NAME // '') . "' " .
		          "INPUT_HASH='"     . ($INPUT_HASH // '') . "' " .
		          "INPUT_DIR='"      . ($INPUT_DIR  // '') . "' " .
		          abs_path($0) . " #${date}\n";
		close $fh;
	}
}

if (!defined $INPUT_NAME || !defined $INPUT_HASH || !defined $INPUT_DIR ||
    $INPUT_HASH !~ m/^[a-f0-9]{40}$/i ||
    $INPUT_NAME !~ m/.+/s ||
    $INPUT_DIR  !~ m/.+/s) {
	print STDERR "encode.pl: INPUT_NAME / INPUT_HASH / INPUT_DIR must be set " .
	             "(INPUT_HASH expects 40 hex chars).\n";
	exit 2;
}
$INPUT_HASH = uc $INPUT_HASH;
my $WORK_DIR = "${INPUT_DIR}/${INPUT_NAME}";

my $OUTPUT_DIR = "${CONF_OUTPUT_ROOT}/${INPUT_HASH}";
my $LOG_FILE   = "${CONF_LOGS_ROOT}/${INPUT_HASH}.log";

local $SIG{__DIE__} = sub {
	my ($message) = @_;
	if (open (my $fh, '>>', $LOG_FILE)) {
		print $fh localtime() . ": $message";
		close $fh;
	}
};

if (!-d $OUTPUT_DIR) {
	mkdir $OUTPUT_DIR or die "Could not create output directory ($OUTPUT_DIR): $!";
}

open (my $info, '>', "${OUTPUT_DIR}/info.txt") or die "Could not open info.txt: $!";
	print $info $INPUT_NAME;
close $info;

open (my $log, '>', $LOG_FILE) or die "Could not open log file ($LOG_FILE): $!";

print $log "${CONF_PREFIX}_ENCODE_START "       . trim(`date +%s`) . " (${date})\n";
print $log "${CONF_PREFIX}_ENCODE_INPUT_NAME "  . $INPUT_NAME      . "\n";
print $log "${CONF_PREFIX}_ENCODE_INPUT_DIR "   . $WORK_DIR        . "\n";
print $log "${CONF_PREFIX}_ENCODE_OUTPUT_DIR "  . $OUTPUT_DIR      . "\n";
print $log "${CONF_PREFIX}_ENCODE_LOG_FILE "    . $LOG_FILE        . "\n";

# 1 if we're encoding something like a film which might have cd1 / cd2 / part1
# pieces, 0 if it's a season of TV with each episode separate. Detection is
# left to the user for now.
my $CONCAT_FILES = 0;
print $log "${CONF_PREFIX}_ENCODE_CONCAT_FILES ${CONCAT_FILES}\n";

my @files_found;
find(sub { -f and push @files_found, $File::Find::name } , $WORK_DIR);
@files_found = grep (/\.(mp4|m4v|mkv|avi|ts)$/, @files_found);
@files_found = nsort(@files_found);

if (scalar @files_found eq 0) {
	die "${CONF_PREFIX}_FAIL Unable to find any encodable files\n";
}

# ABR ladder. Match the JSON ladder in config/config.example.php; both
# pipelines should produce the same set of variants so the player can switch
# between them without knowing which encoder produced the stream.
my %LADDER = (
	"0_FHD" => { Height => 1080, VideoBitrate => 4000, AudioBitrate => 128, AudioChannels => 2 },
	"1_HDR" => { Height =>  720, VideoBitrate => 1500, AudioBitrate => 128, AudioChannels => 2 },
	"2_SD"  => { Height =>  480, VideoBitrate =>  800, AudioBitrate =>  64, AudioChannels => 1 },
	"3_LOW" => { Height =>   -1, VideoBitrate =>  800, AudioBitrate =>  64, AudioChannels => 1 },
	"4_RAW" => { Height =>   -1, VideoBitrate =>   -1, AudioBitrate =>  -1, AudioChannels => 1 },
);

my %TO_ENCODE;

my $i = 0;
for my $file (@files_found) {
	$i += 1;

	if ($file =~ /sample/i or -s $file < 50000000) { # 50 MB
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_X_IS_SAMPLE TRUE\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_X_SAMPLE $file\n";
		$i -= 1;
		next;
	}

	print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FILE ${file}\n";
	print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FORMAT " . substr($file, -4) . "\n";

	my $AudioBitrate   = trim(`mediainfo --Output=Audio\\;%BitRate% "${file}"`);
	my $VideoBitrate   = trim(`mediainfo --Output=Video\\;%BitRate% "${file}"`);
	my $OverallBitrate = trim(`mediainfo --Output=General\\;%OverallBitRate% "${file}"`);
	if ($OverallBitrate !~ /^\d+$/) {
		die "${CONF_PREFIX}_FAIL Unable to determine the bit rate of '${file}'";
	}
	if ($VideoBitrate !~ /^\d+$/ || $AudioBitrate !~ /^\d+$/) {
		# split overall 90/10 if mediainfo can't tell us per-stream
		$AudioBitrate = $OverallBitrate * 0.1;
		$VideoBitrate = $OverallBitrate * 0.9;
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_INTERPOLATED_BITRATE TRUE\n";
	}
	$AudioBitrate   = int($AudioBitrate   / 1000);
	$VideoBitrate   = int($VideoBitrate   / 1000);
	$OverallBitrate = int($OverallBitrate / 1000);

	my $Height        = trim(`mediainfo --Output=Video\\;%Height%       "${file}"`);
	my $FrameRate     = trim(`mediainfo --Output=Video\\;%FrameRate%    "${file}"`);
	my $AudioChannels = trim(`mediainfo --Output=Audio\\;%Channels%     "${file}"`);

	if ($FrameRate > 25 || !looks_like_number($FrameRate)) {
		$FrameRate = 25;
	}

	foreach my $key (sort keys %LADDER) {
		my ($n, $name) = split('_', $key, 2);
		my $rung = $LADDER{$key};

		if ($rung->{Height} > 0 && $Height > $rung->{Height} * 0.95) {
			my $channels = $AudioChannels < $rung->{AudioChannels}
				? $AudioChannels
				: $rung->{AudioChannels};
			my $vbr = $VideoBitrate > $rung->{VideoBitrate} ? $rung->{VideoBitrate} : $VideoBitrate;
			my $abr = $VideoBitrate > $rung->{VideoBitrate} ? $rung->{AudioBitrate} : $AudioBitrate;
			my $tag = $VideoBitrate > $rung->{VideoBitrate} ? '' : '-LOW';

			$TO_ENCODE{$n}{$i} = {
				Name => $name, Height => $rung->{Height}, AudioChannels => $channels,
				FrameRate => $FrameRate, File => $file,
				VideoBitrate => $vbr, AudioBitrate => $abr,
				Filename => "${i}-$rung->{Height}${tag}.mp4",
			};
		} elsif ($rung->{Height} < 0) {
			my $already_set = 0;
			foreach my $enc (values %TO_ENCODE) {
				$already_set = 1 if exists $enc->{$i};
			}
			next if $already_set;

			my $channels = $AudioChannels < $rung->{AudioChannels}
				? $AudioChannels : $rung->{AudioChannels};
			$TO_ENCODE{$n}{$i} = {
				Name => $name, Height => $Height, AudioChannels => $channels,
				FrameRate => $FrameRate, File => $file,
				VideoBitrate => $VideoBitrate, AudioBitrate => $AudioBitrate,
				Filename => "${i}-${name}.mp4",
			};
		}
	}
}

print $log "${CONF_PREFIX}_ENCODE_FILES_COUNT ${i}\n";
print $log "${CONF_PREFIX}_ENCODE_FILE_LIST_START\n" . join("\n", @files_found) . "\n${CONF_PREFIX}_ENCODE_FILE_LIST_END\n";

# Highest quality first so the player can pick whatever rung is ready earliest.
foreach my $QualityLevel (reverse sort keys %TO_ENCODE) {
	my @CONCAT;
	foreach my $FileNumber (nsort keys %{$TO_ENCODE{$QualityLevel}}) {
		my $rung = $TO_ENCODE{$QualityLevel}{$FileNumber};
		print $log "${CONF_PREFIX}_ENCODE_STATUS ENCODING ${FileNumber}/$rung->{Name}\n";

		my @cmd = ($CONF_FFMPEG_BIN);
		push @cmd, "-i", escape_shell_param($rung->{File});
		push @cmd, "-y";
		push @cmd, "-strict", "experimental";
		push @cmd, "-f", "mp4";
		push @cmd, "-c:v", "libx264";
		push @cmd, "-b:v", "$rung->{VideoBitrate}k";
		push @cmd, "-c:a", "libfdk_aac";
		push @cmd, "-b:a", "$rung->{AudioBitrate}k";
		push @cmd, "-ac",  $rung->{AudioChannels};
		push @cmd, "-r",   $rung->{FrameRate};
		push @cmd, "-vf",  "scale='-2:$rung->{Height}'";
		push @cmd, "-threads", "0";
		push @cmd, escape_shell_param("${OUTPUT_DIR}/$rung->{Filename}.tmp");

		open FFMPEG, "-|", join(' ', @cmd) . $FFMPEG_CMD_WORK or die $!;
		while (<FFMPEG>) { print $log $_; $log->flush(); }

		print $log "${CONF_PREFIX}_ENCODE_STATUS DONE ${FileNumber}/$rung->{Name}\n";

		if ($CONCAT_FILES) {
			my @concat_cmd = ($CONF_FFMPEG_BIN);
			push @concat_cmd, "-i", pop(@cmd);
			push @concat_cmd, "-y", "-c", "copy", "-bsf:v", "h264_mp4toannexb", "-f", "mpegts";
			push @concat_cmd, escape_shell_param("${OUTPUT_DIR}/$rung->{Filename}.ts");

			open FFMPEG, "-|", join(' ', @concat_cmd) . $FFMPEG_CMD_WORK or die $!;
			while (<FFMPEG>) { print $log $_; $log->flush(); }

			push @CONCAT, pop(@concat_cmd);
		}
	}

	if ($CONCAT_FILES) {
		my @cmd = ($CONF_FFMPEG_BIN);
		push @cmd, "-i", "concat:" . join("|", @CONCAT);
		push @cmd, "-y", "-c", "copy", "-bsf:a", "aac_adtstoasc";
		push @cmd, escape_shell_param(
			(do { dirname($CONCAT[0])=~s/^'//r } . "/" .
			 do { basename($CONCAT[0])=~s/^.+?-(.+)\.ts[']?$/$1/r })
		);

		open FFMPEG, "-|", join(' ', @cmd) . $FFMPEG_CMD_WORK or die $!;
		while (<FFMPEG>) { print $log $_; $log->flush(); }

		# clean up the intermediate .ts and .tmp
		my @files;
		find(sub { -f and push @files, $File::Find::name }, $OUTPUT_DIR);
		unlink($_) for grep (/\.(ts|tmp)$/, @files);
	} else {
		# atomic-ish rename of .tmp -> final
		my @files;
		find(sub { -f and push @files, $File::Find::name }, $OUTPUT_DIR);
		for my $f (grep (/\.tmp$/, @files)) {
			move($f, $f =~ s/\.tmp$//r);
		}
	}
}

print $log "${CONF_PREFIX}_ENCODE_EXECUTION_TIME " . (gettimeofday - $timestamp) . "\n";
print $log "${CONF_PREFIX}_ENCODE_DONE "            . trim(`date +%s`) . " (${date})\n";
close $log;
