#!/usr/bin/perl
use strict;
use warnings;

# TODO
# integrate http://cpansearch.perl.org/src/MIYAGAWA/P2P-Ingest-Remote-0.02/lib/P2P/Ingest/Remote.pm
# integrate SO sysread
# add paths for libs for multi-platform support
# make it compile
# make a config file

use Cwd 'abs_path';
use File::Copy;
use DateTime;
use Time::HiRes qw/gettimeofday/;
use File::Find;
use Sort::Naturally;
use File::Basename;
use IO::Handle;
use File::Copy "cp";
use Scalar::Util qw(looks_like_number);

sub trim { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s };
sub rtrim { my $r = shift; $r =~ s/\s+$//; return $r };
sub escape_shell_param($) { #return "'", do { $a=@_; $a=~s/'/'"'"'/gr }, "'";
	my ($par) = @_;
	$par =~ s/'/'"'"'/g;
	return "'$par'";
}
sub convert_time {
  my $time = shift;
  my $days = int($time / 86400);
  $time -= ($days * 86400);
  my $hours = int($time / 3600);
  $time -= ($hours * 3600);
  my $minutes = int($time / 60);
  my $seconds = $time % 60;

  $days = $days < 1 ? '' : $days .'d ';
  $hours = $hours < 1 ? '' : $hours .'h ';
  $minutes = $minutes < 1 ? '' : $minutes . 'm ';
  $time = $days . $hours . $minutes . $seconds . 's';
  return $time;
}

my $date = localtime();
my $timestamp = gettimeofday;

# Configuration section start
my $CONF_DEBUG = 1;
my $CONF_CALL_LOG_FILE = "/home/encode/scripts/call.log";
my $CONF_BACKUP_ITEM_FILES = 1;
my $CONF_INGEST_USERNAME = "seb";
my $CONF_INGEST_PASSWORD = "<pw>";
my $CONF_PREFIX = "MOVIEVENTURE";
my $CONF_FFMPEG_BIN = "/usr/bin/ffmpeg";
my $CONF_ETA_DELAY = 100;
my $CONF_TIMEZONE = "America/Chicago"; # we can probably retrieve this automatically from the machine
# no trailing slashes please
my $CONF_ENCODE_OUTPUT_DIR = "/home/encode/output";
my $CONF_ENCODE_LOG_DIR = "/home/encode/scripts/logs";
my $CONF_ITEM_BACKUP_DIR = "/home/encode/scripts/items";
my $CONF_INGEST_ITEMS_DIR = "/home/ingest-daemon/.config/ingest-daemon/items"; # default location as of at least ingest-daemon v2.84
# Configuration section end
# the below variable's leading space is very important, do not remove.
my $FFMPEG_CMD_WORK = " 2>&1 | stdbuf -i0 -o0 -eL tr '\\r' '\\n'"; # this may need to be modified on some systems, stdbuf <args> is interchangable with expect's unbuffer

sub current_time_tz {
        my $d = DateTime->now(time_zone => $CONF_TIMEZONE);
        return $d;
}

my $INPUT_ID;
my $INPUT_NAME;
my $INPUT_HASH;
my $INPUT_DIR;

if ($CONF_DEBUG) {
        open(my $fh, '>>', $CONF_CALL_LOG_FILE) or die "Could not open call log file (${CONF_CALL_LOG_FILE}): $!";
        print $fh "env INPUT_ID='$ENV{'INPUT_ID'}' env INPUT_HASH='$ENV{'INPUT_HASH'}' env INPUT_NAME='$ENV{'INPUT_NAME'}' env INPUT_DIR='$ENV{'INPUT_DIR'}' " . abs_path($0) . " #${date}\n";
        close $fh;
}

if (defined $ENV{'INPUT_ID'} && defined $ENV{'INPUT_NAME'} && defined $ENV{'INPUT_HASH'} && defined $ENV{'INPUT_DIR'}
		&& $ENV{'INPUT_ID'} =~ m/^\d+$/i && $ENV{'INPUT_HASH'} =~ m/^[a-f0-9]{40}$/i && $ENV{'INPUT_NAME'} =~ m/.+/s && $ENV{'INPUT_DIR'} =~ m/.+/s) {
	$INPUT_ID = $ENV{'INPUT_ID'};
	$INPUT_NAME = $ENV{'INPUT_NAME'};
	$INPUT_HASH = uc $ENV{'INPUT_HASH'};
	$INPUT_DIR = $ENV{'INPUT_DIR'};
} else {
	print "The given environment variables must be the in the format (all checks are case insensitive):\n	INPUT_ID	/^[a-f0-9]{16}\$/\n	INPUT_NAME	/^.+\$/\n	INPUT_HASH	/^[a-f0-9]{40}\$/\n	INPUT_DIR	/^.+\$/\nIf you don't know what these formats are, please see the reference at http://perldoc.perl.org/perlreref.html\n";
	exit;
}
$INPUT_DIR = "${INPUT_DIR}/${INPUT_NAME}";

my $ENCODE_OUTPUT_DIR = "${CONF_ENCODE_OUTPUT_DIR}/${INPUT_HASH}";
my $ENCODE_LOG_FILE = "${CONF_ENCODE_LOG_DIR}/${INPUT_HASH}.log";

local $SIG{__DIE__} = sub {
	my ($message) = @_;
	open (my $fh, '>>', $ENCODE_LOG_FILE);
	print $fh localtime() . ": " . $message;
	close $fh;
};

# TMP PUSH
system("/home/encode/scripts/push.sh 'Encoding begun: $INPUT_NAME' 'ID: $INPUT_ID\\nNAME: $INPUT_NAME\\nHASH: $INPUT_HASH\\nDIR: $INPUT_DIR\\nTIME: $date'");

if ($CONF_BACKUP_ITEM_FILES) {
	my $item_id = lc substr $INPUT_HASH, 0, 16;
	copy("${CONF_INGEST_ITEMS_DIR}/${INPUT_NAME}.${item_id}.item", "${CONF_ITEM_BACKUP_DIR}/${INPUT_HASH}.item") or die "Failed to backup .item file: $!"; # possibly not necessary to die here as this won't affect encoding
}

# 1 if we are encoding something like a film which might have cd1 and cd2 or part1 and part,
# 0 if we are encoding something like multiple tv shows which are all in individual files.
my $CONCAT_FILES = 0; # not really a config parameter, should be programatically set

# TMP PUSH
system("/home/encode/scripts/push.sh 'Concat, $CONCAT_FILES: $INPUT_NAME' 'Deciding factor: ...'");

if ($CONF_DEBUG) {
	print "DEBUG: ${CONF_DEBUG}\nID: ${INPUT_ID}\nNAME: ${INPUT_NAME}\nHASH: ${INPUT_HASH}\nDIR: ${INPUT_DIR}\nDATE: ${date}\nCONCAT: ${CONCAT_FILES}\n";
}

open (my $log, '>', $ENCODE_LOG_FILE) or die "Could not open log file (${ENCODE_LOG_FILE}): $!";

if (!-d $ENCODE_OUTPUT_DIR) {
	mkdir $ENCODE_OUTPUT_DIR or die "Could not create output directory (${ENCODE_OUTPUT_DIR}): $!";
}

open (my $info, '>', "${ENCODE_OUTPUT_DIR}/info.txt") or die "Could not open info.txt: $!"; # just in case
	print $info $INPUT_NAME;
close $info;

print $log "${CONF_PREFIX}_ENCODE_START " . trim(`date +%s`) . " (${date})\n";
print $log "${CONF_PREFIX}_ENCODE_INPUT_NAME ${INPUT_NAME}\n";
print $log "${CONF_PREFIX}_ENCODE_INPUT_DIR ${INPUT_DIR}\n";
print $log "${CONF_PREFIX}_ENCODE_OUTPUT_DIR ${ENCODE_OUTPUT_DIR}\n";
print $log "${CONF_PREFIX}_ENCODE_LOG_FILE ${ENCODE_LOG_FILE}\n";

print $log "${CONF_PREFIX}_ENCODE_CONCAT_FILES ${CONCAT_FILES}\n";

# not necessary, but for some reason setting the seed ratio to 0 doesn't pause on finish
# comment the following two lines of code to keep the items seeding
# WARNING: ingest seems to output some funny bytes at the end of it's 401: Unauthorised output, detailed here: https://github.com/ingest/ingest/issues/174
my $output = trim(`download-client-cli 127.0.0.1:9100 --auth=${CONF_INGEST_USERNAME}:${CONF_INGEST_PASSWORD} -t ${INPUT_HASH} --stop 2>&1`);
print $log "${CONF_PREFIX}_ENCODE_STOPPING_ITEM ${output}\n";

my @files_found;
find(sub { -f and push @files_found, $File::Find::name } , $INPUT_DIR);

@files_found = grep (/\.(mp4|m4v|mkv|avi)$/, @files_found);
@files_found = nsort(@files_found); # nsort tries to list the files in a human-sensible way

if (scalar @files_found eq 0) {
	die "${CONF_PREFIX}_FAIL Unable to find any encodable files\n";
}

my %ENCODE_SETTINGS;

$ENCODE_SETTINGS{"0_FHD"}{Height} = 1080;
$ENCODE_SETTINGS{"0_FHD"}{VideoBitrate} = 4000;
$ENCODE_SETTINGS{"0_FHD"}{AudioBitrate} = 128;
$ENCODE_SETTINGS{"0_FHD"}{AudioChannels} = 2;

$ENCODE_SETTINGS{"1_HDR"}{Height} = 720;
$ENCODE_SETTINGS{"1_HDR"}{VideoBitrate} = 1500;
$ENCODE_SETTINGS{"1_HDR"}{AudioBitrate} = 128;
$ENCODE_SETTINGS{"1_HDR"}{AudioChannels} = 2;

$ENCODE_SETTINGS{"2_SD"}{Height} = 480;
$ENCODE_SETTINGS{"2_SD"}{VideoBitrate} = 800;
$ENCODE_SETTINGS{"2_SD"}{AudioBitrate} = 64;
$ENCODE_SETTINGS{"2_SD"}{AudioChannels} = 1;

$ENCODE_SETTINGS{"3_LOW"}{Height} = -1;
$ENCODE_SETTINGS{"3_LOW"}{VideoBitrate} = 800;
$ENCODE_SETTINGS{"3_LOW"}{AudioBitrate} = 64;
$ENCODE_SETTINGS{"3_LOW"}{AudioChannels} = 1;

$ENCODE_SETTINGS{"4_RAW"}{Height} = -1;
$ENCODE_SETTINGS{"4_RAW"}{VideoBitrate} = -1;
$ENCODE_SETTINGS{"4_RAW"}{AudioBitrate} = -1;
$ENCODE_SETTINGS{"4_RAW"}{AudioChannels} = 1;

my %TO_ENCODE;

my $i = 0;
for my $file (@files_found) {
	$i += 1;

	if ($file =~ /sample/i or -s $file < 50000000) { # 50 Mb
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_X_IS_SAMPLE TRUE\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_X_SAMPLE $file\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_X_IGNORING_SAMPLES TRUE\n";
		$i -= 1;
	} else {
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FILE ${file}\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FORMAT " . substr($file, -4, length $file) . "\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_IS_SAMPLE FALSE\n";

		my $AudioBitrate = trim(`mediainfo --Output=Audio\\;%BitRate% "${file}"`);
		my $VideoBitrate = trim(`mediainfo --Output=Video\\;%BitRate% "${file}"`);
		my $OverallBitrate = trim(`mediainfo --Output=General\\;%OverallBitRate% "${file}"`);
		if ($OverallBitrate !~ /^\d+$/) {
			die "${CONF_PREFIX}_FAIL Unable to determine the bit rate of file '${file}'";
		}
		if ($VideoBitrate !~ /^\d+$/ || $AudioBitrate !~ /^\d+$/) {
			$AudioBitrate = $OverallBitrate * 0.1; # 10% of bit rate to be used for audio
			$VideoBitrate = $OverallBitrate * 0.9; # 90% of bit rate to be used for video
			print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_INTERPOLATED_BITRATE TRUE\n";
		} else {
			print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_INTERPOLATED_BITRATE FALSE\n";
		}
		$AudioBitrate = int($AudioBitrate / 1000);
		$VideoBitrate = int($VideoBitrate / 1000);
		$OverallBitrate = int($OverallBitrate / 1000);

		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_BITRATE ${AudioBitrate}\n";
                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_VIDEO_BITRATE ${VideoBitrate}\n";
                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_OVERALL_BITRATE ${OverallBitrate}\n";

		my $Height = trim(`mediainfo --Output=Video\\;%Height% "${file}"`);
		my $FrameRate = trim(`mediainfo --Output=Video\\;%FrameRate% "${file}"`);
		my $AudioChannels = trim(`mediainfo --Output=Audio\\;%Channels% "${file}"`);

		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_HEIGHT ${Height}\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FRAME_RATE ${FrameRate}\n";
		print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS ${AudioChannels}\n";

		if ($FrameRate > 25 || !looks_like_number($FrameRate)) {
                        $FrameRate = 25;
                        print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FRAME_RATE_FORCED TRUE (${FrameRate})\n";
                } else {
			print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_FRAME_RATE_FORCED FALSE\n";
		}

		my $ENCODE_AUDIO_CHANNELS;

		foreach my $key (sort keys %ENCODE_SETTINGS) {

			my $name = $key;
                        $name =~ s/^(.+)_//;
			my $n = $key;
			$n =~ s/^(\d+)_.+/$1/;

			if ($ENCODE_SETTINGS{$key}{'Height'} > 0) {

				if ($Height > $ENCODE_SETTINGS{$key}{'Height'} * 0.95) { # a lot of files seem to be encoded to just under the standards so we allow for a little leeway and a slight up-encode

					if ($AudioChannels < $ENCODE_SETTINGS{$key}{'AudioChannels'}) {
                                	        $ENCODE_AUDIO_CHANNELS = $AudioChannels;
                                	        print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED TRUE ($ENCODE_SETTINGS{$key}{'AudioChannels'} => ${AudioChannels})\n";
                                	} else {
						$ENCODE_AUDIO_CHANNELS = $ENCODE_SETTINGS{$key}{'AudioChannels'};
                                	        print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED FALSE\n";
                                	}

					$TO_ENCODE{$n}{$i}{Name} = $name;
					$TO_ENCODE{$n}{$i}{Height} = $ENCODE_SETTINGS{$key}{Height};
					$TO_ENCODE{$n}{$i}{AudioChannels} = $ENCODE_AUDIO_CHANNELS;
					$TO_ENCODE{$n}{$i}{FrameRate} = $FrameRate;
					$TO_ENCODE{$n}{$i}{File} = $file;
					if ($VideoBitrate > $ENCODE_SETTINGS{$key}{'VideoBitrate'}) {
						$TO_ENCODE{$n}{$i}{VideoBitrate} = $ENCODE_SETTINGS{$key}{VideoBitrate};
						$TO_ENCODE{$n}{$i}{AudioBitrate} = $ENCODE_SETTINGS{$key}{AudioBitrate};
						$TO_ENCODE{$n}{$i}{Filename} = "${i}-$ENCODE_SETTINGS{$key}{Height}.mp4";
						print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_ENCODE_SETTINGS H: $ENCODE_SETTINGS{$key}{'Height'}, V: $ENCODE_SETTINGS{$key}{'VideoBitrate'}k, A: $ENCODE_AUDIO_CHANNELS channel(s) $ENCODE_SETTINGS{$key}{'AudioBitrate'}k, F: ${FrameRate} [TRUE $name]\n";
					} else {
						$TO_ENCODE{$n}{$i}{VideoBitrate} = $VideoBitrate;
                                                $TO_ENCODE{$n}{$i}{AudioBitrate} = $AudioBitrate;
                                                $TO_ENCODE{$n}{$i}{Filename} = "${i}-$ENCODE_SETTINGS{$key}{Height}-LOW.mp4";
						print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_ENCODE_SETTINGS H: $ENCODE_SETTINGS{$key}{'Height'}, V: ${VideoBitrate}k, A: $ENCODE_AUDIO_CHANNELS channel(s) ${AudioBitrate}k, F: ${FrameRate} [LOW BITRATE $name]\n";
					}

				}
			} else {
				my $USE_LAST_RESORT = 1;
				foreach my $encode_key (keys %TO_ENCODE) {
					foreach my $file_key (keys %{$TO_ENCODE{$encode_key}}) {
						if ($file_key == $i) {
							$USE_LAST_RESORT = 0;
						}
					}
				}
				if ($USE_LAST_RESORT) {

					if ($AudioChannels < $ENCODE_SETTINGS{$key}{'AudioChannels'}) {
                                                $ENCODE_AUDIO_CHANNELS = $AudioChannels;
                                                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED TRUE ($ENCODE_SETTINGS{$key}{'AudioChannels'} => ${AudioChannels})\n";
						#print "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED TRUE ($ENCODE_SETTINGS{$key}{'AudioChannels'} => ${AudioChannels})\n";
                                        } else {
                                                $ENCODE_AUDIO_CHANNELS = $ENCODE_SETTINGS{$key}{'AudioChannels'};
                                                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED FALSE\n";
						#print "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_AUDIO_CHANNELS_FORCED FALSE\n";
                                        }

					$TO_ENCODE{$n}{$i}{Name} = $name;
					$TO_ENCODE{$n}{$i}{Height} = $Height;
                                        $TO_ENCODE{$n}{$i}{AudioChannels} = $ENCODE_AUDIO_CHANNELS;
                                        $TO_ENCODE{$n}{$i}{FrameRate} = $FrameRate;
                                        $TO_ENCODE{$n}{$i}{File} = $file;
					if ($VideoBitrate > $ENCODE_SETTINGS{$key}{'VideoBitrate'}) {
						$TO_ENCODE{$n}{$i}{VideoBitrate} = $ENCODE_SETTINGS{$key}{VideoBitrate};
                                                $TO_ENCODE{$n}{$i}{AudioBitrate} = $ENCODE_SETTINGS{$key}{AudioBitrate};
                                                $TO_ENCODE{$n}{$i}{Filename} = "${i}-${name}.mp4";
                                                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_ENCODE_SETTINGS H: ${Height}, V: $ENCODE_SETTINGS{$key}{'VideoBitrate'}k, A: $ENCODE_AUDIO_CHANNELS channel(s) $ENCODE_SETTINGS{$key}{'AudioBitrate'}k, F: ${FrameRate} [$name]\n";
                                        } else {
						$TO_ENCODE{$n}{$i}{VideoBitrate} = $ENCODE_SETTINGS{$key}{VideoBitrate};
                                                $TO_ENCODE{$n}{$i}{AudioBitrate} = $ENCODE_SETTINGS{$key}{AudioBitrate};
                                                $TO_ENCODE{$n}{$i}{Filename} = "${i}-${name}.mp4";
                                                print $log "${CONF_PREFIX}_ENCODE_FILE_FOUND_${i}_ENCODE_SETTINGS H: ${Height}, V: ${VideoBitrate}k, A: $ENCODE_AUDIO_CHANNELS channel(s) ${AudioBitrate}k, F: ${FrameRate} [$name]\n";
                                        }
				}
			}
		}
	}
}

print $log "${CONF_PREFIX}_ENCODE_FILES_COUNT ${i}\n";

print $log "${CONF_PREFIX}_ENCODE_FILE_LIST_START\n" . join("\n", @files_found) . "\n${CONF_PREFIX}_ENCODE_FILE_LIST_END\n";

my $c = 0;
# encode highest number first
foreach my $QualityLevel (reverse sort keys %TO_ENCODE) {
	my @CONCAT;
	foreach my $FileNumber (nsort keys %{$TO_ENCODE{$QualityLevel}}) {
		$c += 1;
		print $log "${CONF_PREFIX}_ENCODE_STATUS ENCODING ${FileNumber}/$TO_ENCODE{$QualityLevel}{$FileNumber}{Name}\n";
		# now lets encode
		my @ffmpeg_cmd = ($CONF_FFMPEG_BIN);
		push @ffmpeg_cmd, "-i", escape_shell_param($TO_ENCODE{$QualityLevel}{$FileNumber}{File});
		push @ffmpeg_cmd, "-y";
		push @ffmpeg_cmd, "-strict", "experimental"; # check necessity
		push @ffmpeg_cmd, "-f", "mp4";
		push @ffmpeg_cmd, "-c:v", "libx264";
		push @ffmpeg_cmd, "-b:v", "$TO_ENCODE{$QualityLevel}{$FileNumber}{VideoBitrate}k";
		push @ffmpeg_cmd, "-c:a", "libfdk_aac";
		push @ffmpeg_cmd, "-b:a", "$TO_ENCODE{$QualityLevel}{$FileNumber}{AudioBitrate}k";
		push @ffmpeg_cmd, "-ac", "$TO_ENCODE{$QualityLevel}{$FileNumber}{AudioChannels}";
		push @ffmpeg_cmd, "-r", "$TO_ENCODE{$QualityLevel}{$FileNumber}{FrameRate}";
		push @ffmpeg_cmd, "-vf", "scale='-2:$TO_ENCODE{$QualityLevel}{$FileNumber}{Height}'";
		push @ffmpeg_cmd, "-threads", "0";
		push @ffmpeg_cmd, escape_shell_param($ENCODE_OUTPUT_DIR . "/" . $TO_ENCODE{$QualityLevel}{$FileNumber}{Filename} . ".tmp");

		my $duration = -1;
		my $ETA = -1;
		my $ETA_delay = $CONF_ETA_DELAY;

		open FFMPEG, "-|", join(' ', @ffmpeg_cmd) . $FFMPEG_CMD_WORK or die $!;
		while (<FFMPEG>) { # in this loop we could process the ffmpeg output
			print $_;
			print $log $_;

			if ($duration == -1) {
				if ($_ =~ /Duration: (\d{2}):(\d{2}):(\d{2})\.(\d{2}), start.+$/) {
					$duration = ($1 * 3600) + ($2 * 60) + ($3) + ($4 / 100);
				}
			} else {
				if ($ETA == -1) {
					if ($_ =~ /^frame=.+ time=(\d{2}):(\d{2}):(\d{2})\.(\d{2}) .+ speed=[ ]*(\d+\.\d+)x/) {
						if ($ETA_delay == 0) {
							my $speed = $5;
							my $current_time = ($1 * 3600) + ($2 * 60) + ($3) + ($4 / 100);
							my $ETA_seconds = ($duration - $current_time) * (1/$speed);
							my $ETA_timestamp = current_time_tz()->add(seconds => $ETA_seconds);
							$ETA = convert_time($ETA_seconds);
							system("/home/encode/scripts/push.sh 'ETA (#$c/" . (keys %{$TO_ENCODE{$QualityLevel}}) . "*" . (keys %{$TO_ENCODE{$QualityLevel}{$FileNumber}}) . "), $ETA: $INPUT_NAME' 'ID: $INPUT_ID\\nNAME: $INPUT_NAME\\nDURATION: $duration\\nCURRENT_TIME: $current_time\\nSPEED: $speed\\nETA: $ETA\\nETA_TIMESTAMP: " . $ETA_timestamp->strftime("%a, %d %b %Y %T %Z") . "\\nTIME: " . current_time_tz()->strftime("%a, %d %b %Y %T %Z") . "'");
						} else {
							$ETA_delay = $ETA_delay -= 1;
						}
					}
				}
			}

			$log->flush();
		}

		print $log "${CONF_PREFIX}_ENCODE_STATUS DONE ${FileNumber}/$TO_ENCODE{$QualityLevel}{$FileNumber}{Name}\n";

		if ($CONCAT_FILES) {
			print $log "${CONF_PREFIX}_PREPARING_CONCATENATION START\n";

			my @concat_cmd = ($CONF_FFMPEG_BIN);
			push @concat_cmd, "-i", pop(@ffmpeg_cmd);
			push @concat_cmd, "-y";
			push @concat_cmd, "-c", "copy";
			push @concat_cmd, "-bsf:v", "h264_mp4toannexb";
			push @concat_cmd, "-f", "mpegts";
			push @concat_cmd, escape_shell_param($ENCODE_OUTPUT_DIR . "/" . $TO_ENCODE{$QualityLevel}{$FileNumber}{Filename} . ".ts");

			open FFMPEG, "-|", join(' ', @concat_cmd) . $FFMPEG_CMD_WORK or die $!;
                	while (<FFMPEG>) {
                		print $log $_;
				$log->flush();
                	}

			push @CONCAT, pop(@concat_cmd);

			print $log "${CONF_PREFIX}_PREPARING_CONCATENATION END\n";
		} else {
			print $log "${CONF_PREFIX}_PREPARING_CONCATENATION FALSE\n";
		}

	}

	if ($CONCAT_FILES) {
		print $log "${CONF_PREFIX}_CONCATENATING_FILES START\n";
		my @concat_cmd = ($CONF_FFMPEG_BIN);
		push @concat_cmd, "-i", "concat:" . join("|", @CONCAT);
		push @concat_cmd, "-y";
		push @concat_cmd, "-c", "copy";
		push @concat_cmd, "-bsf:a", "aac_adtstoasc";
		push @concat_cmd, escape_shell_param( ( do { dirname($CONCAT[0])=~s/^'//r } . "/" . do { basename($CONCAT[0])=~s/^.+?-(.+)\.ts[']?$/$1/r; } ) );

		open FFMPEG, "-|", join(' ', @concat_cmd) . $FFMPEG_CMD_WORK or die $!;
                while (<FFMPEG>) {
                        print $log $_;
                        $log->flush();
                }

		print $log "${CONF_PREFIX}_CONCATENATING_FILES END\n";

		print $log "${CONF_PREFIX}_CLEANING_UP_ENCODED_FILES START\n";
		# delete .ts and .tmp
		my @cleanup_files;
		find(sub { -f and push @cleanup_files, $File::Find::name } , $ENCODE_OUTPUT_DIR);
		@cleanup_files = grep (/\.(ts|tmp)$/, @cleanup_files);
		for my $file (@cleanup_files) {
			unlink($file);
		}
		print $log "${CONF_PREFIX}_CLEANING_UP_ENCODED_FILES END\n";
	} else {
		print $log "${CONF_PREFIX}_CONCATENATING_FILES FALSE\n";
		print $log "${CONF_PREFIX}_CLEANING_UP_ENCODED_FILES START\n";
		# move .tmp to .mp4
		my @cleanup_files;
		find(sub { -f and push @cleanup_files, $File::Find::name } , $ENCODE_OUTPUT_DIR);
		@cleanup_files = grep (/\.tmp$/, @cleanup_files);
		for my $file (@cleanup_files) {
			move($file, $file=~s/\.tmp$//r);
		}
		print $log "${CONF_PREFIX}_CLEANING_UP_ENCODED_FILES END\n";
        }

}

#my $output = trim(`download-client-cli 127.0.0.1:9100 --auth=${CONF_INGEST_USERNAME}:${CONF_INGEST_PASSWORD} -t ${INPUT_HASH} --remove-and-delete 2>&1`);
#print $log "${CONF_PREFIX}_ENCODE_REMOVING_ITEM ${output}\n";

print $log "${CONF_PREFIX}_ENCODE_EXECUTION TIME " . (gettimeofday - $timestamp) . "\n";
print $log "${CONF_PREFIX}_ENCODE_DONE " . trim(`date +%s`) . " (${date})\n";

close $log;

# TMP PUSH
system("/home/encode/scripts/push.sh 'Finished encoding: $INPUT_NAME' 'ID: $INPUT_ID\\nNAME: $INPUT_NAME\\nHASH: $INPUT_HASH\\nDIR: $INPUT_DIR\\nTIME: $date\\nEXECUTION TIME: " . (gettimeofday - $timestamp) . "'");
