#!/bin/bash

for d in $(find /home/encodes/); do
	if [[ $d =~ info\.txt ]]; then
		echo "$d == `cat $d`";
	fi;
done;
