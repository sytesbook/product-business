#!/bin/bash
# Create MySQL users from environment variables
# This script runs automatically when MySQL container starts for the first time

set -e

echo "Creating database users..."

# wp-home-site user
if [ -n "$WP_HOME_DB_USER" ] && [ -n "$WP_HOME_DB_PASSWORD" ]; then
  # Connect as root. During the Docker init phase the temporary MySQL server already has
  # MYSQL_ROOT_PASSWORD set, so a password is required even for localhost connections.
  # MYSQL_PWD is used instead of -p to avoid the "password on CLI is insecure" warning.
  MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root <<-EOSQL
    CREATE USER IF NOT EXISTS '$WP_HOME_DB_USER'@'%' IDENTIFIED BY '$WP_HOME_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`wp_home_site_db\`.* TO '$WP_HOME_DB_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "Created user: $WP_HOME_DB_USER"
fi

# wp-customer-sites user
if [ -n "$WP_CUSTOMERS_DB_USER" ] && [ -n "$WP_CUSTOMERS_DB_PASSWORD" ]; then
  MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root <<-EOSQL
    CREATE USER IF NOT EXISTS '$WP_CUSTOMERS_DB_USER'@'%' IDENTIFIED BY '$WP_CUSTOMERS_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`wp_customer_sites_db\`.* TO '$WP_CUSTOMERS_DB_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "Created user: $WP_CUSTOMERS_DB_USER"
fi

# content-service user
if [ -n "$CONTENT_DB_USER" ] && [ -n "$CONTENT_DB_PASSWORD" ]; then
  MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root <<-EOSQL
    CREATE USER IF NOT EXISTS '$CONTENT_DB_USER'@'%' IDENTIFIED BY '$CONTENT_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`content_db\`.* TO '$CONTENT_DB_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "Created user: $CONTENT_DB_USER"
fi

echo "Database users created successfully!"
