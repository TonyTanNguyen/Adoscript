#!/bin/bash

# Hostinger FTP Deployment Script
# Update these variables with your Hostinger credentials

FTP_HOST="ftp.yourdomain.com"      # Your Hostinger FTP host
FTP_USER="your_ftp_username"        # Your FTP username
FTP_PASS="your_ftp_password"        # Your FTP password
REMOTE_DIR="/public_html"           # Remote directory
LOCAL_DIR="."                       # Local directory to upload

echo "Starting deployment to Hostinger..."

# Using lftp for reliable FTP sync
lftp -c "
set ftp:ssl-allow no;
open -u $FTP_USER,$FTP_PASS $FTP_HOST;
lcd $LOCAL_DIR;
cd $REMOTE_DIR;
mirror --reverse --delete --verbose \
    --exclude .git/ \
    --exclude .gitignore \
    --exclude deploy.sh \
    --exclude .DS_Store \
    --exclude adobe-scripts-marketplace-prd.md;
quit
"

echo "Deployment complete!"
