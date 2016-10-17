#!/bin/sh

# project image
docker build -t ipa .
docker run -d --name="ipa_app" -p 80:80 -p 3306:3306 -v $(pwd)/src:/app ipa

# node/gulp/bower image
docker pull evolution7/nodejs-bower-gulp
# docker exec -it ipa_app bash


# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp npm install
# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp bower install --allow-root
# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp gulp build

# load database
docker exec -it $(docker ps -aqf "name=ipa_app") mysql -u root -e "CREATE DATABASE ipa"
docker exec -it $(docker ps -aqf "name=ipa_app") mysql -u root -e "use ipa; source /app/db/ipa.sql;"
