### Installation

issues running setup:install - the following command worked for me (otherwise you get the unhelpful error "parameter validation failed")

php bin/magento setup:install --base-url="http://localhost/" --db-host="mage2docker-db-1" --db-name="magento" --db-user="root" --db-password="root" --admin-firstname="admin" --admin-lastname="admin" --admin-email="a
dmin@admin.com" --admin-user="admin" --admin-password="admin123?" --use-rewrites="1" --backend-frontname="admin" --elasticsearch-host="mage2docker-elasticsearch-1"


