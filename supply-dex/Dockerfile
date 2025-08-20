FROM oven/bun:1.2.20-debian AS base
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git bash wget xz-utils && \
    rm -rf /var/lib/apt/lists/*

RUN curl -L https://foundry.paradigm.xyz | bash && \
    /root/.foundry/bin/foundryup && \
    ln -sf /root/.foundry/bin/forge /usr/local/bin/forge && \
    ln -sf /root/.foundry/bin/cast  /usr/local/bin/cast && \
    ln -sf /root/.foundry/bin/anvil /usr/local/bin/anvil && \
    forge --version && cast --version && anvil --version

COPY service/package.json service/bun.lock ./service/
RUN cd service && bun install --frozen-lockfile

COPY service/src ./service/src
COPY service/public ./service/public
COPY service/tsconfig.json ./service/tsconfig.json
COPY foundry ./foundry

RUN FOUNDRY_DIR=/root/.foundry forge --version && \
    cd /app/foundry 


RUN git init . && cd foundry && forge install openzeppelin/openzeppelin-contracts && forge install foundry-rs/forge-std

RUN forge build

COPY service/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh && mkdir -p /app/data && touch /app/data/instance.json
RUN chmod 777 /app/data/instance.json

EXPOSE 5000 8545
ENTRYPOINT ["/app/entrypoint.sh"]
