#!/bin/bash
# This file does most of the cron jobs so I don't get inandated with
# email.
# BLP 2021-10-27 -- moved the backup of the allnatural database and add
# mysql to bkupdb.sh

echo "bartonlp: all-cron.sh Start";

# update Sitemap.xml
# Updatesitemap bartonphillips.com, tysonweb and newbernzig.com

/var/www/bartonphillips.com/scripts/updatesitemap.php
/var/www/tysonweb/scripts/updatesitemap.php
/var/www/newbernzig.com/scripts/updatesitemap.php
# No longer using allnatural
#/var/www/allnaturalcleaningcompany/scripts/updatesitemap.php
/var/www/bonnieburch.com/scripts/updatesitemap.php
# Cleanup the 'tracker', 'bots2', 'daycounts' and 'counter2'
/var/www/bartonlp/scripts/cleanuptables.php
# Do the analysis
# This will update all of the files in https://bartonphillips.net/analysis
# The files are used by webstats.php.
/var/www/bartonlp/scripts/update-analysis.sh
# checktracker2 is now done in crontab every 15 minutes
# myipupdate.php is done in crontab at 3AM
# rsync /etc/apache2/sites-enabled/ to /var/www/bartonlp/apache-conf/
# is done in crontab at 4AM
# These two are done in crontab as follows:
# 0 17 * * 1-5 /var/www/bartonphillips.com/scripts/updatestocktotals.sh
# 0 20 * * 1-5 /var/www/bartonphillips.com/scripts/updatemutuals.sh

# Backup jobs

# NOTE: we only need to do the bkupdb.sh in /var/www/bartonlp/scripts
# as it does the 'barton', 'bartonphillips', 'allnatural', 'tysonweb', 'bridge',
# 'marathon' and 'mysql' databases.

/var/www/bartonlp/scripts/bkupdb.sh

echo "bartonlp: all-cron.sh Done"
