FROM z3r0ck/nginx-base:8.3-vm

# Build args
ARG HOST_GID=1000 \
    HOST_UID=1000

ENV ENV="/root/.ashrc"

# Install shadow (enables groupmod and usermod) and runuser
RUN apk add --no-cache shadow runuser

# Set working directory
WORKDIR /var/www/app

# Copy app files
COPY . .

# Setup permissions
RUN groupmod -g $HOST_GID www-data \
    && usermod -u $HOST_UID www-data

# Setup alias
COPY ./docker/alias /root/.ashrc

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
