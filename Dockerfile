FROM tutum/lamp:latest
COPY docker/000-default.conf /etc/apache2/sites-available
RUN apt-get install php5-xdebug
RUN echo "xdebug.remote_enable=1" >> /etc/php5/apache2/php.ini

# this doesnt work, need to be host ip address
RUN echo "xdebug.remote_host=$(hostname -I | cut -d ' ' -f 1)" >> /etc/php5/apache2/php.ini

EXPOSE 80 3306
CMD ["/run.sh"]