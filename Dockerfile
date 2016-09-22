FROM tutum/lamp:latest
COPY docker/000-default.conf /etc/apache2/sites-available
EXPOSE 80 3306
CMD ["/run.sh"]