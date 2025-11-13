# Set SSH user and host variables
SSH_USER="root"         # <-- Replace with your SSH username
SSH_HOST="webster.digitall.net.au"
SSH_PORT=22

MARLOO_STAGING_PATH="/home/marloo_staging_cinema/website/wp-content/plugins/."
rsync -avz -e "ssh -p $SSH_PORT" wp_d-cine_sessiontimes_data ${SSH_USER}@${SSH_HOST}:$MARLOO_STAGING_PATH
ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} chmod -R g+rw ${MARLOO_STAGING_PATH}
ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} chown -R www-data:www-data ${MARLOO_STAGING_PATH}
