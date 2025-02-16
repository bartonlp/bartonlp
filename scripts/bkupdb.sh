#!/bin/bash
# Backup the database before starting.
# BLP 2022-01-02 -- Added BONNIEBRIDGE to backups.
# BLP 2021-10-27 -- Add allnatural and mysql to list of backups.

echo "bartonlp: bkupdb.sh Start"
cd /var/www
dir=bartonlp/other
bkupdate=`date +%B-%d-%y`

# do the following for each of my active databases

filename="BARTONLP.$bkupdate.sql"

# We backup the 'barton' database to /var/www/bartonlp/other

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data barton 2>/dev/null > $dir/bartonlp.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table barton 2>/dev/null >$dir/$filename
gzip $dir/$filename

# We backup the 'bartonphillips' database to /var/www/bartonlp/other

filename="BARTONPHILLIPS.$bkupdate.sql";

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data bartonphillips 2>/dev/null > $dir/bartonphillips.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table bartonphillips 2>/dev/null >$dir/$filename;
gzip $dir/$filename

filename="BONNIEBRIDGE.$bkupdate.sql";

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data bridge 2>/dev/null > $dir/bonniebridge.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table bridge 2>/dev/null >$dir/$filename;
gzip $dir/$filename

filename="BONNIEMARATHON.$bkupdate.sql";

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data marathon 2>/dev/null > $dir/bonniemarathon.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table marathon 2>/dev/null >$dir/$filename;
gzip $dir/$filename

filename="BONNIE.$bkupdate.sql"

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data bonnie 2>/dev/null > $dir/bonnie.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table bonnie 2>/dev/null >$dir/$filename;
gzip $dir/$filename

filename="MYSQL.$bkupdate.sql"

mysqldump --defaults-file=/home/barton/mysql-password --user=barton --no-data mysql 2>/dev/null > $dir/mysql.schema
mysqldump --defaults-file=/home/barton/mysql-password --user=barton --add-drop-table mysql 2>/dev/null >$dir/$filename
gzip $dir/$filename

# Finally remove all old files

find $dir -mtime +7 -exec rm '{}' \;

echo "bartonlp: bkupdb.sh Done"
