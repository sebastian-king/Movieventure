#!/bin/bash

title="${1}";
body="${2}";

if [ -z "$body" ] || [ -z "${title}" ] || [ "$#" != 2 ]; then
	echo "\$1 and \$2 must be title body only";
	exit;
fi

curl -k -H "Access-Token: <TOKEN>" -H "Content-Type: application/json" -d "{\"body\":\"${body}\",\"title\":\"${title}\",\"type\":\"note\",\"device_iden\":\"<IDENT>\"}" -X POST https://api.pushbullet.com/v2/pushes
