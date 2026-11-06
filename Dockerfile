# Used by the Release workflow: install-php-extensions and install-php-extensions-gz are the PHAR files built by it
FROM scratch

LABEL org.opencontainers.image.source="https://github.com/mlocati/install-php-extensions"
LABEL org.opencontainers.image.description="install-php-extensions, to be copied into other images"
LABEL org.opencontainers.image.licenses="MIT"

COPY --chmod=755 install-php-extensions /usr/bin/install-php-extensions
COPY --chmod=755 install-php-extensions-gz /usr/bin/install-php-extensions-gz
