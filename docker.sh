#!/bin/sh

# project image
docker build -t ipa .
docker run -d -p 80:80 -p 3306:3306 -v $(pwd)/src:/app ipa

# node/gulp/bower image
docker pull evolution7/nodejs-bower-gulp
# docker exec -it 001fe54b06e6 bash


# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp npm install
# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp bower install --allow-root
# docker run --rm -v `pwd`:/app evolution7/nodejs-bower-gulp gulp build