#!/bin/bash
# Create MySQL users from environment variables
# This script runs automatically when MySQL container starts for the first time

set -e

echo "Creating database users..."

# wp-home-site user
if [ -n "$WP_HOME_DB_USER" ] && [ -n "$WP_HOME_DB_PASSWORD" ]; then
  mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE USER IF NOT EXISTS '$WP_HOME_DB_USER'@'%' IDENTIFIED BY '$WP_HOME_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`wp_home_site_db\`.* TO '$WP_HOME_DB_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "Created user: $WP_HOME_DB_USER"
fi

# wp-customer-sites user
if [ -n "$WP_CUSTOMERS_DB_USER" ] && [ -n "$WP_CUSTOMERS_DB_PASSWORD" ]; then
  mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE USER IF NOT EXISTS '$WP_CUSTOMERS_DB_USER'@'%' IDENTIFIED BY '$WP_CUSTOMERS_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON \`wp_customer_sites_db\`.* TO '$WP_CUSTOMERS_DB_USER'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "Created user: $WP_CUSTOMERS_DB_USER"
fi

echo "Database users created successfully!"
