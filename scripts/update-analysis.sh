#!/bin/bash
# Why am I using wget? The analysis.php is a web based program not a
# cli so I have to run it via apache via the internet. Wget does that.

echo "bartonlp: update-analysis.sh Start"

for x in Allnatural Bartonphillips BartonlpOrg Tysonweb Newbernzig Swam JT-Lawnservice ALL
do
wget -qO- https://bartonlp.com/otherpages/analysis.php?siteupdate=$x >/dev/null
done

wget -qO- https://bartonphillips.org/analysis.php?siteupdate=BartonphillipsOrg >/dev/null
wget -qO- http://bartonphillips.org:8080/analysis.php?siteupdate=Rpi >/dev/null

echo "bartonlp: update-analysis.sh Done" ;
