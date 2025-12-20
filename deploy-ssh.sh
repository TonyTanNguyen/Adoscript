#!/bin/bash

# Hostinger SSH/Rsync Deployment Script
# Requires SSH access (available on Business/Premium plans)

SSH_HOST="your_ssh_host"            # e.g., ssh.yourdomain.com
SSH_USER="your_ssh_username"        # Your SSH username
SSH_PORT="65002"                    # Hostinger SSH port (usually 65002)
REMOTE_DIR="/home/username/public_html"  # Full remote path
LOCAL_DIR="./"                      # Local directory

echo "Starting deployment via SSH..."

rsync -avz --progress --delete \
    --exclude '.git/' \
    --exclude '.gitignore' \
    --exclude 'deploy.sh' \
    --exclude 'deploy-ssh.sh' \
    --exclude '.DS_Store' \
    --exclude 'adobe-scripts-marketplace-prd.md' \
    -e "ssh -p $SSH_PORT" \
    $LOCAL_DIR $SSH_USER@$SSH_HOST:$REMOTE_DIR

echo "Deployment complete!"
