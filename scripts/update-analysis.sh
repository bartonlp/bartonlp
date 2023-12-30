#!/bin/bash
# Why am I using wget? The analysis.php is a web based program not a
# cli so I have to run it via apache via the internet. Wget does that.

echo "bartonlp: update-analysis.sh Start"

for x in Allnatural Bartonphillips BartonlpOrg Tysonweb Newbernzig Swam JT-Lawnservice Littlejohnplumbing ALL 
do
wget -qO- https://bartonlp.com/otherpages/analysis.php?siteupdate=$x >/dev/null
done

# BLP 2023-09-13 - I have made a special version of analysis.php for
# the RPI. I can modified the standard version and do all of the
# special stuff for the RPI and Bartonphillips.org with phpseclib which
# seems to work all right with a key. TBD

# BLP 2023-09-10 - NOTE, I had to change the /etc/ssh/sshd_config file. I commented out
# 'PasswordAuthentication no' and placed a '#BLP' at the place.

wget -qO- https://bartonphillips.org/analysis.php?siteupdate=BartonphillipsOrg >/dev/null

# BLP 2023-09-13 - This is special file just for the RPI.

wget -qO- http://bartonphillips.org:8000/analysis.php?siteupdate=Rpi >/dev/null

echo "bartonlp: update-analysis.sh Done" ;
