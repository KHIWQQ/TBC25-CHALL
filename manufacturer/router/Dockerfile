FROM alpine:3.20
RUN apk add --no-cache iptables bash tcpdump curl
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
CMD ["/entrypoint.sh"]
