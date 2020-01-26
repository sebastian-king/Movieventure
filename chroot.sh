#!/bin/bash

# ./chroot.sh /bin/{ls,cat,echo,rm,bash} /usr/bin/vi /etc/hosts

PARAMS=""
while (( "$#" )); do
  case "$1" in
    -d|--chroot-directory)
      CHROOT_DIRECTORY=$2
      shift 2
      ;;
    --) # end argument parsing
      shift
      break
      ;;
    -*|--*=) # unsupported flags
      echo "Error: Unsupported flag $1" >&2
      exit 1
      ;;
    *) # preserve positional arguments
      PARAMS="$PARAMS $1"
      shift
      ;;
  esac
done

# set positional arguments in their proper place
eval set -- "$PARAMS"

if [ -z "$CHROOT_DIRECTORY" ]; then
	exitl
fi

mkdir -p $CHROOT_DIRECTORY

for i in $( ldd $* | grep -v dynamic | cut -d " " -f 3 | sed 's/://' | sort | uniq )
  do
    cp --parents $i $CHROOT_DIRECTORY
  done

# ARCH amd64
if [ -f /lib64/ld-linux-x86-64.so.2 ]; then
   cp --parents /lib64/ld-linux-x86-64.so.2 /$CHROOT_DIRECTORY
fi

# ARCH i386
if [ -f  /lib/ld-linux.so.2 ]; then
   cp --parents /lib/ld-linux.so.2 /$CHROOT_DIRECTORY
fi

echo "Chroot jail is ready. To access it execute: chroot $CHROOT_DIRECTORY"
