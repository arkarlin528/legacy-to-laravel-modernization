FROM nginx:1.27-alpine

WORKDIR /etc/facade
COPY nginx.conf upstreams.docker.conf ./
COPY phases/ phases/

# PHASE selects which routing table to start with, e.g. 0-legacy-only, 1-shadow, 3b-cutover.
ENV PHASE=0-legacy-only

# Switch phase in a running container:
#   docker compose exec facade sh -c 'cp phases/2-read-only-slice.conf routing.conf && nginx -p /etc/facade/ -c nginx.conf -s reload'
CMD sh -c "mkdir -p logs temp && cp upstreams.docker.conf upstreams.conf && cp phases/${PHASE}.conf routing.conf \
    && ln -sf /dev/stdout logs/access.log && ln -sf /dev/stderr logs/error.log \
    && exec nginx -p /etc/facade/ -c nginx.conf -g 'daemon off;'"
