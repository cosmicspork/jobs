FROM ubuntu:24.04

ARG NODE_VERSION=24
ARG POSTGRES_VERSION=17
ARG PHP_VERSION=8.4
ARG USERNAME=ubuntu

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV HOME=/home/${USERNAME}

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y gnupg gosu curl ca-certificates \
    && mkdir -p /etc/apt/keyrings \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' \
       | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
       > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
       | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" \
       > /etc/apt/sources.list.d/nodesource.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor \
       | tee /etc/apt/keyrings/pgdg.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" \
       > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y \
       zip unzip git gh supervisor sudo sqlite3 \
       libcap2-bin libgd3 libpng-dev librsvg2-bin \
       python3 dnsutils fswatch ffmpeg nano locales \
       nodejs \
       postgresql-client-$POSTGRES_VERSION \
       valkey valkey-redis-compat \
       php${PHP_VERSION}-cli \
       php${PHP_VERSION}-dev \
       php${PHP_VERSION}-pgsql \
       php${PHP_VERSION}-sqlite3 \
       php${PHP_VERSION}-gd \
       php${PHP_VERSION}-curl \
       php${PHP_VERSION}-mongodb \
       php${PHP_VERSION}-imap \
       php${PHP_VERSION}-mysql \
       php${PHP_VERSION}-mbstring \
       php${PHP_VERSION}-xml \
       php${PHP_VERSION}-zip \
       php${PHP_VERSION}-bcmath \
       php${PHP_VERSION}-soap \
       php${PHP_VERSION}-intl \
       php${PHP_VERSION}-readline \
       php${PHP_VERSION}-ldap \
       php${PHP_VERSION}-msgpack \
       php${PHP_VERSION}-igbinary \
       php${PHP_VERSION}-redis \
       php${PHP_VERSION}-memcached \
       php${PHP_VERSION}-pcov \
       php${PHP_VERSION}-imagick \
       php${PHP_VERSION}-xdebug \
    && echo "xdebug.mode=debug" >> /etc/php/${PHP_VERSION}/mods-available/xdebug.ini \
    && echo "pcov.directory=/workspaces/jobs" >> /etc/php/${PHP_VERSION}/mods-available/pcov.ini \
    && locale-gen en_US.UTF-8 \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && npm install -g npm \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY valkey.conf /etc/supervisor/conf.d/valkey.conf

RUN mkdir -p ${HOME} && chown -R ${USERNAME}:${USERNAME} ${HOME} \
    && echo "${USERNAME} ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/${USERNAME} \
    && chmod 0440 /etc/sudoers.d/${USERNAME}

USER ${USERNAME}
WORKDIR ${HOME}

ENV PATH="${HOME}/.npm-global/bin:${HOME}/.config/composer/vendor/bin:${PATH}"

RUN mkdir -p "${HOME}/.npm-global" \
    && npm config set prefix "${HOME}/.npm-global"

RUN composer global require laravel/cloud-cli
