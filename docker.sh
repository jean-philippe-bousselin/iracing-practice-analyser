#!/bin/sh

docker build -t ipa .
docker run -d -p 80:80 -p 3306:3306 -v $(pwd)/src:/app ipa